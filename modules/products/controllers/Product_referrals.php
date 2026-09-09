<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_referrals extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('products/product_referrals_model');
    }

    public function index()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        $data['codes'] = $this->product_referrals_model->get_all_codes();
        $data['title'] = _l('product_referral_program');
        $this->load->view('product_referrals/product_referrals', $data);
    }

    public function settings()
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        if ($this->input->post()) {
            $percent = (float) $this->input->post('product_referral_commission_percent');
            $fixed = (float) $this->input->post('product_referral_commission_fixed');
            update_option('product_referral_commission_percent', $percent >= 0 ? $percent : 10);
            update_option('product_referral_commission_fixed', $fixed >= 0 ? $fixed : 0);
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('products/product_referrals'));
        }
    }
}
