<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_reviews extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('products/product_reviews_model');
    }

    public function index()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('products', 'tables/product_review'));
        }
        $data['title'] = _l('product_reviews');
        $this->load->view('product_reviews/product_reviews', $data);
    }

    public function approve($id)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        if ($this->product_reviews_model->edit(['approved' => 1], $id)) {
            set_alert('success', _l('product_review_approved'));
        }
        redirect(admin_url('products/product_reviews'));
    }

    public function unapprove($id)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        if ($this->product_reviews_model->edit(['approved' => 0], $id)) {
            set_alert('success', _l('product_review_unapproved'));
        }
        redirect(admin_url('products/product_reviews'));
    }

    public function delete($id)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        if ($this->product_reviews_model->delete($id)) {
            set_alert('success', _l('product_review_deleted'));
        }
        redirect(admin_url('products/product_reviews'));
    }
}
