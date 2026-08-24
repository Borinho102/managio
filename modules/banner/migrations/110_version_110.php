<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_110 extends App_module_migration {
    public function __construct() {
        parent::__construct();
    }

    public function up() {
        add_option('enabled_banner_random_mode', 0);

        if (!get_instance()->db->table_exists(db_prefix() . 'news_ticker')) {
            get_instance()->db->query('CREATE TABLE `' . db_prefix() . 'news_ticker` (
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
			) ENGINE = InnoDB DEFAULT CHARSET='.get_instance()->db->char_set.';');
        }
    }
}
