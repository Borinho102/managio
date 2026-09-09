<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_129 extends App_module_migration
{
    public function up()
    {
        add_option('product_exit_popups_enabled', 0);
        add_option('product_exit_popup_dismiss_days', 7);
        add_option('product_heatmap_script_enabled', 0);
        add_option('product_heatmap_script', '');

        $CI = &get_instance();
        if (!$CI->db->table_exists(db_prefix() . 'product_exit_popups')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_exit_popups` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `body` TEXT NOT NULL,
                `image_path` VARCHAR(500) NULL DEFAULT NULL,
                `cta_text` VARCHAR(100) NULL DEFAULT NULL,
                `cta_url` VARCHAR(500) NULL DEFAULT NULL,
                `coupon_code` VARCHAR(50) NULL DEFAULT NULL,
                `trigger_type` ENUM("exit_intent","time_delay","scroll") NOT NULL DEFAULT "exit_intent",
                `trigger_value` INT NOT NULL DEFAULT 0,
                `target_pages` VARCHAR(50) NOT NULL DEFAULT "all",
                `require_cart_items` TINYINT(1) NOT NULL DEFAULT 0,
                `dont_show_days` INT NOT NULL DEFAULT 7,
                `sort_order` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `impressions` INT NOT NULL DEFAULT 0,
                `clicks` INT NOT NULL DEFAULT 0,
                `datecreated` DATETIME NOT NULL,
                `dateupdated` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX (`active`),
                INDEX (`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        }
        $path = get_upload_path_by_type('products') . 'exit_popups/';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }
}
