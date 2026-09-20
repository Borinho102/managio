<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Connect Affiliate Management to the public FrontCMS website.
 */
class Migration_Version_137 extends App_module_migration
{
    public function up()
    {
        if (function_exists('saas_ensure_affiliate_website_menu')) {
            saas_ensure_affiliate_website_menu();
        }
    }
}
