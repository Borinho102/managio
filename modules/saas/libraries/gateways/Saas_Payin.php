<?php defined('BASEPATH') or exit('No direct script access allowed');

class Saas_Payin extends Saas_payment
{
    public bool $processingFees = false;
    protected $gateway  = 'payin';
    protected $type     = 'package';
    protected $currency = 'XAF';

    protected function resolveCurrency($context = null): string
    {
        $package = null;
        if (is_object($context) && isset($context->monthly_price)) {
            $package = $context;
        } elseif (is_array($context) && !empty($context['package_id'])) {
            $package = get_old_result('tbl_saas_packages', ['id' => $context['package_id']], false);
        } elseif (is_object($context) && !empty($context->package_id) && empty($context->monthly_price)) {
            $package = get_old_result('tbl_saas_packages', ['id' => $context->package_id], false);
        }
        if (function_exists('saas_package_currency')) {
            return saas_package_currency($package);
        }

        return $this->currency;
    }

    public function __construct()
    {
        parent::__construct();
        $this->setId('payin');
        $this->setName('PayIn');
        if (!class_exists('Payin_client', false)) {
            $path = APP_MODULES_PATH . 'payin/libraries/Payin_client.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    }

    public function setProvider(): void
    {
        $base   = trim((string) ConfigItems('payin_api_base_url'));
        $client = trim((string) ConfigItems('payin_client_id'));
        $secret = trim((string) ConfigItems('payin_client_secret'));
        if ($base === '' || $client === '' || $secret === '') {
            log_message('error', 'PayIn: SaaS merchant credentials are not configured.');
        }
    }

    public function accessToken(): void
    {
        $this->setProvider();
    }

    public function syncProducts($package = null): array
    {
        $result = [];
        $packages = !empty($package) ? [(object) $package] : all_packages();
        foreach ($packages as $pkg) {
            $pkg = is_array($pkg) ? (object) $pkg : $pkg;
            $product = $this->getProduct($pkg->id ?? null);
            $result[] = !empty($product->product_id)
                ? $this->updateProduct($product->product_id, $pkg)
                : $this->createProduct($pkg);
        }

        $this->createWebhook();
        return $result;
    }

    public function syncModuleProducts($package = null): array
    {
        $result = [];
        $packages = !empty($package)
            ? [(object) $package]
            : get_old_result('tbl_saas_package_module', ['status' => 'published'], 'object');

        foreach ($packages as $pkg) {
            $pkg->name = $pkg->module_title ?? ($pkg->name ?? 'Module');
            $pkg->id = $pkg->package_module_id ?? $pkg->id;
            $product = $this->getProduct($pkg->id, 'module');
            $result[] = !empty($product->product_id)
                ? $this->updateProduct($product->product_id, $pkg)
                : $this->createProduct($pkg, 'module');
        }

        $this->createWebhook('module');
        return $result;
    }

    public function createWebhook($type = 'package'): array
    {
        $url = ($type === 'package')
            ? base_url('webhooks/payin')
            : base_url('webhooks/payin_module');

        $webhookInfo = get_old_result('tbl_saas_gateway_webhooks', ['gateway_name' => $this->gateway, 'type' => $type], false);
        $this->ci->saas_model->_table_name = 'tbl_saas_gateway_webhooks';
        $this->ci->saas_model->_primary_key = 'id';
        $data = [
            'webhook_id'     => 'payin_' . $type . '_webhook',
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

    public function verifyWebhook($data): bool
    {
        return true;
    }

    public function getProduct($package_id, $type = 'package')
    {
        return get_old_result(
            'tbl_saas_gateway_products',
            ['package_id' => $package_id, 'gateway_name' => $this->gateway, 'type' => $type],
            false
        );
    }

    public function createProduct($package, $type = 'package'): array
    {
        try {
            $productId = 'payin_' . bin2hex(random_bytes(8));
            $this->ci->saas_model->updateProduct($productId, json_encode($this->buildLocalPlanIds($package)), $package, $this->gateway, $type);
            return ['status' => 'success', 'message' => 'PayIn product registered successfully.'];
        } catch (Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    public function updateProduct($product_id, $package): array
    {
        try {
            $this->ci->saas_model->updateProduct($product_id, json_encode($this->buildLocalPlanIds($package)), $package, $this->gateway);
            return ['status' => 'success', 'message' => 'PayIn product updated successfully.'];
        } catch (Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    public function createCustomer($company, $return = false)
    {
        $this->ci->saas_model->_table_name  = 'tbl_saas_gateway_subscriptions';
        $this->ci->saas_model->_primary_key = 'id';
        $data = [
            'company_id'   => $company->id,
            'gateway_name' => $this->gateway,
            'type'         => $this->type,
            'customer_id'  => 'payin_' . $company->id,
        ];
        if ($this->type === 'module' && !empty($company->package_module_id)) {
            $data['module_id'] = $company->package_module_id;
        } else {
            $data['module_id'] = null;
        }
        $id = $this->ci->saas_model->save_old($data);

        return $return ? $id : null;
    }

    public function getPaymentForm($package, $subs_info = null)
    {
        $company = get_old_result('tbl_saas_companies', ['id' => $package['companies_id']], false);
        $payinCompany = get_old_result('tbl_saas_gateway_subscriptions', [
            'type'         => 'package',
            'company_id'   => $package['companies_id'],
            'gateway_name' => $this->gateway,
        ], false);

        if (empty($payinCompany) && !empty($company)) {
            $this->createCustomer($company, true);
            $payinCompany = get_old_result('tbl_saas_gateway_subscriptions', [
                'type'         => 'package',
                'company_id'   => $package['companies_id'],
                'gateway_name' => $this->gateway,
            ], false);
        }

        $billingCycle = $package['billing_cycle'] ?? 'monthly_price';
        $catalogPackage = !empty($package['package_id'])
            ? get_old_result('tbl_saas_packages', ['id' => $package['package_id']], false)
            : null;
        $catalogAmount = function_exists('saas_package_cycle_price')
            ? saas_package_cycle_price($catalogPackage, $billingCycle)
            : 0.0;
        $amount = (float) ($package['amount'] ?? 0);
        if ($amount <= 0) {
            $amount = (float) $catalogAmount;
        }
        $hidden = [
            'package_id'        => $package['package_id'] ?? '',
            'companies_id'      => $package['companies_id'] ?? '',
            'billing_cycle'     => $billingCycle,
            'amount'            => $amount,
            'currency'          => $this->resolveCurrency($package),
            'package_module_id' => $package['package_module_id'] ?? '',
            'type'              => 'package',
        ];

        if ($amount <= 0) {
            $hidden['zero_price'] = '1';
            $hidden['token'] = 'FREE_' . bin2hex(random_bytes(8));
            return ['paymentForm' => $this->buildZeroPriceForm($hidden)];
        }

        try {
            return ['paymentForm' => $this->buildRedirectCheckout($hidden, $payinCompany)];
        } catch (Throwable $e) {
            return ['paymentForm' => '<div class="alert alert-danger">PayIn: ' . html_escape($e->getMessage()) . '</div>'];
        }
    }

    public function subscribe($data)
    {
        $package_module_id = $data['package_module_id'] ?? '';
        $data['package_id'] = $package_module_id;
        $company = get_old_result('tbl_saas_companies', ['id' => $data['companies_id']], false);
        $payinCompany = get_old_result('tbl_saas_gateway_subscriptions', [
            'module_id'    => $package_module_id,
            'type'         => 'module',
            'company_id'   => $data['companies_id'],
            'gateway_name' => $this->gateway,
        ], false);

        if (empty($payinCompany) && !empty($company)) {
            $this->type = 'module';
            $company->package_module_id = $package_module_id;
            $this->createCustomer($company, true);
            $payinCompany = get_old_result('tbl_saas_gateway_subscriptions', [
                'module_id'    => $package_module_id,
                'type'         => 'module',
                'company_id'   => $data['companies_id'],
                'gateway_name' => $this->gateway,
            ], false);
        }

        $amount = (float) ($data['amount'] ?? 0);
        $hidden = [
            'package_id'        => $package_module_id,
            'companies_id'      => $data['companies_id'] ?? '',
            'billing_cycle'     => 'monthly_price',
            'amount'            => $amount,
            'currency'          => $this->resolveCurrency($data),
            'package_module_id' => $package_module_id,
            'type'              => 'module',
            'new_module'        => $data['new_module'] ?? '',
            'module_name'       => $data['module_name'] ?? '',
        ];

        if ($amount == 0) {
            $hidden['zero_price'] = '1';
            $hidden['token'] = 'FREE_' . bin2hex(random_bytes(8));
            return ['paymentForm' => $this->buildZeroPriceForm($hidden)];
        }

        try {
            return ['paymentForm' => $this->buildRedirectCheckout($hidden, $payinCompany)];
        } catch (Throwable $e) {
            return ['paymentForm' => '<div class="alert alert-danger">PayIn: ' . html_escape($e->getMessage()) . '</div>'];
        }
    }

    public function cancel_subscription($company_subs): array
    {
        return ['status' => 'success', 'message' => 'Subscription cancelled successfully.'];
    }

    public function resume_subscription($company_subs): array
    {
        return ['status' => 'success', 'message' => 'Subscription resumed successfully.'];
    }

    public function processCallback(array $pending, array $payload = []): array
    {
        $token = (string) ($pending['token'] ?? '');
        $isFreeToken = $token !== '' && strpos($token, 'FREE_') === 0;
        $isZeroPrice = !empty($pending['zero_price']) || $isFreeToken;

        $billingCycle = $pending['billing_cycle'] ?? 'monthly_price';
        $catalogPackage = !empty($pending['package_id'])
            ? get_old_result('tbl_saas_packages', ['id' => $pending['package_id']], false)
            : null;
        $catalogAmount = function_exists('saas_package_cycle_price')
            ? saas_package_cycle_price($catalogPackage, $billingCycle)
            : 0.0;
        $amount = (float) ($pending['amount'] ?? 0);
        // Never override an explicit free checkout with catalog pricing
        if (!$isZeroPrice && $amount <= 0) {
            $amount = (float) $catalogAmount;
        }

        if ($isZeroPrice || $amount <= 0) {
            return [
                'success'        => true,
                'transaction_id' => 'PAYIN_FREE_' . ($token !== '' ? $token : ('FREE_' . time())),
            ];
        }

        if (empty($payload)) {
            return ['success' => false, 'message' => 'PayIn callback payload is missing.'];
        }

        $client = Payin_client::fromSaasMerchant();
        $client->assertSuccessful($payload, $amount);

        return [
            'success'        => true,
            'transaction_id' => $client->transactionId($payload),
        ];
    }

    public function pendingKey(string $token): string
    {
        return 'payin_saas_' . $token;
    }

    private function buildRedirectCheckout(array $hidden, $payinCompany): string
    {
        $token = bin2hex(random_bytes(16));
        $hidden['token'] = $token;
        $this->ci->session->set_userdata($this->pendingKey($token), $hidden);

        if (!empty($payinCompany->id)) {
            $this->ci->saas_model->_table_name = 'tbl_saas_gateway_subscriptions';
            $this->ci->saas_model->_primary_key = 'id';
            $this->ci->saas_model->save_old([
                'subscription_id' => 'PAYIN_PENDING_' . $token,
                'status'          => 'pending',
                'temp'            => json_encode($hidden),
            ], $payinCompany->id);
        }

        $successUrl = base_url('saas/gb/payin_payment_callback/' . $hidden['companies_id'] . '/' . $hidden['package_id'] . '/' . $token);
        $cancelUrl = base_url('saas/gb/payin_payment_cancel/' . $hidden['companies_id'] . '/' . $token);
        $client = Payin_client::fromSaasMerchant();
        $approvedUrl = $client->createExpressCheckout(
            (float) $hidden['amount'],
            $hidden['currency'] ?? $this->currency,
            $successUrl,
            $cancelUrl,
            ConfigItems('payin_enable_wallet') != '0' && ConfigItems('payin_enable_wallet') !== 'FALSE',
            ConfigItems('payin_enable_pawapay') != '0' && ConfigItems('payin_enable_pawapay') !== 'FALSE'
        );

        $html  = '<div class="text-center" style="padding:20px;">';
        $html .= '<p>Redirecting to PayIn checkout...</p>';
        $html .= '<a class="btn btn-success btn-lg" href="' . html_escape($approvedUrl) . '">Pay with PayIn</a>';
        $html .= '</div>';
        $html .= '<script>window.location.href = ' . json_encode($approvedUrl) . ';</script>';

        return $html;
    }

    private function buildZeroPriceForm(array $hiddenFields): string
    {
        $token = $hiddenFields['token'] ?? ('FREE_' . bin2hex(random_bytes(8)));
        $hiddenFields['token'] = $token;
        $hiddenFields['zero_price'] = '1';

        $action_url = base_url('saas/gb/payin_payment_callback/' . ($hiddenFields['companies_id'] ?? '') . '/' . ($hiddenFields['package_id'] ?? '') . '/' . $token);
        $csrf_name  = $this->ci->security->get_csrf_token_name();
        $csrf_hash  = $this->ci->security->get_csrf_hash();

        $this->ci->session->set_userdata($this->pendingKey($token), $hiddenFields);

        // Persist like paid checkout so GET / lost-session can still complete
        $payinCompany = get_old_result('tbl_saas_gateway_subscriptions', [
            'company_id'   => $hiddenFields['companies_id'] ?? 0,
            'type'         => ($hiddenFields['type'] ?? 'package') === 'module' ? 'module' : 'package',
            'gateway_name' => $this->gateway,
        ], false);
        if (!empty($payinCompany->id)) {
            $this->ci->saas_model->_table_name = 'tbl_saas_gateway_subscriptions';
            $this->ci->saas_model->_primary_key = 'id';
            $this->ci->saas_model->save_old([
                'subscription_id' => 'PAYIN_PENDING_' . $token,
                'status'          => 'pending',
                'temp'            => json_encode($hiddenFields),
            ], $payinCompany->id);
        }

        $form  = '<form id="payin-free-form" action="' . html_escape($action_url) . '" method="POST">';
        $form .= '<input type="hidden" name="' . html_escape($csrf_name) . '" value="' . html_escape($csrf_hash) . '">';
        foreach ($hiddenFields as $key => $value) {
            $form .= '<input type="hidden" name="' . html_escape($key) . '" value="' . html_escape($value) . '">';
        }
        $form .= '<div class="text-center" style="padding:20px;">';
        $form .= '<p class="text-success"><i class="fa fa-check-circle"></i> This plan is <strong>free</strong>. No payment required.</p>';
        $form .= '<button type="submit" class="btn btn-success btn-block btn-lg">Complete Purchase</button>';
        $form .= '</div></form>';
        $form .= '<script>document.getElementById("payin-free-form").submit();</script>';

        return $form;
    }

    private function buildLocalPlanIds($package): array
    {
        $id       = is_array($package) ? ($package['id'] ?? '') : ($package->id ?? '');
        $monthly  = is_array($package) ? ($package['monthly_price']  ?? 0) : ($package->monthly_price  ?? 0);
        $yearly   = is_array($package) ? ($package['yearly_price']   ?? 0) : ($package->yearly_price   ?? 0);
        $lifetime = is_array($package) ? ($package['lifetime_price'] ?? 0) : ($package->lifetime_price ?? 0);

        return [
            'monthly'  => ['id' => 'payin_' . $id . '_monthly',  'price' => $monthly],
            'yearly'   => ['id' => 'payin_' . $id . '_yearly',   'price' => $yearly],
            'lifetime' => ['id' => 'payin_' . $id . '_lifetime', 'price' => $lifetime],
        ];
    }
}
