<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Saas_package_module_prices_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a specific price for a module/currency/billing_cycle
     * Returns object or null
     */
    public function get_price($package_module_id, $currency, $billing_cycle = null)
    {
        // Guard: return null if table not present to avoid fatal DB exceptions when migrations are pending
        if (!$this->db->table_exists('tbl_saas_package_module_prices')) {
            return null;
        }
        try {
            $this->db->from('tbl_saas_package_module_prices');
            $this->db->where('package_module_id', $package_module_id);
            $this->db->where('currency', strtoupper($currency));
            if ($billing_cycle === null) {
                $this->db->where('billing_cycle IS NULL', null, false);
            } else {
                $this->db->where('billing_cycle', $billing_cycle);
            }
            $query = $this->db->get();
            return $query->row();
        } catch (\Throwable $e) {
            log_message('error', '[saas] Saas_package_module_prices_model::get_price error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all prices for a module
     */
    public function get_prices_for_module($package_module_id)
    {
        if (!$this->db->table_exists('tbl_saas_package_module_prices')) {
            return [];
        }
        try {
            $this->db->from('tbl_saas_package_module_prices');
            $this->db->where('package_module_id', $package_module_id);
            $query = $this->db->get();
            return $query->result();
        } catch (\Throwable $e) {
            log_message('error', '[saas] Saas_package_module_prices_model::get_prices_for_module error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Save a single price (insert or update)
     * $data = ['package_module_id'=>, 'currency'=>, 'billing_cycle'=>, 'amount'=>]
     */
    public function save_price($data)
    {
        if (empty($data['package_module_id']) || empty($data['currency'])) {
            return false;
        }
        if (!$this->db->table_exists('tbl_saas_package_module_prices')) {
            log_message('error', '[saas] Attempt to save per-currency price but table tbl_saas_package_module_prices does not exist.');
            return false;
        }

        $currency = strtoupper($data['currency']);
        $billing_cycle = isset($data['billing_cycle']) ? $data['billing_cycle'] : null;

        try {
            $existing = $this->get_price($data['package_module_id'], $currency, $billing_cycle);
            $save = [
                'package_module_id' => $data['package_module_id'],
                'currency' => $currency,
                'billing_cycle' => $billing_cycle,
                'amount' => floatval($data['amount']),
            ];

            if ($existing) {
                $this->db->where('package_module_price_id', $existing->package_module_price_id);
                $this->db->update('tbl_saas_package_module_prices', $save);
                return $existing->package_module_price_id;
            }

            $this->db->insert('tbl_saas_package_module_prices', $save);
            return $this->db->insert_id();
        } catch (\Throwable $e) {
            log_message('error', '[saas] Saas_package_module_prices_model::save_price error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save multiple prices: $prices = [ ['currency'=>'EUR','amount'=>10,'billing_cycle'=>null], ... ]
     */
    public function save_prices_bulk($package_module_id, $prices)
    {
        if (empty($package_module_id) || !is_array($prices)) {
            return false;
        }
        if (!$this->db->table_exists('tbl_saas_package_module_prices')) {
            log_message('error', '[saas] Attempt to bulk save per-currency prices but table tbl_saas_package_module_prices does not exist.');
            return false;
        }
        foreach ($prices as $p) {
            $p['package_module_id'] = $package_module_id;
            $this->save_price($p);
        }
        return true;
    }

    /**
     * Delete prices for a module (optionally per currency)
     */
    public function delete_by_module($package_module_id, $currency = null, $billing_cycle = null)
    {
        if (!$this->db->table_exists('tbl_saas_package_module_prices')) {
            return false;
        }
        try {
            $this->db->where('package_module_id', $package_module_id);
            if ($currency !== null) {
                $this->db->where('currency', strtoupper($currency));
            }
            if ($billing_cycle !== null) {
                $this->db->where('billing_cycle', $billing_cycle);
            }
            $this->db->delete('tbl_saas_package_module_prices');
            return ($this->db->affected_rows() > 0);
        } catch (\Throwable $e) {
            log_message('error', '[saas] Saas_package_module_prices_model::delete_by_module error: ' . $e->getMessage());
            return false;
        }
    }
}
