<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_127 extends App_module_migration
{
    public function up()
    {
        if (function_exists('saas_ensure_payin_schema')) {
            saas_ensure_payin_schema();
        }
    }
}
