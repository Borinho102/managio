<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Coupons_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($id = false)
    {
        if ($id) {
            $this->db->where('id', $id);
            $coupon = $this->db->get(db_prefix() . 'coupons')->row();
            return $coupon;
        }
        $coupons = $this->db->get(db_prefix() . 'coupons')->result_array();

        return $coupons;
    }

    public function get_by_code($code = false)
    {
        if ($code) {
            $this->db->where('code', $code);
            $coupon = $this->db->get(db_prefix() . 'coupons')->row();
            return $coupon;
        }
        $coupons = $this->db->get(db_prefix() . 'coupons')->result_array();

        return $coupons;
    }

    public function is_available_for_cart($id, $client_id, $cart_items, $cart_subtotal)
    {
        if (!$this->is_available($id, $client_id)) {
            return false;
        }
        $coupon = $this->get($id);
        if (!$coupon) {
            return false;
        }
        if (!empty($coupon->min_order_amount) && (float) $coupon->min_order_amount > (float) $cart_subtotal) {
            return false;
        }
        $product_ids = !empty($coupon->product_ids) ? array_map('intval', array_filter(explode(',', $coupon->product_ids))) : [];
        $category_ids = !empty($coupon->category_ids) ? array_map('intval', array_filter(explode(',', $coupon->category_ids))) : [];
        if (empty($product_ids) && empty($category_ids)) {
            return true;
        }
        $has_eligible = false;
        foreach ($cart_items as $item) {
            $pid = (int) ($item->product_id ?? $item['product_id']);
            $cat_id = (int) ($item->product_category_id ?? $item['product_category_id'] ?? 0);
            if (!empty($product_ids) && in_array($pid, $product_ids)) {
                $has_eligible = true;
                break;
            }
            if (!empty($category_ids) && in_array($cat_id, $category_ids)) {
                $has_eligible = true;
                break;
            }
        }
        return $has_eligible;
    }

    public function is_available($id, $client_id = false)
    {
        if (!$id) {
            return false;
        }
        $this->db->where('id', $id);
        $coupon = $this->db->get(db_prefix() . 'coupons')->row();
        if (!$coupon) {
            return false;
        }
        $now = date('Y-m-d');
        if ($coupon->start_date && $coupon->start_date > $now) {
            return false;
        }
        if ($coupon->end_date && $coupon->end_date < $now) {
            return false;
        }
        $this->db->where('coupon_id', $coupon->id);
        $total_uses = $this->db->count_all_results(db_prefix() . 'invoices');
        if ($coupon->max_uses > 0 && $total_uses >= $coupon->max_uses) {
            return false;
        }
        if ($client_id && $coupon->max_uses_per_client > 0) {
            $this->db->where('coupon_id', $coupon->id);
            $this->db->where('clientid', $client_id);
            $client_uses = $this->db->count_all_results(db_prefix() . 'invoices');
            if ($client_uses >= $coupon->max_uses_per_client) {
                return false;
            }
        }
        return true;
    }

    public function get_availables($client_id = false)
    {
        $coupons = $this->db->get(db_prefix() . 'coupons')->result_array();
        $now = date('Y-m-d');
        $available_coupons = [];
        foreach ($coupons as $coupon) {
            if ($coupon['start_date'] && $coupon['start_date'] > $now) {
                continue;
            }
            if ($coupon['end_date'] && $coupon['end_date'] < $now) {
                continue;
            }
            $this->db->where('coupon_id', $coupon['id']);
            $total_uses = $this->db->count_all_results(db_prefix() . 'invoices');
            if ($coupon['max_uses'] > 0 && $total_uses >= $coupon['max_uses']) {
                continue;
            }
            if ($client_id && $coupon['max_uses_per_client'] > 0) {
                $this->db->where('coupon_id', $coupon['id']);
                $this->db->where('clientid', $client_id);
                $client_uses = $this->db->count_all_results(db_prefix() . 'invoices');
                if ($client_uses >= $coupon['max_uses_per_client']) {
                    continue;
                }
            }
            $available_coupons[] = $coupon;
        }
        return $available_coupons;
    }

    public function get_used_times($id = false)
    {
        if ($id) {
            $this->db->where('coupon_id', $id);
        }
        $invoices = $this->db->get(db_prefix() . 'invoices')->result_array();

        return count($invoices);
    }
    
    public function add($data)
    {
        $data['start_date'] = !empty($data['start_date']) ? to_sql_date($data['start_date'], true) : null;
        $data['end_date'] = !empty($data['end_date']) ? to_sql_date($data['end_date'], true) : null;
        $data = $this->normalise_currency($data);

        $this->db->insert(db_prefix() . 'coupons', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            log_activity('New Coupon Added [ ID:' . $insert_id . ', ' . $data['code'] . ' ]');

            return $insert_id;
        }

        return false;
    }
    
    public function edit($data, $id)
    {
        $data['start_date'] = !empty($data['start_date']) ? to_sql_date($data['start_date'], true) : null;
        $data['end_date'] = !empty($data['end_date']) ? to_sql_date($data['end_date'], true) : null;
        $data = $this->normalise_currency($data);

        $coupon = $this->get($id);
        $this->db->where('id', $id);
        $res = $this->db->update(db_prefix() . 'coupons', $data);
        if ($this->db->affected_rows() > 0) {
            log_activity('Coupon Details updated[ ID: ' . $id . ', ' . $coupon->code . ' ]');
        }
        if ($res) {
            return true;
        }

        return false;
    }

    /**
     * Keep the coupon's currency column sane regardless of how the form was
     * rendered: 0 ("any currency") when multi-currency is off, and dropped
     * entirely when the column is not there yet, so a file update that lands
     * before the migration cannot break coupon saving.
     *
     * @param array $data
     *
     * @return array
     */
    private function normalise_currency($data)
    {
        if (!$this->db->field_exists('currency', db_prefix() . 'coupons')) {
            unset($data['currency']);

            return $data;
        }

        $data['currency'] = products_multicurrency_enabled() ? (int) ($data['currency'] ?? 0) : 0;

        return $data;
    }

    public function delete($id)
    {
        $coupon  = $this->get($id);
        if (!empty($id)) {
            $this->db->where('id', $id);
        }
        $result = $this->db->delete(db_prefix() . 'coupons');
        log_activity('Coupon Deleted[ ID: ' . $id . ', '. $coupon->code . ' ]');

        return $result;
    }
}
