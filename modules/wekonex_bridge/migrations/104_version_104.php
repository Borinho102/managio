<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_104 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->load->helper('wekonex_bridge/wekonex_bridge');

        if (function_exists('wekonex_bridge_sync_custom_fields')) {
            wekonex_bridge_sync_custom_fields();
        }
    }
}
