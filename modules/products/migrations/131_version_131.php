<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_131 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Gateway URLs (SMS vs WhatsApp) - use when template webhook_url is empty
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
        add_option('product_upsell_enabled', 0);
        add_option('product_social_proof_enabled', 0);
        add_option('product_ab_testing_enabled', 0);
        add_option('product_segmentation_enabled', 0);

        // Add gateway column to notifications - channel already exists, add use_global_gateway
        if ($CI->db->field_exists('webhook_url', db_prefix() . 'product_notification_templates') && !$CI->db->field_exists('use_global_gateway', db_prefix() . 'product_notification_templates')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_notification_templates` ADD `use_global_gateway` TINYINT(1) NOT NULL DEFAULT 1 AFTER `channel`');
        }

        // Product reviews
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

        // Referral program
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

        // Back in stock + Price drop notifications
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

        // Newsletter / Marketing consent
        if (!$CI->db->field_exists('product_marketing_consent', db_prefix() . 'contacts')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'contacts` ADD `product_marketing_consent` TINYINT(1) NOT NULL DEFAULT 0');
        }

        // Gift cards
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

        // Post-purchase upsell rules
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

        // Social proof settings
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

        // A/B testing for offers
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

        // Segmentation rules
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

        // Create gift card upload folder
        $path = get_upload_path_by_type('products') . 'gift_cards/';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        // New email templates for marketing features
        $templates = [
            ['type' => 'products', 'slug' => 'product-review-request', 'language' => 'english', 'name' => 'Product Review Request', 'subject' => 'How was your purchase? Leave a review', 'message' => 'Hi {client_name},<br><br>Thank you for your order #{order_id}. We\'d love to hear your feedback!<br><br><a href="{product_link}">Leave a review</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
            ['type' => 'products', 'slug' => 'product-back-in-stock', 'language' => 'english', 'name' => 'Product Back In Stock', 'subject' => 'Back in stock: {product_name}', 'message' => 'Hi,<br><br>Great news! {product_name} is back in stock.<br><br><a href="{product_link}">View product</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
            ['type' => 'products', 'slug' => 'product-price-drop', 'language' => 'english', 'name' => 'Product Price Drop Alert', 'subject' => 'Price drop: {product_name}', 'message' => 'Hi,<br><br>{product_name} is now available at a lower price: {product_price}<br><br><a href="{product_link}">View product</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
            ['type' => 'products', 'slug' => 'product-newsletter-welcome', 'language' => 'english', 'name' => 'Newsletter Welcome', 'subject' => 'Welcome to our newsletter', 'message' => 'Hi {client_name},<br><br>Thank you for subscribing to our newsletter. You\'ll receive exclusive offers and updates.<br><br><a href="{store_link}">Visit our store</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
            ['type' => 'products', 'slug' => 'product-gift-card', 'language' => 'english', 'name' => 'Gift Card', 'subject' => 'You received a gift card from {companyname}', 'message' => 'Hi {recipient_name},<br><br>You have received a gift card worth {gift_card_amount} {currency}.<br><br>Code: {gift_card_code}<br>Expiry: {gift_card_expiry}<br><br><a href="{store_link}">Redeem now</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
            ['type' => 'products', 'slug' => 'product-referral-invite', 'language' => 'english', 'name' => 'Referral Invite', 'subject' => 'Your friend invites you to shop at {companyname}', 'message' => 'Hi,<br><br>Your friend {referrer_name} invites you to check out {companyname}. Use code {referral_code} for a special offer!<br><br><a href="{store_link}">Visit store</a><br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
            ['type' => 'products', 'slug' => 'product-order-placed-client', 'language' => 'english', 'name' => 'Order Placed (Before Payment)', 'subject' => 'Your order #{order_id} has been received', 'message' => 'Hi {client_name},<br><br>Thank you for your order #{order_id}. Total: {total} {currency}.<br><br>Pay here: {invoice_link}<br><br>Best regards,<br>{companyname}', 'fromname' => '{companyname}', 'active' => '1'],
        ];
        foreach ($templates as $t) {
            $exists = $CI->db->where('type', $t['type'])->where('slug', $t['slug'])->get(db_prefix() . 'emailtemplates')->row();
            if (empty($exists)) {
                $CI->db->insert(db_prefix() . 'emailtemplates', $t);
            }
        }
    }
}
