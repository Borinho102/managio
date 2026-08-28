<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->load->helper('wekonex_bridge/wekonex_bridge');

        if (function_exists('wekonex_bridge_install_custom_fields')) {
            wekonex_bridge_install_custom_fields();
        }

        $table = db_prefix() . 'customfields';
        if (!$CI->db->table_exists($table)) {
            return;
        }

        $enabled = function_exists('wekonex_bridge_is_enabled') && wekonex_bridge_is_enabled();
        $names = function_exists('wekonex_bridge_custom_field_names')
            ? wekonex_bridge_custom_field_names()
            : [
                'wekonex_tenant_id',
                'wekonex_domain',
                'wekonex_user_id',
                'wekonex_user_uuid',
                'wekonex_role',
                'wekonex_is_alumni',
                'wekonex_company',
                'wekonex_job_title',
                'wekonex_payment_id',
                'wekonex_payment_uuid',
                'wekonex_payment_type',
            ];

        if (function_exists('wekonex_bridge_apply_custom_fields_query')) {
            wekonex_bridge_apply_custom_fields_query($CI->db);
        } else {
            $CI->db->where_in('name', $names);
        }

        $CI->db->update($table, [
            'required' => 0,
            'active' => $enabled ? 1 : 0,
        ]);
    }
}
