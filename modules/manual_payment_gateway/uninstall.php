<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
// Drop the 'manual_payment_gateways' table if it exists
if ($CI->db->table_exists(db_prefix() . 'manual_payment_gateways')) {
    $CI->db->query("DROP TABLE `" . db_prefix() . "manual_payment_gateways`");
}

// Drop the 'manual_payment_requests' table if it exists
if ($CI->db->table_exists(db_prefix() . 'manual_payment_requests')) {
    $CI->db->query("DROP TABLE `" . db_prefix() . "manual_payment_requests`");
}
