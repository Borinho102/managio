<?php

defined('BASEPATH') or exit('No direct script access allowed');

add_option('wekonex_bridge_enabled', '1');
add_option('wekonex_bridge_wekonex_url', 'https://wekonex.net');
add_option('wekonex_bridge_sso_secret', '');
add_option('wekonex_bridge_webhook_secret', '');
add_option('wekonex_bridge_log_days', '90');
add_option('wekonex_bridge_api_staff_id', '');
add_option('wekonex_bridge_api_staff_email', 'integration@wekonex.local');
add_option('wekonex_bridge_api_staff_password', '');
add_option('wekonex_bridge_api_key', '');
add_option('wekonex_bridge_api_token', '');
add_option('wekonex_bridge_api_last_test', '');
add_option('wekonex_bridge_api_last_test_status', '');
add_option('wekonex_bridge_api_last_test_message', '');

$CI = &get_instance();
$CI->load->dbforge();

$ssoTokens = db_prefix() . 'wekonex_sso_tokens';
if (!$CI->db->table_exists($ssoTokens)) {
    $CI->dbforge->add_field([
        'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
        'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
        'payload' => ['type' => 'TEXT', 'null' => true],
        'expires_at' => ['type' => 'DATETIME'],
        'used_at' => ['type' => 'DATETIME', 'null' => true],
        'created_at' => ['type' => 'DATETIME'],
    ]);
    $CI->dbforge->add_key('id', true);
    $CI->dbforge->add_key('token_hash', false, true);
    $CI->dbforge->create_table('wekonex_sso_tokens', true);
}

$idempotency = db_prefix() . 'wekonex_idempotency_keys';
if (!$CI->db->table_exists($idempotency)) {
    $CI->dbforge->add_field([
        'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
        'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 191],
        'action' => ['type' => 'VARCHAR', 'constraint' => 64],
        'response_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        'created_at' => ['type' => 'DATETIME'],
    ]);
    $CI->dbforge->add_key('id', true);
    $CI->dbforge->add_key('idempotency_key', false, true);
    $CI->dbforge->create_table('wekonex_idempotency_keys', true);
}

$syncLogs = db_prefix() . 'wekonex_sync_logs';
if (!$CI->db->table_exists($syncLogs)) {
    $CI->dbforge->add_field([
        'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
        'direction' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'inbound'],
        'action' => ['type' => 'VARCHAR', 'constraint' => 64],
        'success' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        'http_status' => ['type' => 'SMALLINT', 'constraint' => 5, 'null' => true],
        'payload' => ['type' => 'TEXT', 'null' => true],
        'error_message' => ['type' => 'TEXT', 'null' => true],
        'created_at' => ['type' => 'DATETIME'],
    ]);
    $CI->dbforge->add_key('id', true);
    $CI->dbforge->create_table('wekonex_sync_logs', true);
}

$mappings = db_prefix() . 'wekonex_entity_mappings';
if (!$CI->db->table_exists($mappings)) {
    $CI->dbforge->add_field([
        'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
        'external_key' => ['type' => 'VARCHAR', 'constraint' => 191],
        'entity_type' => ['type' => 'VARCHAR', 'constraint' => 32],
        'managio_entity_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        'wekonex_tenant_id' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
        'created_at' => ['type' => 'DATETIME'],
        'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $CI->dbforge->add_key('id', true);
    $CI->dbforge->add_key('external_key', false, true);
    $CI->dbforge->create_table('wekonex_entity_mappings', true);
}

wekonex_bridge_install_custom_fields();
