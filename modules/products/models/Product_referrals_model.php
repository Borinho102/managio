<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_referrals_model extends App_Model
{
    public function get_code_by_id($id)
    {
        return $this->db->where('id', $id)->get(db_prefix() . 'product_referral_codes')->row();
    }

    public function get_code_by_code($code)
    {
        return $this->db->where('code', $code)->get(db_prefix() . 'product_referral_codes')->row();
    }

    public function get_or_create_for_client($client_id)
    {
        $row = $this->db->where('client_id', $client_id)->get(db_prefix() . 'product_referral_codes')->row();
        if ($row) {
            return $row;
        }
        $code = strtoupper(substr(md5(uniqid((string) $client_id, true)), 0, 8));
        while ($this->get_code_by_code($code)) {
            $code = strtoupper(substr(md5(uniqid((string) $client_id . time(), true)), 0, 8));
        }
        $this->db->insert(db_prefix() . 'product_referral_codes', [
            'client_id' => $client_id,
            'code' => $code,
            'commission_percent' => get_option('product_referral_commission_percent') ?: 10,
            'commission_fixed' => get_option('product_referral_commission_fixed') ?: 0,
            'total_earned' => 0,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        return $this->get_code_by_id($this->db->insert_id());
    }

    public function record_order_referral($order_id, $referral_code_id, $order_total)
    {
        $ref = $this->get_code_by_id($referral_code_id);
        if (!$ref) {
            return false;
        }
        $commission = 0;
        if ($ref->commission_percent > 0) {
            $commission += $order_total * ($ref->commission_percent / 100);
        }
        if ($ref->commission_fixed > 0) {
            $commission += $ref->commission_fixed;
        }
        if ($commission <= 0) {
            return false;
        }
        $this->db->insert(db_prefix() . 'product_referral_tracking', [
            'referral_code_id' => $referral_code_id,
            'order_id' => $order_id,
            'commission' => $commission,
            'status' => 'pending',
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        $this->db->set('total_earned', 'total_earned + ' . (float) $commission, false);
        $this->db->where('id', $referral_code_id);
        $this->db->update(db_prefix() . 'product_referral_codes');
        return true;
    }

    public function get_all_codes()
    {
        return $this->db->order_by('id', 'DESC')->get(db_prefix() . 'product_referral_codes')->result_array();
    }

    public function get_tracking_by_code($code_id)
    {
        return $this->db->where('referral_code_id', $code_id)->order_by('datecreated', 'DESC')->get(db_prefix() . 'product_referral_tracking')->result_array();
    }
}
