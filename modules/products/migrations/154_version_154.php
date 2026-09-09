<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * 1.5.4 is a front-end fix (the Variation Value picker on the product detail
 * page). There is no schema change, but Perfex looks for a migration matching
 * every module version, so this file has to exist or Upgrade Database fails
 * with "No migration could be found with the version number: 154".
 *
 * It re-asserts the module options idempotently rather than doing nothing, so
 * an install that missed an earlier migration is topped up here.
 */
class Migration_Version_154 extends App_module_migration
{
    public function up()
    {
        add_option('product_multicurrency_enabled', 0);
        add_option('product_currencies_enabled', '');
        add_option('product_currency_switcher_enabled', 1);
        add_option('product_currencies_no_tax', '');
        add_option('product_guest_checkout_enabled', 0);
    }
}
