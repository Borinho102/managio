<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Migration 1.0.1 → 1.0.2
 *
 * Ships the "Approval Pulse" feature set (risk score, SLA/escalation,
 * email digest). A live 1.0.0 install upgrades straight to 1.0.2 in a
 * single "Upgrade Database" click: App_module_migration::to_latest() runs
 * every migration whose number exceeds installed_version*100, so from
 * 1.0.0 (threshold 100) it executes 101 (multi-level schema) and then this
 * 102 in one pass — no intermediate state.
 *
 * Strictly additive & idempotent (see brief rule 3 — a migration NEVER
 * touches buyer data): CREATE TABLE IF NOT EXISTS, an ENUM MODIFY (safe,
 * MySQL extends the set without rewriting rows), add_option() (no-op when
 * the option already exists). No UPDATE/DELETE on module data.
 *
 * The final update_option() keeps the internal approvalflow_db_version in
 * sync with the installed schema — install.php only runs on (re)activation,
 * never on the upgrade path, so the bump MUST live here (the 1.0.1 bug).
 *
 * @package ApprovalFlow\Migrations
 * @since   1.0.2
 */
class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $charset = $this->ci->db->char_set;
        $prefix  = db_prefix();

        // ── 1. New table: digest delivery log (support/audit trail of every send)
        if (!$this->ci->db->table_exists($prefix . 'approvalflow_digest_log')) {
            $this->ci->db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'approvalflow_digest_log` (
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

        // ── 2. Extend history action ENUM with "escalated" (SLA escalation event).
        //       Safe MODIFY — extending an ENUM never rewrites existing rows.
        $this->ci->db->query('ALTER TABLE `' . $prefix . 'approvalflow_history`
            MODIFY COLUMN `action` enum("created","approved","rejected","cancelled","reminder_sent","viewed_warning","reassigned","level_activated","escalated") NOT NULL');

        // ── 3. New options — Approval Pulse, all OFF/neutral by default so the
        //       upgraded module looks identical until the buyer opts in.
        add_option('approvalflow_risk_enabled',        '0');
        add_option('approvalflow_risk_amount_ceiling', '10000');
        add_option('approvalflow_sla_hours',           '0');
        add_option('approvalflow_escalate_to',         '');
        add_option('approvalflow_digest_enabled',      '0');
        add_option('approvalflow_digest_freq',         'weekly');
        add_option('approvalflow_digest_day',          '1');

        // ── 4. Sync the internal schema-version option with this release.
        update_option('approvalflow_db_version', '1.0.2');
    }
}
