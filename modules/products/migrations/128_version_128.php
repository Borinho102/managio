<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_128 extends App_module_migration
{
    public function up()
    {
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

        $CI = &get_instance();
        if ($CI->db->table_exists(db_prefix() . 'product_abandoned_cart')) {
            if (!$CI->db->field_exists('emailed', db_prefix() . 'product_abandoned_cart')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'product_abandoned_cart`
                    ADD `emailed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `dateadded`');
            }
        }
    }
}
