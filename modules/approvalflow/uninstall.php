<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Module Uninstaller
 *
 * Hard cleanup on module removal: drops the four tblapprovalflow_*
 * tables and deletes every approvalflow_* row from tbloptions.
 * Native Perfex tables (tblinvoices, tblestimates, tblproposals,
 * tblcontracts, tblexpenses, tblstaff, tblclients, tblnotifications)
 * are NEVER touched.
 *
 * WARNING: destructive. To pause the module, deactivate instead.
 *
 * @package ApprovalFlow
 */

$CI = &get_instance();

$tables = [
    'approvalflow_logs',
    'approvalflow_history',
    'approvalflow_requests',
    'approvalflow_rule_levels',
    'approvalflow_rules',
];

foreach ($tables as $table) {
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . $table . '`');
}

$options = [
    'approvalflow_enable_invoices',
    'approvalflow_enable_estimates',
    'approvalflow_enable_proposals',
    'approvalflow_enable_contracts',
    'approvalflow_enable_expenses',
    'approvalflow_notifications_internal',
    'approvalflow_notifications_email',
    'approvalflow_require_reject_comment',
    'approvalflow_admin_auto_approve',
    'approvalflow_reminder_days',
    'approvalflow_debug_enabled',
    'approvalflow_logs_retention_days',
    'approvalflow_db_version',
];

foreach ($options as $option) {
    $CI->db->where('name', $option)->delete(db_prefix() . 'options');
}
