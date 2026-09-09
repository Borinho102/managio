<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_132 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->field_exists('referral_code_id', db_prefix() . 'order_master')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master` ADD `referral_code_id` INT NULL DEFAULT NULL AFTER `clientid`');
        }

        if (!$CI->db->field_exists('sale_price', db_prefix() . 'product_master')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master` ADD `sale_price` DECIMAL(15,2) NULL DEFAULT NULL AFTER `rate`');
        }
        if (!$CI->db->field_exists('sale_price_end', db_prefix() . 'product_master')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master` ADD `sale_price_end` DATETIME NULL DEFAULT NULL AFTER `sale_price`');
        }

        add_option('product_referral_commission_percent', 10);
        add_option('product_referral_commission_fixed', 0);

        if (!$CI->db->table_exists(db_prefix() . 'product_gift_card_purchases')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_gift_card_purchases` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `invoice_id` INT NOT NULL,
                `template_id` INT NULL,
                `amount` DECIMAL(15,2) NOT NULL,
                `recipient_email` VARCHAR(255) NULL,
                `recipient_name` VARCHAR(255) NULL,
                `message` TEXT NULL,
                `gift_card_id` INT NULL,
                `datecreated` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        }
    }
}
