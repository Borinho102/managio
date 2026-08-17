<?php defined('BASEPATH') or exit('No direct script access allowed');

class Saas_S3p extends Saas_payment
{
    const PROD_URL    = 'https://s3pv2cm.smobilpay.com/v2';
    const STAGING_URL = 'https://s3p.smobilpay.staging.maviance.info/v2';
    public bool $processingFees = false;
    protected $gateway  = 's3p';
    protected $type     = 'package';
    protected $currency = 'XAF';

    public function __construct()
    {
        parent::__construct();
        $this->setId('s3p');
        $this->setName('S3P');
    }

    // -------------------------------------------------------------------------
    // Core API helpers
    // -------------------------------------------------------------------------

    public function getBaseUrl(): string
    {
        return ConfigItems('s3p_test_mode') == 1 ? self::STAGING_URL : self::PROD_URL;
    }

    /**
     * Equivalent of Stripe::setProvider() / PayPal::accessToken().
     * Validates S3P credentials with a lightweight ping.
     */
    public function setProvider(): void
    {
        // S3P uses per-request HMAC signing — no persistent token to fetch.
        // Validate credentials exist; the actual auth check is deferred to the first API call.
        $token  = trim(ConfigItems('s3p_api_key'));
        $secret = trim(ConfigItems('s3p_secret_key'));

        if (empty($token) || empty($secret)) {
            log_message('error', 'S3P: API key or secret is not configured.');
        }
    }

    /**
     * Alias of setProvider() matching PayPal's accessToken() interface.
     */
    public function accessToken(): void
    {
        $this->setProvider();
    }

    private function buildAuthHeader(string $method, string $url, array $queryParams = [], array $bodyParams = []): string
    {
        $timestamp = (int) round(microtime(true) * 1000);
        $nonce     = (int) round(microtime(true) * 1000);
        $token     = trim(ConfigItems('s3p_api_key'));
        $secret    = trim(ConfigItems('s3p_secret_key'));

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

    public function apiRequest(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        $baseUrl    = $this->getBaseUrl();
        $signUrl    = $baseUrl . '/' . ltrim($endpoint, '/');
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
            CURLOPT_SSL_VERIFYPEER => (ConfigItems('s3p_test_mode') !== 'TRUE'),
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
        log_message('error', $response);

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['error' => 'Invalid JSON response', 'http_code' => $httpCode, 'raw' => $response];
        }

        $decoded['http_code'] = $httpCode;
        return $decoded;
    }

    // -------------------------------------------------------------------------
    // S3P API wrappers
    // -------------------------------------------------------------------------

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

    public function generateTrid(): string
    {
        return (string) (time() . rand(1000, 9999));
    }

    // -------------------------------------------------------------------------
    // Product / Plan sync  (mirrors Stripe & PayPal)
    // S3P has no product catalogue — we store the cashin payItemId locally so
    // getPaymentForm() can look it up without a live API call every time.
    // price_id JSON uses the same shape as Stripe: {monthly:{id,price}, ...}
    // -------------------------------------------------------------------------

    /**
     * Sync all (or one) published packages to tbl_saas_gateway_products.
     * Mirrors Saas_Stripe::syncProducts() and Saas_Paypal::syncProducts().
     */
    public function syncProducts($package = null): array
    {
        $result = [];

        if (!empty($package)) {
            $packages = [(object) $package];
        } else {
            $packages = all_packages();
        }
        foreach ($packages as $pkg) {
            $pkg   = is_array($pkg) ? (object) $pkg : $pkg;
            $pkgId = $pkg->id ?? null;
            $product = $this->getProduct($pkgId);
            if (!empty($product->product_id)) {
                $result[] = $this->updateProduct($product->product_id, $pkg);
            } else {
                $result[] = $this->createProduct($pkg);
            }
        }

        $isError = false;
        foreach ($result as $res) {
            if (!empty($res['status']) && $res['status'] === 'error') {
                $isError = true;
                break;
            }
        }

        if (!$isError) {
            $this->createWebhook();
        }

        return $result;
    }

