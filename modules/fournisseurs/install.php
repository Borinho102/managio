<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'fournisseurs')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "fournisseurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company` varchar(191) NOT NULL,
  `vat` varchar(50) DEFAULT NULL,
  `phonenumber` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `country` int(11) NOT NULL DEFAULT 0,
  `contact_fullname` varchar(191) DEFAULT NULL,
  `contact_phonenumber` varchar(50) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `notes` text,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `datecreated` datetime DEFAULT NULL,
  `addedfrom` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `active` (`active`),
  KEY `addedfrom` (`addedfrom`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
