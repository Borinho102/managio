<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: ApprovalFlow
Description: Approval workflows for Perfex CRM — intercept invoices, estimates, proposals, contracts and expenses with a configurable rule engine, native notifications, internal email, dashboard KPIs and immutable audit history. No core file is modified.
Version: 1.0.2
Requires at least: 3.0.*
Author: Ticempresarial
Author URI: https://codecanyon.net/user/ticempresarial
Module URI: https://codecanyon.net/user/ticempresarial/portfolio
*/

/**
 * ApprovalFlow — Module Bootstrap / Manifest
 *
 * Entry point loaded by Perfex on every admin request. Defines version
 * constants, wires activation/uninstall hooks, registers the language
 * files, sidebar menu, staff capabilities, settings section, and the
 * 5 entity hooks that feed the approval rule engine.
 *
 * @package ApprovalFlow
 */

define('APPROVALFLOW_MODULE_NAME', 'approvalflow');
define('APPROVALFLOW_VERSION', '1.0.2');

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 1 — Lifecycle hooks
 * Activation creates schema (install.php). Uninstall hard-drops it
 * (uninstall.php). Deactivation is a no-op so toggling the module on/off
 * never loses configured rules, requests or audit history.
 * ════════════════════════════════════════════════════════════════════ */
register_activation_hook(APPROVALFLOW_MODULE_NAME, 'approvalflow_activation_hook');
register_deactivation_hook(APPROVALFLOW_MODULE_NAME, 'approvalflow_deactivation_hook');
register_uninstall_hook(APPROVALFLOW_MODULE_NAME, 'approvalflow_uninstall_hook');

/** Runs once when the buyer activates the module. */
function approvalflow_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/** No-op — kept for symmetry; data is preserved on deactivation. */
function approvalflow_deactivation_hook() {}

/** Runs on full module uninstall. Destructive. */
function approvalflow_uninstall_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/uninstall.php');
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 2 — Language registration
 * Registers approvalflow_lang.php from language/<active>/ so _l() resolves
 * the module keys. English + Spanish ship in v1.0; extra locales can be
 * dropped into language/<code>/approvalflow_lang.php with no code change.
 * ════════════════════════════════════════════════════════════════════ */
register_language_files(APPROVALFLOW_MODULE_NAME, [APPROVALFLOW_MODULE_NAME]);

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 3 — Hook wiring
 * Subscribes the module to native Perfex events. Three families:
 *   a) admin_init   — boots sidebar + settings + permissions on each request
 *   b) entity hooks — after_*_added: feeds the rule engine
 *   c) lifecycle    — login/cron/staff-deleted: reminders + integrity
 *   d) preview      — after_*_preview_template: inline warning banners
 * ════════════════════════════════════════════════════════════════════ */
hooks()->add_action('admin_init', 'approvalflow_admin_init');
hooks()->add_action('admin_init', 'approvalflow_permissions');

// Entity interception — feeds the rule engine
hooks()->add_action('after_invoice_added',  'approvalflow_after_invoice_added');
hooks()->add_action('after_estimate_added', 'approvalflow_after_estimate_added');
hooks()->add_action('proposal_created',     'approvalflow_after_proposal_added');
hooks()->add_action('after_contract_added', 'approvalflow_after_contract_added');
hooks()->add_action('after_expense_added',  'approvalflow_after_expense_added');

// Reminder system — double safety net
hooks()->add_action('after_staff_login', 'approvalflow_after_staff_login');
hooks()->add_action('after_cron_run',    'approvalflow_cron');

// Staff lifecycle — reassign pending approvals when an approver is deleted
hooks()->add_action('staff_member_deleted', 'approvalflow_staff_member_deleted');

// Warning banners — inline in native preview templates when a request is pending
hooks()->add_action('after_left_panel_invoice_preview_template',                  'approvalflow_banner_invoice');
hooks()->add_action('after_left_panel_expense_preview_template',                  'approvalflow_banner_expense');
hooks()->add_action('after_admin_estimate_preview_template_tab_menu_last_item',   'approvalflow_banner_estimate');
hooks()->add_action('after_admin_proposal_preview_template_tab_menu_last_item',   'approvalflow_banner_proposal');
hooks()->add_action('after_contract_view_as_client_link',                         'approvalflow_banner_contract');