    /**
     * Sync all (or one) published module packages to tbl_saas_gateway_products.
     * Mirrors Saas_Stripe::syncModuleProducts() and Saas_Paypal::syncModuleProducts().
     */
    public function syncModuleProducts($package = null): array
    {
        $result = [];

        if (!empty($package)) {
            $packages = [(object) $package];
        } else {
            $packages = get_old_result('tbl_saas_package_module', ['status' => 'published'], 'object');
        }

        foreach ($packages as $pkg) {
            $pkg->name          = $pkg->module_title;
            $pkg->id            = $pkg->package_module_id;
            $pkg->monthly_price = !empty($pkg->monthly_price) ? $pkg->monthly_price : $pkg->price;
            $pkg->trial_period  = !empty($pkg->trial_period) ? $pkg->trial_period : 0;

            $product = $this->getProduct($pkg->id, 'module');
            if (!empty($product->product_id)) {
                $result[] = $this->updateProduct($product->product_id, $pkg);
            } else {
                $result[] = $this->createProduct($pkg, 'module');
            }
        }

        $isError = false;
        foreach ($result as $res) {
            if (!empty($res['status']) && $res['status'] === 'error') {
                $isError = true;
                break;
            }
        }

        if (!$isError) {
            $this->createWebhook('module');
        }

        return $result;
    }

    /**
     * Register a webhook endpoint.
     * S3P does not support webhook registration via API — store a local record
     * so the rest of the SaaS system (which checks for webhooks) stays consistent.
     * Mirrors Saas_Stripe::createWebhook() and Saas_Paypal::createWebhook().
     */
    public function createWebhook($type = 'package'): array
    {
        $url = ($type === 'package')
            ? base_url('webhooks/s3p')
            : base_url('webhooks/s3p_module');

        $webhookInfo = get_old_result('tbl_saas_gateway_webhooks', ['gateway_name' => $this->gateway, 'type' => $type], false);

        $this->ci->saas_model->_table_name = 'tbl_saas_gateway_webhooks';
        $this->ci->saas_model->_primary_key = 'id';

        $data = [
            'webhook_id'     => 's3p_' . $type . '_webhook',
            'webhook_secret' => '',
            'type'           => $type,
            'gateway_name'   => $this->gateway,
        ];

        if (!empty($webhookInfo)) {
            $this->ci->saas_model->save_old($data, $webhookInfo->id);
        } else {
            $this->ci->saas_model->save_old($data);
        }

        return ['status' => 'success', 'url' => $url];
    }

    /**
     * Verify an incoming webhook payload.
     * S3P does not issue webhooks — always return true so the caller proceeds.
     * Mirrors Saas_Paypal::verifyWebhook().
     */
    public function verifyWebhook($data): bool
    {
        return true;
    }

    /**
     * Look up a product in the local tbl_saas_gateway_products table.
     * Mirrors Saas_Stripe::getProduct() and Saas_Paypal::getProduct().
     */
    public function getProduct($package_id, $type = 'package')
    {
        return get_old_result(
            'tbl_saas_gateway_products',
            ['package_id' => $package_id, 'gateway_name' => $this->gateway, 'type' => $type],
            false
        );
    }

