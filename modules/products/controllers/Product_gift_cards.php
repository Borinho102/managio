<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_gift_cards extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('products/product_gift_cards_model');
    }

    public function index()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        close_setup_menu();
        $data['cards'] = $this->product_gift_cards_model->get();
        $data['title'] = _l('product_gift_cards');
        $this->load->view('product_gift_cards/product_gift_cards', $data);
    }

    public function templates()
    {
        if (!has_permission('products', '', 'view')) {
            access_denied('products View');
        }
        $templates = $this->db->order_by('id')->get(db_prefix() . 'product_gift_card_templates')->result_array();
        $data['templates'] = $templates;
        $data['title'] = _l('product_gift_card_templates');
        $this->load->view('product_gift_cards/templates', $data);
    }

    public function template($id = null)
    {
        if (!has_permission('products', '', 'create')) {
            access_denied();
        }
        if ($this->input->post()) {
            $data = [
                'name' => $this->input->post('name'),
                'design_html' => $this->input->post('design_html'),
                'merge_fields_info' => $this->input->post('merge_fields_info'),
                'active' => $this->input->post('active') ? 1 : 0,
            ];
            if ($id) {
                $this->db->where('id', $id)->update(db_prefix() . 'product_gift_card_templates', $data);
                if (!empty($_FILES['design_image']['name'])) {
                    $this->_upload_template_image($id);
                }
                set_alert('success', _l('updated_successfully'));
            } else {
                $data['datecreated'] = date('Y-m-d H:i:s');
                $this->db->insert(db_prefix() . 'product_gift_card_templates', $data);
                $id = $this->db->insert_id();
                if ($id && !empty($_FILES['design_image']['name'])) {
                    $this->_upload_template_image($id);
                }
                set_alert('success', _l('added_successfully'));
            }
            redirect(admin_url('products/product_gift_cards/templates'));
        }
        $template = $id ? $this->db->where('id', $id)->get(db_prefix() . 'product_gift_card_templates')->row() : null;
        $data['template'] = $template;
        $data['title'] = $id ? _l('product_gift_card_edit_template') : _l('product_gift_card_add_template');
        $this->load->view('product_gift_cards/template_form', $data);
    }

    private function _upload_template_image($template_id)
    {
        $path = get_upload_path_by_type('products') . 'gift_cards/';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $this->load->library('upload', [
            'upload_path' => $path,
            'allowed_types' => 'gif|jpg|jpeg|png|webp',
            'max_size' => 2048,
        ]);
        if ($this->upload->do_upload('design_image')) {
            $upload = $this->upload->data();
            $this->db->where('id', $template_id)->update(db_prefix() . 'product_gift_card_templates', ['design_image' => 'gift_cards/' . $upload['file_name']]);
        }
    }

    public function delete_template($id)
    {
        if (!has_permission('products', '', 'delete')) {
            access_denied();
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'product_gift_card_templates');
        set_alert('success', _l('deleted'));
        redirect(admin_url('products/product_gift_cards/templates'));
    }
}
