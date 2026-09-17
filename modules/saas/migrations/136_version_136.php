<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Prefer Affiliate Management over native SaaS affiliate.
 * Disables native enable_affiliate when the affiliate module is active.
 */
class Migration_Version_136 extends App_module_migration
{
    public function up()
    {
        if (!function_exists('saas_affiliate_management_active') || !saas_affiliate_management_active()) {
            return;
        }

        if (function_exists('update_option')) {
            update_option('enable_affiliate', 'FALSE');
        }
    }
}
