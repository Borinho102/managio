<?php

defined('BASEPATH') or exit('No direct script access allowed');

class S3p_gateway extends App_gateway
{
    const PROD_URL    = 'https://s3pv2cm.smobilpay.com/v2';
    const STAGING_URL = 'https://s3p.smobilpay.staging.maviance.info/v2';

    public function __construct()
    {
        parent::__construct();
        $this->setId(S3P_MODULE_GATEWAY_ID);
        $this->setName('S3P Smobilpay');
        $this->setSettings([
            [
                'name'  => 'api_key',
                'label' => 'S3P API Key (Token)',
                'type'  => 'input',
            ],
            [
                'name'      => 'api_secret',
                'label'     => 'S3P API Secret',
                'encrypted' => true,
                'type'      => 'input',
            ],
            [
                'name'          => 'environment',
                'label'         => 'Use Production Environment? (No = Staging)',
                'type'          => 'yes_no',
                'default_value' => 0,
            ],
            [
                'name'          => 'service_id',
                'label'         => 'CASHIN Service ID (e.g. 20052 for Orange Money)',
                'type'          => 'input',
                'default_value' => '20052',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'XAF',
            ],
        ]);
    }

    public function getBaseUrl(): string
    {
        return $this->getSetting('environment') == 0 ? self::STAGING_URL : self::PROD_URL;
    }

    private function buildAuthHeader(string $method, string $url, array $queryParams = [], array $bodyParams = []): string
    {
        $timestamp = (int) round(microtime(true) * 1000);
        $nonce     = (int) round(microtime(true) * 1000);
        $token     = trim($this->getSetting('api_key'));
        $secret    = trim($this->decryptSetting('api_secret'));

        $s3pParams = [
            's3pAuth_nonce'            => $nonce,
            's3pAuth_signature_method' => 'HMAC-SHA1',
            's3pAuth_timestamp'        => $timestamp,
            's3pAuth_token'            => $token,
        ];

        $allParams = array_merge($queryParams, $bodyParams, $s3pParams);

        // Trim string values (matches Postman prerequest script)
        foreach ($allParams as $k => $v) {
            if (is_string($v)) {
                $allParams[$k] = trim($v);
            }
        }

        ksort($allParams);

        // Build parameter string WITHOUT URL-encoding individual values
        $parts = [];
        foreach ($allParams as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $parameterString = implode('&', $parts);

        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($parameterString);
        $signature  = base64_encode(hash_hmac('sha1', $baseString, $secret, true));

        return 's3pAuth ' .
            's3pAuth_timestamp="' . $timestamp . '", ' .
            's3pAuth_signature="' . $signature . '", ' .
            's3pAuth_nonce="' . $nonce . '", ' .
            's3pAuth_signature_method="HMAC-SHA1", ' .
            's3pAuth_token="' . $token . '"';
    }

    private function apiRequest(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        $baseUrl   = $this->getBaseUrl();
        $signUrl   = $baseUrl . '/' . ltrim($endpoint, '/');
        $authHeader = $this->buildAuthHeader($method, $signUrl, $queryParams, $body);

        $url = $signUrl;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $authHeader,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => $error, 'http_code' => 0];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['error' => 'Invalid JSON response', 'http_code' => $httpCode, 'raw' => $response];
        }

        $decoded['http_code'] = $httpCode;
        return $decoded;
    }

    public function getCashinServices(string $serviceId): array
    {
        return $this->apiRequest('GET', 'cashin', ['serviceid' => $serviceId]);
    }

    public function getQuote(string $payItemId, float $amount): array
    {
        return $this->apiRequest('POST', 'quotestd', [], [
            'payItemId' => $payItemId,
            'amount'    => (int) $amount,
        ]);
    }

    public function collect(array $data): array
    {
        return $this->apiRequest('POST', 'collectstd', [], $data);
    }

    public function verifyTransaction(string $trid): array
    {
        return $this->apiRequest('GET', 'verifytx', ['trid' => $trid]);
    }

    public function generateTrid(int $invoiceId): string
    {
        return (string) (time() . str_pad($invoiceId, 4, '0', STR_PAD_LEFT));
    }

    /**
     * Called by Perfex CRM when customer clicks "Pay Now"
     */
    public function process_payment(array $data): void
    {
        $invoiceId = $data['invoiceid'];
        $hash      = $data['invoice']->hash;
        $amount    = (float) $data['amount'];
        $serviceId = $this->getSetting('service_id') ?: '20052';

        // Get cashin services to find payItemId
        $services = $this->getCashinServices($serviceId);

        if (!empty($services['error']) || empty($services[0]['payItemId'])) {
            set_alert('danger', 'S3P Gateway: Could not retrieve payment services. Please try again.');
            redirect(site_url('invoice/' . $invoiceId . '/' . $hash));
            return;
        }

        $payItemId = $services[0]['payItemId'];

        // Get quote
        $quote = $this->getQuote($payItemId, $amount);

        if (!empty($quote['error']) || empty($quote['quoteId'])) {
            $msg = $quote['message'] ?? ($quote['error'] ?? 'Quote failed');
            set_alert('danger', 'S3P Gateway: ' . $msg);
            redirect(site_url('invoice/' . $invoiceId . '/' . $hash));
            return;
        }

        // Store in session for the payment form
        $this->ci->session->set_userdata([
            's3p_quote_id'  => $quote['quoteId'],
            's3p_amount'    => $amount,
            's3p_invoice_id' => $invoiceId,
            's3p_currency'  => $quote['currency'] ?? $this->getSetting('currencies'),
        ]);

        redirect(site_url('s3p/payment/' . $invoiceId . '/' . $hash));
    }
}
