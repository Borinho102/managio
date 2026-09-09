<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wishlist_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function add($client_id, $product_id, $product_variation_id = null)
    {
        if (!$client_id || !$product_id) {
            return false;
        }
        $existing = $this->get_item($client_id, $product_id, $product_variation_id);
        if ($existing) {
            return $existing->id;
        }
        $this->db->insert(db_prefix() . 'product_wishlist', [
            'client_id' => $client_id,
            'product_id' => $product_id,
            'product_variation_id' => ($product_variation_id !== null && $product_variation_id !== '') ? $product_variation_id : null,
            'dateadded' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function remove($client_id, $product_id, $product_variation_id = null)
    {
        $this->db->where('client_id', $client_id);
        $this->db->where('product_id', $product_id);
        if ($product_variation_id !== null && $product_variation_id !== '') {
            $this->db->where('product_variation_id', $product_variation_id);
        } else {
            $this->db->where('(product_variation_id IS NULL OR product_variation_id = 0)');
        }
        $this->db->delete(db_prefix() . 'product_wishlist');
        return $this->db->affected_rows() > 0;
    }

    public function get_item($client_id, $product_id, $product_variation_id = null)
    {
        $this->db->where('client_id', $client_id);
        $this->db->where('product_id', $product_id);
        if ($product_variation_id !== null && $product_variation_id !== '') {
            $this->db->where('product_variation_id', $product_variation_id);
        } else {
            $this->db->where('(product_variation_id IS NULL OR product_variation_id = 0)');
        }
        return $this->db->get(db_prefix() . 'product_wishlist')->row();
    }

    public function get_by_client($client_id)
    {
        $this->db->where('w.client_id', $client_id);
        $this->db->select('w.*, p.product_name, p.product_image, p.rate, p.slug');
        $this->db->from(db_prefix() . 'product_wishlist w');
        $this->db->join(db_prefix() . 'product_master p', 'p.id = w.product_id');
        $items = $this->db->get()->result_array();
        foreach ($items as $i => $item) {
            if (!empty($item['product_variation_id'])) {
                $this->db->select('pv.rate, v.name as variation_name, vv.value as variation_value');
                $this->db->from(db_prefix() . 'product_variations pv');
                $this->db->join(db_prefix() . 'variations v', 'v.id = pv.variation_id');
                $this->db->join(db_prefix() . 'variation_values vv', 'vv.id = pv.variation_value_id');
                $this->db->where('pv.id', $item['product_variation_id']);
                $pv = $this->db->get()->row();
                if ($pv) {
                    $items[$i]['rate'] = $pv->rate;
                    $items[$i]['variation_display'] = $pv->variation_name . ': ' . $pv->variation_value;
                }
            }
        }
        return $items;
    }

    public function toggle($client_id, $product_id, $product_variation_id = null)
    {
        $existing = $this->get_item($client_id, $product_id, $product_variation_id);
        if ($existing) {
            $this->remove($client_id, $product_id, $product_variation_id);
            return false;
        }
        $this->add($client_id, $product_id, $product_variation_id);
        return true;
    }
}
