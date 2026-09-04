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
            // Shared resolver: admin per-currency price first, then the base
            // price, then live conversion (display only).
            echo json_encode(saas_module_price_payload($module_id, $currency, $billing_cycle));
        } catch (Throwable $e) {
            log_message('error', '[saas] Ajax::module_price error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'exception']);
        }
    }
}
