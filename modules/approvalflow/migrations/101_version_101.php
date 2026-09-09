<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Migration 1.0.0 → 1.0.1
 *
 * Adds multi-level approval chain support:
 *   1. New table tblapprovalflow_rule_levels — extra sequential approvers
 *      (level 2+) per rule. Level 1 stays in rules.approver_staff_id.
 *   2. tblapprovalflow_requests: add `level` + `total_levels` columns,
 *      add `waiting` to status ENUM, swap UNIQUE KEY to include level.
 *   3. tblapprovalflow_history: add `level_activated` to action ENUM.
 *
 * Every DDL is guarded by an explicit existence check (table_exists /
 * list_fields / index_exists). This is mandatory on PHP 8.1+: mysqli
 * throws mysqli_sql_exception on ANY SQL error regardless of CI's
 * db_debug flag, so a bare "@" no longer suppresses a duplicate-key or
 * missing-key error — it would surface as an uncaught 500 and abort the
 * whole upgrade. Guarding by existence keeps every operation idempotent,
 * so re-running (e.g. a buyer retrying after a partial failure) is safe.
 *
 * @package ApprovalFlow\Migrations
 * @since   1.0.1
 */
class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $charset = $this->ci->db->char_set;
        $prefix  = db_prefix();

        // ── 1. New table: rule_levels
        if (!$this->ci->db->table_exists($prefix . 'approvalflow_rule_levels')) {
            $this->ci->db->query('CREATE TABLE `' . $prefix . 'approvalflow_rule_levels` (
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

        // ── 2. Alter requests: add level columns if missing
        $existing_cols = $this->ci->db->list_fields($prefix . 'approvalflow_requests');

        if (!in_array('level', $existing_cols)) {
            $this->ci->db->query('ALTER TABLE `' . $prefix . 'approvalflow_requests`
                ADD COLUMN `level`        smallint(5) NOT NULL DEFAULT 1 AFTER `rule_id`,
                ADD COLUMN `total_levels` smallint(5) NOT NULL DEFAULT 1 AFTER `level`');
        }

        // Add waiting to status ENUM (safe — re-applying the same ENUM is a no-op)
        $this->ci->db->query('ALTER TABLE `' . $prefix . 'approvalflow_requests`
            MODIFY COLUMN `status` enum("pending","approved","rejected","cancelled","waiting") NOT NULL DEFAULT "pending"');

        // Swap the unique constraint to include level. Each step is guarded by
        // an index-existence check so the operation is idempotent and never
        // raises a duplicate/missing-key error (which would throw on PHP 8.1+).
        if ($this->index_exists($prefix . 'approvalflow_requests', 'unique_entity')) {
            $this->ci->db->query('ALTER TABLE `' . $prefix . 'approvalflow_requests` DROP KEY `unique_entity`');
        }
        if (!$this->index_exists($prefix . 'approvalflow_requests', 'unique_entity_level')) {
            $this->ci->db->query('ALTER TABLE `' . $prefix . 'approvalflow_requests`
                ADD UNIQUE KEY `unique_entity_level` (`entity_type`, `entity_id`, `level`)');
        }
        if (!$this->index_exists($prefix . 'approvalflow_requests', 'idx_entity')) {
            $this->ci->db->query('ALTER TABLE `' . $prefix . 'approvalflow_requests`
                ADD KEY `idx_entity` (`entity_type`, `entity_id`)');
        }

        // ── 3. Alter history: add level_activated to action ENUM (idempotent MODIFY)
        $this->ci->db->query('ALTER TABLE `' . $prefix . 'approvalflow_history`
            MODIFY COLUMN `action` enum("created","approved","rejected","cancelled","reminder_sent","viewed_warning","reassigned","level_activated") NOT NULL');
    }

    /**
     * True when $index_name exists on $table (full, prefixed table name).
     * Used to make every index DDL idempotent — mysqli throws on a duplicate
     * ADD KEY or a missing DROP KEY on PHP 8.1+, so we never issue one blindly.
     */
    private function index_exists($table, $index_name)
    {
        $row = $this->ci->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index_name]
        )->row();
        return $row && (int) $row->c > 0;
    }
}
