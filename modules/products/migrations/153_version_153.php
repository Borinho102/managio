<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_153 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Beta guest checkout. Off by default: enabling it changes who can
        // create customer records, so it has to be an explicit decision.
        add_option('product_guest_checkout_enabled', 0);

        // Marks customers created by the guest checkout, so a merchant can tell
        // them apart from customers who registered themselves.
        if (!$CI->db->field_exists('product_guest_customer', db_prefix() . 'clients')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'clients`
                ADD `product_guest_customer` TINYINT(1) NOT NULL DEFAULT 0');
        }
    }
}
