<?php

defined('BASEPATH') or exit('No direct script access allowed');

add_option('wasabi_storage_enabled', '0');
add_option('wasabi_access_key', '');
add_option('wasabi_secret_key', '');
add_option('wasabi_bucket', '');
add_option('wasabi_region', 'eu-central-1');
add_option('wasabi_endpoint', 'https://s3.eu-central-1.wasabisys.com');
add_option('wasabi_path_prefix', 'managio/');
add_option('wasabi_signed_url_ttl', '3600');
add_option('wasabi_backup_enabled', '0');
add_option('wasabi_migrate_status', '');
add_option('wasabi_last_error', '');
add_option('wasabi_known_backups', '[]');

if (!function_exists('wasabi_storage_ensure_table')) {
    function wasabi_storage_ensure_table()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'wasabi_files';
        if ($CI->db->table_exists($table)) {
            return;
        }

        $CI->db->query('CREATE TABLE `' . $table . '` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rel_type` VARCHAR(64) NOT NULL,
            `rel_id` INT(11) NOT NULL DEFAULT 0,
            `file_name` VARCHAR(255) NOT NULL,
            `object_key` TEXT NOT NULL,
            `filetype` VARCHAR(120) NULL,
            `dateadded` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `rel_lookup` (`rel_type`, `rel_id`),
            KEY `file_name` (`file_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
    }
}

wasabi_storage_ensure_table();
