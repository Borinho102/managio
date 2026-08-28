<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_103 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->load->helper('wekonex_bridge/wekonex_bridge');

        $table = db_prefix() . 'customfields';
        if (!$CI->db->table_exists($table)) {
            return;
        }

        $enabled = function_exists('wekonex_bridge_is_enabled') && wekonex_bridge_is_enabled();

        wekonex_bridge_apply_custom_fields_query($CI->db);
        $CI->db->update($table, [
            'required' => 0,
            'active' => $enabled ? 1 : 0,
        ]);
    }
}
