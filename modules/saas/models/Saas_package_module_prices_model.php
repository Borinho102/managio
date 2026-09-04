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
     * Per-currency module prices live on the master database (the same database
     * that holds tbl_saas_package_module, which is always read through
     * get_old_result()). On tenant subdomains $CI->db points to the tenant
     * database, so resolve the master connection exactly like get_old_result()
     * does via config_db(null, true). Falls back to the default connection
     * (master instance) when the master connection cannot be established.
     */
    private function db_conn()
    {
        static $conn = null, $resolved = false;
        if ($resolved) {
            return $conn;
        }
        $resolved = true;

        $CI = &get_instance();
        if (function_exists('config_db')) {
            $master = config_db(null, true);
            if (!empty($master)) {
                $conn = $master;
            }
        }
        if (empty($conn)) {
            $conn = $CI->db;
        }

        return $conn;
    }

    private function prices_table()
    {
        return db_prefix() . 'saas_package_module_prices';
    }

    /**
     * Get a specific price for a module/currency/billing_cycle
     * Returns object or null
     */
    public function get_price($package_module_id, $currency, $billing_cycle = null)
    {
        $db = $this->db_conn();
        $table = $this->prices_table();

        // Guard: return null if table not present to avoid fatal DB exceptions when migrations are pending
        if (!$db->table_exists($table)) {
            return null;
        }
        try {
            $db->from($table);
            $db->where('package_module_id', $package_module_id);
            $currency = strtoupper($currency);
            $equiv = saas_equivalent_currency_codes($currency);
            $db->where_in('currency', $equiv);
            if ($billing_cycle === null) {
                $db->where('billing_cycle IS NULL', null, false);
            } else {
                $db->where('billing_cycle', $billing_cycle);
            }
            $query = $db->get();
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
        $db = $this->db_conn();
        $table = $this->prices_table();

        if (!$db->table_exists($table)) {
            return [];
        }
        try {
            $db->from($table);
            $db->where('package_module_id', $package_module_id);
            $query = $db->get();
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

        $db = $this->db_conn();
        $table = $this->prices_table();

        if (!$db->table_exists($table)) {
            log_message('error', '[saas] Attempt to save per-currency price but table ' . $table . ' does not exist.');
            return false;
        }

        $currency = saas_normalize_currency_code($data['currency']);
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
                $db->where('package_module_price_id', $existing->package_module_price_id);
                $db->update($table, $save);
                return $existing->package_module_price_id;
            }

            $db->insert($table, $save);
            return $db->insert_id();
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
        $db = $this->db_conn();
        if (!$db->table_exists($this->prices_table())) {
            log_message('error', '[saas] Attempt to bulk save per-currency prices but table ' . $this->prices_table() . ' does not exist.');
            return false;
        }
        foreach ($prices as $p) {
            $p['package_module_id'] = $package_module_id;
            $this->save_price($p);
        }
        return true;
    }

    /**
     * Delete prices for a module (optionally per currency or array of currencies)
     * @param int $package_module_id
     * @param string|array|null $currency Single currency code or array of currency codes
     * @param string|null $billing_cycle
     * @return bool
     */
    public function delete_by_module($package_module_id, $currency = null, $billing_cycle = null, $currencies_array = null)
    {
        $db = $this->db_conn();
        $table = $this->prices_table();

        if (!$db->table_exists($table)) {
            return false;
        }
        try {
            $db->where('package_module_id', $package_module_id);
            
            // Handle array of currencies (for selective deletion)
            if (is_array($currencies_array) && !empty($currencies_array)) {
                $normalized = array_map('saas_normalize_currency_code', $currencies_array);
                $flat = [];
                foreach ($normalized as $code) {
                    foreach (saas_equivalent_currency_codes($code) as $eq) {
                        $flat[] = $eq;
                    }
                }
                $flat = array_unique($flat);
                $db->where_in('currency', $flat);
            } elseif ($currency !== null) {
                $currency = saas_normalize_currency_code($currency);
                $db->where_in('currency', saas_equivalent_currency_codes($currency));
            }
            
            if ($billing_cycle !== null) {
                $db->where('billing_cycle', $billing_cycle);
            }
            $db->delete($table);
            return ($db->affected_rows() > 0);
        } catch (\Throwable $e) {
            log_message('error', '[saas] Saas_package_module_prices_model::delete_by_module error: ' . $e->getMessage());
            return false;
        }
    }
}
