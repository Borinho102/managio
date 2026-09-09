<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Product_notifications extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->model('products/product_notifications_model');
    }

    public function index()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('products', 'tables/product_notification'));
        }
        $data['title'] = _l('product_notifications');
        $this->load->view('product_notifications/product_notifications', $data);
    }

    public function add()
    {
        if (!has_permission('products', '', 'create')) {
            access_denied('products Create');
        }
        close_setup_menu();
        $post = $this->input->post();
        if (!empty($post)) {
            $this->form_validation->set_rules('name', _l('product_notification_name'), 'required|max_length[100]');
            $this->form_validation->set_rules('trigger_event', _l('product_notification_trigger'), 'required');
            $this->form_validation->set_rules('message_template', _l('product_notification_message'), 'required');
            if (empty($post['use_global_gateway'])) {
                $this->form_validation->set_rules('webhook_url', _l('product_notification_webhook_url'), 'required|valid_url');
            }
            if ($this->form_validation->run()) {
                $data = [
                    'name' => $post['name'],
                    'channel' => in_array($post['channel'] ?? '', ['whatsapp', 'sms', 'webhook']) ? $post['channel'] : 'whatsapp',
                    'use_global_gateway' => !empty($post['use_global_gateway']) ? 1 : 0,
                    'trigger_event' => $post['trigger_event'],
                    'recipient' => in_array($post['recipient'] ?? '', ['client', 'staff']) ? $post['recipient'] : 'client',
                    'message_template' => $post['message_template'],
                    'webhook_url' => $post['webhook_url'] ?? null,
                    'webhook_method' => ($post['webhook_method'] ?? 'POST') === 'GET' ? 'GET' : 'POST',
                    'webhook_body' => $post['webhook_body'] ?? null,
                    'active' => !empty($post['active']) ? 1 : 0,
                ];
                if ($this->product_notifications_model->add($data)) {
                    set_alert('success', _l('product_notification_added'));
                    redirect(admin_url('products/product_notifications'), 'refresh');
                }
            } else {
                set_alert('danger', validation_errors());
            }
        }
        $data['tpl'] = null;
        $data['title'] = _l('product_notification_new');
        $this->load->view('product_notifications/add', $data);
    }

    public function edit($id)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied('products Create');
        }
        close_setup_menu();
        $tpl = $this->product_notifications_model->get($id);
        if (!$tpl) {
            set_alert('danger', _l('not_found_products'));
            redirect(admin_url('products/product_notifications'), 'refresh');
        }
        $post = $this->input->post();
        if (!empty($post)) {
            $this->form_validation->set_rules('name', _l('product_notification_name'), 'required|max_length[100]');
            $this->form_validation->set_rules('trigger_event', _l('product_notification_trigger'), 'required');
            $this->form_validation->set_rules('message_template', _l('product_notification_message'), 'required');
            if (empty($post['use_global_gateway'])) {
                $this->form_validation->set_rules('webhook_url', _l('product_notification_webhook_url'), 'required|valid_url');
            }
            if ($this->form_validation->run()) {
                $data = [
                    'name' => $post['name'],
                    'channel' => in_array($post['channel'] ?? '', ['whatsapp', 'sms', 'webhook']) ? $post['channel'] : 'whatsapp',
                    'use_global_gateway' => !empty($post['use_global_gateway']) ? 1 : 0,
                    'trigger_event' => $post['trigger_event'],
                    'recipient' => in_array($post['recipient'] ?? '', ['client', 'staff']) ? $post['recipient'] : 'client',
                    'message_template' => $post['message_template'],
                    'webhook_url' => $post['webhook_url'] ?? null,
                    'webhook_method' => ($post['webhook_method'] ?? 'POST') === 'GET' ? 'GET' : 'POST',
                    'webhook_body' => $post['webhook_body'] ?? null,
                    'active' => !empty($post['active']) ? 1 : 0,
                ];
                if ($this->product_notifications_model->edit($data, $id)) {
                    set_alert('success', _l('product_notification_updated'));
                    redirect(admin_url('products/product_notifications'), 'refresh');
                }
            } else {
                set_alert('danger', validation_errors());
            }
        }
        $data['tpl'] = $tpl;
        $data['title'] = _l('product_notification_edit');
        $this->load->view('product_notifications/add', $data);
    }

    public function delete($id)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        if (!$id) {
            redirect(admin_url('products/product_notifications'));
        }
        if ($this->product_notifications_model->delete($id)) {
            set_alert('success', _l('product_notification_deleted'));
        } else {
            set_alert('warning', _l('problem_deleting', _l('product_notifications')));
        }
        redirect(admin_url('products/product_notifications'));
    }
}