// Module action links — open dashboard + settings shortcut
hooks()->add_filter('module_approvalflow_action_links', 'approvalflow_action_links');

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 4 — Module action links (Setup → Modules row)
 * Minimal entry points on the modules list. Full navigation lives in
 * the sidebar so the actions row stays uncluttered.
 * ════════════════════════════════════════════════════════════════════ */

/**
 * Filter handler for `module_approvalflow_action_links`. Returns two
 * shortcuts (Dashboard + Settings) when the module is active.
 *
 * @param  array $actions  links already provided by Perfex / other modules
 * @return array
 */
function approvalflow_action_links($actions)
{
    if (get_instance()->app_modules->is_active(APPROVALFLOW_MODULE_NAME)) {
        $actions[] = '<a href="' . admin_url('approvalflow') . '">' . _l('approvalflow_open_dashboard') . '</a>';
        $actions[] = '<a href="' . admin_url('settings?group=approvalflow') . '">' . _l('settings') . '</a>';
    }
    return $actions;
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 5 — Admin bootstrap
 * Builds the sidebar menu (parent + 5 children) and registers the
 * Integrations settings section. Runs on every admin request but bails
 * out early when the staff member lacks `view` capability — avoids
 * polluting the UI for unauthorized users.
 * ════════════════════════════════════════════════════════════════════ */

/**
 * Registers the sidebar menu, sub-items and the Integrations settings
 * card. Called from the admin_init hook on every page render.
 */
function approvalflow_admin_init()
{
    $CI = &get_instance();

    if (!staff_can('view', APPROVALFLOW_MODULE_NAME)) {
        return;
    }

    $CI->app_menu->add_sidebar_menu_item('af-main', [
        'slug'     => 'af-main',
        'name'     => _l('approvalflow'),
        'position' => 11,
        'icon'     => 'fa-solid fa-circle-check',
        'collapse' => true,
    ]);

    $CI->app_menu->add_sidebar_children_item('af-main', [
        'slug'     => 'af-dashboard',
        'name'     => _l('approvalflow_dashboard'),
        'href'     => admin_url('approvalflow'),
        'position' => 1,
        'icon'     => 'fa-solid fa-gauge-high',
    ]);

    $CI->app_menu->add_sidebar_children_item('af-main', [
        'slug'     => 'af-pending',
        'name'     => _l('approvalflow_pending'),
        'href'     => admin_url('approvalflow/pending'),
        'position' => 2,
        'icon'     => 'fa-solid fa-hourglass-half',
    ]);

    $CI->app_menu->add_sidebar_children_item('af-main', [
        'slug'     => 'af-rules',
        'name'     => _l('approvalflow_rules'),
        'href'     => admin_url('approvalflow/rules'),
        'position' => 3,
        'icon'     => 'fa-solid fa-cog',
    ]);

    $CI->app_menu->add_sidebar_children_item('af-main', [
        'slug'     => 'af-history',
        'name'     => _l('approvalflow_history'),
        'href'     => admin_url('approvalflow/history'),
        'position' => 4,
        'icon'     => 'fa-solid fa-history',
    ]);

    $CI->app_menu->add_sidebar_children_item('af-main', [
        'slug'     => 'af-settings',
        'name'     => _l('settings'),
        'href'     => admin_url('settings?group=approvalflow'),
        'position' => 5,
        'icon'     => 'fa-solid fa-gear',
    ]);

    $CI->app->add_settings_section_child('integrations', 'approvalflow', [
        'name'     => _l('approvalflow'),
        'view'     => 'approvalflow/settings/index',
        'position' => 35,
        'icon'     => 'fa-solid fa-circle-check',
    ]);
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 6 — Staff capabilities
 * Eight granular capabilities. Two-tier listing control:
 *   - `view`     → see the dashboard + own assigned approvals
 *   - `view_all` → see everyone's approvals (admin-style read access)
 * Resolution actions split for separation of duties:
 *   - `approve` and `reject` are independent capabilities
 *   - rule CRUD splits into create / edit / delete so a role can author
 *     rules without being able to remove them (audit-friendly).
 *   - `settings` is its own capability so the integrations tab can be
 *     locked down to ops/finance leads only.
 * ════════════════════════════════════════════════════════════════════ */

/** Registers the 8 capabilities under the module's permission group. */
function approvalflow_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view'          => _l('approvalflow_perm_view'),
            'view_all'      => _l('approvalflow_perm_view_all'),
            'create_rules'  => _l('approvalflow_perm_create_rules'),
            'edit_rules'    => _l('approvalflow_perm_edit_rules'),
            'delete_rules'  => _l('approvalflow_perm_delete_rules'),
            'approve'       => _l('approvalflow_perm_approve'),
            'reject'        => _l('approvalflow_perm_reject'),
            'settings'      => _l('approvalflow_perm_settings'),
        ],
    ];
    register_staff_capabilities(APPROVALFLOW_MODULE_NAME, $capabilities, _l('approvalflow'));
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 7 — Entity hook listeners
 * Each native `after_*_added` event delegates to the shared dispatcher.
 * Listeners are intentionally thin so the engine model remains the
 * single source of truth for rule evaluation. No business decisions
 * happen here.
 * ════════════════════════════════════════════════════════════════════ */

