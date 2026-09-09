<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_150 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Multi-currency pricing: one explicit price per currency, per product or variation.
        // rel_type is 'product' (rel_id => product_master.id) or 'variation'
        // (rel_id => product_variations.id).
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

        // Orders remember the currency they were placed in, so history and reports
        // never mix currencies. 0 means "recorded before multi-currency existed".
        if (!$CI->db->field_exists('currency', db_prefix() . 'order_master')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master`
                ADD `currency` INT NOT NULL DEFAULT 0 AFTER `total`');
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master` ADD INDEX(`currency`);');
        }

        // Fixed-amount coupons and minimum-spend thresholds are currency specific.
        // 0 keeps a coupon valid in every currency (correct for percentage coupons).
        if (!$CI->db->field_exists('currency', db_prefix() . 'coupons')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'coupons`
                ADD `currency` INT NOT NULL DEFAULT 0 AFTER `amount`');
        }

        add_option('product_multicurrency_enabled', 0);
        add_option('product_currencies_enabled', '');
        add_option('product_currency_switcher_enabled', 1);

        $base = $this->base_currency_id();

        // Backfill: existing rates are base-currency rates. Seeding them keeps every
        // storefront price identical after the upgrade, and gives the merchant a row
        // to copy from when adding a second currency.
        if ($base) {
            $this->backfill_prices($base);
            $CI->db->where('currency', 0)->update(db_prefix() . 'order_master', ['currency' => $base]);
        }
    }

    private function base_currency_id()
    {
        $CI = &get_instance();
        $CI->load->model('currencies_model');
        $base = $CI->currencies_model->get_base_currency();

        return $base ? (int) $base->id : 0;
    }

    private function backfill_prices($currency_id)
    {
        $CI = &get_instance();

        $products = $CI->db
            ->select('id, rate, sale_price')
            ->get(db_prefix() . 'product_master')
            ->result();
        foreach ($products as $product) {
            $this->seed_price('product', (int) $product->id, $currency_id, $product->rate, $product->sale_price ?? null);
        }

        $variations = $CI->db
            ->select('id, rate')
            ->get(db_prefix() . 'product_variations')
            ->result();
        foreach ($variations as $variation) {
            $this->seed_price('variation', (int) $variation->id, $currency_id, $variation->rate, null);
        }
    }

    private function seed_price($rel_type, $rel_id, $currency_id, $rate, $sale_price)
    {
        $CI = &get_instance();

        $exists = $CI->db
            ->where('rel_type', $rel_type)
            ->where('rel_id', $rel_id)
            ->where('currency_id', $currency_id)
            ->get(db_prefix() . 'product_prices')
            ->row();
        if ($exists) {
            return;
        }

        $CI->db->insert(db_prefix() . 'product_prices', [
            'rel_type'    => $rel_type,
            'rel_id'      => $rel_id,
            'currency_id' => $currency_id,
            'rate'        => (float) $rate,
            'sale_price'  => $sale_price !== null && $sale_price !== '' ? (float) $sale_price : null,
        ]);
    }
}
