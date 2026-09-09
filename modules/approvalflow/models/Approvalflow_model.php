<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Main Model
 *
 * Single data-access layer for all module features. Sections inside:
 *   - Dashboard / KPIs
 *   - Rules CRUD
 *   - Requests CRUD + lifecycle (approve / reject / bulk / cancel)
 *   - Engine (rule matching + condition evaluation)
 *   - History (timeline write + timeline read)
 *   - Logs (technical, gated by debug)
 *   - Reminders sweep
 *   - Entity helpers (fetch / snapshot / admin URL)
 *
 * Strict rules:
 *   - Cero SQL crudo concatenado: only Query Builder.
 *   - Every mutation logs to _history when it changes a request state.
 *   - Notifications never block the flow: add_notification() / email
 *     failures are caught and logged but the request still goes through.
 *
 * @package ApprovalFlow
 */
class Approvalflow_model extends App_Model
{
    /** Whitelist of recognised entity types — used to validate inputs */
    private $entity_types = ['invoice', 'estimate', 'proposal', 'contract', 'expense'];

    public function __construct()
    {
        parent::__construct();
    }

    /* ════════════════════════════════════════════════════════════════
     * 1. DASHBOARD / KPIs
     * Six aggregates queried in a single round trip when possible.
     * Scope is governed by $only_for_staff: when set, every count is
     * filtered to that approver. NULL means "all" (view_all permission).
     * ════════════════════════════════════════════════════════════════ */

    /**
     * Compute the 6 KPIs of the dashboard.
     *
     * @param int|null $only_for_staff  approver_staff_id filter, or NULL for global
     * @return array
     */
    public function dashboard_counts($only_for_staff = null)
    {
        $month_start = date('Y-m-01 00:00:00');
        $month_end   = date('Y-m-d 23:59:59');

        $kpis = [
            'pending'        => 0,
            'approved_month' => 0,
            'rejected_month' => 0,
            'avg_time_hours' => null,
            'high_value'     => 0,
            'top_requester'  => null,
        ];

        // 1. Pending
        $this->db->where('status', 'pending');
        if ($only_for_staff !== null) {
            $this->db->where('approver_staff_id', (int) $only_for_staff);
        }
        $kpis['pending'] = (int) $this->db->count_all_results(db_prefix() . 'approvalflow_requests');

        // 2. Approved this month (uses idx_status_created)
        $this->db->where('status', 'approved')
            ->where('created_at >=', $month_start)
            ->where('created_at <=', $month_end);
        if ($only_for_staff !== null) {
            $this->db->where('approver_staff_id', (int) $only_for_staff);
        }
        $kpis['approved_month'] = (int) $this->db->count_all_results(db_prefix() . 'approvalflow_requests');

        // 3. Rejected this month
        $this->db->where('status', 'rejected')
            ->where('created_at >=', $month_start)
            ->where('created_at <=', $month_end);
        if ($only_for_staff !== null) {
            $this->db->where('approver_staff_id', (int) $only_for_staff);
        }
        $kpis['rejected_month'] = (int) $this->db->count_all_results(db_prefix() . 'approvalflow_requests');

        // 4. Avg resolution time (hours) — only over resolved requests
        $this->db->select('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_min', false)
            ->where_in('status', ['approved', 'rejected'])
            ->where('resolved_at IS NOT NULL', null, false);
        if ($only_for_staff !== null) {
            $this->db->where('approver_staff_id', (int) $only_for_staff);
        }
        $row = $this->db->get(db_prefix() . 'approvalflow_requests')->row_array();
        if ($row && $row['avg_min'] !== null) {
            $kpis['avg_time_hours'] = round(((float) $row['avg_min']) / 60, 1);
        }

        // 5. High value approved this month — read entity_snapshot.total in PHP
        // (JSON_EXTRACT does not use indexes in MySQL 5.7; for v1.0 we iterate
        //  the approved subset of this month — typically 30-300 rows.)
        $this->db->select('entity_snapshot')
            ->where('status', 'approved')
            ->where('created_at >=', $month_start)
            ->where('created_at <=', $month_end);
        if ($only_for_staff !== null) {
            $this->db->where('approver_staff_id', (int) $only_for_staff);
        }
        $rows = $this->db->get(db_prefix() . 'approvalflow_requests')->result_array();
        $high = 0.0;
        foreach ($rows as $r) {
            if (empty($r['entity_snapshot'])) continue;
            $snap = json_decode($r['entity_snapshot'], true);
            if (is_array($snap) && isset($snap['total'])) {
                $total = (float) $snap['total'];
                if ($total > $high) {
                    $high = $total;
                }
            }
        }
        $kpis['high_value'] = $high;

        // 6. Top requester this month
        $this->db->select('requester_staff_id, COUNT(*) as cnt')
            ->where('created_at >=', $month_start)
            ->where('created_at <=', $month_end)
            ->where('requester_staff_id >', 0)
            ->group_by('requester_staff_id')
            ->order_by('cnt', 'DESC')
            ->limit(1);
        if ($only_for_staff !== null) {
            $this->db->where('approver_staff_id', (int) $only_for_staff);
        }
        $top = $this->db->get(db_prefix() . 'approvalflow_requests')->row_array();
        if ($top) {
            $kpis['top_requester'] = [
                'staff_id' => (int) $top['requester_staff_id'],
                'count'    => (int) $top['cnt'],
            ];
        }

        return $kpis;
    }

    /**
     * Most recent pending requests for the dashboard widget. Ordered by
     * creation date desc so freshest approvals surface first.
     */
    public function recent_pending($limit = 5, $only_for_staff = null)
    {
        $this->db->select('r.*, s_appr.firstname as approver_fn, s_appr.lastname as approver_ln,'
            . ' s_req.firstname as requester_fn, s_req.lastname as requester_ln,'
            . ' rule.name as rule_name')
            ->from(db_prefix() . 'approvalflow_requests r')
            ->join(db_prefix() . 'staff s_appr', 's_appr.staffid = r.approver_staff_id', 'left')
            ->join(db_prefix() . 'staff s_req',  's_req.staffid = r.requester_staff_id',  'left')
            ->join(db_prefix() . 'approvalflow_rules rule', 'rule.id = r.rule_id', 'left')
            ->where('r.status', 'pending')
            ->order_by('r.created_at', 'DESC')
            ->limit((int) $limit);
        if ($only_for_staff !== null) {
            $this->db->where('r.approver_staff_id', (int) $only_for_staff);
        }
        return $this->db->get()->result_array();
    }

    /**
     * Recent history entries for the dashboard activity feed.
     */
    public function recent_activity($limit = 10, $only_for_staff = null)
    {
        $this->db->select('h.*, r.entity_type, r.entity_id, r.rule_id,'
            . ' s.firstname, s.lastname')
            ->from(db_prefix() . 'approvalflow_history h')
            ->join(db_prefix() . 'approvalflow_requests r', 'r.id = h.request_id', 'left')
            ->join(db_prefix() . 'staff s', 's.staffid = h.actor_staff_id', 'left')
            ->order_by('h.created_at', 'DESC')
            ->limit((int) $limit);
        if ($only_for_staff !== null) {
            $this->db->where('r.approver_staff_id', (int) $only_for_staff);
        }
        return $this->db->get()->result_array();
    }

    /* ════════════════════════════════════════════════════════════════
     * 2. RULES — CRUD
     * Conditions go in / out as PHP arrays. We persist them as a JSON
     * string in the `conditions` text column.
     * ════════════════════════════════════════════════════════════════ */

