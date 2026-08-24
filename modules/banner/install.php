<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Banner module install / schema setup.
 * License obfuscation removed — it called undefined sprintsf() and caused HTTP 500 on activate.
 */

add_option('enabled_banner_random_mode', 0);

$CI = get_instance();
$charset = $CI->db->char_set ?: 'utf8mb4';

if (!$CI->db->table_exists(db_prefix() . 'banner')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'banner` (
	   	`id` int NOT NULL AUTO_INCREMENT,
		`title` varchar(100) NOT NULL,
		`detail` mediumtext NOT NULL,
		`status` tinyint(1) NOT NULL DEFAULT "0",
		`date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`start_date` date NOT NULL,
		`end_date` date NOT NULL,
		`admin_area` tinyint(1) NOT NULL DEFAULT "0",
		`clients_area` tinyint(1) NOT NULL DEFAULT "0",
		`staff_ids` text,
		`client_ids` text,
		`has_action` tinyint(1) NOT NULL DEFAULT "0",
		`action_label` varchar(250) DEFAULT NULL,
		`label_color` varchar(250) DEFAULT NULL,
		`action_url` text,
		`action_target` tinyint(1) NOT NULL DEFAULT "0",
		PRIMARY KEY (`id`)
	) ENGINE = InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'news_ticker')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'news_ticker` (
	   	`id` int NOT NULL AUTO_INCREMENT,
		`news_title` varchar(250) NOT NULL,
		`news_details` text NOT NULL,
		`status` tinyint(1) NOT NULL DEFAULT "0",
		`date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`start_date` date NOT NULL,
		`end_date` date NOT NULL,
		`news_type` varchar(250) NOT NULL,
		`title_icon` varchar(250) DEFAULT NULL,
		`title_text_color` varchar(250) NOT NULL,
		`title_bg_color` varchar(250) NOT NULL,
		`admin_area` tinyint(1) NOT NULL DEFAULT "0",
		`clients_area` tinyint(1) NOT NULL DEFAULT "0",
		`staff_ids` text,
		`client_ids` text,
		PRIMARY KEY (`id`)
	) ENGINE = InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if ($CI->db->table_exists(db_prefix() . 'banner')) {
    if (!$CI->db->field_exists('has_action', db_prefix() . 'banner')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'banner` ADD `has_action` tinyint(1) NOT NULL DEFAULT "0"');
    }
    if (!$CI->db->field_exists('action_label', db_prefix() . 'banner')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'banner` ADD `action_label` varchar(250) DEFAULT NULL');
    }
    if (!$CI->db->field_exists('action_url', db_prefix() . 'banner')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'banner` ADD `action_url` text DEFAULT NULL');
    }
    if (!$CI->db->field_exists('label_color', db_prefix() . 'banner')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'banner` ADD `label_color` varchar(250) DEFAULT NULL');
    }
    if (!$CI->db->field_exists('action_target', db_prefix() . 'banner')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'banner` ADD `action_target` tinyint(1) NOT NULL DEFAULT "0"');
    }
}

$themeHome = VIEWPATH . 'themes/perfex/views/my_home.php';
$themeHomeSrc = module_dir_path(BANNER_MODULE, '/resources/application/views/themes/perfex/views/my_home.php');
if (!file_exists($themeHome) && file_exists($themeHomeSrc)) {
    @copy($themeHomeSrc, $themeHome);
}
