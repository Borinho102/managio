<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_132 extends App_module_migration
{
    public function up()
    {
        // Create the per-currency module pricing table on the master database
        // (tbl_saas_package_module_prices) when it does not exist yet.
        if (function_exists('saas_ensure_module_prices_schema')) {
            saas_ensure_module_prices_schema();
        }
    }
}
