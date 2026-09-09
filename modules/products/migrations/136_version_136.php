<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_136 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        if (!$CI->db->field_exists('gift_card_discount', db_prefix() . 'invoices')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices`
                ADD `gift_card_discount` DECIMAL(15,2) DEFAULT 0.00 AFTER `coupon_discount`');
        }
    }
}
