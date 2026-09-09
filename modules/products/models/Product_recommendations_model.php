<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_recommendations_model extends App_Model
{
    public function get_also_bought($product_id, $limit = 6)
    {
        $orders_with_product = $this->db->select('oi.order_id')
            ->from(db_prefix() . 'order_items oi')
            ->join(db_prefix() . 'order_master om', 'om.id = oi.order_id')
            ->where('om.status', 2)
            ->where('oi.product_id', $product_id)
            ->get()
            ->result_array();
        if (empty($orders_with_product)) {
            return [];
        }
        $order_ids = array_column($orders_with_product, 'order_id');
        $this->db->select('oi.product_id, COUNT(*) as cnt');
        $this->db->from(db_prefix() . 'order_items oi');
        $this->db->where_in('oi.order_id', $order_ids);
        $this->db->where('oi.product_id !=', $product_id);
        $this->db->group_by('oi.product_id');
        $this->db->order_by('cnt', 'DESC');
        $this->db->limit($limit);
        $rows = $this->db->get()->result_array();
        if (empty($rows)) {
            return [];
        }
        $ids = array_column($rows, 'product_id');
        return $this->db->where_in('id', $ids)->get(db_prefix() . 'product_master')->result();
    }

    public function get_by_category($product_id, $category_ids, $limit = 6)
    {
        if (empty($category_ids)) {
            return [];
        }
        $cat_ids = is_array($category_ids) ? array_filter(array_map('intval', $category_ids)) : [(int) $category_ids];
        if (empty($cat_ids)) {
            return [];
        }
        $this->db->select('p.*');
        $this->db->from(db_prefix() . 'product_master p');
        $this->db->where_in('p.product_category_id', $cat_ids);
        $this->db->where('p.id !=', $product_id);
        $this->db->limit($limit);
        return $this->db->get()->result();
    }
}