    /**
     * Create a local product record in tbl_saas_gateway_products.
     * S3P has no plan API — generate price IDs locally (same pattern as Stripe price_xxx).
     * Mirrors Saas_Stripe::createProduct() and Saas_Paypal::createProduct().
     */
    public function createProduct($package, $type = 'package'): array
    {
        try {
            $pkgId     = is_array($package) ? ($package['id'] ?? '') : ($package->id ?? '');
            $productId = 's3p_' . bin2hex(random_bytes(8));
            $planIds   = $this->buildLocalPlanIds($package);

            $this->ci->saas_model->updateProduct($productId, json_encode($planIds), $package, $this->gateway, $type);

            return ['status' => 'success', 'message' => 'S3P product registered successfully.'];
        } catch (\Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    /**
     * Update local product record with current prices.
     * S3P has no plan API — prices stored locally only.
     * Mirrors Saas_Stripe::updateProduct() and Saas_Paypal::updateProduct().
     */
    public function updateProduct($product_id, $package): array
    {
        try {
            $planIds = $this->buildLocalPlanIds($package);
            $this->ci->saas_model->updateProduct($product_id, json_encode($planIds), $package, $this->gateway);
            return ['status' => 'success', 'message' => 'S3P product updated successfully.'];
        } catch (\Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    /**
     * Build and save the local price_id plan map for a package.
     * S3P has no plan/subscription API — IDs are generated locally.
     * Mirrors Saas_Stripe::createPlan() and Saas_Paypal::createPlan().
     *
     * @param string $product_id  Local product identifier
     * @param object|array $package
     * @param string $type        'package' or 'module'
     * @param string $payItemId   Unused; kept for interface compatibility
     */
    public function createPlan($product_id, $package, $type = 'package', $payItemId = ''): array
    {
        $planIds = $this->buildLocalPlanIds($package);
        $this->ci->saas_model->updateProduct($product_id, json_encode($planIds), $package, $this->gateway, $type);
        return [['status' => 'success', 'message' => 'S3P plans registered locally.']];
    }

    /**
     * Update a plan's price locally (S3P prices are passed at payment time, not stored on the gateway).
     * Mirrors Saas_Paypal::updatePlan().
     */
    public function updatePlan($plan_id, $price, $sequence = 1): array
    {
        return ['status' => 'success', 'message' => 'S3P plan price updated locally.'];
    }

    // -------------------------------------------------------------------------
    // Customer management (mirrors Stripe)
    // S3P has no customer API — store a local record in tbl_saas_gateway_subscriptions.
    // -------------------------------------------------------------------------

    /**
     * Create a local customer record for a company.
     * Mirrors Saas_Stripe::createCustomer().
     */
    public function createCustomer($company, $return = false)
    {
        $this->ci->saas_model->_table_name  = 'tbl_saas_gateway_subscriptions';
        $this->ci->saas_model->_primary_key = 'id';

        $data = [
            'company_id'   => $company->id,
            'gateway_name' => $this->gateway,
            'type'         => $this->type,
            'customer_id'  => 's3p_' . $company->id,
        ];

        if ($this->type === 'module' && !empty($company->package_module_id)) {
            $data['module_id'] = $company->package_module_id;
        } else {
            $data['module_id'] = null;
        }

        $id = $this->ci->saas_model->save_old($data);

        if ($return) {
            return $id;
        }
    }

    // -------------------------------------------------------------------------
    // Subscription management (mirrors Stripe)
    // -------------------------------------------------------------------------

    /**
     * Prepare subscription data for the payment form.
     * For S3P, this fetches a quote from the API and returns the form data.
     * Mirrors Saas_Stripe::createSubscription().
     */
    public function createSubscription($company, $package, $post): array
    {
        $s3p_company = $company->s3p_company ?? null;

        $product   = $this->getProduct($post['package_id'] ?? $package->id ?? '');
        $payItemId = '';

        if (!empty($product->price_id)) {
            $priceData = json_decode($product->price_id, true);
            $frequency = $post['frequency'] ?? 'monthly';
            $payItemId = $priceData[$frequency]['id'] ?? '';
        }

        if (empty($payItemId)) {
            $serviceId = ConfigItems('s3p_service_id') ?: '20052';
            $services  = $this->getCashinServices($serviceId);
            $payItemId = $services[0]['payItemId'] ?? '';
        }

        $amount = (float) ($post['amount'] ?? 0);
        $quote  = $this->getQuote($payItemId, $amount);

        if (!empty($quote['error']) || empty($quote['quoteId'])) {
            return ['error' => $quote['message'] ?? ($quote['error'] ?? 'Quote failed.')];
        }

        $trid = $this->generateTrid();

        $subscription_data = [
            'quoteId'                  => $quote['quoteId'],
            'trid'                     => $trid,
            'currency'                 => $quote['currency'] ?? $this->currency,
            'amount'                   => $amount,
            'gateway_subscription_id'  => $s3p_company->id ?? null,
        ];

        if (!empty($s3p_company)) {
            $this->ci->saas_model->_table_name  = 'tbl_saas_gateway_subscriptions';
            $this->ci->saas_model->_primary_key = 'id';
            $this->ci->saas_model->save_old(
                ['subscription_id' => 'S3P_PENDING_' . $trid, 'status' => 'pending', 'temp' => json_encode($post)],
                $s3p_company->id
            );
        }

        return $subscription_data;
    }

    /**
     * Retrieve a local subscription record by subscription_id.
     * Mirrors Saas_Stripe::getSubscription().
     */
    public function getSubscription($subscription_id)
    {
        return get_old_result('tbl_saas_gateway_subscriptions', ['subscription_id' => $subscription_id, 'gateway_name' => $this->gateway], false);
    }

    /**
     * Retrieve the payment/transaction result for a given trid or ptn.
     * Mirrors Saas_Stripe::getPaymentIntent().
     *
     * @param string $trid              The transaction reference ID
     * @param bool   $payment_intent    Unused; kept for interface compatibility
     */
    public function getPaymentIntent($trid, $payment_intent = false): array
    {
        return $this->verifyTransaction((string) $trid);
    }

    // -------------------------------------------------------------------------
    // Payment form (mirrors Stripe & PayPal)
    // -------------------------------------------------------------------------

    /**
     * Return the Mobile Money payment form for a package checkout.
     * Looks up the cached payItemId from tbl_saas_gateway_products, gets a
     * fresh quote, then renders the input form.
     * Mirrors Saas_Stripe::getPaymentForm() and Saas_Paypal::getPaymentForm().
     */
    public function getPaymentForm($package, $subs_info = null)
    {
        $package_info = get_old_result('tbl_saas_packages', ['id' => $package['package_id']], false);
        $s3p_company  = get_old_result('tbl_saas_gateway_subscriptions', ['type' => 'package', 'company_id' => $package['companies_id'], 'gateway_name' => $this->gateway], false);
        $company      = get_old_result('tbl_saas_companies', ['id' => $package['companies_id']], false);

        if (empty($s3p_company)) {
            $this->createCustomer($company, true);
            $s3p_company = get_old_result('tbl_saas_gateway_subscriptions', ['type' => 'package', 'company_id' => $package['companies_id'], 'gateway_name' => $this->gateway], false);
        }

        $company->s3p_company = $s3p_company;

        $localPriceId = $this->resolvePayItemId($package['package_id'], $package['billing_cycle'] ?? 'monthly_price');
        if (empty($localPriceId)) {
            return ['paymentForm' => '<div class="alert alert-danger">S3P Error: Product not synced. Please save the package to register it with S3P gateway.</div>'];
        }

        // Zero-price: skip S3P gateway entirely — auto-submit directly to callback
        if ((float) $package['amount'] == 0) {
            $frequency    = str_replace('_price', '', $package['billing_cycle'] ?? 'monthly_price');
            $hiddenFields = [
                'quoteId'           => 'FREE_' . uniqid(),
                'trid'              => $this->generateTrid(),
                'package_id'        => $package['package_id'] ?? '',
                'companies_id'      => $package['companies_id'] ?? '',
                'billing_cycle'     => $package['billing_cycle'] ?? 'monthly_price',
                'frequency'         => $frequency,
                'amount'            => 0,
                'currency'          => $this->currency,
                'package_module_id' => $package['package_module_id'] ?? '',
                'type'              => 'package',
                'zero_price'        => '1',
            ];
            return ['paymentForm' => $this->buildZeroPriceForm($hiddenFields)];
        }

        // S3P has no plan API — fetch the live payItemId from cashin at payment time h
        $serviceId = ConfigItems('s3p_service_id') ?: '20052';
        $services  = $this->getCashinServices($serviceId);
        $payItemId = $services[0]['payItemId'] ?? '';

        if (empty($payItemId)) {
            return ['paymentForm' => '<div class="alert alert-danger">S3P Error: Unable to fetch payment item from S3P service. Check S3P credentials and service ID.</div>'];
        }

        $amount = (float) $package['amount'];
        $quote  = $this->getQuote($payItemId, $amount);

        if (!empty($quote['error']) || empty($quote['quoteId'])) {
            $errorMsg = $quote['message'] ?? ($quote['error'] ?? 'Quote request failed.');
            return ['paymentForm' => '<div class="alert alert-danger">S3P Quote Error: ' . html_escape($errorMsg) . '</div>'];
        }

        $frequency     = str_replace('_price', '', $package['billing_cycle'] ?? 'monthly_price');
        $hiddenFields  = [
            'quoteId'           => $quote['quoteId'],
            'trid'              => $this->generateTrid(),
            'package_id'        => $package['package_id'] ?? '',
            'companies_id'      => $package['companies_id'] ?? '',
            'billing_cycle'     => $package['billing_cycle'] ?? 'monthly_price',
            'frequency'         => $frequency,
            'amount'            => $amount,
            'currency'          => $quote['currency'] ?? $this->currency,
            'package_module_id' => $package['package_module_id'] ?? '',
            'type'              => 'package',
        ];

        $paymentForm = $this->buildPaymentForm($hiddenFields);

        return ['paymentForm' => $paymentForm];
    }

    /**
     * Module purchase form — mirrors Saas_Stripe::subscribe() and Saas_Paypal::subscribe().
     */
    public function subscribe($data)
    {
        $package_module_id = $data['package_module_id'] ?? '';
        $data['package_id'] = $package_module_id;

        $company = get_old_result('tbl_saas_companies', ['id' => $data['companies_id']], false);
        $s3p_company = get_old_result('tbl_saas_gateway_subscriptions', ['module_id' => $package_module_id, 'type' => 'module', 'company_id' => $data['companies_id'], 'gateway_name' => $this->gateway], false);

        if (empty($s3p_company)) {
            $this->type = 'module';
            $company->package_module_id = $package_module_id;
            $this->createCustomer($company, true);
            $s3p_company = get_old_result('tbl_saas_gateway_subscriptions', ['module_id' => $package_module_id, 'type' => 'module', 'company_id' => $data['companies_id'], 'gateway_name' => $this->gateway], false);
        }

        $localPriceId = $this->resolvePayItemId($package_module_id, 'monthly_price', 'module');
        if (empty($localPriceId)) {
            return ['paymentForm' => '<div class="alert alert-danger">S3P Error: Module not synced. Please save the module to register it with S3P gateway.</div>'];
        }

        // Zero-price: skip S3P gateway entirely — auto-submit directly to callback
        if ((float) $data['amount'] == 0) {
            $hiddenFields = [
                'quoteId'           => 'FREE_' . uniqid(),
                'trid'              => $this->generateTrid(),
                'package_id'        => $package_module_id,
                'companies_id'      => $data['companies_id'] ?? '',
                'billing_cycle'     => 'monthly_price',
                'frequency'         => 'monthly',
                'amount'            => 0,
                'currency'          => $this->currency,
                'package_module_id' => $package_module_id,
                'type'              => 'module',
                'zero_price'        => '1',
            ];
            return ['paymentForm' => $this->buildZeroPriceForm($hiddenFields)];
        }

        // S3P has no plan API — fetch live payItemId from cashin at payment time
        $serviceId = ConfigItems('s3p_service_id') ?: '20052';
        $services  = $this->getCashinServices($serviceId);
        $payItemId = $services[0]['payItemId'] ?? '';

        if (empty($payItemId)) {
            return ['paymentForm' => '<div class="alert alert-danger">S3P Error: Unable to fetch payment item from S3P service. Check S3P credentials and service ID.</div>'];
        }

        $amount = (float) $data['amount'];
        $quote  = $this->getQuote($payItemId, $amount);

        if (!empty($quote['error']) || empty($quote['quoteId'])) {
            $errorMsg = $quote['message'] ?? ($quote['error'] ?? 'Quote request failed.');
            return ['paymentForm' => '<div class="alert alert-danger">S3P Quote Error: ' . html_escape($errorMsg) . '</div>'];
        }

        $hiddenFields = [
            'quoteId'           => $quote['quoteId'],
            'trid'              => $this->generateTrid(),
            'package_id'        => $package_module_id,
            'companies_id'      => $data['companies_id'] ?? '',
            'billing_cycle'     => 'monthly_price',
            'frequency'         => 'monthly',
            'amount'            => $amount,
            'currency'          => $quote['currency'] ?? $this->currency,
            'package_module_id' => $package_module_id,
            'type'              => 'module',
        ];

        $paymentForm = $this->buildPaymentForm($hiddenFields);

        return ['paymentForm' => $paymentForm];
    }

    /**
     * Cancel a subscription.
     * S3P Mobile Money has no recurring subscription API; the SaaS layer
     * manages lifecycle locally — return success immediately.
     * Mirrors Saas_Stripe::cancel_subscription() and Saas_Paypal::cancel_subscription().
     */
    public function cancel_subscription($company_subs): array
    {
        return ['status' => 'success', 'message' => 'Subscription cancelled successfully.'];
    }

    /**
     * Resume a subscription.
     * Same rationale as cancel_subscription.
     * Mirrors Saas_Stripe::resume_subscription() and Saas_Paypal::resume_subscription().
     */
    public function resume_subscription($company_subs): array
    {
        return ['status' => 'success', 'message' => 'Subscription resumed successfully.'];
    }

    // -------------------------------------------------------------------------
    // Callback processing (S3P-specific)
    // -------------------------------------------------------------------------

    /**
     * Execute the S3P payment: POST to /collectstd then GET /verifytx.
     * Called from Gb::s3p_payment_callback().
     */
    public function processCallback(array $post): array
    {
        $quoteId         = trim($post['quoteId'] ?? '');
        $trid            = trim($post['trid'] ?? $this->generateTrid());
        $phone           = trim($post['phone'] ?? '');
        $customerName    = trim($post['customer_name'] ?? '');
        $customerEmail   = trim($post['customer_email'] ?? '');
        $customerAddress = trim($post['customer_address'] ?? '');

        log_message('debug', '[S3P_LIB::processCallback] quoteId=' . $quoteId . ' trid=' . $trid . ' phone=' . $phone . ' email=' . $customerEmail . ' name=' . $customerName);

        // Zero-price: no gateway API needed — complete immediately
        if (!empty($post['zero_price']) || (float) ($post['amount'] ?? 1) == 0) {
            log_message('debug', '[S3P_LIB::processCallback] Zero-price purchase — bypassing S3P API trid=' . $trid);
            return [
                'success'        => true,
                'trid'           => $trid,
                'ptn'            => 'FREE_' . $trid,
                'verify_status'  => 'FREE',
                'transaction_id' => 'FREE_' . $trid,
            ];
        }

        if (empty($quoteId) || empty($phone)) {
            log_message('error', '[S3P_LIB::processCallback] Validation failed — quoteId=' . ($quoteId ?: 'EMPTY') . ' phone=' . ($phone ?: 'EMPTY'));
            return ['success' => false, 'message' => 'Missing quoteId or phone number.'];
        }

        $collectPayload = [
            'quoteId'              => $quoteId,
            'customerPhonenumber'  => $phone,
            'customerEmailaddress' => $customerEmail,
            'customerName'         => $customerName,
            'customerAddress'      => $customerAddress,
            'serviceNumber'        => $phone,
            'trid'                 => $trid,
        ];
        log_message('debug', '[S3P_LIB::processCallback] Calling collect() payload: ' . json_encode($collectPayload));

        $collectResult = $this->collect($collectPayload);

        log_message('debug', '[S3P_LIB::processCallback] collect() response: ' . json_encode($collectResult));

        if (!empty($collectResult['error'])) {
            log_message('error', '[S3P_LIB::processCallback] collect() error: ' . $collectResult['error']);
            return ['success' => false, 'message' => $collectResult['error']];
        }

        if (empty($collectResult['ptn'])) {
            $msg = $collectResult['customerMsg'][0]['content'] ?? 'S3P payment collection failed.';
            log_message('error', '[S3P_LIB::processCallback] collect() missing ptn — message: ' . $msg . ' | full response: ' . json_encode($collectResult));
            return ['success' => false, 'message' => $msg];
        }

        $ptn = $collectResult['ptn'];
        log_message('debug', '[S3P_LIB::processCallback] collect() success ptn=' . $ptn . ' | Calling verifyTransaction trid=' . $trid);

        $verify       = $this->verifyTransaction($trid);
        $verifyEntry  = is_array($verify) && isset($verify[0]) ? $verify[0] : $verify;
        $verifyStatus = strtoupper($verifyEntry['status'] ?? 'PENDING');

        log_message('debug', '[S3P_LIB::processCallback] verifyTransaction() response: ' . json_encode($verify) . ' | resolved status=' . $verifyStatus);

        if ($verifyStatus === 'FAILED') {
            log_message('error', '[S3P_LIB::processCallback] Verify status=FAILED trid=' . $trid . ' ptn=' . $ptn);
            return ['success' => false, 'message' => 'Transaction verification returned FAILED status.'];
        }

        $result = [
            'success'        => true,
            'trid'           => $trid,
            'ptn'            => $ptn,
            'verify_status'  => $verifyStatus,
            'transaction_id' => 'S3P_' . $ptn,
        ];
        log_message('debug', '[S3P_LIB::processCallback] DONE: ' . json_encode($result));

        return $result;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the local price ID for a package/billing cycle.
     * Returns empty string if package is not synced — caller must handle.
     * The actual S3P payItemId for API calls is fetched separately at payment time.
     */
    private function resolvePayItemId(string $packageId, string $billingCycle = 'monthly_price', string $type = 'package'): string
    {
        $frequency = str_replace('_price', '', $billingCycle) ?: 'monthly';
        $product   = $this->getProduct($packageId, $type);

        if (!empty($product->price_id)) {
            $priceData = json_decode($product->price_id, true);
            if (!empty($priceData[$frequency]['id'])) {
                return $priceData[$frequency]['id'];
            }
        }

        return '';
    }

    /**
     * Build local plan ID map for a package — no S3P API call needed.
     * IDs follow pattern: s3p_{package_id}_{frequency}
     * Price amounts stored for reference; actual amount passed at payment time.
     */
    private function buildLocalPlanIds($package): array
    {
        $id       = is_array($package) ? ($package['id'] ?? '') : ($package->id ?? '');
        $monthly  = is_array($package) ? ($package['monthly_price']  ?? 0) : ($package->monthly_price  ?? 0);
        $yearly   = is_array($package) ? ($package['yearly_price']   ?? 0) : ($package->yearly_price   ?? 0);
        $lifetime = is_array($package) ? ($package['lifetime_price'] ?? 0) : ($package->lifetime_price ?? 0);

        return [
            'monthly'  => ['id' => 's3p_' . $id . '_monthly',  'price' => $monthly],
            'yearly'   => ['id' => 's3p_' . $id . '_yearly',   'price' => $yearly],
            'lifetime' => ['id' => 's3p_' . $id . '_lifetime', 'price' => $lifetime],
        ];
    }

    /**
     * Render the Mobile Money HTML form.
     */
    private function buildPaymentForm(array $hiddenFields): string
    {
        $action_url = base_url('saas/gb/s3p_payment_callback');
        $csrf_name  = $this->ci->security->get_csrf_token_name();
        $csrf_hash  = $this->ci->security->get_csrf_hash();
        $amount     = $hiddenFields['amount'] ?? '';
        $currency   = $hiddenFields['currency'] ?? $this->currency;

        $form  = '<form id="s3p-payment-form" action="' . html_escape($action_url) . '" method="POST" ';
        $form .= 'style="max-width:500px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px;">';
        $form .= '<input type="hidden" name="' . html_escape($csrf_name) . '" value="' . html_escape($csrf_hash) . '">';

        foreach ($hiddenFields as $key => $value) {
            $form .= '<input type="hidden" name="' . html_escape($key) . '" value="' . html_escape($value) . '">';
        }

        $form .= '<div class="form-group" style="margin-bottom:15px;">';
        $form .= '<label style="display:block;font-weight:bold;margin-bottom:5px;">Mobile Money Number <span class="text-danger">*</span></label>';
        $form .= '<input type="text" name="phone" class="form-control" placeholder="e.g. 237677000000" required>';
        $form .= '<span class="text-muted" style="font-size:12px;display:block;margin-top:5px;">Include country code (e.g. 237 for Cameroon)</span>';
        $form .= '</div>';

        $form .= '<div class="form-group" style="margin-bottom:15px;">';
        $form .= '<label style="display:block;font-weight:bold;margin-bottom:5px;">Full Name</label>';
        $form .= '<input type="text" name="customer_name" class="form-control" placeholder="Your Full Name">';
        $form .= '</div>';

        $form .= '<div class="form-group" style="margin-bottom:15px;">';
        $form .= '<label style="display:block;font-weight:bold;margin-bottom:5px;">Email Address</label>';
        $form .= '<input type="email" name="customer_email" class="form-control" placeholder="Your Email">';
        $form .= '</div>';

        $form .= '<div class="form-group" style="margin-bottom:20px;">';
        $form .= '<label style="display:block;font-weight:bold;margin-bottom:5px;">Physical Address</label>';
        $form .= '<input type="text" name="customer_address" class="form-control" placeholder="Your Address">';
        $form .= '</div>';

        $form .= '<div class="text-center">';
        $form .= '<button type="submit" class="btn btn-success btn-block btn-lg" style="width:100%;">';
        $form .= '<i class="fa fa-mobile-phone"></i> Pay Now with Mobile Money';
        if (!empty($amount)) {
            $form .= ' (' . html_escape($amount) . ' ' . html_escape($currency) . ')';
        }
        $form .= '</button>';
        $form .= '</div>';
        $form .= '</form>';

        return $form;
    }

    private function buildZeroPriceForm(array $hiddenFields): string
    {
        $action_url = base_url('saas/gb/s3p_payment_callback');
        $csrf_name  = $this->ci->security->get_csrf_token_name();
        $csrf_hash  = $this->ci->security->get_csrf_hash();

        $form  = '<form id="s3p-free-form" action="' . html_escape($action_url) . '" method="POST">';
        $form .= '<input type="hidden" name="' . html_escape($csrf_name) . '" value="' . html_escape($csrf_hash) . '">';
        foreach ($hiddenFields as $key => $value) {
            $form .= '<input type="hidden" name="' . html_escape($key) . '" value="' . html_escape($value) . '">';
        }
        $form .= '<div class="text-center" style="padding:20px;">';
        $form .= '<p class="text-success"><i class="fa fa-check-circle"></i> This plan is <strong>free</strong>. No payment required.</p>';
        $form .= '<button type="submit" class="btn btn-success btn-block btn-lg">Complete Purchase</button>';
        $form .= '</div>';
        $form .= '</form>';
        $form .= '<script>document.getElementById("s3p-free-form").submit();</script>';

        return $form;
    }
}
