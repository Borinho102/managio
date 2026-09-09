<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Exit_popups_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($id = false)
    {
        if ($id) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'product_exit_popups')->row();
        }
        $this->db->order_by('sort_order', 'ASC');
        return $this->db->get(db_prefix() . 'product_exit_popups')->result_array();
    }

    public function get_active_for_page($page_type, $has_cart_items)
    {
        if (get_option('product_exit_popups_enabled') != '1') {
            return [];
        }
        $this->db->where('active', 1);
        $this->db->group_start();
        $this->db->where('target_pages', 'all');
        $this->db->or_where('target_pages', $page_type);
        $this->db->group_end();
        if (!$has_cart_items) {
            $this->db->where('require_cart_items', 0);
        }
        $this->db->order_by('sort_order', 'ASC');
        return $this->db->get(db_prefix() . 'product_exit_popups')->result_array();
    }

    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['trigger_value'] = (int) ($data['trigger_value'] ?? 0);
        $data['dont_show_days'] = (int) ($data['dont_show_days'] ?? 7);
        $data['require_cart_items'] = !empty($data['require_cart_items']) ? 1 : 0;
        $data['active'] = !empty($data['active']) ? 1 : 0;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $this->db->insert(db_prefix() . 'product_exit_popups', $data);
        return $this->db->insert_id();
    }

    public function edit($data, $id)
    {
        $data['dateupdated'] = date('Y-m-d H:i:s');
        $data['trigger_value'] = (int) ($data['trigger_value'] ?? 0);
        $data['dont_show_days'] = (int) ($data['dont_show_days'] ?? 7);
        $data['require_cart_items'] = !empty($data['require_cart_items']) ? 1 : 0;
        $data['active'] = !empty($data['active']) ? 1 : 0;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'product_exit_popups', $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete($id)
    {
        $popup = $this->get($id);
        if ($popup && !empty($popup->image_path)) {
            $path = get_upload_path_by_type('products') . 'exit_popups/' . $popup->image_path;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'product_exit_popups');
        return $this->db->affected_rows() > 0;
    }

    public function increment_impressions($id)
    {
        $this->db->set('impressions', 'impressions+1', false);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'product_exit_popups');
    }

    public function increment_clicks($id)
    {
        $this->db->set('clicks', 'clicks+1', false);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'product_exit_popups');
    }
}