function approvalflow_after_invoice_added($invoice_id)
{
    approvalflow_dispatch_entity('invoice', (int) $invoice_id);
}

function approvalflow_after_estimate_added($estimate_id)
{
    approvalflow_dispatch_entity('estimate', (int) $estimate_id);
}

function approvalflow_after_proposal_added($proposal_id)
{
    approvalflow_dispatch_entity('proposal', (int) $proposal_id);
}

function approvalflow_after_contract_added($contract_id)
{
    approvalflow_dispatch_entity('contract', (int) $contract_id);
}

function approvalflow_after_expense_added($expense_id)
{
    approvalflow_dispatch_entity('expense', (int) $expense_id);
}

/**
 * Loads the engine model and runs evaluate() for the given entity.
 * Wrapped in try/catch so a buggy rule never aborts the native insert
 * — the document is created either way; only the approval request is
 * skipped on error.
 *
 * @param string $entity_type  invoice|estimate|proposal|contract|expense
 * @param int    $entity_id
 */
function approvalflow_dispatch_entity($entity_type, $entity_id)
{
    if ($entity_id <= 0) {
        return;
    }
    $CI = &get_instance();
    try {
        $CI->load->model('approvalflow/approvalflow_model');
        $CI->approvalflow_model->evaluate($entity_type, $entity_id);
    } catch (Exception $e) {
        log_activity('ApprovalFlow engine error: ' . $e->getMessage());
    }
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 8 — Reminder system
 * Two-layer safety net:
 *   a) Login sweep — when an approver logs in, we look at their pending
 *      approvals and emit any pending reminders. This is the primary
 *      path on active deployments.
 *   b) Cron sweep — catches approvers who haven't logged in for days
 *      so reminders still fire (via internal notification + email).
 * Both paths defer to the same engine method so the dedup logic
 * (reminder_sent_at timestamp) lives in one place.
 * ════════════════════════════════════════════════════════════════════ */

function approvalflow_after_staff_login()
{
    $staff_id = (int) get_staff_user_id();
    if ($staff_id <= 0) {
        return;
    }
    $CI = &get_instance();
    $CI->load->model('approvalflow/approvalflow_model');
    $CI->approvalflow_model->sweep_reminders_for_approver($staff_id);
}

