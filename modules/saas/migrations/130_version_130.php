<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_130 extends App_module_migration
{
    public function up()
    {
        if (function_exists('saas_ensure_flutex_staff_schema')) {
            saas_ensure_flutex_staff_schema();
        }
    }
}
