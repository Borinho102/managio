<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_106 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'affiliate_users';
        if ($CI->db->table_exists($table)) {
            $CI->db->where('approval IS NULL OR approval != 1', null, false);
            $CI->db->update($table, [
                'approval' => 1,
                'status'   => 1,
            ]);
        }
    }
}
