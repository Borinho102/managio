<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * 1.5.1 shipped as a code-only fix (multi-currency settings screen) with no
 * migration file. Perfex still looks for a migration matching the module
 * version and fails with "No migration could be found with the version number:
 * 151", which blocks Upgrade Database entirely.
 *
 * This migration exists to satisfy that lookup. It re-asserts the 1.5.0 schema
 * idempotently rather than doing nothing, so an install whose 150 migration was
 * interrupted is repaired instead of being left half migrated.
 */
class Migration_Version_151 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'product_prices')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_prices` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `rel_type` ENUM("product","variation") NOT NULL DEFAULT "product",
                `rel_id` INT NOT NULL,
                `currency_id` INT NOT NULL,
                `rate` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `sale_price` DECIMAL(15,2) NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `product_prices_unique` (`rel_type`, `rel_id`, `currency_id`),
                INDEX (`currency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        }

        if (!$CI->db->field_exists('currency', db_prefix() . 'order_master')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master`
                ADD `currency` INT NOT NULL DEFAULT 0 AFTER `total`');
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master` ADD INDEX(`currency`);');
        }

        if (!$CI->db->field_exists('currency', db_prefix() . 'coupons')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'coupons`
                ADD `currency` INT NOT NULL DEFAULT 0 AFTER `amount`');
        }

        add_option('product_multicurrency_enabled', 0);
        add_option('product_currencies_enabled', '');
        add_option('product_currency_switcher_enabled', 1);
    }
}
