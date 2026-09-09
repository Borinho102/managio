<?php

defined('BASEPATH') or exit('No direct script access allowed');

add_option('products_enabled', 1);
add_option('nlu_product_menu_disabled', 0);
add_option('nlu_hiddenprices_disabled', 0);
add_option('b2bmode_disabled', 0);
add_option('product_menu_disabled', 0);
add_option('product_low_quantity', 10);
add_option('product_flat_rate_shipping', 0);
add_option('product_tax_for_shipping_cost', 0);

$CI->db->query('SET foreign_key_checks = 0');
if (!$CI->db->table_exists(db_prefix() . 'product_master')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_master` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `product_name` VARCHAR(200) NOT NULL,
        `product_description` VARCHAR(200) NOT NULL,
        `product_category_id` INT NOT NULL,
        `rate` DECIMAL(15,2) NOT NULL,
        `quantity_number` INT NOT NULL,
        `product_image` VARCHAR(200) NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master` ADD INDEX(`product_category_id`);');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master` 
    	ADD FOREIGN KEY (`product_category_id`) 
    	REFERENCES `' . db_prefix() . 'product_categories`(`p_category_id`) 
    	ON DELETE CASCADE 
    	ON UPDATE CASCADE'
    );
}

if (!$CI->db->table_exists(db_prefix() . 'product_categories')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_categories` (
		`p_category_id` INT NOT NULL AUTO_INCREMENT,
		`parent_id` INT NOT NULL DEFAULT 0,
		`p_category_name` VARCHAR(50) NOT NULL,
		`slug` VARCHAR(255) NULL DEFAULT NULL,
		`meta_title` VARCHAR(255) NULL DEFAULT NULL,
		`meta_description` TEXT NULL DEFAULT NULL,
		`p_category_description` TEXT NOT NULL,
		`active` TINYINT(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (`p_category_id`)
	) ENGINE = InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'order_master')) {
    $CI->db->query('CREATE TABLE `'.db_prefix()."order_master` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `invoice_id` INT NOT NULL,
        `clientid` INT NOT NULL,
        `datecreated` DATETIME NOT NULL,
        `order_date` DATE NOT NULL,
        `subtotal` DECIMAL(15,2) NOT NULL,
        `total` DECIMAL(15,2) NOT NULL,
        `status` INT NOT NULL DEFAULT '1',
        PRIMARY KEY (`id`),
        INDEX (`invoice_id`),
        INDEX (`clientid`)
    ) ENGINE = InnoDB DEFAULT CHARSET=".$CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'order_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'order_items` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `order_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `qty` DECIMAL(15,2) NOT NULL,
        `rate` DECIMAL(15,2) NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`order_id`),
        INDEX (`product_id`)
    ) ENGINE = InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('taxes', db_prefix() . 'product_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master`
        ADD `taxes` VARCHAR(255) NOT NULL AFTER `rate`,
        ADD `recurring` INT NOT NULL DEFAULT "0" AFTER `product_image`,
        ADD `recurring_type` VARCHAR(10) NOT NULL AFTER `recurring`,
        ADD `custom_recurring` TINYINT(1) NOT NULL DEFAULT "0" AFTER `recurring_type`,
        ADD `cycles` INT NOT NULL DEFAULT "0" AFTER `custom_recurring`'
    );
}

