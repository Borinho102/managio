<?php

defined('BASEPATH') or exit('No direct script access allowed');

delete_option('wekonex_bridge_enabled');
delete_option('wekonex_bridge_wekonex_url');
delete_option('wekonex_bridge_sso_secret');
delete_option('wekonex_bridge_webhook_secret');
delete_option('wekonex_bridge_log_days');
delete_option('wekonex_bridge_api_staff_id');
delete_option('wekonex_bridge_api_staff_email');
delete_option('wekonex_bridge_api_staff_password');
delete_option('wekonex_bridge_api_key');
delete_option('wekonex_bridge_api_token');
delete_option('wekonex_bridge_api_last_test');
delete_option('wekonex_bridge_api_last_test_status');
delete_option('wekonex_bridge_api_last_test_message');

$CI = &get_instance();
$CI->load->dbforge();

foreach (['wekonex_sso_tokens', 'wekonex_idempotency_keys', 'wekonex_sync_logs'] as $table) {
    $full = db_prefix() . $table;
    if ($CI->db->table_exists($full)) {
        $CI->dbforge->drop_table($table, true);
    }
}
