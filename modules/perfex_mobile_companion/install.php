<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
if (!$CI->db->table_exists(db_prefix() . 'mobile_app_access_tokens')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'mobile_app_access_tokens` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `userid` VARCHAR(50) NOT NULL,
      `login_as` VARCHAR(50) NOT NULL,
      `token` VARCHAR(255) NOT NULL,
      `expiration_date` DATETIME NOT NULL,
      PRIMARY KEY (`id`));
');
}

if (!$CI->db->table_exists(db_prefix() . 'keys')) {
  $CI->db->query("CREATE TABLE `" . db_prefix() . "keys` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) NOT NULL,
      `key` VARCHAR(40) NOT NULL,
      `level` INT(2) NOT NULL,
      `ignore_limits` TINYINT(1) NOT NULL DEFAULT '0',
      `is_private_key` TINYINT(1)  NOT NULL DEFAULT '0',
      `ip_addresses` TEXT NULL DEFAULT NULL,
      `date_created` INT(11) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}

if (!$CI->db->field_exists('login_as', db_prefix() . 'keys')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'keys` ADD `login_as` VARCHAR(50) NULL DEFAULT NULL;');
}

if ($CI->db->field_exists('password', db_prefix() . 'mobile_app_access_tokens')) {
  $CI->db->query('ALTER TABLE ' . db_prefix() . 'mobile_app_access_tokens DROP `password`');
}

if (!$CI->db->table_exists(db_prefix() . 'push_notification_devices')) {
  $CI->db->query("CREATE TABLE `" . db_prefix() . "push_notification_devices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `token` VARCHAR(255) NOT NULL,
    `additional_data` text NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}

if (!$CI->db->field_exists('device_id', db_prefix() . 'push_notification_devices')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'push_notification_devices` ADD COLUMN `device_id` VARCHAR(255) DEFAULT NULL');
}

if (!$CI->db->field_exists('qr_code_otp', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD `qr_code_otp` VARCHAR(255) DEFAULT NULL;');
}

if (!$CI->db->field_exists('otp_valid_until', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD `otp_valid_until` TIMESTAMP NULL DEFAULT NULL;');
}

$uris = $CI->config->item('csrf_exclude_uris');
if (!empty($uris) && is_array($uris) && !in_array("perfex_mobile_companion\/.+", $uris)) {

  $myfile = fopen(APPPATH . "config/config.php", "a") or die("Unable to open file!");
  $txt = "if(!isset(\$config['csrf_exclude_uris'])) { \$config['csrf_exclude_uris']=[]; }";
  fwrite($myfile, "\n" . $txt);
  $txt = "\$config['csrf_exclude_uris'] = array_merge(\$config['csrf_exclude_uris'],array('perfex_mobile_companion\/.+'));";
  fwrite($myfile, "\n" . $txt);
  fclose($myfile);
}
