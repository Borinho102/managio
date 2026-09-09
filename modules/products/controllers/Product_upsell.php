<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_upsell extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        $rules = $this->db->order_by('sort_order')->get(db_prefix() . 'product_upsell_rules')->result_array();
        $data['rules'] = $rules;
        $data['products'] = $this->products_model->get_by_id_product();
        $data['title'] = _l('product_upsell_rules');
        $this->load->view('product_upsell/product_upsell', $data);
    }

    public function save()
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        $trigger_ids = $this->input->post('trigger_product_ids');
        $upsell_ids = $this->input->post('upsell_product_ids');
        $name = $this->input->post('name') ?: 'Upsell Rule';
        $trigger_str = is_array($trigger_ids) ? implode(',', array_map('intval', $trigger_ids)) : '';
        $upsell_str = is_array($upsell_ids) ? implode(',', array_map('intval', array_filter($upsell_ids))) : '';
        if (empty($upsell_str)) {
            set_alert('danger', _l('product_upsell_select_products'));
            redirect(admin_url('products/product_upsell'));
        }
        $id = $this->input->post('id');
        $data = [
            'name' => $name,
            'trigger_product_ids' => $trigger_str ?: null,
            'upsell_product_ids' => $upsell_str,
            'display_type' => 'modal',
            'active' => $this->input->post('active') ? 1 : 0,
            'sort_order' => (int) $this->input->post('sort_order'),
        ];
        if ($id) {
            $this->db->where('id', $id)->update(db_prefix() . 'product_upsell_rules', $data);
            set_alert('success', _l('updated_successfully'));
        } else {
            $data['datecreated'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'product_upsell_rules', $data);
            set_alert('success', _l('added_successfully'));
        }
        redirect(admin_url('products/product_upsell'));
    }

    public function delete($id)
    {
        if (!has_permission('products', '', 'delete')) {
            access_denied();
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'product_upsell_rules');
        set_alert('success', _l('deleted'));
        redirect(admin_url('products/product_upsell'));
    }
}
