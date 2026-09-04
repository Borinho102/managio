<?php defined('BASEPATH') or exit('No direct script access allowed');

class Ajax extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
    }

    /**
     * POST/GET: module_id, currency
     * Returns JSON: { success: true, price: <numeric>, price_html: <formatted> }
     */
    public function module_price()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }

        $module_id = $this->input->post_get('module_id');
        $currency = $this->input->post_get('currency');
        $module_id = is_numeric($module_id) ? (int) $module_id : 0;
        $currency = strtoupper(trim((string) $currency));

        // billing_cycle expected values: 'monthly','yearly','lifetime' or 'default' (or empty)
        $billing_cycle_raw = $this->input->post_get('billing_cycle');
        $billing_cycle = null;
        if (!empty($billing_cycle_raw) && $billing_cycle_raw !== 'default') {
            $billing_cycle = trim((string)$billing_cycle_raw);
        }

        if (empty($module_id) || $currency === '') {
            echo json_encode(['success' => false, 'message' => 'missing_parameters']);
            return;
        }

        try {
            $module = get_row('tbl_saas_package_module', ['package_module_id' => $module_id]);
            if (empty($module)) {
                echo json_encode(['success' => false, 'message' => 'module_not_found']);
                return;
            }

            // Base currency for conversion: prefer the current tenant/company currency, then the package currency,
            // and finally the default SaaS currency.
            $baseCurrency = saas_tenant_currency();

            if (!empty($module->package_id)) {
                $pkg = get_row('tbl_saas_packages', ['id' => $module->package_id]);
                if (!empty($pkg) && !empty($pkg->currency)) {
                    $baseCurrency = strtoupper(trim((string) $pkg->currency));
                }
            }

            $amount = (float) $module->price;

            // First, prefer an admin-specified per-currency price for this module
            $this->load->model('saas_package_module_prices_model');
            // Try billing-specific saved price first (if billing_cycle provided), then fallback to default (NULL)
            $savedPrice = null;
            if ($billing_cycle !== null) {
                $savedPrice = $this->saas_package_module_prices_model->get_price($module_id, $currency, $billing_cycle);
            }
            if (empty($savedPrice)) {
                $savedPrice = $this->saas_package_module_prices_model->get_price($module_id, $currency, null);
            }

            if (!empty($savedPrice) && isset($savedPrice->amount)) {
                $converted = (float) $savedPrice->amount;
                $formatted = display_money($converted, $currency);
                echo json_encode(['success' => true, 'price' => $converted, 'price_html' => $formatted, 'source' => 'admin_price']);
                return;
            }

            if (saas_currency_codes_match($baseCurrency, $currency)) {
                $formatted = display_money($amount, $currency);
                echo json_encode(['success' => true, 'price' => $amount, 'price_html' => $formatted]);
                return;
            }

            // Use the cached helper for conversion fallback
            // Load helper (exists in modules/saas/helpers)
            $helperPath = APP_MODULES_PATH . 'saas/helpers/saas_currency_helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
            }

            // Default TTL taken by helper (12 hours) unless option added later
            try {
                if (function_exists('saas_convert_amount')) {
                    $converted = saas_convert_amount($amount, $baseCurrency, $currency, 2);
                    $formatted = display_money($converted, $currency);
                    echo json_encode(['success' => true, 'price' => $converted, 'price_html' => $formatted, 'source' => 'converted']);
                    return;
                }
            } catch (Throwable $e) {
                log_message('error', '[saas] currency conversion helper error: ' . $e->getMessage());
                // fallback to direct provider below if helper fails
            }

            // Last-resort: fallback to exchangerate.host direct call (existing behavior)
            $from = urlencode($baseCurrency);
            $to = urlencode($currency);
            $amt = urlencode((string) $amount);
            $url = "https://api.exchangerate.host/convert?from={$from}&to={$to}&amount={$amt}&places=2";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $resp = curl_exec($ch);
            curl_close($ch);

            if ($resp === false || $resp === null) {
                $formatted = display_money($amount, $currency);
                echo json_encode(['success' => true, 'price' => $amount, 'price_html' => $formatted, 'warning' => 'conversion_failed']);
                return;
            }

            $json = @json_decode($resp);
            if (empty($json) || !isset($json->result)) {
                $formatted = display_money($amount, $currency);
                echo json_encode(['success' => true, 'price' => $amount, 'price_html' => $formatted, 'warning' => 'conversion_failed']);
                return;
            }

            $converted = (float) $json->result;
            $formatted = display_money($converted, $currency);
            echo json_encode(['success' => true, 'price' => $converted, 'price_html' => $formatted]);
            return;
        } catch (Throwable $e) {
            log_message('error', '[saas] Ajax::module_price error: ' . $e->getMessage());
            $formatted = display_money($module->price ?? 0, $currency);
            echo json_encode(['success' => true, 'price' => $module->price ?? 0, 'price_html' => $formatted, 'warning' => 'exception']);
            return;
        }
    }
}
