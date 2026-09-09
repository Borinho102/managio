<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Exit_popups extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->model('products/exit_popups_model');
    }

    public function index()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('products', 'tables/exit_popup'));
        }
        $data['title'] = _l('exit_popups');
        $this->load->view('exit_popups/exit_popups', $data);
    }

    public function add()
    {
        if (!has_permission('products', '', 'create')) {
            access_denied('products Create');
        }
        close_setup_menu();
        $post = $this->input->post();
        if (!empty($post)) {
            $this->form_validation->set_rules('name', _l('exit_popup_name'), 'required|max_length[100]');
            $this->form_validation->set_rules('title', _l('exit_popup_title'), 'required|max_length[200]');
            $this->form_validation->set_rules('body', _l('exit_popup_body'), 'required');
            if ($this->form_validation->run()) {
                $data = [
                    'name' => $post['name'],
                    'title' => $post['title'],
                    'body' => $post['body'],
                    'cta_text' => $post['cta_text'] ?? null,
                    'cta_url' => $post['cta_url'] ?? null,
                    'coupon_code' => $post['coupon_code'] ?? null,
                    'trigger_type' => in_array($post['trigger_type'] ?? '', ['exit_intent', 'time_delay', 'scroll']) ? $post['trigger_type'] : 'exit_intent',
                    'trigger_value' => (int) ($post['trigger_value'] ?? 0),
                    'target_pages' => in_array($post['target_pages'] ?? '', ['all', 'product_listing', 'product_detail', 'cart_checkout']) ? $post['target_pages'] : 'all',
                    'require_cart_items' => !empty($post['require_cart_items']) ? 1 : 0,
                    'dont_show_days' => (int) ($post['dont_show_days'] ?? 7),
                    'sort_order' => (int) ($post['sort_order'] ?? 0),
                    'active' => !empty($post['active']) ? 1 : 0,
                ];
                $id = $this->exit_popups_model->add($data);
                if ($id) {
                    $filename = handle_exit_popup_image_upload($id);
                    if ($filename) {
                        $this->exit_popups_model->edit(['image_path' => $filename], $id);
                    }
                    set_alert('success', _l('exit_popup_added'));
                    redirect(admin_url('products/exit_popups'), 'refresh');
                }
            } else {
                set_alert('danger', validation_errors());
            }
        }
        $data['popup'] = null;
        $data['title'] = _l('exit_popup_new');
        $this->load->view('exit_popups/add', $data);
    }

    public function edit($id)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied('products Create');
        }
        close_setup_menu();
        $popup = $this->exit_popups_model->get($id);
        if (!$popup) {
            set_alert('danger', _l('not_found_products'));
            redirect(admin_url('products/exit_popups'), 'refresh');
        }
        $post = $this->input->post();
        if (!empty($post)) {
            $this->form_validation->set_rules('name', _l('exit_popup_name'), 'required|max_length[100]');
            $this->form_validation->set_rules('title', _l('exit_popup_title'), 'required|max_length[200]');
            $this->form_validation->set_rules('body', _l('exit_popup_body'), 'required');
            if ($this->form_validation->run()) {
                $data = [
                    'name' => $post['name'],
                    'title' => $post['title'],
                    'body' => $post['body'],
                    'cta_text' => $post['cta_text'] ?? null,
                    'cta_url' => $post['cta_url'] ?? null,
                    'coupon_code' => $post['coupon_code'] ?? null,
                    'trigger_type' => in_array($post['trigger_type'] ?? '', ['exit_intent', 'time_delay', 'scroll']) ? $post['trigger_type'] : 'exit_intent',
                    'trigger_value' => (int) ($post['trigger_value'] ?? 0),
                    'target_pages' => in_array($post['target_pages'] ?? '', ['all', 'product_listing', 'product_detail', 'cart_checkout']) ? $post['target_pages'] : 'all',
                    'require_cart_items' => !empty($post['require_cart_items']) ? 1 : 0,
                    'dont_show_days' => (int) ($post['dont_show_days'] ?? 7),
                    'sort_order' => (int) ($post['sort_order'] ?? 0),
                    'active' => !empty($post['active']) ? 1 : 0,
                ];
                $filename = handle_exit_popup_image_upload($id);
                if ($filename) {
                    if ($popup->image_path) {
                        $old = get_upload_path_by_type('products') . 'exit_popups/' . $popup->image_path;
                        if (file_exists($old)) {
                            @unlink($old);
                        }
                    }
                    $data['image_path'] = $filename;
                }
                if ($this->exit_popups_model->edit($data, $id)) {
                    set_alert('success', _l('exit_popup_updated'));
                    redirect(admin_url('products/exit_popups'), 'refresh');
                }
            } else {
                set_alert('danger', validation_errors());
            }
        }
        $data['popup'] = $popup;
        $data['title'] = _l('exit_popup_edit');
        $this->load->view('exit_popups/add', $data);
    }

    public function preview($id)
    {
        if (!has_permission('products', '', 'view')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);
            return;
        }
        $popup = $this->exit_popups_model->get($id);
        if (!$popup) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => _l('not_found_products')]);
            return;
        }
        $base = module_dir_url('products', 'uploads/exit_popups/');
        $data = [
            'success' => true,
            'id' => (int) $popup->id,
            'title' => $popup->title,
            'body' => $popup->body,
            'image_url' => !empty($popup->image_path) ? $base . $popup->image_path : '',
            'coupon_code' => $popup->coupon_code ?? '',
            'cta_text' => $popup->cta_text ?? '',
            'cta_url' => $popup->cta_url ?? '',
        ];
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function delete($id)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        if (!$id) {
            redirect(admin_url('products/exit_popups'));
        }
        if ($this->exit_popups_model->delete($id)) {
            set_alert('success', _l('exit_popup_deleted'));
        } else {
            set_alert('warning', _l('problem_deleting', _l('exit_popups')));
        }
        redirect(admin_url('products/exit_popups'));
    }
}
