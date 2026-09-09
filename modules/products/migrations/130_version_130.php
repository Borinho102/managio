<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_130 extends App_module_migration
{
    public function up()
    {
        add_option('product_notifications_enabled', 0);

        $CI = &get_instance();
        if (!$CI->db->table_exists(db_prefix() . 'product_notification_templates')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'product_notification_templates` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `channel` ENUM("whatsapp","sms","webhook") NOT NULL DEFAULT "whatsapp",
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
    }
}
