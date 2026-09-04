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

            // Base currency for conversion: prefer package currency if available, otherwise saas_default_currency()
            $baseCurrency = saas_default_currency();

            if (!empty($module->package_id)) {
                $pkg = get_row('tbl_saas_packages', ['id' => $module->package_id]);
                if (!empty($pkg) && !empty($pkg->currency)) {
                    $baseCurrency = strtoupper(trim((string) $pkg->currency));
                }
            }

            $amount = (float) $module->price;

            if (saas_currency_codes_match($baseCurrency, $currency)) {
                $formatted = display_money($amount, $currency);
                echo json_encode(['success' => true, 'price' => $amount, 'price_html' => $formatted]);
                return;
            }

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
