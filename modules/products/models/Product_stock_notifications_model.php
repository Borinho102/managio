<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_stock_notifications_model extends App_Model
{
    public function subscribe($product_id, $email, $client_id = null)
    {
        $this->db->where('product_id', $product_id);
        $this->db->where('email', $email);
        $this->db->where('notified', 0);
        if ($this->db->count_all_results(db_prefix() . 'product_stock_notifications') > 0) {
            return ['status' => 'already'];
        }
        $this->db->insert(db_prefix() . 'product_stock_notifications', [
            'product_id' => $product_id,
            'email' => $email,
            'client_id' => $client_id,
            'notified' => 0,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'ok', 'id' => $this->db->insert_id()];
    }

    public function get_pending_for_product($product_id)
    {
        return $this->db->where('product_id', $product_id)->where('notified', 0)->get(db_prefix() . 'product_stock_notifications')->result_array();
    }

    public function mark_notified($id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'product_stock_notifications', ['notified' => 1]);
    }

    public function get_products_now_in_stock()
    {
        $this->db->select('DISTINCT sn.product_id');
        $this->db->from(db_prefix() . 'product_stock_notifications sn');
        $this->db->join(db_prefix() . 'product_master p', 'p.id = sn.product_id');
        $this->db->where('sn.notified', 0);
        $this->db->where('p.quantity_number >', 0);
        return $this->db->get()->result_array();
    }
}
