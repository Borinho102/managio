<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Payin_client
{
    protected $ci;
    protected string $baseUrl = '';
    protected string $ssoKey = 'managio';
    protected string $ssoSecret = '';
    protected string $clientId = '';
    protected string $clientSecret = '';

    public function __construct(array $config = [])
    {
        $this->ci = &get_instance();
        $this->configure($config);
    }

    public static function fromSsoConfig(): self
    {
        return new self([]);
    }

    public static function fromSaasMerchant(): self
    {
        return new self([
            'client_id'     => (string) ConfigItems('payin_client_id'),
            'client_secret' => (string) ConfigItems('payin_client_secret'),
        ]);
    }

    public function configure(array $config = []): void
    {
        $base = $config['base_url'] ?? ConfigItems('payin_api_base_url');
        $this->baseUrl = rtrim((string) $base, '/');
        $this->ssoKey = (string) ($config['sso_key'] ?? (ConfigItems('payin_sso_key') ?: 'managio'));
        $this->ssoSecret = (string) ($config['sso_secret'] ?? ConfigItems('payin_sso_secret'));
        $this->clientId = (string) ($config['client_id'] ?? $this->clientId);
        $this->clientSecret = (string) ($config['client_secret'] ?? $this->clientSecret);
    }

    public function setMerchantCredentials(string $clientId, string $clientSecret, ?string $baseUrl = null): void
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        if ($baseUrl !== null && $baseUrl !== '') {
            $this->baseUrl = rtrim($baseUrl, '/');
        }
    }

    public function isSsoConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->ssoSecret !== '' && $this->ssoKey !== '';
    }

    public function isMerchantConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public static function normalizeCurrency(?string $currencyCode): string
    {
        $currency = strtoupper(trim((string) $currencyCode));
        $map = ['FCFA' => 'XAF', 'CFA' => 'XAF', 'XAF CFA' => 'XAF', 'CFA FRANC' => 'XAF'];

        return $map[$currency] ?? ($currency !== '' ? $currency : 'XAF');
    }

    public function provisionCompany($companyInfo): array
    {
        if (empty($companyInfo) || empty($companyInfo->domain) || empty($companyInfo->email)) {
            throw new RuntimeException('Company domain and email are required to provision PayIn.');
        }

        $nameParts = preg_split('/\s+/', trim((string) $companyInfo->name), 2);
        $urls = function_exists('all_company_url') ? all_company_url($companyInfo->domain) : [];
        $siteUrl = $urls['url'] ?? (rtrim(site_url(), '/') . '/');
        if (strlen($siteUrl) > 100) {
            $siteUrl = substr($siteUrl, 0, 100);
        }

        $payload = [
            'company_code' => (string) $companyInfo->domain,
            'company_name' => (string) ($companyInfo->name ?: $companyInfo->domain),
            'email'        => (string) $companyInfo->email,
            'first_name'   => $nameParts[0] ?? 'Company',
            'last_name'    => $nameParts[1] ?? 'Admin',
            'phone'        => (string) ($companyInfo->mobile ?? ''),
            'site_url'     => $siteUrl,
            'currency'     => 'XAF',
        ];

        $data = $this->ssoRequest('POST', '/api/internal/sso/provision-company', $payload);
        $this->storeCompanyMapping($companyInfo, $data);
        $this->storeTenantGatewayOptions($companyInfo, $data);
        $this->activateTenantModule($companyInfo);

        return $data;
    }

    public function loginToken(int $payinUserId, string $companyCode): array
    {
        return $this->ssoRequest('POST', '/api/internal/sso/company-login-token', [
            'payin_user_id' => $payinUserId,
            'company_code'  => $companyCode,
        ]);
    }

    public function consumePayload(int $payinUserId, string $companyCode): array
    {
        $tokenData = $this->loginToken($payinUserId, $companyCode);
        $consumeUrl = $tokenData['consume_url'] ?? ($this->baseUrl . '/sso/consume');
        $token = $tokenData['token'] ?? null;
        if (empty($token)) {
            throw new RuntimeException('Failed to create PayIn login token.');
        }

        return [
            'consume_url' => $consumeUrl,
            'token'       => $token,
            'provisioned' => true,
        ];
    }

    public function walletBalance(int $payinUserId, string $companyCode, string $currency = 'XAF'): array
    {
        $data = $this->ssoRequest('GET', '/api/internal/sso/company-wallet-balance', [
            'payin_user_id' => $payinUserId,
            'company_code'  => $companyCode,
            'currency'      => $currency,
        ]);

        $code = $data['code'] ?? 'XAF';
        $balance = (float) ($data['balance'] ?? 0);

        return [
            'provisioned' => true,
            'error'       => false,
            'code'        => $code,
            'symbol'      => $data['symbol'] ?? $code,
            'balance'     => $balance,
            'formatted'   => number_format($balance, 0, ',', ' ') . ' ' . $code,
        ];
    }

    public function createExpressCheckout(float $amount, string $currency, string $successUrl, string $cancelUrl, bool $enableWallet = true, bool $enablePawapay = true): string
    {
        if (!$this->isMerchantConfigured()) {
            throw new RuntimeException('PayIn merchant credentials are not configured.');
        }

        $accessToken = $this->verifyAndGetAccessToken();
        $body = [
            'payer'          => 'PayMoney',
            'amount'         => $amount,
            'currency'       => self::normalizeCurrency($currency),
            'successUrl'     => $successUrl,
            'cancelUrl'      => $cancelUrl,
            'enable_wallet'  => $enableWallet ? 1 : 0,
            'enable_pawapay' => $enablePawapay ? 1 : 0,
        ];

        $response = $this->httpJson('POST', $this->baseUrl . '/merchant/api/transaction-info', $body, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        if (($response['status'] ?? '') !== 'success' || empty($response['data']['approvedUrl'])) {
            throw new RuntimeException($response['message'] ?? 'PayIn transaction creation failed.');
        }

        return (string) $response['data']['approvedUrl'];
    }

    public function decodeCallbackPayload(): array
    {
        $candidates = [];
        $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if ($queryString !== '') {
            $candidates[] = $queryString;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (str_contains($requestUri, '?')) {
            $candidates[] = substr($requestUri, strpos($requestUri, '?') + 1);
        }

        $get = $this->ci->input->get();
        if (is_array($get) && count($get) === 1) {
            $key = array_key_first($get);
            $value = $get[$key];
            $candidates[] = ($value === null || $value === '') ? (string) $key : (string) $value;
        }

        foreach ($candidates as $raw) {
            $payload = $this->tryDecodeBase64Json((string) $raw);
            if ($payload !== null) {
                return $payload;
            }
        }

        log_message('error', 'PayIn callback payload decode failed');
        throw new RuntimeException('PayIn callback payload is missing');
    }

    public function assertSuccessful(array $payload, float $expectedAmount, float $tolerance = 0.05): void
    {
        if ((int) ($payload['status'] ?? 0) !== 200) {
            throw new RuntimeException('PayIn payment was not successful');
        }

        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;
        $total = isset($payload['total']) ? (float) $payload['total'] : null;
        $amountOk = $amount !== null && abs($amount - $expectedAmount) <= $tolerance;
        $totalOk = $total !== null && abs($total - $expectedAmount) <= $tolerance;

        if (!$amountOk && !$totalOk) {
            log_message('error', 'PayIn amount mismatch expected=' . $expectedAmount . ' amount=' . $amount . ' total=' . $total);
            throw new RuntimeException('PayIn paid amount does not match the pending transaction');
        }
    }

    public function transactionId(array $payload): string
    {
        foreach (['transaction_id', 'transactionId', 'trx_id', 'id'] as $key) {
            if (!empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return 'PAYIN_' . time();
    }

    private function verifyAndGetAccessToken(): string
    {
        $response = $this->httpJson('POST', $this->baseUrl . '/merchant/api/verify', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (($response['status'] ?? '') !== 'success' || empty($response['data']['access_token'])) {
            throw new RuntimeException($response['message'] ?? 'PayIn client verification failed.');
        }

        return (string) $response['data']['access_token'];
    }

    private function ssoRequest(string $method, string $path, array $data = []): array
    {
        if (!$this->isSsoConfigured()) {
            throw new RuntimeException('PayIn SSO is not configured.');
        }

        $method = strtoupper($method);
        $signedPath = '/' . ltrim($path, '/');
        $url = $this->baseUrl . $signedPath;
        $body = '';

        if ($method === 'GET') {
            ksort($data);
            $query = http_build_query($data);
            if ($query !== '') {
                $signedPath .= '?' . $query;
                $url .= '?' . $query;
            }
        } else {
            $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $timestamp = (string) time();
        $payload = $method . "\n" . $signedPath . "\n" . $timestamp . "\n" . $body;
        $headers = [
            'X-Sso-Key: ' . $this->ssoKey,
            'X-Sso-Timestamp: ' . $timestamp,
            'X-Sso-Signature: ' . hash_hmac('sha256', $payload, $this->ssoSecret),
            'Accept: application/json',
        ];
        if ($method !== 'GET') {
            $headers[] = 'Content-Type: application/json';
        }

        $response = $this->httpJson($method, $url, $method === 'GET' ? [] : $data, $headers, $body);
        if (($response['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($response['message'] ?? 'PayIn SSO request failed.'));
        }

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    private function httpJson(string $method, string $url, array $jsonBody = [], array $headers = [], ?string $rawBody = null): array
    {
        $headers = array_merge(['Accept: application/json'], $headers);
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $rawBody !== null ? $rawBody : json_encode($jsonBody);
            if ($rawBody === null) {
                $headers[] = 'Content-Type: application/json';
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('PayIn HTTP error: ' . $error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('PayIn returned an invalid JSON response (HTTP ' . $httpCode . ').');
        }

        $decoded['http_code'] = $httpCode;
        return $decoded;
    }

    private function tryDecodeBase64Json(string $raw): ?array
    {
        $raw = ltrim($raw, '?');
        if ($raw === '') {
            return null;
        }

        $raw = urldecode($raw);
        $normalized = str_replace(' ', '+', $raw);
        foreach ([$normalized, $raw] as $candidate) {
            $decoded = base64_decode($candidate, true);
            if ($decoded === false) {
                $decoded = base64_decode($candidate);
            }
            if ($decoded === false || $decoded === '') {
                continue;
            }
            $payload = json_decode($decoded, true);
            if (is_array($payload) && isset($payload['status'])) {
                return $payload;
            }
        }

        return null;
    }

    private function storeCompanyMapping($companyInfo, array $data): void
    {
        $companyId = $companyInfo->companies_id ?? $companyInfo->id;
        if (empty($companyId) || !is_numeric($companyId)) {
            return;
        }

        $master = function_exists('config_db') ? config_db(null, true) : $this->ci->db;
        $master->where('id', $companyId)->update('tbl_saas_companies', [
            'payin_user_id'        => $data['payin_user_id'] ?? null,
            'payin_merchant_id'    => $data['merchant_id'] ?? null,
            'payin_provisioned_at' => date('Y-m-d H:i:s'),
        ]);

        $companyInfo->payin_user_id = $data['payin_user_id'] ?? ($companyInfo->payin_user_id ?? null);
        $companyInfo->payin_merchant_id = $data['merchant_id'] ?? ($companyInfo->payin_merchant_id ?? null);
    }

    private function storeTenantGatewayOptions($companyInfo, array $data): void
    {
        if (empty($companyInfo->db_name)) {
            return;
        }

        $clientId = (string) ($data['client_id'] ?? '');
        $clientSecret = (string) ($data['client_secret'] ?? '');
        $existingId = $this->readTenantOption($companyInfo->db_name, 'paymentmethod_payin_client_id');
        $existingSecret = $this->readTenantOption($companyInfo->db_name, 'paymentmethod_payin_client_secret');
        $writeSecrets = $clientId !== '' && $clientSecret !== '' && (empty($existingId) || empty($existingSecret));

        $options = [
            'paymentmethod_payin_api_base_url'     => $this->baseUrl,
            'paymentmethod_payin_payin_user_id'    => (string) ($data['payin_user_id'] ?? ''),
            'paymentmethod_payin_payin_merchant_id'=> (string) ($data['merchant_id'] ?? ''),
            'paymentmethod_payin_enable_wallet'    => '1',
            'paymentmethod_payin_enable_pawapay'   => '1',
            'paymentmethod_payin_currencies'       => 'XAF',
            'paymentmethod_payin_active'           => '1',
            'paymentmethod_payin_label'            => 'PayIn',
            'paymentmethod_payin_default_selected' => '1',
            'paymentmethod_payin_initialized'      => '1',
        ];

        if ($writeSecrets) {
            $options['paymentmethod_payin_client_id'] = $clientId;
            $options['paymentmethod_payin_client_secret'] = $this->ci->encryption->encrypt($clientSecret);
        }

        foreach ($options as $name => $value) {
            $this->writeTenantOption($companyInfo->db_name, $name, $value);
        }
    }

    private function activateTenantModule($companyInfo): void
    {
        if (empty($companyInfo->db_name)) {
            return;
        }

        $table = $companyInfo->db_name . '.' . db_prefix() . 'modules';
        $existing = $this->ci->db->where('module_name', 'payin')->get($table)->row();
        if (!empty($existing)) {
            $this->ci->db->where('module_name', 'payin')->update($table, ['active' => 1]);
            return;
        }

        $this->ci->db->insert($table, [
            'module_name'       => 'payin',
            'installed_version' => '1.0.0',
            'active'            => 1,
        ]);
    }

    private function readTenantOption(string $dbName, string $name): string
    {
        $table = $dbName . '.' . db_prefix() . 'options';
        $row = $this->ci->db->where('name', $name)->get($table)->row();

        return $row->value ?? '';
    }

    private function writeTenantOption(string $dbName, string $name, string $value): void
    {
        $table = $dbName . '.' . db_prefix() . 'options';
        $exists = $this->ci->db->where('name', $name)->get($table)->row();
        if (!empty($exists)) {
            $this->ci->db->where('name', $name)->update($table, ['value' => $value]);
            return;
        }

        $this->ci->db->insert($table, [
            'name'     => $name,
            'value'    => $value,
            'autoload' => 1,
        ]);
    }
}
