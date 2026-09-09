<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_126 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Product slug and SEO fields
        if (!$CI->db->field_exists('slug', db_prefix() . 'product_master')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master`
                ADD `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `product_name`,
                ADD `meta_title` VARCHAR(255) NULL DEFAULT NULL AFTER `slug`,
                ADD `meta_description` TEXT NULL DEFAULT NULL AFTER `meta_title`,
                ADD `product_type` ENUM("physical","digital","service") NOT NULL DEFAULT "physical" AFTER `is_digital`');
        }

        // Digital product file path (for downloads)
        if (!$CI->db->field_exists('digital_file_path', db_prefix() . 'product_master')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master`
                ADD `digital_file_path` VARCHAR(500) NULL DEFAULT NULL AFTER `product_type`');
        }

        // Category hierarchy and SEO
        if (!$CI->db->field_exists('parent_id', db_prefix() . 'product_categories')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_categories`
                ADD `parent_id` INT NOT NULL DEFAULT 0 AFTER `p_category_id`,
                ADD `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `p_category_name`,
                ADD `meta_title` VARCHAR(255) NULL DEFAULT NULL AFTER `slug`,
                ADD `meta_description` TEXT NULL DEFAULT NULL AFTER `meta_title`,
                ADD `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `p_category_description`');
        }

        // Coupon product/category restrictions
        if (!$CI->db->field_exists('product_ids', db_prefix() . 'coupons')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'coupons`
                ADD `product_ids` TEXT NULL DEFAULT NULL AFTER `end_date`,
                ADD `category_ids` TEXT NULL DEFAULT NULL AFTER `product_ids`,
                ADD `min_order_amount` DECIMAL(15,2) NULL DEFAULT NULL AFTER `category_ids`');
        }

        // Wishlist table
        if (!$CI->db->table_exists(db_prefix() . 'product_wishlist')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_wishlist` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `client_id` INT NOT NULL,
                `product_id` INT NOT NULL,
                `product_variation_id` INT NULL DEFAULT NULL,
                `dateadded` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_client_product` (`client_id`, `product_id`, `product_variation_id`),
                INDEX (`client_id`),
                INDEX (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        }

        // Product downloads / access log for digital products
        if (!$CI->db->table_exists(db_prefix() . 'product_downloads')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_downloads` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `order_id` INT NOT NULL,
                `product_id` INT NOT NULL,
                `product_variation_id` INT NULL DEFAULT NULL,
                `client_id` INT NOT NULL,
                `invoice_id` INT NOT NULL,
                `download_count` INT NOT NULL DEFAULT 0,
                `last_download_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX (`client_id`),
                INDEX (`product_id`),
                INDEX (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        }

        // Abandoned cart tracking for analytics
        if (!$CI->db->table_exists(db_prefix() . 'product_abandoned_cart')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_abandoned_cart` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `client_id` INT NULL DEFAULT NULL,
                `session_id` VARCHAR(128) NULL DEFAULT NULL,
                `cart_data` TEXT NOT NULL,
                `cart_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `dateadded` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`client_id`),
                INDEX (`dateadded`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        }

        // Generate slugs for existing products (run if slug column exists)
        if ($CI->db->field_exists('slug', db_prefix() . 'product_master')) {
            $products = $CI->db->get(db_prefix() . 'product_master')->result();
            foreach ($products as $p) {
                if (empty($p->slug)) {
                    $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($p->product_name));
                    $slug = preg_replace('/-+/', '-', trim($slug, '-'));
                    if (empty($slug)) {
                        $slug = 'product';
                    }
                    $slug .= '-' . $p->id;
                    $CI->db->where('id', $p->id);
                    $CI->db->update(db_prefix() . 'product_master', ['slug' => $slug]);
                }
            }
        }

        // Generate slugs for existing categories (run if slug column exists)
        if ($CI->db->field_exists('slug', db_prefix() . 'product_categories')) {
            $categories = $CI->db->get(db_prefix() . 'product_categories')->result();
            foreach ($categories as $c) {
                if (empty($c->slug)) {
                    $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($c->p_category_name));
                    $slug = preg_replace('/-+/', '-', trim($slug, '-'));
                    if (empty($slug)) {
                        $slug = 'category';
                    }
                    $slug .= '-' . $c->p_category_id;
                    $CI->db->where('p_category_id', $c->p_category_id);
                    $CI->db->update(db_prefix() . 'product_categories', ['slug' => $slug]);
                }
            }
        }
    }
}
