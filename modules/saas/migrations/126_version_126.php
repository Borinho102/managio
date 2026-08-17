<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_126 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!empty(subdomain())) {
            set_alert('warning', 'Only superadmin can update the system.');
            redirect('admin/dashboard');
        }

        // Netim purchased domains
        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_netim_domains` (
          `domain_id` int NOT NULL AUTO_INCREMENT,
          `company_id` int NOT NULL,
          `domain_name` varchar(255) NOT NULL,
          `tld` varchar(50) NOT NULL,
          `contact_handle` varchar(100) DEFAULT NULL,
          `status` varchar(50) NOT NULL DEFAULT 'pending',
          `expiry_date` date DEFAULT NULL,
          `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
          `dns_configured` tinyint(1) NOT NULL DEFAULT 0,
          `netim_order_id` varchar(100) DEFAULT NULL,
          `purchase_price` decimal(10,2) DEFAULT NULL,
          `currency` varchar(10) DEFAULT 'USD',
          `registered_at` timestamp NULL DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`domain_id`),
          KEY `idx_company_id` (`company_id`),
          KEY `idx_domain_name` (`domain_name`)
        ) ENGINE=InnoDB;");

        // Netim contacts (WHOIS registrant info per company)
        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_netim_contacts` (
          `contact_id` int NOT NULL AUTO_INCREMENT,
          `company_id` int NOT NULL,
          `netim_handle` varchar(100) NOT NULL,
          `first_name` varchar(100) DEFAULT NULL,
          `last_name` varchar(100) DEFAULT NULL,
          `email` varchar(255) DEFAULT NULL,
          `phone` varchar(50) DEFAULT NULL,
          `address` varchar(255) DEFAULT NULL,
          `city` varchar(100) DEFAULT NULL,
          `state` varchar(100) DEFAULT NULL,
          `country` varchar(10) DEFAULT NULL,
          `zipcode` varchar(20) DEFAULT NULL,
          `legal_type` varchar(20) NOT NULL DEFAULT 'INDIVIDUAL',
          `company_name` varchar(200) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`contact_id`),
          KEY `idx_company_id` (`company_id`)
        ) ENGINE=InnoDB;");

        // Netim domain purchase requests (pending admin action)
        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_netim_requests` (
          `request_id` int NOT NULL AUTO_INCREMENT,
          `company_id` int NOT NULL,
          `domain_name` varchar(255) NOT NULL,
          `contact_id` int DEFAULT NULL,
          `status` varchar(50) NOT NULL DEFAULT 'pending',
          `price` decimal(10,2) DEFAULT NULL,
          `currency` varchar(10) DEFAULT 'USD',
          `notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `processed_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`request_id`),
          KEY `idx_company_id` (`company_id`)
        ) ENGINE=InnoDB;");

        // Default Netim options (add_option skips if already exists)
        add_option('netim_api_login',     '');
        add_option('netim_api_password',  '');
        add_option('netim_sandbox_mode',  '0');
        add_option('netim_nameserver_1',  '');
        add_option('netim_nameserver_2',  '');
        add_option('netim_server_ip',     '');
        add_option('netim_auto_dns',      '1');
        add_option('netim_auto_register', '0');
    }
}
