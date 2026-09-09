<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_price_alerts_model extends App_Model
{
    public function subscribe($product_id, $email, $client_id = null, $target_price = null)
    {
        $this->db->where('product_id', $product_id);
        $this->db->where('email', $email);
        $this->db->where('notified', 0);
        if ($this->db->count_all_results(db_prefix() . 'product_price_alerts') > 0) {
            return ['status' => 'already'];
        }
        if ($target_price === null || $target_price <= 0) {
            $p = $this->db->where('id', $product_id)->get(db_prefix() . 'product_master')->row();
            $target_price = $p ? (isset($p->sale_price) && $p->sale_price ? $p->sale_price : $p->rate) : null;
        }
        $this->db->insert(db_prefix() . 'product_price_alerts', [
            'product_id' => $product_id,
            'email' => $email,
            'client_id' => $client_id,
            'target_price' => $target_price,
            'notified' => 0,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'ok', 'id' => $this->db->insert_id()];
    }

    public function get_pending_for_product($product_id, $current_price)
    {
        $this->db->where('product_id', $product_id);
        $this->db->where('notified', 0);
        $rows = $this->db->get(db_prefix() . 'product_price_alerts')->result_array();
        $out = [];
        foreach ($rows as $r) {
            if ($r['target_price'] === null || (float) $r['target_price'] >= $current_price) {
                $out[] = $r;
            }
        }
        return $out;
    }

    public function mark_notified($id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'product_price_alerts', ['notified' => 1]);
    }
}
