<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Module Installer
 *
 * Runs on activation. Idempotent — every CREATE TABLE / INSERT is guarded
 * by IF NOT EXISTS or option-already-present checks so re-running on an
 * existing install never destroys data. Creates the module tables
 * (rules, rule_levels, requests, history, logs) and seeds the default options.
 *
 * Schema decisions:
 *   - All FK-like columns are INT UNSIGNED for signedness consistency.
 *   - history.action ENUM includes 'reassigned' and 'level_activated'
 *     for multi-level approval chain transitions.
 *   - requests has idx_status_created for KPI queries (status + month).
 *   - history has idx_request_created for timeline-by-request queries.
 *   - Low-cardinality keys (priority, action, actor, level) intentionally
 *     left unindexed — the optimiser is faster without them on small fanout.
 *
 * @package ApprovalFlow
 */

$CI      = &get_instance();
$charset = $CI->db->char_set;

// ── 0. Register/refresh module row in tblmodules
$exists = $CI->db->where('module_name', APPROVALFLOW_MODULE_NAME)
    ->count_all_results(db_prefix() . 'modules');
if ($exists) {
    $CI->db->where('module_name', APPROVALFLOW_MODULE_NAME)
        ->update(db_prefix() . 'modules', [
            'installed_version' => APPROVALFLOW_VERSION,
            'active'            => 1,
        ]);
} else {
    $CI->db->insert(db_prefix() . 'modules', [
        'module_name'       => APPROVALFLOW_MODULE_NAME,
        'installed_version' => APPROVALFLOW_VERSION,
        'active'            => 1,
    ]);
}