if (!$CI->db->field_exists('is_digital', db_prefix() . 'product_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master`
        ADD `is_digital` TINYINT(1) NOT NULL DEFAULT 0 AFTER `quantity_number`');
}

if (!$CI->db->field_exists('slug', db_prefix() . 'product_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master`
        ADD `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `product_name`,
        ADD `meta_title` VARCHAR(255) NULL DEFAULT NULL AFTER `slug`,
        ADD `meta_description` TEXT NULL DEFAULT NULL AFTER `meta_title`,
        ADD `product_type` ENUM("physical","digital","service") NOT NULL DEFAULT "physical" AFTER `is_digital`,
        ADD `digital_file_path` VARCHAR(500) NULL DEFAULT NULL AFTER `product_type`');
}

if (!$CI->db->field_exists('parent_id', db_prefix() . 'product_categories')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_categories`
        ADD `parent_id` INT NOT NULL DEFAULT 0 AFTER `p_category_id`,
        ADD `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `p_category_name`,
        ADD `meta_title` VARCHAR(255) NULL DEFAULT NULL AFTER `slug`,
        ADD `meta_description` TEXT NULL DEFAULT NULL AFTER `meta_title`,
        ADD `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `p_category_description`');
}

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

if (!$CI->db->table_exists(db_prefix() . 'product_abandoned_cart')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_abandoned_cart` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `client_id` INT NULL DEFAULT NULL,
        `session_id` VARCHAR(128) NULL DEFAULT NULL,
        `cart_data` TEXT NOT NULL,
        `cart_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `dateadded` DATETIME NOT NULL,
        `emailed` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        INDEX (`client_id`),
        INDEX (`dateadded`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

add_option('coupons_disabled', 1);
add_option('product_debug_timing_enabled', 0);
add_option('product_wishlist_enabled', 1);
add_option('product_detail_pages_enabled', 1);
add_option('product_seo_meta_enabled', 1);
add_option('product_analytics_enabled', 1);
add_option('product_abandoned_cart_tracking_enabled', 1);
add_option('product_digital_downloads_enabled', 1);
add_option('product_remarketing_facebook_enabled', 0);
add_option('product_remarketing_facebook_pixel_id', '');
add_option('product_remarketing_google_enabled', 0);
add_option('product_remarketing_google_id', '');
add_option('product_remarketing_google_label', '');
add_option('product_remarketing_custom_enabled', 0);
add_option('product_remarketing_custom_script', '');
add_option('product_abandoned_cart_email_enabled', 0);
add_option('product_abandoned_cart_email_delay_hours', 24);
add_option('product_exit_popups_enabled', 0);
add_option('product_exit_popup_dismiss_days', 7);
add_option('product_heatmap_script_enabled', 0);
add_option('product_heatmap_script', '');
add_option('product_notifications_enabled', 0);
add_option('product_sms_gateway_url', '');
add_option('product_whatsapp_gateway_url', '');
add_option('product_reviews_enabled', 0);
add_option('product_referral_enabled', 0);
add_option('product_back_in_stock_enabled', 0);
add_option('product_price_drop_enabled', 0);
add_option('product_recommendations_enabled', 0);
add_option('product_newsletter_enabled', 0);
add_option('product_urgency_enabled', 0);
add_option('product_gift_cards_enabled', 0);
add_option('product_gift_card_min_amount', 1);
add_option('product_upsell_enabled', 0);
add_option('product_social_proof_enabled', 0);
add_option('product_social_proof_recent_hours', 24);
add_option('product_social_proof_recent_anonymous', 1);
add_option('product_ab_testing_enabled', 0);
add_option('product_segmentation_enabled', 0);
if (!$CI->db->table_exists(db_prefix() . 'product_notification_templates')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_notification_templates` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `channel` ENUM("whatsapp","sms","webhook") NOT NULL DEFAULT "whatsapp",
        `use_global_gateway` TINYINT(1) NOT NULL DEFAULT 1,
        `trigger_event` VARCHAR(50) NOT NULL,
        `recipient` ENUM("client","staff") NOT NULL DEFAULT "client",
        `message_template` TEXT NOT NULL,
        `webhook_url` VARCHAR(500) NULL DEFAULT NULL,
        `webhook_method` VARCHAR(10) NOT NULL DEFAULT "POST",
        `webhook_body` TEXT NULL DEFAULT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`trigger_event`),
        INDEX (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
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
$exit_popups_path = get_upload_path_by_type('products') . 'exit_popups/';
if (!is_dir($exit_popups_path)) {
    @mkdir($exit_popups_path, 0755, true);
}
$gift_cards_path = get_upload_path_by_type('products') . 'gift_cards/';
if (!is_dir($gift_cards_path)) {
    @mkdir($gift_cards_path, 0755, true);
}
$product_images_path = FCPATH . 'uploads/products/';
if (!is_dir($product_images_path)) {
    @mkdir($product_images_path, 0755, true);
}
if (!$CI->db->table_exists(db_prefix() . 'product_reviews')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_reviews` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `product_id` INT NOT NULL,
        `client_id` INT NOT NULL,
        `contact_id` INT NULL,
        `rating` TINYINT NOT NULL DEFAULT 5,
        `review_text` TEXT NULL,
        `approved` TINYINT(1) NOT NULL DEFAULT 0,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`product_id`),
        INDEX (`client_id`),
        INDEX (`approved`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_referral_codes')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_referral_codes` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `client_id` INT NOT NULL,
        `code` VARCHAR(50) NOT NULL,
        `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
        `commission_fixed` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_earned` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `datecreated` DATETIME NOT NULL,
        UNIQUE KEY (`code`),
        PRIMARY KEY (`id`),
        INDEX (`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_referral_tracking')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_referral_tracking` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `referral_code_id` INT NOT NULL,
        `order_id` INT NOT NULL,
        `commission` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `status` ENUM("pending","paid","cancelled") NOT NULL DEFAULT "pending",
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`referral_code_id`),
        INDEX (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_stock_notifications')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_stock_notifications` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `product_id` INT NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `client_id` INT NULL,
        `notified` TINYINT(1) NOT NULL DEFAULT 0,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`product_id`),
        INDEX (`notified`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_price_alerts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_price_alerts` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `product_id` INT NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `client_id` INT NULL,
        `target_price` DECIMAL(15,2) NULL,
        `notified` TINYINT(1) NOT NULL DEFAULT 0,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`product_id`),
        INDEX (`notified`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->field_exists('product_marketing_consent', db_prefix() . 'contacts')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'contacts` ADD `product_marketing_consent` TINYINT(1) NOT NULL DEFAULT 0');
}
if (!$CI->db->table_exists(db_prefix() . 'product_gift_card_templates')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_gift_card_templates` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `design_image` VARCHAR(255) NULL,
        `design_html` TEXT NULL,
        `merge_fields_info` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_gift_cards')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_gift_cards` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(50) NOT NULL,
        `template_id` INT NULL,
        `amount` DECIMAL(15,2) NOT NULL,
        `balance` DECIMAL(15,2) NOT NULL,
        `currency` INT NOT NULL,
        `purchaser_client_id` INT NULL,
        `recipient_email` VARCHAR(255) NULL,
        `recipient_name` VARCHAR(255) NULL,
        `message` TEXT NULL,
        `expiry_date` DATE NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `datecreated` DATETIME NOT NULL,
        UNIQUE KEY (`code`),
        PRIMARY KEY (`id`),
        INDEX (`template_id`),
        INDEX (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_gift_card_redemptions')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_gift_card_redemptions` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `gift_card_id` INT NOT NULL,
        `invoice_id` INT NULL,
        `amount` DECIMAL(15,2) NOT NULL,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`gift_card_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_upsell_rules')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_upsell_rules` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `trigger_product_ids` TEXT NULL,
        `trigger_category_ids` TEXT NULL,
        `upsell_product_ids` TEXT NOT NULL,
        `display_type` ENUM("modal","inline") NOT NULL DEFAULT "modal",
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_social_proof')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_social_proof` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `product_id` INT NULL,
        `message_type` ENUM("recent_purchase","viewing_now","popular") NOT NULL,
        `display_text` VARCHAR(255) NOT NULL,
        `anonymous` TINYINT(1) NOT NULL DEFAULT 1,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'product_ab_tests')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_ab_tests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `test_type` ENUM("exit_popup","banner","coupon") NOT NULL,
        `rel_id` INT NULL,
        `variant_a_config` TEXT NULL,
        `variant_b_config` TEXT NULL,
        `traffic_split` INT NOT NULL DEFAULT 50,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
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
if (!$CI->db->table_exists(db_prefix() . 'product_segments')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_segments` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `rules` TEXT NOT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
if (!$CI->db->table_exists(db_prefix() . 'coupons')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'coupons` (
		`id` INT NOT NULL AUTO_INCREMENT,
		`code` VARCHAR(30) NOT NULL,
		`type` VARCHAR(10) NOT NULL,
		`amount` DECIMAL(10,2) NOT NULL,
		`max_uses` INT NOT NULL DEFAULT "0",
		`max_uses_per_client` INT NOT NULL DEFAULT "0",
		`start_date` DATE NULL DEFAULT NULL,
		`end_date` DATE NULL DEFAULT NULL,
		`product_ids` TEXT NULL DEFAULT NULL,
		`category_ids` TEXT NULL DEFAULT NULL,
		`min_order_amount` DECIMAL(15,2) NULL DEFAULT NULL,
		PRIMARY KEY (`id`)
	) ENGINE = InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'variations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'variations` (
		`id` INT NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(50) NOT NULL,
		`description` TEXT NULL DEFAULT NULL,
		PRIMARY KEY (`id`)
	) ENGINE = InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'variation_values')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'variation_values` (
		`id` INT NOT NULL AUTO_INCREMENT,
        `variation_id` INT NOT NULL,
		`value` VARCHAR(50) NOT NULL,
        `value_order` INT NOT NULL,
		`description` VARCHAR(50) NULL DEFAULT NULL,
		PRIMARY KEY (`id`),
        FOREIGN KEY (variation_id) REFERENCES `' . db_prefix() . 'variations`(`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'product_variations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_variations` (
		`id` INT NOT NULL AUTO_INCREMENT,
        `product_id` INT NOT NULL,
		`variation_id` INT NOT NULL,
        `variation_value_id` INT NOT NULL,
        `rate` DECIMAL(15,2) NOT NULL,
        `quantity_number` INT NOT NULL,
		PRIMARY KEY (`id`),
        FOREIGN KEY (product_id) REFERENCES `' . db_prefix() . 'product_master`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (variation_id) REFERENCES `' . db_prefix() . 'variations`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (variation_value_id) REFERENCES `' . db_prefix() . 'variation_values`(`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('product_variation_id', db_prefix() . 'order_items')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_items`
        ADD `product_variation_id` INT AFTER `product_id`');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_items` 
        ADD FOREIGN KEY (`product_variation_id`) 
        REFERENCES `' . db_prefix() . 'product_variations`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE'
    );
} else {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_items`
        MODIFY COLUMN `product_variation_id` INT');
}

if (!$CI->db->field_exists('is_variation', db_prefix() . 'product_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master`
        ADD `is_variation` TINYINT(1) NOT NULL DEFAULT "0"');
} else {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master`
        MODIFY COLUMN `is_variation` TINYINT(1) NOT NULL DEFAULT "0"');
}

if (!$CI->db->field_exists('coupon_id', db_prefix() . 'invoices')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices`
        ADD `coupon_id` INT AFTER `currency`');
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices`
        ADD FOREIGN KEY (`coupon_id`) 
        REFERENCES `' . db_prefix() . 'coupons`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
    ');
} else {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices`
        MODIFY COLUMN `coupon_id` INT');
}
if (!$CI->db->field_exists('referral_code_id', db_prefix() . 'order_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master` ADD `referral_code_id` INT NULL DEFAULT NULL AFTER `clientid`');
}
if (!$CI->db->field_exists('sale_price', db_prefix() . 'product_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master` ADD `sale_price` DECIMAL(15,2) NULL DEFAULT NULL AFTER `rate`');
}
if (!$CI->db->field_exists('sale_price_end', db_prefix() . 'product_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_master` ADD `sale_price_end` DATETIME NULL DEFAULT NULL');
}

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
if (!$CI->db->field_exists('currency', db_prefix() . 'order_master')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master` ADD `currency` INT NOT NULL DEFAULT 0 AFTER `total`');
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_master` ADD INDEX(`currency`);');
}
if (!$CI->db->field_exists('currency', db_prefix() . 'coupons')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'coupons` ADD `currency` INT NOT NULL DEFAULT 0 AFTER `amount`');
}
add_option('product_multicurrency_enabled', 0);
add_option('product_currencies_enabled', '');
add_option('product_currency_switcher_enabled', 1);
add_option('product_currencies_no_tax', '');
add_option('product_guest_checkout_enabled', 0);
if (!$CI->db->field_exists('product_guest_customer', db_prefix() . 'clients')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD `product_guest_customer` TINYINT(1) NOT NULL DEFAULT 0');
}
add_option('product_referral_commission_percent', 10);
add_option('product_referral_commission_fixed', 0);
if (!$CI->db->field_exists('coupon_discount', db_prefix() . 'invoices')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices`
        ADD `coupon_discount` DECIMAL(15,2) DEFAULT "0" AFTER `total_tax`');
} else {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices`
        MODIFY COLUMN `coupon_discount` DECIMAL(15,2) DEFAULT "0"');
}
if (!$CI->db->field_exists('gift_card_discount', db_prefix() . 'invoices')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices`
        ADD `gift_card_discount` DECIMAL(15,2) DEFAULT 0.00 AFTER `coupon_discount`');
}

$CI->db->query('SET foreign_key_checks = 1');

$email_template[0]['type']     = 'order';
$email_template[0]['slug']     = 'order-to-admin';
$email_template[0]['language'] = 'english';
$email_template[0]['name']     = 'Success Order For Admin';
$email_template[0]['subject']  = 'Order Paid Successfully';
$email_template[0]['message']  = '<em>You received a new order {order_id} with a total amount of {total} {currency}. Invoice: {invoice_link}</em>';
$email_template[0]['fromname'] = '{companyname}';
$email_template[0]['active']   = '1';

$email_template[1]['type']     = 'order';
$email_template[1]['slug']     = 'order-to-client';
$email_template[1]['language'] = 'english';
$email_template[1]['name']     = 'Success Order For Customer';
$email_template[1]['subject']  = 'Order Placed Successfully';
$email_template[1]['message']  = '<em>Your payment for order {order_id} is paid through {invoice_link} with a total amount of {total} {currency}</em>';
$email_template[1]['fromname'] = '{companyname}';
$email_template[1]['active']   = '1';

$CI->db->where('type', 'order');
$result = $CI->db->get(db_prefix() . 'emailtemplates')->row();
if (empty($result)) {
    $CI->db->insert_batch(db_prefix() . 'emailtemplates', $email_template);
}

$products_templates = [
    ['type' => 'products', 'slug' => 'product-review-request', 'language' => 'english', 'name' => 'Product Review Request', 'subject' => 'How was your purchase? Leave a review', 'message' => 'Hi {client_name},<br><br>Thank you for your order #{order_id}. We\'d love to hear your feedback!<br><br><a href="{product_link}">Leave a review</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
    ['type' => 'products', 'slug' => 'product-back-in-stock', 'language' => 'english', 'name' => 'Product Back In Stock', 'subject' => 'Back in stock: {product_name}', 'message' => 'Hi,<br><br>Great news! {product_name} is back in stock.<br><br><a href="{product_link}">View product</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
    ['type' => 'products', 'slug' => 'product-price-drop', 'language' => 'english', 'name' => 'Product Price Drop Alert', 'subject' => 'Price drop: {product_name}', 'message' => 'Hi,<br><br>{product_name} is now available at a lower price: {product_price}<br><br><a href="{product_link}">View product</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
    ['type' => 'products', 'slug' => 'product-newsletter-welcome', 'language' => 'english', 'name' => 'Newsletter Welcome', 'subject' => 'Welcome to our newsletter', 'message' => 'Hi {client_name},<br><br>Thank you for subscribing to our newsletter. You\'ll receive exclusive offers and updates.<br><br><a href="{store_link}">Visit our store</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
    ['type' => 'products', 'slug' => 'product-gift-card', 'language' => 'english', 'name' => 'Gift Card', 'subject' => 'You received a gift card from {companyname}', 'message' => 'Hi {recipient_name},<br><br>You have received a gift card worth {gift_card_amount} {currency}.<br><br>Code: {gift_card_code}<br>Expiry: {gift_card_expiry}<br><br><a href="{store_link}">Redeem now</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
    ['type' => 'products', 'slug' => 'product-referral-invite', 'language' => 'english', 'name' => 'Referral Invite', 'subject' => 'Your friend invites you to shop at {companyname}', 'message' => 'Hi,<br><br>Your friend {referrer_name} invites you to check out {companyname}. Use code {referral_code} for a special offer!<br><br><a href="{store_link}">Visit store</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
    ['type' => 'products', 'slug' => 'product-order-placed-client', 'language' => 'english', 'name' => 'Order Placed (Before Payment)', 'subject' => 'Your order #{order_id} has been received', 'message' => 'Hi {client_name},<br><br>Thank you for your order #{order_id}. Total: {total} {currency}.<br><br>Pay here: {invoice_link}<br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
    ['type' => 'products', 'slug' => 'product-abandoned-cart', 'language' => 'english', 'name' => 'Abandoned Cart Reminder', 'subject' => 'You left items in your cart', 'message' => 'Hi {client_name},<br><br>You have items worth {cart_total} in your cart.<br><br><a href="{cart_link}">Complete your purchase</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '0'],
];
foreach ($products_templates as $t) {
    $ex = $CI->db->where('type', $t['type'])->where('slug', $t['slug'])->get(db_prefix() . 'emailtemplates')->row();
    if (empty($ex)) {
        $CI->db->insert(db_prefix() . 'emailtemplates', $t);
    }
}
