<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_131 extends App_module_migration
{
    public function up()
    {
        if (function_exists('saas_ensure_credentials_email_template')) {
            saas_ensure_credentials_email_template();
        }

        if (!function_exists('saas_sync_master_email_settings_to_tenant')) {
            return;
        }

        $CI = &get_instance();
        if (empty($CI->db) || !$CI->db->table_exists('tbl_saas_companies')) {
            return;
        }

        $companies = $CI->db
            ->select('db_name')
            ->from('tbl_saas_companies')
            ->where('db_name IS NOT NULL', null, false)
            ->where('db_name !=', '')
            ->where('for_seed IS NULL', null, false)
            ->get()
            ->result();

        foreach ($companies as $company) {
            saas_sync_master_email_settings_to_tenant($company->db_name, true);
        }
    }
}
