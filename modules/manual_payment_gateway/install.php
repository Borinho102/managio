<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Create manual_payment_gateways table if it doesn't exist
if (!$CI->db->table_exists(db_prefix() . 'manual_payment_gateways')) {
    $CI->db->query('
        CREATE TABLE `' . db_prefix() . 'manual_payment_gateways` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `status` TINYINT(1) DEFAULT 0,
            `form_fields` LONGTEXT NULL,
            `description` LONGTEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';
    ');
}

// Create manual_payment_requests table if it doesn't exist
if (!$CI->db->table_exists(db_prefix() . 'manual_payment_requests')) {
    $CI->db->query('
        CREATE TABLE `' . db_prefix() . 'manual_payment_requests` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `invoice_id` VARCHAR(255) NOT NULL,
            `gateway_id` VARCHAR(255) NOT NULL,
            `gateway_name` VARCHAR(255) NOT NULL,
            `user_id` INT NOT NULL,
            `details` LONGTEXT NOT NULL,
            `status` TINYINT(1) DEFAULT 0,
            `amount` FLOAT DEFAULT 0,
            `message` VARCHAR(255) NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';
    ');
}