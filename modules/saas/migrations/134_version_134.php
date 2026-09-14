<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Force remap existing USD records to XAF on all tenant DBs.
 * Runs even if migration 133 already applied (only set base currency).
 */
class Migration_Version_134 extends App_module_migration
{
    public function up()
    {
        if (function_exists('update_option')) {
            update_option('saas_default_currency', 'XAF');
        }

        if (!function_exists('saas_force_records_currency_to_xaf')) {
            return;
        }

        saas_force_records_currency_to_xaf();

        $CI = &get_instance();
        if (empty($CI->db) || !$CI->db->table_exists('tbl_saas_companies')) {
            return;
        }

        $companies = $CI->db
            ->select('db_name')
            ->from('tbl_saas_companies')
            ->where('db_name IS NOT NULL', null, false)
            ->where('db_name !=', '')
            ->get()
            ->result();

        foreach ($companies as $company) {
            if (empty($company->db_name)) {
                continue;
            }
            try {
                saas_force_records_currency_to_xaf($company->db_name);
            } catch (Throwable $e) {
                log_message('error', '[saas migration 134] Force XAF remap failed for ' . $company->db_name . ': ' . $e->getMessage());
            }
        }

        if ($CI->db->table_exists('tbl_saas_companies') && $CI->db->field_exists('currency', 'tbl_saas_companies')) {
            $CI->db->query("UPDATE `tbl_saas_companies` SET `currency` = 'XAF' WHERE `currency` IS NULL OR `currency` = '' OR UPPER(`currency`) IN ('USD', '$')");
        }

        if ($CI->db->table_exists('tbl_saas_packages') && $CI->db->field_exists('currency', 'tbl_saas_packages')) {
            $CI->db->query("UPDATE `tbl_saas_packages` SET `currency` = 'XAF' WHERE `currency` IS NULL OR `currency` = '' OR UPPER(`currency`) IN ('USD', '$')");
        }
    }
}
