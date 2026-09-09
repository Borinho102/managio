<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Main Controller
 *
 * Hosts every admin URL of the module. Sections:
 *   - Dashboard (read-only KPIs)
 *   - Pending list (with bulk approve + AJAX inline actions)
 *   - Rules CRUD (list + form + toggle + delete)
 *   - History (filtered audit timeline)
 *   - Settings (renders inside Integrations tab)
 *   - Request detail (view modal partial via AJAX)
 *
 * Every endpoint validates capability via staff_cant() and CSRF via
 * CodeIgniter's built-in token. AJAX endpoints respond JSON; non-AJAX
 * fallbacks redirect with set_alert() (graceful degradation).
 *
 * @package ApprovalFlow
 */
class Approvalflow extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('approvalflow/approvalflow_model');
    }

    /* ════════════════════════════════════════════════════════════════
     * 1. DASHBOARD
     * 6 KPIs + recent pending + recent activity. Scope honors view_all.
     * ════════════════════════════════════════════════════════════════ */
    public function index()
    {
        if (staff_cant('view', APPROVALFLOW_MODULE_NAME)) {
            access_denied(APPROVALFLOW_MODULE_NAME);
        }

        $scope = $this->resolve_scope();

        $data['title']        = _l('approvalflow_dashboard');
        $data['kpis']         = $this->approvalflow_model->dashboard_counts($scope);
        $data['recent_pending'] = $this->approvalflow_model->recent_pending(5, $scope);
        $data['recent_activity']= $this->approvalflow_model->recent_activity(8, $scope);
        $data['has_any_rule'] = $this->has_any_rule();
        $data['can_view_all'] = ($scope === null);

        $this->push_assets();
        $this->load->view('approvalflow/dashboard', $data);
    }

    /* ════════════════════════════════════════════════════════════════
     * 2. PENDING LIST
     * Filterable list with AJAX approve / reject / bulk approve.
     * ════════════════════════════════════════════════════════════════ */
    public function pending()
    {
        if (staff_cant('view', APPROVALFLOW_MODULE_NAME)) {
            access_denied(APPROVALFLOW_MODULE_NAME);
        }

        $scope = $this->resolve_scope();

        $filters = [
            'entity_type'        => (string) $this->input->get('entity_type'),
            'requester_staff_id' => (int) $this->input->get('requester_staff_id'),
        ];

        // Server-side pagination — scales linearly even with 100k+ rows.
        // 50 rows/page hits a sweet spot: dense enough to make scrolling
        // useful, light enough that the table render stays under 200ms.
        $per_page = 50;
        $page = max(1, (int) $this->input->get('page'));
        $total = $this->approvalflow_model->count_pending($filters, $scope);
        $total_pages = (int) max(1, ceil($total / $per_page));
        if ($page > $total_pages) { $page = $total_pages; }
        $offset = ($page - 1) * $per_page;

        $data['title']    = _l('approvalflow_pending');
        $data['filters']  = $filters;
        $data['requests'] = $this->approvalflow_model->get_pending($filters, $scope, $per_page, $offset);
        $data['pager'] = [
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'base_url'    => admin_url('approvalflow/pending'),
            'query'       => array_filter([
                'entity_type'        => $filters['entity_type'],
                'requester_staff_id' => $filters['requester_staff_id'] ? (int) $filters['requester_staff_id'] : '',
            ], 'strlen'),
        ];
        $data['can_view_all'] = ($scope === null);
        $data['can_approve'] = staff_can('approve', APPROVALFLOW_MODULE_NAME);
        $data['can_reject']  = staff_can('reject',  APPROVALFLOW_MODULE_NAME);
        $data['current_staff_id'] = (int) get_staff_user_id();
        $data['is_admin'] = is_admin();

        // Approval Pulse (v1.0.2) — risk score + SLA overdue, both opt-in.
        // When risk is off, no score is attached and the column is hidden,
        // so the list renders exactly as in v1.0.1.
        $data['risk_enabled'] = (int) get_option('approvalflow_risk_enabled') === 1;
        $data['sla_hours']    = (int) get_option('approvalflow_sla_hours');
        if ($data['risk_enabled']) {
            foreach ($data['requests'] as &$row) {
                $row['risk'] = $this->approvalflow_model->compute_risk_score($row);
            }
            unset($row);
        }

        $this->push_assets();
        $this->load->view('approvalflow/pending/list', $data);
    }

    /**
     * AJAX approve. Detects AJAX request to return JSON; falls back to
     * set_alert + redirect for non-AJAX (graceful degradation).
     */
    public function approve($id = 0)
    {
        if (staff_cant('approve', APPROVALFLOW_MODULE_NAME)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_not_authorized')], 403);
        }
        $id = (int) $id;
        $request = $this->approvalflow_model->get_request($id);
        if (!$request) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_invalid_request')], 404);
        }
        if (!$this->user_can_act_on($request)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_warning_not_your_request')], 403);
        }
        if ($request['status'] !== 'pending') {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_already_processed')], 409);
        }

        $comment = trim((string) $this->input->post('comment'));
        $res = $this->approvalflow_model->approve_request($id, $comment);
        if (empty($res['success'])) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_already_processed')], 409);
        }

        return $this->respond([
            'success'    => true,
            'message'    => _l('approvalflow_approved_successfully'),
            'request_id' => $id,
            'new_status' => 'approved',
            'kpi_updates'=> $this->approvalflow_model->dashboard_counts($this->resolve_scope()),
        ]);
    }

    /**
     * AJAX reject. Comment validation depends on the setting
     * approvalflow_require_reject_comment.
     */
    public function reject($id = 0)
    {
        if (staff_cant('reject', APPROVALFLOW_MODULE_NAME)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_not_authorized')], 403);
        }
        $id = (int) $id;
        $request = $this->approvalflow_model->get_request($id);
        if (!$request) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_invalid_request')], 404);
        }
        if (!$this->user_can_act_on($request)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_warning_not_your_request')], 403);
        }
        if ($request['status'] !== 'pending') {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_already_processed')], 409);
        }

        $comment = trim((string) $this->input->post('comment'));
        $require_comment = (int) get_option('approvalflow_require_reject_comment') === 1;
        if ($require_comment && mb_strlen($comment) < 3) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_comment_required')], 400);
        }

        $res = $this->approvalflow_model->reject_request($id, $comment);
        if (empty($res['success'])) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_already_processed')], 409);
        }

        return $this->respond([
            'success'    => true,
            'message'    => _l('approvalflow_rejected_successfully'),
            'request_id' => $id,
            'new_status' => 'rejected',
            'kpi_updates'=> $this->approvalflow_model->dashboard_counts($this->resolve_scope()),
        ]);
    }

    /**
     * AJAX bulk approve. Best-effort: each ID processed independently.
     * Skips silently any request the user is not entitled to approve.
     */
    public function bulk_approve()
    {
        if (staff_cant('approve', APPROVALFLOW_MODULE_NAME)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_not_authorized')], 403);
        }
        $ids_raw = $this->input->post('ids');
        if (!is_array($ids_raw)) {
            $ids_raw = json_decode((string) $ids_raw, true);
        }
        $ids = is_array($ids_raw) ? $ids_raw : [];
        if (empty($ids)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_no_selection')], 400);
        }
        $comment = trim((string) $this->input->post('comment'));
        $admin_flag = is_admin();
        $result = $this->approvalflow_model->bulk_approve($ids, $comment, (int) get_staff_user_id(), $admin_flag);

        return $this->respond([
            'success'         => true,
            'message'         => _l('approvalflow_bulk_done', [(int) $result['processed_count'], (int) $result['skipped_count']]),
            'processed_count' => (int) $result['processed_count'],
            'skipped_count'   => (int) $result['skipped_count'],
            'failed_ids'      => $result['failed_ids'],
            'kpi_updates'     => $this->approvalflow_model->dashboard_counts($this->resolve_scope()),
        ]);
    }

    /**
     * Modal partial for "View request" without leaving the list. Returns
     * an HTML fragment when AJAX, full page otherwise.
     */
    public function view_request($id = 0)
    {
        if (staff_cant('view', APPROVALFLOW_MODULE_NAME)) {
            access_denied(APPROVALFLOW_MODULE_NAME);
        }
        $id = (int) $id;
        $request = $this->approvalflow_model->get_request($id);
        if (!$request) {
            if ($this->input->is_ajax_request()) {
                $this->output->set_status_header(404);
                echo '<div class="approvalflow-modal-body-empty">' . _l('approvalflow_error_invalid_request') . '</div>';
                return;
            }
            set_alert('warning', _l('approvalflow_error_invalid_request'));
            redirect(admin_url('approvalflow/pending'));
        }

        // IDOR guard — staff without view_all can only see their own
        // requests (as approver) or those they originated (as requester).
        if ($this->resolve_scope() !== null) {
            $current_id = (int) get_staff_user_id();
            $is_approver = ((int) $request['approver_staff_id'] === $current_id);
            $is_requester = ((int) $request['requester_staff_id'] === $current_id);
            if (!$is_approver && !$is_requester) {
                if ($this->input->is_ajax_request()) {
                    $this->output->set_status_header(403);
                    echo '<div class="approvalflow-modal-body-empty">' . _l('approvalflow_error_not_authorized') . '</div>';
                    return;
                }
                access_denied(APPROVALFLOW_MODULE_NAME);
            }
        }

        $data['request']  = $request;
        $data['history']  = $this->approvalflow_model->history_for_request($id);
        $data['doc_url']  = $this->approvalflow_model->entity_admin_url($request['entity_type'], (int) $request['entity_id']);

        if ($this->input->is_ajax_request()) {
            $this->load->view('approvalflow/pending/modal_view', $data);
            return;
        }

        $data['title'] = _l('approvalflow_view_request');
        $this->push_assets();
        $this->load->view('approvalflow/pending/modal_page', $data);
    }

    /* ════════════════════════════════════════════════════════════════
     * 3. RULES — list + form + toggle + delete
     * ════════════════════════════════════════════════════════════════ */
    public function rules()
    {
        if (staff_cant('view', APPROVALFLOW_MODULE_NAME)) {
            access_denied(APPROVALFLOW_MODULE_NAME);
        }

        // Server-side pagination — rules count rarely exceeds 50-100 but
        // we still paginate so the page stays consistent with pending+history
        // and any over-engineered customer with 500+ rules stays performant.
        $per_page = 50;
        $page = max(1, (int) $this->input->get('page'));
        $total = $this->approvalflow_model->count_rules();
        $total_pages = (int) max(1, ceil($total / $per_page));
        if ($page > $total_pages) { $page = $total_pages; }
        $offset = ($page - 1) * $per_page;

        $data['title']        = _l('approvalflow_rules');
        $data['rules']        = $this->approvalflow_model->get_rules('', [], $per_page, $offset);
        $data['pager'] = [
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'base_url'    => admin_url('approvalflow/rules'),
            'query'       => [],
        ];
        $data['can_create']   = staff_can('create_rules', APPROVALFLOW_MODULE_NAME);
        $data['can_edit']     = staff_can('edit_rules',   APPROVALFLOW_MODULE_NAME);
        $data['can_delete']   = staff_can('delete_rules', APPROVALFLOW_MODULE_NAME);

        $this->push_assets();
        $this->load->view('approvalflow/rules/list', $data);
    }

    public function rule_form($id = 0)
    {
        $id = (int) $id;
        $is_edit = $id > 0;
        $cap = $is_edit ? 'edit_rules' : 'create_rules';
        if (staff_cant($cap, APPROVALFLOW_MODULE_NAME)) {
            access_denied(APPROVALFLOW_MODULE_NAME);
        }

        if ($this->input->post()) {
            // extra_levels[] is an array of staff IDs for levels 2, 3, …
            $extra_raw = $this->input->post('extra_levels');
            $extra_levels = [];
            if (is_array($extra_raw)) {
                foreach ($extra_raw as $sid) {
                    $sid = (int) $sid;
                    if ($sid > 0) {
                        $extra_levels[] = $sid;
                    }
                }
            }
            $payload = [
                'name'              => $this->input->post('name'),
                'entity_type'       => $this->input->post('entity_type'),
                'approver_staff_id' => $this->input->post('approver_staff_id'),
                'priority'          => $this->input->post('priority'),
                'active'            => $this->input->post('active') ? 1 : 0,
                'conditions'        => (array) $this->input->post('conditions'),
                'extra_levels'      => $extra_levels,
            ];

            // Validation
            if (empty($payload['name'])) {
                set_alert('danger', _l('approvalflow_error_name_required'));
            } elseif (empty($payload['entity_type'])) {
                set_alert('danger', _l('approvalflow_error_entity_required'));
            } elseif (empty($payload['approver_staff_id'])) {
                set_alert('danger', _l('approvalflow_error_approver_required'));
            } else {
                if ($is_edit) {
                    $ok = $this->approvalflow_model->update_rule($id, $payload);
                    if ($ok) {
                        set_alert('success', _l('approvalflow_rule_updated_successfully'));
                        redirect(admin_url('approvalflow/rules'));
                    } else {
                        set_alert('danger', _l('approvalflow_error_save_failed'));
                    }
                } else {
                    $new_id = $this->approvalflow_model->create_rule($payload);
                    if ($new_id > 0) {
                        set_alert('success', _l('approvalflow_rule_created_successfully'));
                        redirect(admin_url('approvalflow/rules'));
                    } else {
                        set_alert('danger', _l('approvalflow_error_save_failed'));
                    }
                }
            }
        }

        $data['title'] = $is_edit ? _l('approvalflow_edit_rule') : _l('approvalflow_new_rule');
        $data['rule']  = $is_edit ? $this->approvalflow_model->get_rules($id) : null;
        $data['is_edit'] = $is_edit;
        $data['staff_options'] = $this->approver_options();
        // Searchable dropdowns for conditions with FK semantics. Pre-loaded
        // server-side so the form opens without an extra AJAX round-trip;
        // payload size stays manageable for the PYME target (<= ~1000 rows).
        $data['condition_options'] = $this->condition_dropdown_options();

        $this->push_assets();
        $this->load->view('approvalflow/rules/form', $data);
    }

    public function rule_toggle($id = 0)
    {
        if (staff_cant('edit_rules', APPROVALFLOW_MODULE_NAME)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_not_authorized')], 403);
        }
        $new = $this->approvalflow_model->toggle_rule_active((int) $id);
        if ($new === null) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_invalid_rule')], 404);
        }
        return $this->respond([
            'success' => true,
            'active'  => (int) $new,
            'message' => $new ? _l('approvalflow_rule_activated') : _l('approvalflow_rule_deactivated'),
        ]);
    }

    public function rule_delete($id = 0)
    {
        if (staff_cant('delete_rules', APPROVALFLOW_MODULE_NAME)) {
            return $this->respond(['success' => false, 'message' => _l('approvalflow_error_not_authorized')], 403);
        }
        $id = (int) $id;
        $pending_count = $this->approvalflow_model->count_pending_for_rule($id);
        if ($pending_count > 0) {
            // Defensive: deactivate instead of delete when there are pending requests
            $this->approvalflow_model->update_rule($id, ['active' => 0]);
            return $this->respond([
                'success' => true,
                'soft'    => true,
                'message' => _l('approvalflow_rule_in_use_warning'),
            ]);
        }
        $ok = $this->approvalflow_model->delete_rule($id);
        return $this->respond([
            'success' => (bool) $ok,
            'message' => $ok ? _l('approvalflow_rule_deleted_successfully') : _l('approvalflow_error_delete_failed'),
        ]);
    }

    /* ════════════════════════════════════════════════════════════════
     * 4. HISTORY
     * ════════════════════════════════════════════════════════════════ */
    public function history()
    {
        if (staff_cant('view', APPROVALFLOW_MODULE_NAME)) {
            access_denied(APPROVALFLOW_MODULE_NAME);
        }
        $scope = $this->resolve_scope();
        $filters = [
            'entity_type'    => (string) $this->input->get('entity_type'),
            'actor_staff_id' => (int) $this->input->get('actor_staff_id'),
            'action'         => (string) $this->input->get('action'),
            'date_from'      => (string) $this->input->get('date_from'),
            'date_to'        => (string) $this->input->get('date_to'),
        ];

        // Server-side pagination — history is the table most likely to grow
        // (every state transition writes a row), so this is the critical one
        // performance-wise. 50/page is consistent with the other listings.
        $per_page = 50;
        $page = max(1, (int) $this->input->get('page'));
        $total = $this->approvalflow_model->count_history($filters, $scope);
        $total_pages = (int) max(1, ceil($total / $per_page));
        if ($page > $total_pages) { $page = $total_pages; }
        $offset = ($page - 1) * $per_page;

        $data['title']   = _l('approvalflow_history');
        $data['filters'] = $filters;
        $data['history'] = $this->approvalflow_model->get_history($filters, $per_page, $offset, $scope);
        $data['pager'] = [
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'base_url'    => admin_url('approvalflow/history'),
            'query'       => array_filter([
                'entity_type'    => $filters['entity_type'],
                'actor_staff_id' => $filters['actor_staff_id'] ? (int) $filters['actor_staff_id'] : '',
                'action'         => $filters['action'],
                'date_from'      => $filters['date_from'],
                'date_to'        => $filters['date_to'],
            ], 'strlen'),
        ];
        $data['staff_options'] = $this->approver_options();

        $this->push_assets();
        $this->load->view('approvalflow/history/list', $data);
    }

    /* ════════════════════════════════════════════════════════════════
     * 5. SETTINGS — rendered inside Integrations tab
     *
     * Persistence is owned by the core Settings::index() controller:
     * inputs in views/settings/index.php carry name="settings[approvalflow_*]"
     * and the bundled Settings_model::update() iterates over them and
     * calls update_option() for each. We do NOT handle POST here — the
     * module's own form was removed when the production retest showed
     * HTML5 form-nesting was silently stripping it.
     *
     * This action only exists so a direct hit to `/admin/approvalflow/
     * settings` (e.g. a stale bookmark) lands on the right Settings tab
     * instead of rendering the raw view without its parent wrapper.
     * ════════════════════════════════════════════════════════════════ */
    public function settings()
    {
        if (staff_cant('settings', APPROVALFLOW_MODULE_NAME)) {
            access_denied(APPROVALFLOW_MODULE_NAME);
        }
        redirect(admin_url('settings?group=approvalflow'));
    }

    /* ════════════════════════════════════════════════════════════════
     * Helpers
     * ════════════════════════════════════════════════════════════════ */

    /** Push css/js assets used across the module. */
    private function push_assets()
    {
        $this->app_css->add('approvalflow-css', approvalflow_asset_url('assets/css/approvalflow.css'));
        $this->app_scripts->add('approvalflow-js', approvalflow_asset_url('assets/js/approvalflow.js'));
    }

    /**
     * Returns NULL when the current staff has view_all (or is admin),
     * meaning "no scope restriction". Returns their staff_id otherwise
     * so listings auto-filter to their own assigned approvals.
     */
    private function resolve_scope()
    {
        if (is_admin() || staff_can('view_all', APPROVALFLOW_MODULE_NAME)) {
            return null;
        }
        return (int) get_staff_user_id();
    }

    /** Ownership check: admin or assigned approver. */
    private function user_can_act_on($request)
    {
        if (is_admin()) return true;
        return (int) $request['approver_staff_id'] === (int) get_staff_user_id();
    }

    /** Build the staff dropdown options — only active staff. */
    private function approver_options()
    {
        return $this->db->select('staffid, firstname, lastname, email')
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->order_by('firstname', 'ASC')
            ->get()->result_array();
    }

    /**
     * Pre-loaded options for the four FK-flavoured conditions
     * (client_id, staff_id, contract_type_id, expense_category_id).
     * Rendered as searchable Bootstrap-Select dropdowns instead of raw
     * numeric inputs so admins never have to copy IDs from URLs.
     *
     * Returned shape: ['client_id' => [['id'=>1,'label'=>'ACME Inc.'], ...], ...]
     */
    private function condition_dropdown_options()
    {
        $out = [
            'client_id'           => [],
            'staff_id'            => [],
            'contract_type_id'    => [],
            'expense_category_id' => [],
        ];

        // Active clients only — soft-deleted (active=0) hidden by design
        $rows = $this->db->select('userid, company')
            ->from(db_prefix() . 'clients')
            ->where('active', 1)
            ->order_by('company', 'ASC')
            ->get()->result_array();
        foreach ($rows as $r) {
            $out['client_id'][] = [
                'id'    => (int) $r['userid'],
                'label' => (string) $r['company'],
            ];
        }

        // Active staff only — same predicate as approver dropdown
        $rows = $this->db->select('staffid, firstname, lastname')
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->order_by('firstname', 'ASC')
            ->get()->result_array();
        foreach ($rows as $r) {
            $name = trim($r['firstname'] . ' ' . $r['lastname']);
            $out['staff_id'][] = [
                'id'    => (int) $r['staffid'],
                'label' => $name !== '' ? $name : ('#' . (int) $r['staffid']),
            ];
        }

        // Contract types — global catalogue
        if ($this->db->table_exists(db_prefix() . 'contracts_types')) {
            $rows = $this->db->select('id, name')
                ->from(db_prefix() . 'contracts_types')
                ->order_by('name', 'ASC')
                ->get()->result_array();
            foreach ($rows as $r) {
                $out['contract_type_id'][] = [
                    'id'    => (int) $r['id'],
                    'label' => (string) $r['name'],
                ];
            }
        }

        // Expense categories — global catalogue
        if ($this->db->table_exists(db_prefix() . 'expenses_categories')) {
            $rows = $this->db->select('id, name')
                ->from(db_prefix() . 'expenses_categories')
                ->order_by('name', 'ASC')
                ->get()->result_array();
            foreach ($rows as $r) {
                $out['expense_category_id'][] = [
                    'id'    => (int) $r['id'],
                    'label' => (string) $r['name'],
                ];
            }
        }

        return $out;
    }

    /** Helper: any rule exists at all (for dashboard empty state). */
    private function has_any_rule()
    {
        return $this->db->count_all_results(db_prefix() . 'approvalflow_rules') > 0;
    }

    /**
     * Unified responder. JSON for AJAX, set_alert + redirect otherwise.
     */
    private function respond($payload, $status = 200)
    {
        if ($this->input->is_ajax_request()) {
            $this->output->set_status_header($status)
                ->set_content_type('application/json')
                ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return;
        }
        $type = !empty($payload['success']) ? 'success' : 'danger';
        set_alert($type, $payload['message'] ?? '');
        redirect(admin_url('approvalflow/pending'));
    }
}