function approvalflow_cron()
{
    $CI = &get_instance();
    $CI->load->model('approvalflow/approvalflow_model');
    $CI->approvalflow_model->sweep_reminders_global();

    // Approval Pulse (v1.0.2) — both are no-ops unless the buyer enabled them.
    // Reuses this existing cron sweep; no new cron job is registered.
    $CI->approvalflow_model->sweep_escalations();
    $CI->approvalflow_model->maybe_send_digest();

    // Logs retention purge
    $days = (int) get_option('approvalflow_logs_retention_days');
    if ($days > 0) {
        $CI->approvalflow_model->purge_old_logs($days);
    }
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 9 — Staff lifecycle integrity
 * When a staff member is deleted, every pending approval assigned to
 * them is reassigned to the substitute Perfex offers via the native
 * "transfer_data_to" payload. If no substitute is selected, those
 * requests are cancelled so the queue never points at a tombstone.
 * ════════════════════════════════════════════════════════════════════ */

function approvalflow_staff_member_deleted($data)
{
    if (!is_array($data) || empty($data['id'])) {
        return;
    }
    $CI = &get_instance();
    $CI->load->model('approvalflow/approvalflow_model');
    $CI->approvalflow_model->handle_staff_deleted(
        (int) $data['id'],
        (int) ($data['transfer_data_to'] ?? 0)
    );
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 10 — Warning banners (inline in preview templates)
 * Banners render directly inside native Perfex document previews so the
 * "this is pending approval" signal persists across reloads — unlike
 * set_alert() flashdata which vanishes after one render. Placement:
 *   - invoice / expense → after_left_panel_*_preview_template
 *     (anchored to the side panel, full-width inside)
 *   - estimate / proposal → after_admin_*_tab_menu_last_item
 *     (fallback: rendered next to the tab menu since Perfex exposes no
 *     dedicated left-panel hook on these documents)
 *   - contract → after_contract_view_as_client_link (only anchor on
 *     contract preview)
 * ════════════════════════════════════════════════════════════════════ */
/**
 * NOTE on parameter shape (applies to all 5 banner callbacks below)
 * ----------------------------------------------------------------
 * Perfex's preview-template hooks (`after_left_panel_*_preview_template`,
 * `after_admin_*_tab_menu_last_item`, `after_contract_view_as_client_link`)
 * pass the FULL document object as the argument — NOT the integer ID.
 *
 * Casting an object directly with `(int) $obj` yields 1, which silently
 * mismatches the request lookup (entity_id=1) and the banner never renders.
 *
 * We accept both shapes (object or int) for resilience across Perfex
 * versions and extract the id via `->id ?? 0` when an object is supplied.
 */
function approvalflow_banner_invoice($invoice)
{
    $id = is_object($invoice) ? (int) ($invoice->id ?? 0) : (int) $invoice;
    if ($id <= 0) {
        return;
    }
    approvalflow_render_banner('invoice', $id);
}

function approvalflow_banner_expense($expense)
{
    $id = is_object($expense) ? (int) ($expense->id ?? 0) : (int) $expense;
    if ($id <= 0) {
        return;
    }
    approvalflow_render_banner('expense', $id);
}

function approvalflow_banner_estimate($estimate)
{
    $id = is_object($estimate) ? (int) ($estimate->id ?? 0) : (int) $estimate;
    if ($id <= 0) {
        return;
    }
    approvalflow_render_banner('estimate', $id);
}

function approvalflow_banner_proposal($proposal)
{
    $id = is_object($proposal) ? (int) ($proposal->id ?? 0) : (int) $proposal;
    if ($id <= 0) {
        return;
    }
    approvalflow_render_banner('proposal', $id);
}

function approvalflow_banner_contract($contract)
{
    $id = is_object($contract) ? (int) ($contract->id ?? 0) : (int) $contract;
    if ($id <= 0) {
        return;
    }
    approvalflow_render_banner('contract', $id);
}

/**
 * Looks up the latest request for the entity and includes the banner
 * partial. Prints nothing when no request exists. Output is fully
 * scoped to `.approvalflow-*` classes so it never bleeds into Perfex
 * layout. Approved/rejected banners auto-hide after 7 days to keep
 * the document preview clean over time.
 *
 * @param string $entity_type
 * @param int    $entity_id
 */
function approvalflow_render_banner($entity_type, $entity_id)
{
    if ($entity_id <= 0) {
        return;
    }
    $CI = &get_instance();
    $CI->load->model('approvalflow/approvalflow_model');
    $all_requests = $CI->approvalflow_model->get_all_for_entity($entity_type, $entity_id);
    if (empty($all_requests)) {
        return;
    }

    // For single-level: use the one request; for multi-level: use all rows.
    // "Active" request = the first non-completed one (pending/waiting), else the last resolved.
    $active = null;
    foreach ($all_requests as $r) {
        if (in_array($r['status'], ['pending', 'waiting'], true)) {
            $active = $r;
            break;
        }
    }
    if (!$active) {
        // All resolved — use the last one to determine final status
        $active = end($all_requests);
    }

    // Hide fully-resolved banners after 7 days
    $all_resolved = true;
    foreach ($all_requests as $r) {
        if (in_array($r['status'], ['pending', 'waiting'], true)) {
            $all_resolved = false;
            break;
        }
    }
    if ($all_resolved) {
        $last_resolved_at = $active['resolved_at'] ?? null;
        if ($last_resolved_at && (time() - strtotime($last_resolved_at)) > (7 * 86400)) {
            return;
        }
    }

    $data = [
        'request'      => $active,
        'all_requests' => $all_requests,
        'entity_type'  => $entity_type,
        'entity_id'    => $entity_id,
        'is_multilevel'=> count($all_requests) > 1,
    ];
    $CI->load->view('approvalflow/partials/banner_warning', $data);
}

/* ════════════════════════════════════════════════════════════════════ *
 * SECTION 11 — Public view helpers
 * Tiny formatters used directly from views (rules list + dashboard
 * timeline) so the markup never has to encode hard-coded class names
 * or hand-build condition summaries.
 * ════════════════════════════════════════════════════════════════════ */

/**
 * Maps a request status to the canonical Font Awesome icon used in
 * badges and timelines (pending / approved / rejected / cancelled).
 *
 * @param  string $status
 * @return string  CSS class string for the icon
 */
function approvalflow_status_icon($status)
{
    $map = [
        'pending'   => 'fa fa-clock-o',
        'approved'  => 'fa fa-check-circle',
        'rejected'  => 'fa fa-times-circle',
        'cancelled' => 'fa fa-ban',
        'waiting'   => 'fa fa-hourglass-half',
    ];
    return $map[$status] ?? 'fa fa-circle';
}

/**
 * Builds a human-readable summary of a rule's JSON conditions. Used in
 * the Rules list so admins see "Amount > 5,000 + 2 more" instead of raw
 * JSON. Truncates after 2 visible parts to keep the cell width stable
 * even with complex rules.
 *
 * @param  string|array $conditions_json  JSON string or already-decoded array
 * @return string  i18n-friendly summary
 */
function approvalflow_format_conditions($conditions_json)
{
    $parts = [];
    if (is_string($conditions_json)) {
        $arr = json_decode($conditions_json, true);
    } elseif (is_array($conditions_json)) {
        $arr = $conditions_json;
    } else {
        $arr = [];
    }
    if (!is_array($arr) || empty($arr)) {
        return _l('approvalflow_condition_none');
    }
    if (isset($arr['amount_gt'])) {
        $parts[] = _l('approvalflow_condition_amount_short') . ' > ' . html_escape($arr['amount_gt']);
    }
    if (isset($arr['discount_percent_gt'])) {
        $parts[] = _l('approvalflow_condition_discount_short') . ' > ' . html_escape($arr['discount_percent_gt']) . '%';
    }
    if (isset($arr['client_id'])) {
        $parts[] = _l('approvalflow_condition_client_short') . ' #' . (int) $arr['client_id'];
    }
    if (isset($arr['staff_id'])) {
        $parts[] = _l('approvalflow_condition_staff_short') . ' #' . (int) $arr['staff_id'];
    }
    if (isset($arr['status'])) {
        $parts[] = _l('approvalflow_condition_status_short') . ' = ' . html_escape($arr['status']);
    }
    if (isset($arr['contract_type_id'])) {
        $parts[] = _l('approvalflow_condition_contract_type_short') . ' #' . (int) $arr['contract_type_id'];
    }
    if (isset($arr['expense_category_id'])) {
        $parts[] = _l('approvalflow_condition_category_short') . ' #' . (int) $arr['expense_category_id'];
    }
    if (empty($parts)) {
        return _l('approvalflow_condition_none');
    }
    if (count($parts) <= 2) {
        return implode(' ' . _l('approvalflow_and') . ' ', $parts);
    }
    return $parts[0] . ' + ' . (count($parts) - 1) . ' ' . _l('approvalflow_more');
}

/**
 * Build a cache-busted URL for a module asset. The buster is the module
 * version PLUS the file's mtime, so any edit to the asset (even within the
 * same released version) invalidates the browser cache automatically — a
 * version-only buster would keep serving stale CSS/JS after a hotfix.
 *
 * @param  string $relative  e.g. 'assets/css/approvalflow.css'
 * @return string  full URL with ?v=<version>.<mtime>
 */
function approvalflow_asset_url($relative)
{
    $ver   = APPROVALFLOW_VERSION;
    $mtime = @filemtime(module_dir_path(APPROVALFLOW_MODULE_NAME, $relative));
    if ($mtime) {
        $ver .= '.' . $mtime;
    }
    return module_dir_url(APPROVALFLOW_MODULE_NAME, $relative) . '?v=' . $ver;
}
