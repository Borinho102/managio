<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_127 extends App_module_migration
{
    public function up()
    {
        // Ensure digital upload folder exists
        $CI = &get_instance();
        $path = get_upload_path_by_type('products') . 'digital/';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }
}
