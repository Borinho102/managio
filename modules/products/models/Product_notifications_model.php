<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Product_notifications_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($id = false)
    {
        if ($id) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'product_notification_templates')->row();
        }
        $this->db->order_by('id', 'ASC');
        return $this->db->get(db_prefix() . 'product_notification_templates')->result_array();
    }

    public function get_active_for_trigger($trigger_event)
    {
        $this->db->where('active', 1);
        $this->db->where('trigger_event', $trigger_event);
        return $this->db->get(db_prefix() . 'product_notification_templates')->result();
    }

    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['active'] = !empty($data['active']) ? 1 : 0;
        $this->db->insert(db_prefix() . 'product_notification_templates', $data);
        return $this->db->insert_id();
    }

    public function edit($data, $id)
    {
        $data['active'] = !empty($data['active']) ? 1 : 0;
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'product_notification_templates', $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'product_notification_templates');
        return $this->db->affected_rows() > 0;
    }
}
