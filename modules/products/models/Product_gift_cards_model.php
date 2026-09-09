<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_gift_cards_model extends App_Model
{
    public function generate_code()
    {
        $code = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 12));
        while ($this->get_by_code($code)) {
            $code = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 12));
        }
        return $code;
    }

    public function get_by_code($code)
    {
        return $this->db->where('code', $code)->where('active', 1)->get(db_prefix() . 'product_gift_cards')->row();
    }

    public function get($id = false)
    {
        if ($id) {
            return $this->db->where('id', $id)->get(db_prefix() . 'product_gift_cards')->row();
        }
        return $this->db->order_by('id', 'DESC')->get(db_prefix() . 'product_gift_cards')->result_array();
    }

    public function create($data)
    {
        $data['code'] = $data['code'] ?? $this->generate_code();
        $data['balance'] = $data['amount'];
        $data['datecreated'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'product_gift_cards', $data);
        return $this->db->insert_id();
    }

    public function redeem($gift_card_id, $amount, $invoice_id = null)
    {
        $gc = $this->get($gift_card_id);
        if (!$gc || (float) $gc->balance < (float) $amount) {
            return false;
        }
        $this->db->where('id', $gift_card_id);
        $this->db->set('balance', 'balance - ' . (float) $amount, false);
        $this->db->update(db_prefix() . 'product_gift_cards');
        $this->db->insert(db_prefix() . 'product_gift_card_redemptions', [
            'gift_card_id' => $gift_card_id,
            'invoice_id' => $invoice_id,
            'amount' => $amount,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function get_templates()
    {
        return $this->db->where('active', 1)->order_by('id')->get(db_prefix() . 'product_gift_card_templates')->result_array();
    }
}
