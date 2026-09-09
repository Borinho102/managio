<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_reviews_model extends App_Model
{
    public function get($id = false)
    {
        if ($id) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'product_reviews')->row();
        }
        $this->db->order_by('datecreated', 'DESC');
        return $this->db->get(db_prefix() . 'product_reviews')->result_array();
    }

    public function get_by_product($product_id, $approved_only = true)
    {
        $this->db->where('product_id', $product_id);
        if ($approved_only) {
            $this->db->where('approved', 1);
        }
        $this->db->order_by('datecreated', 'DESC');
        return $this->db->get(db_prefix() . 'product_reviews')->result();
    }

    public function get_rating_stats($product_id)
    {
        $this->db->where('product_id', $product_id);
        $this->db->where('approved', 1);
        $rows = $this->db->get(db_prefix() . 'product_reviews')->result_array();
        $total = count($rows);
        if ($total == 0) {
            return ['avg' => 0, 'count' => 0, 'distribution' => []];
        }
        $sum = 0;
        $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($rows as $r) {
            $sum += (int) $r['rating'];
            $dist[(int) $r['rating']]++;
        }
        return ['avg' => round($sum / $total, 1), 'count' => $total, 'distribution' => $dist];
    }

    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['approved'] = isset($data['approved']) ? (int) $data['approved'] : 0;
        $this->db->insert(db_prefix() . 'product_reviews', $data);
        return $this->db->insert_id();
    }

    public function edit($data, $id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'product_reviews', $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'product_reviews');
        return $this->db->affected_rows() > 0;
    }

    public function can_review($product_id, $client_id)
    {
        if (!$client_id) {
            return false;
        }
        $this->db->where('product_id', $product_id);
        $this->db->where('client_id', $client_id);
        return $this->db->count_all_results(db_prefix() . 'product_reviews') == 0;
    }

    public function has_purchased($product_id, $client_id)
    {
        $this->db->select('1');
        $this->db->from(db_prefix() . 'order_items oi');
        $this->db->join(db_prefix() . 'order_master o', 'o.id = oi.order_id');
        $this->db->join(db_prefix() . 'invoices i', 'i.id = o.invoice_id');
        $this->db->where('oi.product_id', $product_id);
        $this->db->where('o.clientid', $client_id);
        $this->db->where('i.status', 2);
        return $this->db->get()->num_rows() > 0;
    }
}