    public function get_rules($id = '', $filters = [], $limit = 0, $offset = 0)
    {
        if (is_numeric($id) && (int) $id > 0) {
            $row = $this->db->where('id', (int) $id)
                ->get(db_prefix() . 'approvalflow_rules')->row_array();
            if ($row) {
                $row['conditions_array'] = $this->decode_conditions($row['conditions']);
                $row['extra_levels']     = $this->get_rule_levels((int) $row['id']);
            }
            return $row;
        }

        if (!empty($filters['entity_type']) && in_array($filters['entity_type'], $this->entity_types, true)) {
            $this->db->where('entity_type', $filters['entity_type']);
        }
        if (isset($filters['active']) && $filters['active'] !== '') {
            $this->db->where('active', (int) $filters['active']);
        }

        $this->db->select('r.*, s.firstname as approver_fn, s.lastname as approver_ln, s.active as approver_active,'
                . '(SELECT COUNT(*) FROM ' . db_prefix() . 'approvalflow_requests rq'
                . ' WHERE rq.rule_id = r.id AND rq.status = "pending") as pending_count', false)
            ->from(db_prefix() . 'approvalflow_rules r')
            ->join(db_prefix() . 'staff s', 's.staffid = r.approver_staff_id', 'left')
            ->order_by('r.priority', 'ASC')
            ->order_by('r.id', 'ASC');

        // Optional pagination — when limit > 0, fetch a window only.
        if ((int) $limit > 0) {
            $this->db->limit((int) $limit, (int) $offset);
        }

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['conditions_array'] = $this->decode_conditions($row['conditions']);
            $row['extra_levels']     = $this->get_rule_levels((int) $row['id']);
        }
        return $rows;
    }

    /**
     * Count rules matching the same filters as get_rules(). Cheap COUNT(*)
     * for paginator total. Returns int. NOTE: filters mirror get_rules.
     */
    public function count_rules($filters = [])
    {
        $this->db->from(db_prefix() . 'approvalflow_rules');
        if (!empty($filters['entity_type']) && in_array($filters['entity_type'], $this->entity_types, true)) {
            $this->db->where('entity_type', $filters['entity_type']);
        }
        if (isset($filters['active']) && $filters['active'] !== '') {
            $this->db->where('active', (int) $filters['active']);
        }
        return (int) $this->db->count_all_results();
    }

    public function create_rule($data)
    {
        $payload = $this->sanitize_rule_input($data);
        if (empty($payload['name']) || empty($payload['entity_type']) || empty($payload['approver_staff_id'])) {
            return 0;
        }
        $payload['created_by'] = (int) get_staff_user_id();
        $payload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'approvalflow_rules', $payload);
        $new_id = (int) $this->db->insert_id();
        if ($new_id > 0 && !empty($data['extra_levels'])) {
            $this->save_rule_levels($new_id, (array) $data['extra_levels']);
        }
        return $new_id;
    }

    public function update_rule($id, $data)
    {
        $payload = $this->sanitize_rule_input($data);
        if (empty($payload)) {
            return false;
        }
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)
            ->update(db_prefix() . 'approvalflow_rules', $payload);
        $ok = $this->db->affected_rows() >= 0;
        // Always replace extra levels (even if the rule fields didn't change)
        if (isset($data['extra_levels'])) {
            $this->save_rule_levels((int) $id, (array) $data['extra_levels']);
        }
        return $ok;
    }

    public function delete_rule($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }
        $this->db->where('rule_id', $id)->delete(db_prefix() . 'approvalflow_rule_levels');
        $this->db->where('id', $id)->delete(db_prefix() . 'approvalflow_rules');
        return $this->db->affected_rows() > 0;
    }

    public function toggle_rule_active($id)
    {
        $row = $this->db->select('active')
            ->where('id', (int) $id)
            ->get(db_prefix() . 'approvalflow_rules')->row_array();
        if (!$row) {
            return null;
        }
        $new = $row['active'] ? 0 : 1;
        $this->db->where('id', (int) $id)
            ->update(db_prefix() . 'approvalflow_rules', [
                'active'     => $new,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return $new;
    }

    /**
     * Count pending requests bound to a rule — used by delete() to warn
     * the admin that destructive deletion would orphan live requests.
     */
    public function count_pending_for_rule($rule_id)
    {
        return (int) $this->db->where('rule_id', (int) $rule_id)
            ->where_in('status', ['pending', 'waiting'])
            ->count_all_results(db_prefix() . 'approvalflow_requests');
    }

    /**
     * Fetch extra approval levels (level 2+) for a rule, joined with staff name.
     * Returns an empty array when the rule is single-level.
     */
    public function get_rule_levels($rule_id)
    {
        return $this->db->select('rl.*, s.firstname, s.lastname')
            ->from(db_prefix() . 'approvalflow_rule_levels rl')
            ->join(db_prefix() . 'staff s', 's.staffid = rl.approver_staff_id', 'left')
            ->where('rl.rule_id', (int) $rule_id)
            ->order_by('rl.level', 'ASC')
            ->get()->result_array();
    }

    /**
     * Replace the extra levels (2+) for a rule. Level 1 lives in the main
     * rules row (approver_staff_id) and is never touched here.
     *
     * @param int   $rule_id
     * @param array $levels  indexed array of staff IDs for levels 2, 3, …
     */
    public function save_rule_levels($rule_id, $levels)
    {
        $rule_id = (int) $rule_id;
        $this->db->where('rule_id', $rule_id)->delete(db_prefix() . 'approvalflow_rule_levels');
        $level_num = 2;
        foreach ($levels as $staff_id) {
            $staff_id = (int) $staff_id;
            if ($staff_id <= 0 || $level_num > 10) {
                continue;
            }
            $this->db->insert(db_prefix() . 'approvalflow_rule_levels', [
                'rule_id'           => $rule_id,
                'level'             => $level_num,
                'approver_staff_id' => $staff_id,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
            $level_num++;
        }
    }

    /** Whitelist + sanitize the fields we accept when writing a rule. */
    private function sanitize_rule_input($data)
    {
        $out = [];
        if (isset($data['name'])) {
            $out['name'] = trim((string) $data['name']);
        }
        if (isset($data['entity_type']) && in_array($data['entity_type'], $this->entity_types, true)) {
            $out['entity_type'] = $data['entity_type'];
        }
        if (isset($data['approver_staff_id'])) {
            $out['approver_staff_id'] = (int) $data['approver_staff_id'];
        }
        if (isset($data['priority'])) {
            $out['priority'] = max(1, min(32000, (int) $data['priority']));
        }
        if (isset($data['active'])) {
            $out['active'] = (int) ((int) $data['active'] === 1);
        }
        if (isset($data['conditions'])) {
            $conditions = is_array($data['conditions'])
                ? $this->sanitize_conditions_array($data['conditions'])
                : [];
            $out['conditions'] = json_encode($conditions, JSON_UNESCAPED_UNICODE);
        }
        return $out;
    }

    /** Keep only the keys we recognize, cast values to safe types. */
    private function sanitize_conditions_array($arr)
    {
        $clean = [];
        if (isset($arr['amount_gt']) && $arr['amount_gt'] !== '') {
            $clean['amount_gt'] = (float) $arr['amount_gt'];
        }
        if (isset($arr['discount_percent_gt']) && $arr['discount_percent_gt'] !== '') {
            $clean['discount_percent_gt'] = (float) $arr['discount_percent_gt'];
        }
        if (isset($arr['client_id']) && $arr['client_id'] !== '') {
            $clean['client_id'] = (int) $arr['client_id'];
        }
        if (isset($arr['staff_id']) && $arr['staff_id'] !== '') {
            $clean['staff_id'] = (int) $arr['staff_id'];
        }
        if (isset($arr['status']) && $arr['status'] !== '') {
            $clean['status'] = (string) $arr['status'];
        }
        if (isset($arr['contract_type_id']) && $arr['contract_type_id'] !== '') {
            $clean['contract_type_id'] = (int) $arr['contract_type_id'];
        }
        if (isset($arr['expense_category_id']) && $arr['expense_category_id'] !== '') {
            $clean['expense_category_id'] = (int) $arr['expense_category_id'];
        }
        return $clean;
    }

    private function decode_conditions($json)
    {
        if (empty($json)) return [];
        $arr = json_decode($json, true);
        if (!is_array($arr) || json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        return $arr;
    }

    /* ════════════════════════════════════════════════════════════════
     * 3. REQUESTS — listings + lifecycle
     * One request per (entity_type, entity_id). Status transitions are
     * always recorded in _history with the actor + comment.
     * ════════════════════════════════════════════════════════════════ */

    public function get_request($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->select('r.*, s_appr.firstname as approver_fn, s_appr.lastname as approver_ln, s_appr.email as approver_email,'
                . ' s_req.firstname as requester_fn, s_req.lastname as requester_ln, s_req.email as requester_email,'
                . ' rule.name as rule_name, rule.active as rule_active')
            ->from(db_prefix() . 'approvalflow_requests r')
            ->join(db_prefix() . 'staff s_appr', 's_appr.staffid = r.approver_staff_id', 'left')
            ->join(db_prefix() . 'staff s_req',  's_req.staffid = r.requester_staff_id',  'left')
            ->join(db_prefix() . 'approvalflow_rules rule', 'rule.id = r.rule_id', 'left')
            ->where('r.id', $id)
            ->get()->row_array();
        if ($row) {
            $row['entity_snapshot_array'] = $row['entity_snapshot']
                ? json_decode($row['entity_snapshot'], true)
                : [];
        }
        return $row;
    }

    /**
     * Count pending requests matching the same scope + filters as get_pending().
     * Used to compute total pages for server-side pagination. Cheap COUNT(*)
     * on idx_approver_status — runs in <5ms on 100k rows.
     */
    public function count_pending($filters = [], $only_for_staff = null)
    {
        $this->db->from(db_prefix() . 'approvalflow_requests r')
            ->where('r.status', 'pending');

        if ($only_for_staff !== null) {
            $this->db->where('r.approver_staff_id', (int) $only_for_staff);
        }
        if (!empty($filters['entity_type']) && in_array($filters['entity_type'], $this->entity_types, true)) {
            $this->db->where('r.entity_type', $filters['entity_type']);
        }
        if (!empty($filters['requester_staff_id'])) {
            $this->db->where('r.requester_staff_id', (int) $filters['requester_staff_id']);
        }
        return (int) $this->db->count_all_results();
    }

    /**
     * The pending list query. Filters honor the staff scope (own vs all)
     * + UI filters (entity_type, requester). Uses idx_approver_status.
     */
    public function get_pending($filters = [], $only_for_staff = null, $limit = 100, $offset = 0)
    {
        $this->db->select('r.*, s_appr.firstname as approver_fn, s_appr.lastname as approver_ln,'
                . ' s_req.firstname as requester_fn, s_req.lastname as requester_ln,'
                . ' rule.name as rule_name, rule.active as rule_active')
            ->from(db_prefix() . 'approvalflow_requests r')
            ->join(db_prefix() . 'staff s_appr', 's_appr.staffid = r.approver_staff_id', 'left')
            ->join(db_prefix() . 'staff s_req',  's_req.staffid = r.requester_staff_id',  'left')
            ->join(db_prefix() . 'approvalflow_rules rule', 'rule.id = r.rule_id', 'left')
            ->where('r.status', 'pending');

        if ($only_for_staff !== null) {
            $this->db->where('r.approver_staff_id', (int) $only_for_staff);
        }
        if (!empty($filters['entity_type']) && in_array($filters['entity_type'], $this->entity_types, true)) {
            $this->db->where('r.entity_type', $filters['entity_type']);
        }
        if (!empty($filters['requester_staff_id'])) {
            $this->db->where('r.requester_staff_id', (int) $filters['requester_staff_id']);
        }

        $rows = $this->db->order_by('r.created_at', 'DESC')
            ->limit((int) $limit, (int) $offset)
            ->get()->result_array();

        foreach ($rows as &$row) {
            $row['entity_snapshot_array'] = $row['entity_snapshot']
                ? json_decode($row['entity_snapshot'], true)
                : [];
        }
        return $rows;
    }

    /** Used by the banner renderer in the manifest. */
    public function get_latest_for_entity($entity_type, $entity_id)
    {
        $row = $this->db->select('r.*, s_appr.firstname as approver_fn, s_appr.lastname as approver_ln,'
                . ' rule.name as rule_name')
            ->from(db_prefix() . 'approvalflow_requests r')
            ->join(db_prefix() . 'staff s_appr', 's_appr.staffid = r.approver_staff_id', 'left')
            ->join(db_prefix() . 'approvalflow_rules rule', 'rule.id = r.rule_id', 'left')
            ->where('r.entity_type', $entity_type)
            ->where('r.entity_id', (int) $entity_id)
            ->order_by('r.created_at', 'DESC')
            ->limit(1)
            ->get()->row_array();
        return $row ?: null;
    }

    /**
     * All approval requests for an entity, ordered by level ASC.
     * Used by the banner renderer to show multi-level progress.
     * Returns empty array when no requests exist.
     */
    public function get_all_for_entity($entity_type, $entity_id)
    {
        return $this->db->select('r.*, s_appr.firstname as approver_fn, s_appr.lastname as approver_ln,'
                . ' rule.name as rule_name')
            ->from(db_prefix() . 'approvalflow_requests r')
            ->join(db_prefix() . 'staff s_appr', 's_appr.staffid = r.approver_staff_id', 'left')
            ->join(db_prefix() . 'approvalflow_rules rule', 'rule.id = r.rule_id', 'left')
            ->where('r.entity_type', $entity_type)
            ->where('r.entity_id', (int) $entity_id)
            ->order_by('r.level', 'ASC')
            ->get()->result_array();
    }

    /**
     * Approve a single pending request. Idempotent: returns false with a
     * specific message when the request was already resolved by someone
     * else (race condition guard via SELECT + transactional update).
     */
    public function approve_request($id, $comment = '', $actor_staff_id = 0)
    {
        $id = (int) $id;
        $actor_staff_id = (int) ($actor_staff_id ?: get_staff_user_id());

        $this->db->trans_start();

        $current = $this->db->where('id', $id)
            ->get(db_prefix() . 'approvalflow_requests')->row_array();
        if (!$current || $current['status'] !== 'pending') {
            $this->db->trans_complete();
            return ['success' => false, 'reason' => 'not_pending', 'request' => $current];
        }

        $this->db->where('id', $id)
            ->where('status', 'pending')
            ->update(db_prefix() . 'approvalflow_requests', [
                'status'             => 'approved',
                'resolved_by'        => $actor_staff_id,
                'resolved_at'        => date('Y-m-d H:i:s'),
                'resolution_comment' => $comment ?: null,
            ]);

        $this->add_history($id, 'approved', $actor_staff_id, 'pending', 'approved', $comment);

        // Multi-level: activate the next waiting level for this entity, if any
        $current_level = (int) ($current['level'] ?? 1);
        $next = $this->db->where('entity_type', $current['entity_type'])
            ->where('entity_id', (int) $current['entity_id'])
            ->where('status', 'waiting')
            ->where('level >', $current_level)
            ->order_by('level', 'ASC')
            ->limit(1)
            ->get(db_prefix() . 'approvalflow_requests')->row_array();

        $next_activated_id = 0;
        if ($next) {
            $this->db->where('id', (int) $next['id'])
                ->update(db_prefix() . 'approvalflow_requests', ['status' => 'pending']);
            $this->add_history((int) $next['id'], 'level_activated', 0, 'waiting', 'pending',
                'Level ' . (int) $next['level'] . ' activated after Level ' . $current_level . ' approved');
            $next_activated_id = (int) $next['id'];
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'reason' => 'db_error'];
        }

        // Notify level approver if next level was activated
        if ($next_activated_id > 0) {
            $this->notify_approver($next_activated_id);
        }

        // Notify requester only when all levels are done (no more waiting)
        $still_waiting = $this->db->where('entity_type', $current['entity_type'])
            ->where('entity_id', (int) $current['entity_id'])
            ->where_in('status', ['pending', 'waiting'])
            ->count_all_results(db_prefix() . 'approvalflow_requests');

        if ($still_waiting === 0) {
            $this->notify_requester($id, 'approved', $comment);
        }

        return ['success' => true, 'request_id' => $id, 'new_status' => 'approved', 'next_level' => $next_activated_id > 0];
    }

    /**
     * Reject a single pending request. Comment validation lives in the
     * controller (it depends on the require_reject_comment setting).
     */
    public function reject_request($id, $comment, $actor_staff_id = 0)
    {
        $id = (int) $id;
        $actor_staff_id = (int) ($actor_staff_id ?: get_staff_user_id());

        $this->db->trans_start();

        $current = $this->db->where('id', $id)
            ->get(db_prefix() . 'approvalflow_requests')->row_array();
        if (!$current || $current['status'] !== 'pending') {
            $this->db->trans_complete();
            return ['success' => false, 'reason' => 'not_pending', 'request' => $current];
        }

        $this->db->where('id', $id)
            ->where('status', 'pending')
            ->update(db_prefix() . 'approvalflow_requests', [
                'status'             => 'rejected',
                'resolved_by'        => $actor_staff_id,
                'resolved_at'        => date('Y-m-d H:i:s'),
                'resolution_comment' => $comment,
            ]);

        $this->add_history($id, 'rejected', $actor_staff_id, 'pending', 'rejected', $comment);

        // Multi-level cascade: cancel all waiting levels for the same entity
        $current_level = (int) ($current['level'] ?? 1);
        $waiting = $this->db->where('entity_type', $current['entity_type'])
            ->where('entity_id', (int) $current['entity_id'])
            ->where('status', 'waiting')
            ->get(db_prefix() . 'approvalflow_requests')->result_array();

        foreach ($waiting as $w) {
            $this->db->where('id', (int) $w['id'])
                ->update(db_prefix() . 'approvalflow_requests', [
                    'status'             => 'cancelled',
                    'resolved_at'        => date('Y-m-d H:i:s'),
                    'resolution_comment' => 'Cascade: Level ' . $current_level . ' was rejected',
                ]);
            $this->add_history((int) $w['id'], 'cancelled', $actor_staff_id, 'waiting', 'cancelled',
                'Cascade: Level ' . $current_level . ' was rejected');
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'reason' => 'db_error'];
        }

        $this->notify_requester($id, 'rejected', $comment);

        return ['success' => true, 'request_id' => $id, 'new_status' => 'rejected'];
    }

    /**
     * Bulk approve N requests. Best-effort: each ID is processed
     * independently. Ownership is verified per request (admins bypass).
     * Returns aggregate counters for the toast + audit summary.
     */
    public function bulk_approve($ids, $comment = '', $actor_staff_id = 0, $is_admin_flag = false)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), function ($v) {
            return $v > 0;
        })));
        if (empty($ids)) {
            return ['success' => false, 'processed_count' => 0, 'skipped_count' => 0, 'failed_ids' => []];
        }
        $ids = array_slice($ids, 0, 100); // hard cap

        $actor_staff_id = (int) ($actor_staff_id ?: get_staff_user_id());
        $processed = 0;
        $skipped = 0;
        $failed_ids = [];

        foreach ($ids as $id) {
            // Per-request ownership check
            $row = $this->db->select('approver_staff_id, status')
                ->where('id', $id)
                ->get(db_prefix() . 'approvalflow_requests')->row_array();
            if (!$row || $row['status'] !== 'pending') {
                $skipped++;
                continue;
            }
            if (!$is_admin_flag && (int) $row['approver_staff_id'] !== $actor_staff_id) {
                $skipped++;
                continue;
            }
            $res = $this->approve_request($id, $comment, $actor_staff_id);
            if (!empty($res['success'])) {
                $processed++;
            } else {
                $failed_ids[] = $id;
            }
        }

        return [
            'success'         => $processed > 0,
            'processed_count' => $processed,
            'skipped_count'   => $skipped,
            'failed_ids'      => $failed_ids,
        ];
    }

    /**
     * Cancel any pending request bound to a given entity. Called by
     * before_*_deleted listeners (future expansion) or via lazy cleanup.
     */
    public function cancel_pending_for_entity($entity_type, $entity_id, $reason = 'Document deleted')
    {
        $rows = $this->db->where('entity_type', $entity_type)
            ->where('entity_id', (int) $entity_id)
            ->where_in('status', ['pending', 'waiting'])
            ->get(db_prefix() . 'approvalflow_requests')->result_array();
        if (empty($rows)) {
            return false;
        }
        foreach ($rows as $row) {
            $prev = $row['status'];
            $this->db->where('id', $row['id'])
                ->update(db_prefix() . 'approvalflow_requests', [
                    'status'             => 'cancelled',
                    'resolved_at'        => date('Y-m-d H:i:s'),
                    'resolution_comment' => $reason,
                ]);
            $this->add_history((int) $row['id'], 'cancelled', 0, $prev, 'cancelled', $reason);
        }
        return true;
    }

    /**
     * Staff deletion handler — reassigns the deleted approver's pending
     * approvals to $transfer_to (if Perfex provided one), otherwise marks
     * them cancelled so the queue never points at a missing staff row.
     */
    public function handle_staff_deleted($deleted_staff_id, $transfer_to = 0)
    {
        $deleted_staff_id = (int) $deleted_staff_id;
        $transfer_to      = (int) $transfer_to;

        // 1. Rules — reassign or deactivate (level 1 approver)
        if ($transfer_to > 0) {
            $this->db->where('approver_staff_id', $deleted_staff_id)
                ->update(db_prefix() . 'approvalflow_rules', [
                    'approver_staff_id' => $transfer_to,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            // Also reassign in rule_levels (level 2+)
            $this->db->where('approver_staff_id', $deleted_staff_id)
                ->update(db_prefix() . 'approvalflow_rule_levels', [
                    'approver_staff_id' => $transfer_to,
                ]);
        } else {
            $this->db->where('approver_staff_id', $deleted_staff_id)
                ->update(db_prefix() . 'approvalflow_rules', [
                    'active'     => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            // Remove orphaned entries from rule_levels
            $this->db->where('approver_staff_id', $deleted_staff_id)
                ->delete(db_prefix() . 'approvalflow_rule_levels');
        }

        // 2. Pending requests
        $pending = $this->db->where('approver_staff_id', $deleted_staff_id)
            ->where('status', 'pending')
            ->get(db_prefix() . 'approvalflow_requests')->result_array();

        foreach ($pending as $r) {
            if ($transfer_to > 0) {
                $this->db->where('id', $r['id'])
                    ->update(db_prefix() . 'approvalflow_requests', [
                        'approver_staff_id' => $transfer_to,
                    ]);
                $this->add_history((int) $r['id'], 'reassigned', 0, 'pending', 'pending',
                    'Original approver deleted, transferred to staff #' . $transfer_to);
            } else {
                $this->db->where('id', $r['id'])
                    ->update(db_prefix() . 'approvalflow_requests', [
                        'status'             => 'cancelled',
                        'resolved_at'        => date('Y-m-d H:i:s'),
                        'resolution_comment' => 'Approver deleted, no transfer target',
                    ]);
                $this->add_history((int) $r['id'], 'cancelled', 0, 'pending', 'cancelled',
                    'Approver deleted, no transfer target');
            }
        }
        return true;
    }

    /* ════════════════════════════════════════════════════════════════
     * 4. ENGINE — rule matching + condition evaluation
     * Invoked by the after_*_added listeners through the manifest.
     * ════════════════════════════════════════════════════════════════ */

    /**
     * Evaluate a freshly-added document against active rules and create
     * a request if one matches.
     */
    public function evaluate($entity_type, $entity_id)
    {
        if (!in_array($entity_type, $this->entity_types, true)) {
            return false;
        }
        $entity_id = (int) $entity_id;
        if ($entity_id <= 0) {
            return false;
        }

        // 1. Type enabled in settings?
        if ((int) get_option('approvalflow_enable_' . $entity_type . 's') !== 1) {
            $this->log_event('rule_skipped_type_disabled', $entity_type, $entity_id);
            return false;
        }

        // 2. Already evaluated? (check level 1 for uniqueness)
        $exists = $this->db->where('entity_type', $entity_type)
            ->where('entity_id', $entity_id)
            ->where('level', 1)
            ->count_all_results(db_prefix() . 'approvalflow_requests');
        if ($exists > 0) {
            $this->log_event('duplicate_request_prevented', $entity_type, $entity_id);
            return false;
        }

        // 3. Fetch entity data
        $data = $this->fetch_entity_data($entity_type, $entity_id);
        if (!$data) {
            $this->log_event('entity_not_found', $entity_type, $entity_id, null, 'warn');
            return false;
        }

        // 4. Iterate active rules — first match wins
        $rules = $this->db->where('entity_type', $entity_type)
            ->where('active', 1)
            ->order_by('priority', 'ASC')
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'approvalflow_rules')->result_array();

        $matched = null;
        foreach ($rules as $rule) {
            $conds = $this->decode_conditions($rule['conditions']);
            if ($this->evaluate_conditions($conds, $data, $entity_type)) {
                $matched = $rule;
                break;
            }
        }

        if (!$matched) {
            $this->log_event('no_rule_matched', $entity_type, $entity_id);
            return false;
        }

        // 5. Compute auto-approval branches
        $requester = (int) ($data['addedfrom'] ?? 0);
        $auto_admin = (int) get_option('approvalflow_admin_auto_approve') === 1;

        $initial_status   = 'pending';
        $initial_comment  = null;
        $initial_resolver = 0;

        if ($requester > 0 && $requester === (int) $matched['approver_staff_id']) {
            $initial_status   = 'approved';
            $initial_comment  = 'Self-creation, no review needed';
            $initial_resolver = $requester;
        } elseif ($auto_admin && $requester > 0 && function_exists('is_admin') && is_admin($requester)) {
            $initial_status   = 'approved';
            $initial_comment  = 'Auto-approved (admin)';
            $initial_resolver = $requester;
        }

        // 6. Load extra levels for this rule (level 2+)
        $extra_levels = $this->get_rule_levels((int) $matched['id']);
        $total_levels = 1 + count($extra_levels);

        // 7. Create the request + initial history
        $snapshot = $this->build_entity_snapshot($entity_type, $data);
        $snapshot_json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $payload = [
            'rule_id'             => (int) $matched['id'],
            'level'               => 1,
            'total_levels'        => $total_levels,
            'entity_type'         => $entity_type,
            'entity_id'           => $entity_id,
            'entity_snapshot'     => $snapshot_json,
            'requester_staff_id'  => $requester,
            'approver_staff_id'   => (int) $matched['approver_staff_id'],
            'status'              => $initial_status,
            'resolved_by'         => $initial_resolver,
            'resolved_at'         => $initial_status !== 'pending' ? date('Y-m-d H:i:s') : null,
            'resolution_comment'  => $initial_comment,
            'created_at'          => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'approvalflow_requests', $payload);
        $request_id = (int) $this->db->insert_id();
        if ($request_id <= 0) {
            $this->log_event('insert_failed', $entity_type, $entity_id, (int) $matched['id'], 'error');
            return false;
        }

        // History: creation entry, plus implicit resolution if auto-approved
        $this->add_history($request_id, 'created', $requester, null, $initial_status, null);
        if ($initial_status === 'approved' && $initial_resolver > 0) {
            $this->add_history($request_id, 'approved', $initial_resolver, 'pending', 'approved', $initial_comment);
        }
        $this->log_event('rule_matched', $entity_type, $entity_id, (int) $matched['id']);

        // Create waiting rows for levels 2+ (only when level 1 is pending or auto-approved)
        // If auto-approved at level 1, activate level 2 immediately instead.
        foreach ($extra_levels as $lvl) {
            $extra_status = ($initial_status === 'approved') ? 'pending' : 'waiting';
            $this->db->insert(db_prefix() . 'approvalflow_requests', [
                'rule_id'            => (int) $matched['id'],
                'level'              => (int) $lvl['level'],
                'total_levels'       => $total_levels,
                'entity_type'        => $entity_type,
                'entity_id'          => $entity_id,
                'entity_snapshot'    => $snapshot_json,
                'requester_staff_id' => $requester,
                'approver_staff_id'  => (int) $lvl['approver_staff_id'],
                'status'             => $extra_status,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $extra_id = (int) $this->db->insert_id();
            if ($extra_id > 0) {
                $this->add_history($extra_id, 'created', $requester, null, $extra_status, null);
                // If auto-approve cascaded to this level, notify immediately
                if ($extra_status === 'pending') {
                    $this->notify_approver($extra_id);
                }
            }
            // Only activate next level if we've auto-approved the previous; stop after first pending
            if ($extra_status === 'pending') {
                break;
            }
        }

        // 8. Notifications + alert (only if level 1 is still pending — auto-approval skips)
        if ($initial_status === 'pending') {
            $this->notify_approver($request_id);
            $this->emit_set_alert_for_requester($request_id, $entity_type, $entity_id, $matched);
        }

        return $request_id;
    }

    /** Apply AND-logic across the conditions defined in the rule. */
    private function evaluate_conditions($conditions, $entity_data, $entity_type)
    {
        if (!is_array($conditions) || empty($conditions)) {
            return true; // empty conditions = match-all (warned in UI)
        }
        if (isset($conditions['amount_gt'])) {
            $total = (float) ($entity_data['total'] ?? 0);
            if (!($total > (float) $conditions['amount_gt'])) return false;
        }
        if (isset($conditions['discount_percent_gt'])) {
            $pct = $this->compute_discount_percent($entity_data);
            if (!($pct > (float) $conditions['discount_percent_gt'])) return false;
        }
        if (isset($conditions['client_id'])) {
            if ((int) ($entity_data['clientid'] ?? 0) !== (int) $conditions['client_id']) return false;
        }
        if (isset($conditions['staff_id'])) {
            if ((int) ($entity_data['addedfrom'] ?? 0) !== (int) $conditions['staff_id']) return false;
        }
        if (isset($conditions['status'])) {
            // Only applies to entities with a status column
            if (in_array($entity_type, ['invoice', 'estimate', 'proposal', 'contract'], true)) {
                if ((string) ($entity_data['status'] ?? '') !== (string) $conditions['status']) return false;
            }
        }
        if (isset($conditions['contract_type_id']) && $entity_type === 'contract') {
            if ((int) ($entity_data['contract_type'] ?? 0) !== (int) $conditions['contract_type_id']) return false;
        }
        if (isset($conditions['expense_category_id']) && $entity_type === 'expense') {
            if ((int) ($entity_data['category'] ?? 0) !== (int) $conditions['expense_category_id']) return false;
        }
        return true;
    }

    private function compute_discount_percent($entity)
    {
        $disc = (float) ($entity['discount_total'] ?? 0);
        if ($disc <= 0) return 0.0;
        $type = (string) ($entity['discount_type'] ?? '');
        if ($type === 'percent' || $type === 'before_tax' || $type === 'after_tax') {
            // Perfex stores discount_total as a percentage when discount_type is percent.
            // For before_tax / after_tax we approximate against subtotal.
            $subtotal = (float) ($entity['subtotal'] ?? ($entity['total'] ?? 0));
            if ($type === 'percent' || ($subtotal > 0 && $disc <= 100 && $entity['discount_type'] === 'percent')) {
                return $disc;
            }
            if ($subtotal > 0) {
                return ($disc / $subtotal) * 100;
            }
        }
        // Fixed amount fallback
        $subtotal = (float) ($entity['subtotal'] ?? ($entity['total'] ?? 0));
        if ($subtotal > 0) {
            return ($disc / $subtotal) * 100;
        }
        return 0.0;
    }

    /* ════════════════════════════════════════════════════════════════
     * 5. ENTITY DATA — fetch + snapshot
     * Read-only access to Perfex core tables. We never write to them.
     * ════════════════════════════════════════════════════════════════ */

    public function fetch_entity_data($entity_type, $entity_id)
    {
        $entity_id = (int) $entity_id;
        switch ($entity_type) {
            case 'invoice':
                return $this->db->where('id', $entity_id)
                    ->get(db_prefix() . 'invoices')->row_array();
            case 'estimate':
                return $this->db->where('id', $entity_id)
                    ->get(db_prefix() . 'estimates')->row_array();
            case 'proposal':
                return $this->db->where('id', $entity_id)
                    ->get(db_prefix() . 'proposals')->row_array();
            case 'contract':
                return $this->db->where('id', $entity_id)
                    ->get(db_prefix() . 'contracts')->row_array();
            case 'expense':
                return $this->db->where('id', $entity_id)
                    ->get(db_prefix() . 'expenses')->row_array();
        }
        return null;
    }

    public function entity_exists($entity_type, $entity_id)
    {
        $table_map = [
            'invoice'  => 'invoices',
            'estimate' => 'estimates',
            'proposal' => 'proposals',
            'contract' => 'contracts',
            'expense'  => 'expenses',
        ];
        if (!isset($table_map[$entity_type])) {
            return false;
        }
        return $this->db->where('id', (int) $entity_id)
            ->count_all_results(db_prefix() . $table_map[$entity_type]) > 0;
    }

    /** Whitelist snapshot fields by entity type — bounded payload. */
    private function build_entity_snapshot($entity_type, $data)
    {
        if (!is_array($data)) {
            return [];
        }
        $keep_common = ['id', 'clientid', 'addedfrom', 'total', 'subtotal',
            'discount_total', 'discount_type', 'date', 'datecreated', 'status'];
        $snapshot = [];
        foreach ($keep_common as $k) {
            if (array_key_exists($k, $data)) {
                $snapshot[$k] = $data[$k];
            }
        }
        if ($entity_type === 'contract') {
            $snapshot['contract_value'] = $data['contract_value'] ?? null;
            $snapshot['contract_type']  = $data['contract_type']  ?? null;
            $snapshot['subject']        = $data['subject']        ?? null;
            $snapshot['client']         = $data['client']         ?? null;
            // Contract uses contract_value rather than total
            if (isset($data['contract_value']) && !isset($snapshot['total'])) {
                $snapshot['total'] = $data['contract_value'];
            }
            // Client field name differs
            if (isset($data['client']) && !isset($snapshot['clientid'])) {
                $snapshot['clientid'] = $data['client'];
            }
        }
        if ($entity_type === 'expense') {
            $snapshot['category'] = $data['category'] ?? null;
            $snapshot['amount']   = $data['amount']   ?? null;
            if (isset($data['amount']) && !isset($snapshot['total'])) {
                $snapshot['total'] = $data['amount'];
            }
        }
        if ($entity_type === 'proposal') {
            $snapshot['rel_id']   = $data['rel_id']   ?? null;
            $snapshot['rel_type'] = $data['rel_type'] ?? null;
        }
        return $snapshot;
    }

    /** Deep link to the native Perfex view for an entity. */
    public function entity_admin_url($entity_type, $entity_id)
    {
        $id = (int) $entity_id;
        switch ($entity_type) {
            case 'invoice':  return admin_url('invoices/list_invoices/' . $id);
            case 'estimate': return admin_url('estimates/list_estimates/' . $id);
            case 'proposal': return admin_url('proposals/list_proposals/' . $id);
            case 'contract': return admin_url('contracts/contract/' . $id);
            case 'expense':  return admin_url('expenses/list/' . $id);
        }
        return admin_url('approvalflow');
    }

    /* ════════════════════════════════════════════════════════════════
     * 6. HISTORY
     * Append-only timeline. Reads always ORDER BY created_at ASC so the
     * UI can render the story chronologically.
     * ════════════════════════════════════════════════════════════════ */

    public function add_history($request_id, $action, $actor_staff_id, $prev = null, $new = null, $comment = null)
    {
        $this->db->insert(db_prefix() . 'approvalflow_history', [
            'request_id'      => (int) $request_id,
            'action'          => $action,
            'actor_staff_id'  => (int) $actor_staff_id,
            'previous_status' => $prev,
            'new_status'      => $new,
            'comment'         => $comment,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    public function history_for_request($request_id)
    {
        return $this->db->select('h.*, s.firstname, s.lastname')
            ->from(db_prefix() . 'approvalflow_history h')
            ->join(db_prefix() . 'staff s', 's.staffid = h.actor_staff_id', 'left')
            ->where('h.request_id', (int) $request_id)
            ->order_by('h.created_at', 'ASC')
            ->get()->result_array();
    }

    /**
     * Count history rows matching the same filters + scope as get_history().
     * Used by the controller to compute total pages for the audit timeline
     * paginator. Joins the same tables so filter conditions match exactly.
     */
    public function count_history($filters = [], $only_for_staff = null)
    {
        $valid_actions = ['created', 'approved', 'rejected', 'cancelled', 'reminder_sent', 'viewed_warning', 'reassigned', 'level_activated', 'escalated'];

        $this->db->from(db_prefix() . 'approvalflow_history h')
            ->join(db_prefix() . 'approvalflow_requests r', 'r.id = h.request_id', 'left');

        if (!empty($filters['entity_type']) && in_array($filters['entity_type'], $this->entity_types, true)) {
            $this->db->where('r.entity_type', $filters['entity_type']);
        }
        if (!empty($filters['actor_staff_id'])) {
            $this->db->where('h.actor_staff_id', (int) $filters['actor_staff_id']);
        }
        if (!empty($filters['action']) && in_array($filters['action'], $valid_actions, true)) {
            $this->db->where('h.action', $filters['action']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('h.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('h.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        if ($only_for_staff !== null) {
            $this->db->where('r.approver_staff_id', (int) $only_for_staff);
        }
        return (int) $this->db->count_all_results();
    }

    public function get_history($filters = [], $limit = 100, $offset = 0, $only_for_staff = null)
    {
        $valid_actions = ['created', 'approved', 'rejected', 'cancelled', 'reminder_sent', 'viewed_warning', 'reassigned', 'level_activated', 'escalated'];

        $this->db->select('h.*, r.entity_type, r.entity_id, r.rule_id,'
                . ' s.firstname, s.lastname,'
                . ' rule.name as rule_name')
            ->from(db_prefix() . 'approvalflow_history h')
            ->join(db_prefix() . 'approvalflow_requests r', 'r.id = h.request_id', 'left')
            ->join(db_prefix() . 'staff s', 's.staffid = h.actor_staff_id', 'left')
            ->join(db_prefix() . 'approvalflow_rules rule', 'rule.id = r.rule_id', 'left');

        if (!empty($filters['entity_type']) && in_array($filters['entity_type'], $this->entity_types, true)) {
            $this->db->where('r.entity_type', $filters['entity_type']);
        }
        if (!empty($filters['actor_staff_id'])) {
            $this->db->where('h.actor_staff_id', (int) $filters['actor_staff_id']);
        }
        if (!empty($filters['action']) && in_array($filters['action'], $valid_actions, true)) {
            $this->db->where('h.action', $filters['action']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('h.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('h.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        // IDOR scope guard — staff without view_all sees only their queue.
        if ($only_for_staff !== null) {
            $this->db->where('r.approver_staff_id', (int) $only_for_staff);
        }
        return $this->db->order_by('h.created_at', 'DESC')
            ->limit((int) $limit, (int) $offset)
            ->get()->result_array();
    }

    /* ════════════════════════════════════════════════════════════════
     * 7. LOGS (technical, debug-gated)
     * Writes only when debug=1 OR level=error|warn.
     * ════════════════════════════════════════════════════════════════ */

    public function log_event($event, $entity_type = null, $entity_id = null, $rule_id = null, $level = 'info', $details = null)
    {
        $debug_on = (int) get_option('approvalflow_debug_enabled') === 1;
        if ($level === 'info' && !$debug_on) {
            return; // skip noisy events when debug is off
        }
        $this->db->insert(db_prefix() . 'approvalflow_logs', [
            'event'       => substr((string) $event, 0, 50),
            'entity_type' => $entity_type ? substr((string) $entity_type, 0, 20) : null,
            'entity_id'   => $entity_id !== null ? (int) $entity_id : null,
            'rule_id'     => $rule_id !== null ? (int) $rule_id : null,
            'details'     => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'level'       => in_array($level, ['info', 'warn', 'error'], true) ? $level : 'info',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function purge_old_logs($days)
    {
        $days = (int) $days;
        if ($days <= 0) return 0;
        $cutoff = date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'));
        $this->db->where('created_at <', $cutoff)
            ->delete(db_prefix() . 'approvalflow_logs');
        $purged = (int) $this->db->affected_rows();
        // Same retention applies to the digest delivery log.
        $this->db->where('created_at <', $cutoff)
            ->delete(db_prefix() . 'approvalflow_digest_log');
        return $purged;
    }

    /* ════════════════════════════════════════════════════════════════
     * 8. REMINDERS — on-login sweep + global cron sweep
     * ════════════════════════════════════════════════════════════════ */

    public function sweep_reminders_for_approver($staff_id)
    {
        $days = (int) get_option('approvalflow_reminder_days');
        if ($days <= 0) return 0;
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $rows = $this->db->select('id, entity_type, entity_id, created_at, reminder_sent_at')
            ->where('approver_staff_id', (int) $staff_id)
            ->where('status', 'pending') // only pending — waiting levels don't notify yet
            ->get(db_prefix() . 'approvalflow_requests')->result_array();

        $sent = 0;
        foreach ($rows as $r) {
            $last = $r['reminder_sent_at'] ?: $r['created_at'];
            if ($last > $cutoff) continue;
            $this->send_reminder_for_request((int) $r['id'], (int) $staff_id);
            $sent++;
        }
        return $sent;
    }

    public function sweep_reminders_global()
    {
        $days = (int) get_option('approvalflow_reminder_days');
        if ($days <= 0) return 0;
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $rows = $this->db->select('id, approver_staff_id, entity_type, entity_id, created_at, reminder_sent_at')
            ->where('status', 'pending')
            ->get(db_prefix() . 'approvalflow_requests')->result_array();

        $sent = 0;
        foreach ($rows as $r) {
            $last = $r['reminder_sent_at'] ?: $r['created_at'];
            if ($last > $cutoff) continue;
            $this->send_reminder_for_request((int) $r['id'], (int) $r['approver_staff_id']);
            $sent++;
        }
        return $sent;
    }

    private function send_reminder_for_request($request_id, $approver_staff_id)
    {
        // Update reminder_sent_at first to avoid double-send if hook fires twice
        $this->db->where('id', (int) $request_id)
            ->update(db_prefix() . 'approvalflow_requests', [
                'reminder_sent_at' => date('Y-m-d H:i:s'),
            ]);
        $this->add_history((int) $request_id, 'reminder_sent', 0, 'pending', 'pending', null);

        if ((int) get_option('approvalflow_notifications_internal') === 1) {
            @add_notification([
                'description'      => 'approvalflow_notif_reminder',
                'touserid'         => (int) $approver_staff_id,
                'link'             => 'approvalflow/pending',
                'additional_data'  => serialize([]),
            ]);
        }
    }

    /* ════════════════════════════════════════════════════════════════
     * 9. NOTIFICATIONS (approver + requester) + set_alert flash
     * Every call is wrapped in @ + log fallback because notification
     * failures must NEVER block the native Perfex insert flow.
     * ════════════════════════════════════════════════════════════════ */

    private function notify_approver($request_id)
    {
        $req = $this->get_request($request_id);
        if (!$req) return;

        $internal_on = (int) get_option('approvalflow_notifications_internal') === 1;
        $email_on    = (int) get_option('approvalflow_notifications_email') === 1;

        $entity_label = _l('approvalflow_entity_' . $req['entity_type']);
        $doc_label = $entity_label . ' #' . (int) $req['entity_id'];

        if ($internal_on) {
            $ok = @add_notification([
                'description'      => 'approvalflow_notif_new_request',
                'touserid'         => (int) $req['approver_staff_id'],
                'link'             => 'approvalflow/view_request/' . (int) $request_id,
                'additional_data'  => serialize([$doc_label]),
            ]);
            if (!$ok) {
                $this->log_event('notification_failed', $req['entity_type'], $req['entity_id'], $req['rule_id'], 'warn');
            }
        }

        if ($email_on && !empty($req['approver_email'])) {
            try {
                $this->load->model('emails_model');
                $subject = _l('approvalflow_email_subject_new', $doc_label);
                $body    = $this->email_html(
                    _l('approvalflow_email_body_new', $doc_label),
                    _l('approvalflow_email_cta_open'),
                    admin_url('approvalflow/view_request/' . (int) $request_id)
                );
                $this->emails_model->send_simple_email($req['approver_email'], $subject, $body);
            } catch (Exception $e) {
                $this->log_event('email_failed', $req['entity_type'], $req['entity_id'], $req['rule_id'], 'warn',
                    ['error' => $e->getMessage()]);
            }
        }
    }

    private function notify_requester($request_id, $resolution, $comment = '')
    {
        $req = $this->get_request($request_id);
        if (!$req || (int) $req['requester_staff_id'] <= 0) return;

        $internal_on = (int) get_option('approvalflow_notifications_internal') === 1;
        $email_on    = (int) get_option('approvalflow_notifications_email') === 1;

        $entity_label = _l('approvalflow_entity_' . $req['entity_type']);
        $doc_label = $entity_label . ' #' . (int) $req['entity_id'];
        $notif_key = $resolution === 'approved'
            ? 'approvalflow_notif_approved'
            : 'approvalflow_notif_rejected';

        if ($internal_on) {
            $ok = @add_notification([
                'description'      => $notif_key,
                'touserid'         => (int) $req['requester_staff_id'],
                'link'             => 'approvalflow/view_request/' . (int) $request_id,
                'additional_data'  => serialize([$doc_label]),
            ]);
            if (!$ok) {
                $this->log_event('notification_failed', $req['entity_type'], $req['entity_id'], $req['rule_id'], 'warn');
            }
        }

        if ($email_on && !empty($req['requester_email'])) {
            try {
                $this->load->model('emails_model');
                $subj_key = $resolution === 'approved'
                    ? 'approvalflow_email_subject_approved'
                    : 'approvalflow_email_subject_rejected';
                $body_key = $resolution === 'approved'
                    ? 'approvalflow_email_body_approved'
                    : 'approvalflow_email_body_rejected';
                $actor_name = trim(($req['approver_fn'] ?? '') . ' ' . ($req['approver_ln'] ?? ''));
                if ($actor_name === '') $actor_name = 'System';
                $subject = _l($subj_key, $doc_label);
                $msg     = _l($body_key, [$actor_name, $doc_label]);
                if (!empty($comment)) {
                    $msg .= "\n\n" . _l('approvalflow_request_comment') . ': ' . $comment;
                }
                $body = $this->email_html(
                    $msg,
                    _l('approvalflow_email_cta_open'),
                    admin_url('approvalflow/view_request/' . (int) $request_id)
                );
                $this->emails_model->send_simple_email($req['requester_email'], $subject, $body);
            } catch (Exception $e) {
                $this->log_event('email_failed', $req['entity_type'], $req['entity_id'], $req['rule_id'], 'warn',
                    ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Wrap a plain-text message into a clean, email-client-safe HTML block.
     * Perfex's send_simple_email() concatenates this body between the HTML
     * email header and footer and sends it as HTML — so a bare plain-text
     * body collapses its line breaks and renders unstyled. This produces a
     * readable paragraph (newlines → <br>) plus an optional CTA button that
     * links back into ApprovalFlow. Inline styles only (email-client safe).
     *
     * @param  string $message    plain text (may contain \n)
     * @param  string $cta_label  optional button label
     * @param  string $cta_url    optional button URL
     * @return string  HTML
     */
    private function email_html($message, $cta_label = '', $cta_url = '')
    {
        // Perfex's email header/footer are each a self-contained, centered
        // 600px card and do NOT wrap the body — so a bare body lands left,
        // full-width, misaligned with the logo/footer. We mirror that 600px
        // centered card here so the body sits as one continuous column
        // between the header (rounded top) and footer (rounded bottom).
        $html  = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6f8;">';
        $html .= '<tr><td align="center">';
        $html .= '<table width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;">';
        $html .= '<tr><td align="center" style="padding:30px 30px 34px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;font-size:15px;line-height:1.7;">';
        $html .= '<p style="margin:0 0 22px;">' . nl2br(html_escape($message)) . '</p>';
        if ($cta_label !== '' && $cta_url !== '') {
            $html .= '<a href="' . html_escape($cta_url)
                  . '" style="display:inline-block;background:#0e7490;color:#ffffff;text-decoration:none;'
                  . 'padding:12px 24px;border-radius:6px;font-weight:bold;font-size:14px;">'
                  . html_escape($cta_label) . '</a>';
        }
        $html .= '</td></tr></table>';
        $html .= '</td></tr></table>';
        return $html;
    }

    private function emit_set_alert_for_requester($request_id, $entity_type, $entity_id, $rule)
    {
        $approver = $this->db->select('firstname, lastname')
            ->where('staffid', (int) $rule['approver_staff_id'])
            ->get(db_prefix() . 'staff')->row_array();
        $approver_name = $approver
            ? trim($approver['firstname'] . ' ' . $approver['lastname'])
            : '#' . (int) $rule['approver_staff_id'];
        $msg = _l('approvalflow_warning_pending_approval', [
            _l('approvalflow_entity_' . $entity_type),
            (int) $entity_id,
            $approver_name,
        ]);
        @set_alert('warning', $msg);
    }

    /* ════════════════════════════════════════════════════════════════
     * 10. APPROVAL PULSE (v1.0.2) — risk score, SLA escalation, digest
     * 100% additive. Every entry point is gated by an option that ships
     * OFF, so the module behaves exactly like v1.0.1 until the buyer opts
     * in. No schema is written here at read time (risk is computed on the
     * fly); escalation/digest only run from the existing cron + login
     * sweeps — no new cron job is registered.
     * ════════════════════════════════════════════════════════════════ */

    /**
     * Compute the 0–100 approval risk score for a pending request row.
     * Read-time only — derives everything from data already on the row
     * (entity_snapshot + created_at + level), so legacy 1.0.0/1.0.1 rows
     * score correctly without any backfill (brief rule 4).
     *
     * Weights: amount 40, aging 30, discount 20, level depth 10.
     *
     * @param  array $row  a get_pending() row (with entity_snapshot_array)
     * @return array{score:int,band:string,factors:array}
     */
    public function compute_risk_score(array $row)
    {
        $snap = $row['entity_snapshot_array'] ?? [];

        // Amount (up to 40) — normalized against the configurable ceiling.
        $amount  = isset($snap['total']) ? (float) $snap['total'] : 0;
        $ceiling = (float) get_option('approvalflow_risk_amount_ceiling');
        if ($ceiling <= 0) {
            $ceiling = 10000;
        }
        $amount_pts = (int) round(min(1, $amount / $ceiling) * 40);

        // Aging (up to 30) — against the SLA if set, else a 7-day fallback.
        $sla_hours     = (int) get_option('approvalflow_sla_hours');
        $age_ceiling_h = $sla_hours > 0 ? $sla_hours : 168;
        $waited_h      = max(0, (time() - strtotime($row['created_at'])) / 3600);
        $aging_pts     = (int) round(min(1, $waited_h / $age_ceiling_h) * 30);

        // Discount % (up to 20) — derived from snapshot; 50%+ = full weight.
        $subtotal = isset($snap['subtotal']) ? (float) $snap['subtotal'] : 0;
        $disc     = isset($snap['discount_total']) ? (float) $snap['discount_total'] : 0;
        $disc_pct = $subtotal > 0 ? min(100, ($disc / $subtotal) * 100) : 0;
        $discount_pts = (int) round(min(1, $disc_pct / 50) * 20);

        // Level depth (up to 10) — deeper chains carry more scrutiny.
        $level     = max(1, (int) ($row['level'] ?? 1));
        $total     = max(1, (int) ($row['total_levels'] ?? 1));
        $depth_pts = (int) round(($level / $total) * 10);

        $score = max(0, min(100, $amount_pts + $aging_pts + $discount_pts + $depth_pts));
        $band  = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');

        return [
            'score'   => $score,
            'band'    => $band,
            'factors' => [
                ['label' => _l('approvalflow_risk_factor_amount'),   'points' => $amount_pts],
                ['label' => _l('approvalflow_risk_factor_aging'),    'points' => $aging_pts],
                ['label' => _l('approvalflow_risk_factor_discount'), 'points' => $discount_pts],
                ['label' => _l('approvalflow_risk_factor_depth'),    'points' => $depth_pts],
            ],
        ];
    }

    /**
     * SLA escalation sweep. For every pending request older than the SLA
     * that has not been escalated yet (no 'escalated' history row), notify
     * the configured manager once. Idempotent: the history row itself is
     * the dedup marker, so re-running never double-escalates.
     *
     * No-op unless BOTH approvalflow_sla_hours > 0 AND escalate_to is set.
     *
     * @return int  number of requests escalated this run
     */
    public function sweep_escalations()
    {
        $sla_hours   = (int) get_option('approvalflow_sla_hours');
        $escalate_to = (int) get_option('approvalflow_escalate_to');
        if ($sla_hours <= 0 || $escalate_to <= 0) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $sla_hours . ' hours'));

        $rows = $this->db->select('id')
            ->where('status', 'pending')
            ->where('created_at <', $cutoff)
            ->get(db_prefix() . 'approvalflow_requests')->result_array();

        $escalated = 0;
        foreach ($rows as $r) {
            $already = (int) $this->db->where('request_id', (int) $r['id'])
                ->where('action', 'escalated')
                ->count_all_results(db_prefix() . 'approvalflow_history');
            if ($already > 0) {
                continue;
            }
            $this->escalate_request((int) $r['id'], $escalate_to);
            $escalated++;
        }
        return $escalated;
    }

    /**
     * Escalate one request to the manager: write the history marker first
     * (also the dedup guard), then fire the internal + email notification
     * reusing the same plumbing as approver notifications.
     */
    private function escalate_request($request_id, $manager_id)
    {
        // History first — doubles as the "already escalated" dedup marker.
        $this->add_history((int) $request_id, 'escalated', 0, 'pending', 'pending', null);

        $req = $this->get_request($request_id);
        if (!$req) {
            return;
        }
        $doc_label = _l('approvalflow_entity_' . $req['entity_type']) . ' #' . (int) $req['entity_id'];

        if ((int) get_option('approvalflow_notifications_internal') === 1) {
            @add_notification([
                'description'     => 'approvalflow_notif_escalated',
                'touserid'        => (int) $manager_id,
                'link'            => 'approvalflow/view_request/' . (int) $request_id,
                'additional_data' => serialize([$doc_label]),
            ]);
        }

        if ((int) get_option('approvalflow_notifications_email') === 1) {
            $mgr = $this->db->select('email')->where('staffid', (int) $manager_id)
                ->get(db_prefix() . 'staff')->row_array();
            if (!empty($mgr['email'])) {
                try {
                    $this->load->model('emails_model');
                    $this->emails_model->send_simple_email(
                        $mgr['email'],
                        _l('approvalflow_email_subject_escalated', $doc_label),
                        $this->email_html(
                            _l('approvalflow_email_body_escalated', $doc_label),
                            _l('approvalflow_email_cta_open'),
                            admin_url('approvalflow/view_request/' . (int) $request_id)
                        )
                    );
                } catch (Exception $e) {
                    $this->log_event('escalation_email_failed', $req['entity_type'], $req['entity_id'],
                        $req['rule_id'], 'warn', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Decide whether a digest is due and send it. Called from the cron
     * sweep. Respects frequency (daily/weekly + weekday) and guards against
     * the cron firing multiple times in the same window via the digest log.
     */
    public function maybe_send_digest()
    {
        if ((int) get_option('approvalflow_digest_enabled') !== 1) {
            return;
        }

        $freq = get_option('approvalflow_digest_freq') === 'daily' ? 'daily' : 'weekly';
        $now  = time();

        // Weekly digests only fire on the configured ISO weekday (1=Mon..7=Sun).
        if ($freq === 'weekly') {
            $day = (int) get_option('approvalflow_digest_day');
            if ($day < 1 || $day > 7) {
                $day = 1;
            }
            if ((int) date('N', $now) !== $day) {
                return;
            }
        }

        // Window guard: skip if any digest row was written recently (the cron
        // can run several times a day). Looks at the latest row of any status.
        $last = $this->db->select('created_at')
            ->order_by('id', 'DESC')->limit(1)
            ->get(db_prefix() . 'approvalflow_digest_log')->row_array();
        if (!empty($last['created_at'])) {
            $min_gap = $freq === 'daily' ? 20 * 3600 : 6 * 86400;
            if (($now - strtotime($last['created_at'])) < $min_gap) {
                return;
            }
        }

        $this->send_digest($freq);
    }

    /**
     * Build and send a per-approver digest: each approver with at least one
     * pending request gets a summary (count, overdue count, $ at stake,
     * oldest age). Every run writes one digest_log row for support traceability.
     */
    private function send_digest($freq)
    {
        $email_on       = (int) get_option('approvalflow_notifications_email') === 1;
        $sla_hours      = (int) get_option('approvalflow_sla_hours');
        $overdue_cutoff = $sla_hours > 0 ? strtotime('-' . $sla_hours . ' hours') : null;

        $rows = $this->db->select('r.approver_staff_id, r.created_at, r.entity_snapshot,'
                . ' s.email as approver_email, s.firstname as approver_fn')
            ->from(db_prefix() . 'approvalflow_requests r')
            ->join(db_prefix() . 'staff s', 's.staffid = r.approver_staff_id', 'left')
            ->where('r.status', 'pending')
            ->get()->result_array();

        if (empty($rows)) {
            $this->log_digest($freq, [], 0, 0, 'skipped');
            return;
        }

        // Aggregate per approver.
        $by_approver = [];
        foreach ($rows as $r) {
            $aid = (int) $r['approver_staff_id'];
            if (!isset($by_approver[$aid])) {
                $by_approver[$aid] = [
                    'email'   => $r['approver_email'],
                    'name'    => $r['approver_fn'],
                    'pending' => 0, 'overdue' => 0, 'amount' => 0, 'oldest' => null,
                ];
            }
            $by_approver[$aid]['pending']++;
            $snap = $r['entity_snapshot'] ? json_decode($r['entity_snapshot'], true) : [];
            $by_approver[$aid]['amount'] += isset($snap['total']) ? (float) $snap['total'] : 0;
            $ts = strtotime($r['created_at']);
            if ($by_approver[$aid]['oldest'] === null || $ts < $by_approver[$aid]['oldest']) {
                $by_approver[$aid]['oldest'] = $ts;
            }
            if ($overdue_cutoff !== null && $ts < $overdue_cutoff) {
                $by_approver[$aid]['overdue']++;
            }
        }

        $recipients    = [];
        $total_pending = 0;
        $total_overdue = 0;
        foreach ($by_approver as $d) {
            $total_pending += $d['pending'];
            $total_overdue += $d['overdue'];
            if (!$email_on || empty($d['email'])) {
                continue;
            }
            $oldest_days = $d['oldest'] ? max(0, (int) floor((time() - $d['oldest']) / 86400)) : 0;
            try {
                $this->load->model('emails_model');
                $this->emails_model->send_simple_email(
                    $d['email'],
                    _l('approvalflow_digest_email_subject'),
                    $this->email_html(
                        _l('approvalflow_digest_email_body', [
                            $d['name'] ?: '',
                            (int) $d['pending'],
                            (int) $d['overdue'],
                            app_format_money($d['amount'], ''),
                            (int) $oldest_days,
                        ]),
                        _l('approvalflow_email_cta_open'),
                        admin_url('approvalflow/pending')
                    )
                );
                $recipients[] = $d['email'];
            } catch (Exception $e) {
                $this->log_event('digest_email_failed', null, null, null, 'warn', ['error' => $e->getMessage()]);
            }
        }

        $status = empty($recipients) ? 'skipped' : 'sent';
        $this->log_digest($freq, $recipients, $total_pending, $total_overdue, $status);
    }

    /** Insert one delivery record into the digest log (support trail). */
    private function log_digest($freq, array $recipients, $pending, $overdue, $status)
    {
        $this->db->insert(db_prefix() . 'approvalflow_digest_log', [
            'sent_at'       => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            'freq'          => $freq === 'daily' ? 'daily' : 'weekly',
            'recipients'    => $recipients ? implode(', ', $recipients) : null,
            'pending_count' => (int) $pending,
            'overdue_count' => (int) $overdue,
            'status'        => in_array($status, ['sent', 'failed', 'skipped'], true) ? $status : 'sent',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
