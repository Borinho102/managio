<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Coupons extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->model(['coupons_model']);
    }

    public function index()
    {
        if (0 != get_option('coupons_disabled')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }


        close_setup_menu();
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('products', 'tables/coupon'));
        }
        $data['title']         = _l('coupons');
        $this->load->view('coupons/coupons', $data);
    }    

    public function add()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        if (has_permission('products', '', 'view')) {
            $post          = $this->input->post();
            if (!empty($post)) {
                $this->form_validation->set_rules('code', 'coupon code', 'required|is_unique[coupons.code]');
                $this->form_validation->set_rules('type', 'coupon type', 'required');
                $this->form_validation->set_rules('amount', 'coupon amount', 'required');
                
                if (false == $this->form_validation->run()) {
                    set_alert('danger', preg_replace("/\r|\n/", '', validation_errors()));
                } else {
                    $data = [
                        'code'                          => $post['code'],
                        'type'                          => $post['type'],
                        'amount'                        => $post['amount'],
                        'max_uses'                      => isset($post['max_uses']) ? (int) $post['max_uses'] : 0,
                        'max_uses_per_client'           => isset($post['max_uses_per_client']) ? (int) $post['max_uses_per_client'] : 0,
                        'start_date'                    => $post['start_date'] ?? null,
                        'end_date'                      => $post['end_date'] ?? null,
                        'product_ids'                   => !empty($post['product_ids']) ? implode(',', array_map('intval', (array) $post['product_ids'])) : null,
                        'category_ids'                  => !empty($post['category_ids']) ? implode(',', array_map('intval', (array) $post['category_ids'])) : null,
                        'min_order_amount'              => !empty($post['min_order_amount']) ? $post['min_order_amount'] : null,
                    ];
                    $inserted_id    = $this->coupons_model->add($data);
                    
                    if ($inserted_id) {
                        set_alert('success', 'Coupon Added successfully');
                        redirect(admin_url('products/coupons'), 'refresh');
                    } else {
                        set_alert('warning', _l('Error Found - Coupon not inserted'));
                    }
                }
            }
            $this->load->model(['product_category_model', 'products_model']);
            $data['title']              = _l('add_new', 'coupon');
            $data['action']             = _l('coupons');
            $data['products']           = $this->products_model->get_by_id_product();
            $data['categories']         = $this->product_category_model->get();

            $this->load->view('coupons/add', $data);
        } else {
            access_denied('coupons');
        }
    }

    public function edit($id)
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        if (has_permission('products', '', 'view')) {
            $original_coupon = $data['coupon'] = $this->coupons_model->get($id, true);
            if (empty($original_coupon)) {
                set_alert('danger', _l('not_found_products'));
                redirect(admin_url('products/coupons'), 'refresh');
            }
            $post = $this->input->post();
           
            if (!empty($post)) {
                $this->form_validation->set_rules('code', 'coupon code', 'required');
                $this->form_validation->set_rules('type', 'coupon type', 'required');
                $this->form_validation->set_rules('amount', 'coupon amount', 'required');
                if ($original_coupon->code != $post['code']) {
                    $this->form_validation->set_rules('code', 'coupon code', 'required|is_unique[coupons.code]');
                }
                if (false == $this->form_validation->run()) {
                    set_alert('danger', preg_replace("/\r|\n/", '', validation_errors()));
                } else {
                    $data = [
                        'code'                          => $post['code'],
                        'type'                          => $post['type'],
                        'amount'                        => $post['amount'],
                        'max_uses'                      => isset($post['max_uses']) ? (int) $post['max_uses'] : 0,
                        'max_uses_per_client'           => isset($post['max_uses_per_client']) ? (int) $post['max_uses_per_client'] : 0,
                        'start_date'                    => $post['start_date'] ?? null,
                        'end_date'                      => $post['end_date'] ?? null,
                        'product_ids'                   => !empty($post['product_ids']) ? implode(',', array_map('intval', (array) $post['product_ids'])) : null,
                        'category_ids'                  => !empty($post['category_ids']) ? implode(',', array_map('intval', (array) $post['category_ids'])) : null,
                        'min_order_amount'              => !empty($post['min_order_amount']) ? $post['min_order_amount'] : null,
                    ];
                    $result = $this->coupons_model->edit($data, $id);
                    if ($result) {
                        set_alert('success', 'Coupon Updated successfully');
                        redirect(admin_url('products/coupons'), 'refresh');
                    } else {
                        set_alert('warning', _l('Error Found Or You Have not made any changes'));
                    }
                }
            }
            $this->load->model(['product_category_model', 'products_model']);
            $data['title']              = _l('edit', 'coupon');
            $data['products']           = $this->products_model->get_by_id_product();
            $data['categories']         = $this->product_category_model->get();
            $this->load->view('coupons/add', $data);
        } else {
            access_denied('products');
        }
    }

    public function delete($id)
    {

        if (!$id) {
            redirect(admin_url('products/coupons'));
        }
        $response = $this->coupons_model->delete($id);
        if (true == $response) {
            set_alert('success', _l('deleted', _l('coupons')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('coupons')));
        }
        redirect(admin_url('coupons'));
    }
}