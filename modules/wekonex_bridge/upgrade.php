<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mise à jour Phase 1 (tables + champs custom) sans réinstaller le module.
 */
$CI = &get_instance();
$CI->load->dbforge();

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

if (get_option('wekonex_bridge_sso_auto_staff') === false) {
    add_option('wekonex_bridge_sso_auto_staff', '1');
}
