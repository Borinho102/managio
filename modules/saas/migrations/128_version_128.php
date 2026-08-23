<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_128 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!empty(subdomain())) {
            return;
        }

        if ($CI->db->table_exists('tbl_saas_packages') && !$CI->db->field_exists('currency', 'tbl_saas_packages')) {
            $CI->db->query("ALTER TABLE `tbl_saas_packages` ADD `currency` VARCHAR(10) NULL DEFAULT NULL");
        }

        if (function_exists('add_option')) {
            add_option('saas_default_currency', '');
        }

        if (function_exists('saas_ensure_payin_schema')) {
            saas_ensure_payin_schema();
        }
    }
}