// ── 1. Rules — admin-configured approval rules with JSON conditions
if (!$CI->db->table_exists(db_prefix() . 'approvalflow_rules')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'approvalflow_rules` (
        `id`                int(11) unsigned   NOT NULL AUTO_INCREMENT,
        `name`              varchar(150)       NOT NULL,
        `entity_type`       enum("invoice","estimate","proposal","contract","expense") NOT NULL,
        `approver_staff_id` int(11) unsigned   NOT NULL,
        `priority`          smallint(6)        NOT NULL DEFAULT 10,
        `active`            tinyint(1)         NOT NULL DEFAULT 1,
        `conditions`        text               NOT NULL,
        `created_by`        int(11) unsigned   NOT NULL DEFAULT 0,
        `created_at`        datetime           NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`        datetime           NULL     DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_entity_active` (`entity_type`, `active`),
        KEY `idx_approver`      (`approver_staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ── 1b. Rule levels — extra sequential approvers (level 2+) per rule
//        Level 1 is stored in tblapprovalflow_rules.approver_staff_id.
if (!$CI->db->table_exists(db_prefix() . 'approvalflow_rule_levels')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'approvalflow_rule_levels` (
        `id`                int(11) unsigned   NOT NULL AUTO_INCREMENT,
        `rule_id`           int(11) unsigned   NOT NULL,
        `level`             smallint(5)        NOT NULL DEFAULT 2,
        `approver_staff_id` int(11) unsigned   NOT NULL,
        `label`             varchar(100)       NULL     DEFAULT NULL,
        `created_at`        datetime           NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_rule_level`  (`rule_id`, `level`),
        KEY `idx_rule`                  (`rule_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ── 2. Requests — one row per (entity, level) combination
if (!$CI->db->table_exists(db_prefix() . 'approvalflow_requests')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'approvalflow_requests` (
        `id`                 int(11) unsigned  NOT NULL AUTO_INCREMENT,
        `rule_id`            int(11) unsigned  NOT NULL,
        `level`              smallint(5)       NOT NULL DEFAULT 1,
        `total_levels`       smallint(5)       NOT NULL DEFAULT 1,
        `entity_type`        enum("invoice","estimate","proposal","contract","expense") NOT NULL,
        `entity_id`          int(11) unsigned  NOT NULL,
        `entity_snapshot`    text              NULL,
        `requester_staff_id` int(11) unsigned  NOT NULL DEFAULT 0,
        `approver_staff_id`  int(11) unsigned  NOT NULL,
        `status`             enum("pending","approved","rejected","cancelled","waiting") NOT NULL DEFAULT "pending",
        `resolved_by`        int(11) unsigned  NOT NULL DEFAULT 0,
        `resolved_at`        datetime          NULL     DEFAULT NULL,
        `resolution_comment` text              NULL,
        `created_at`         datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `reminder_sent_at`   datetime          NULL     DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_entity_level`  (`entity_type`, `entity_id`, `level`),
        KEY `idx_status`                  (`status`),
        KEY `idx_status_created`          (`status`, `created_at`),
        KEY `idx_approver_status`         (`approver_staff_id`, `status`),
        KEY `idx_requester`               (`requester_staff_id`),
        KEY `idx_created`                 (`created_at`),
        KEY `idx_rule`                    (`rule_id`),
        KEY `idx_entity`                  (`entity_type`, `entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
} else {
    // Upgrade path: add level columns if missing (idempotent for 1.0.0 → 1.0.1)
    $existing = $CI->db->list_fields(db_prefix() . 'approvalflow_requests');
    if (!in_array('level', $existing)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'approvalflow_requests`
            ADD COLUMN `level` smallint(5) NOT NULL DEFAULT 1 AFTER `rule_id`,
            ADD COLUMN `total_levels` smallint(5) NOT NULL DEFAULT 1 AFTER `level`');
    }
    // Upgrade ENUM to include waiting — MySQL allows modifying ENUMs safely
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'approvalflow_requests`
        MODIFY COLUMN `status` enum("pending","approved","rejected","cancelled","waiting") NOT NULL DEFAULT "pending"');
    // Drop old unique constraint and add the level-aware one. Each step is
    // guarded by an index-existence check — on PHP 8.1+ mysqli throws on a
    // duplicate/missing-key error regardless of db_debug, so a bare "@" no
    // longer suppresses it. Guarding by existence keeps this idempotent.
    $af_index_exists = function ($table, $index) use ($CI) {
        $row = $CI->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        )->row();
        return $row && (int) $row->c > 0;
    };
    if ($af_index_exists(db_prefix() . 'approvalflow_requests', 'unique_entity')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'approvalflow_requests` DROP KEY `unique_entity`');
    }
    if (!$af_index_exists(db_prefix() . 'approvalflow_requests', 'unique_entity_level')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'approvalflow_requests`
            ADD UNIQUE KEY `unique_entity_level` (`entity_type`, `entity_id`, `level`)');
    }
    if (!$af_index_exists(db_prefix() . 'approvalflow_requests', 'idx_entity')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'approvalflow_requests`
            ADD KEY `idx_entity` (`entity_type`, `entity_id`)');
    }
}

// ── 3. History — immutable timeline of every state transition per request
if (!$CI->db->table_exists(db_prefix() . 'approvalflow_history')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'approvalflow_history` (
        `id`              int(11) unsigned  NOT NULL AUTO_INCREMENT,
        `request_id`      int(11) unsigned  NOT NULL,
        `action`          enum("created","approved","rejected","cancelled","reminder_sent","viewed_warning","reassigned","level_activated","escalated") NOT NULL,
        `actor_staff_id`  int(11) unsigned  NOT NULL DEFAULT 0,
        `previous_status` varchar(20)       NULL     DEFAULT NULL,
        `new_status`      varchar(20)       NULL     DEFAULT NULL,
        `comment`         text              NULL     DEFAULT NULL,
        `created_at`      datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_request_created`  (`request_id`, `created_at`),
        KEY `idx_created`          (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
} else {
    // Upgrade path: add level_activated + escalated to the action ENUM if missing
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'approvalflow_history`
        MODIFY COLUMN `action` enum("created","approved","rejected","cancelled","reminder_sent","viewed_warning","reassigned","level_activated","escalated") NOT NULL');
}

// ── 4. Logs — technical / operational events, gated by debug setting
if (!$CI->db->table_exists(db_prefix() . 'approvalflow_logs')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'approvalflow_logs` (
        `id`          int(11) unsigned  NOT NULL AUTO_INCREMENT,
        `event`       varchar(50)       NOT NULL,
        `entity_type` varchar(20)       NULL     DEFAULT NULL,
        `entity_id`   int(11) unsigned  NULL     DEFAULT NULL,
        `rule_id`     int(11) unsigned  NULL     DEFAULT NULL,
        `details`     text              NULL     DEFAULT NULL,
        `level`       enum("info","warn","error") NOT NULL DEFAULT "info",
        `created_at`  datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_event`    (`event`),
        KEY `idx_entity`   (`entity_type`, `entity_id`),
        KEY `idx_created`  (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ── 4b. Digest delivery log — Approval Pulse (support/audit trail of every send)
if (!$CI->db->table_exists(db_prefix() . 'approvalflow_digest_log')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'approvalflow_digest_log` (
        `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
        `sent_at`       datetime         NULL     DEFAULT NULL,
        `freq`          varchar(10)      NOT NULL DEFAULT "weekly",
        `recipients`    text             NULL     DEFAULT NULL,
        `pending_count` int(11) unsigned NOT NULL DEFAULT 0,
        `overdue_count` int(11) unsigned NOT NULL DEFAULT 0,
        `status`        enum("sent","failed","skipped") NOT NULL DEFAULT "sent",
        `created_at`    datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ── 5. Default options — idempotent (add_option only inserts if missing)
add_option('approvalflow_enable_invoices',         '1');
add_option('approvalflow_enable_estimates',        '1');
add_option('approvalflow_enable_proposals',        '1');
add_option('approvalflow_enable_contracts',        '1');
add_option('approvalflow_enable_expenses',         '1');
add_option('approvalflow_notifications_internal',  '1');
add_option('approvalflow_notifications_email',     '1');
add_option('approvalflow_require_reject_comment',  '1');
add_option('approvalflow_admin_auto_approve',      '0');
add_option('approvalflow_reminder_days',           '3');
add_option('approvalflow_debug_enabled',           '0');
add_option('approvalflow_logs_retention_days',     '30');
// Approval Pulse (v1.0.2) — all OFF/neutral by default (no behavior change until opt-in)
add_option('approvalflow_risk_enabled',            '0');
add_option('approvalflow_risk_amount_ceiling',     '10000');
add_option('approvalflow_sla_hours',               '0');
add_option('approvalflow_escalate_to',             '');
add_option('approvalflow_digest_enabled',          '0');
add_option('approvalflow_digest_freq',             'weekly');
add_option('approvalflow_digest_day',              '1');
add_option('approvalflow_db_version',              APPROVALFLOW_VERSION);
// Force-update db_version on upgrade so the version reflects the installed schema
update_option('approvalflow_db_version', APPROVALFLOW_VERSION);
