<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Manual_payment_gateway_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_all_gateways()
    {
        return $this->db->get(db_prefix() . 'manual_payment_gateways')->result();
    }

    public function change_status($id, $status)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'manual_payment_gateways', [
            'status' => $status,
        ]);

        return true;
    }

    public function insert_data($data)
    {
        $this->db->insert(db_prefix() . 'manual_payment_gateways', $data);
        return 1;
    }

    public function edit($id)
    {
        dd($id);
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'manual_payment_gateways');

        return true;
    }

    public function update_data($id,$data)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'manual_payment_gateways', $data);
        return 1;
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'manual_payment_gateways');

        return true;
    }

    public function get($id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'manual_payment_gateways')->row();
    }

    public function save_request($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->db->insert(db_prefix() . 'manual_payment_requests', $data);

        if ($this->db->affected_rows() > 0) {
            return $this->db->insert_id();
        }

        return false;
    }

    public function get_all_requests()
    {
        return $this->db->get(db_prefix() . 'manual_payment_requests')->result();
    }

    public function get_request($id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'manual_payment_requests')->row();
    }

    public function update_request($data, $id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'manual_payment_requests', $data);
        return 1;
    }

    public function upload_file($input_name, $upload_folder = 'manual_payment')
    {
        if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
            return false; // No file or upload error
        }

        // Upload path
        $upload_path = FCPATH . 'uploads/' . $upload_folder . '/';

        // Create folder if it doesn't exist
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0755, true);
        }

        // Upload config
        $config['upload_path']   = $upload_path;
        $config['allowed_types'] = 'jpg|jpeg|png|pdf|doc|docx';
        $config['max_size']      = 2048; // in KB
        $config['encrypt_name']  = true;

        $CI =& get_instance();
        $CI->load->library('upload', $config);

        if (!$CI->upload->do_upload($input_name)) {
            return false;
        }

        $data = $CI->upload->data();
        return 'uploads/' . $upload_folder . '/' . $data['file_name'];
    }

    public function mark_as_read($id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'manual_payment_requests', [
            'is_read' => 1,
        ]);

        return true;
    }
}
