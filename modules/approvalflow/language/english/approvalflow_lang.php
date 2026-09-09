<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — English language file
 *
 * Every user-facing string in the module flows through _l() — no string
 * is hardcoded in views, controllers or JS. Keys are grouped by feature
 * section so translators can context-scan quickly. Total ~150 keys.
 *
 * @package ApprovalFlow
 */

// ── Module name / general
$lang['approvalflow']                              = 'ApprovalFlow';
$lang['approvalflow_open_dashboard']               = 'Open Dashboard';
$lang['approvalflow_dashboard']                    = 'Dashboard';
$lang['approvalflow_pending']                      = 'Pending Approvals';
$lang['approvalflow_rules']                        = 'Rules';
$lang['approvalflow_history']                      = 'History';
$lang['approvalflow_settings']                     = 'Settings';
$lang['approvalflow_new_rule']                     = 'New Rule';
$lang['approvalflow_edit_rule']                    = 'Edit Rule';
$lang['approvalflow_save']                         = 'Save';
$lang['approvalflow_save_rule']                    = 'Save Rule';
$lang['approvalflow_cancel']                       = 'Cancel';
$lang['approvalflow_back']                         = 'Back';
$lang['approvalflow_yes']                          = 'Yes';
$lang['approvalflow_no']                           = 'No';
$lang['approvalflow_active']                       = 'Active';
$lang['approvalflow_inactive']                     = 'Inactive';
$lang['approvalflow_actions']                      = 'Actions';
$lang['approvalflow_search']                       = 'Search';
$lang['approvalflow_filter']                       = 'Filter';
$lang['approvalflow_empty_state']                  = 'Nothing here yet.';
$lang['approvalflow_and']                          = 'and';
$lang['approvalflow_more']                         = 'more';
$lang['approvalflow_loading']                      = 'Loading…';
$lang['approvalflow_close']                        = 'Close';
$lang['approvalflow_view']                         = 'View';
$lang['approvalflow_view_document']                = 'View document';
$lang['approvalflow_view_request']                 = 'View request';
$lang['approvalflow_review_pending']               = 'Review Pending Approvals';
$lang['approvalflow_dashboard_subtitle']           = 'Govern who can approve invoices, estimates, proposals, contracts and expenses across your team.';
$lang['approvalflow_open_request']                 = 'Open request';
$lang['approvalflow_processing']                   = 'Processing…';
$lang['approvalflow_hero_meta_pending']            = '%d request(s) waiting on you';
$lang['approvalflow_hero_meta_all_clear']          = 'All caught up — no pending approvals';
$lang['approvalflow_hero_meta_this_month']         = 'This month: %d approved · %d rejected';
$lang['approvalflow_cta_pending_sub']              = 'Sorted by oldest first';
$lang['approvalflow_pagination_showing']           = 'Showing %d–%d of %d';
$lang['approvalflow_pagination_prev']              = 'Previous';
$lang['approvalflow_pagination_next']              = 'Next';
$lang['approvalflow_pagination_page_of']           = 'Page %d of %d';
$lang['approvalflow_pagination_first']             = 'First';
$lang['approvalflow_pagination_last']              = 'Last';
$lang['approvalflow_pagination_per_page']          = 'per page';

// ── Entities (singular + plural)
$lang['approvalflow_entity_invoice']               = 'Invoice';
$lang['approvalflow_entity_estimate']              = 'Estimate';
$lang['approvalflow_entity_proposal']              = 'Proposal';
$lang['approvalflow_entity_contract']              = 'Contract';
$lang['approvalflow_entity_expense']               = 'Expense';
$lang['approvalflow_entity_invoices']              = 'Invoices';
$lang['approvalflow_entity_estimates']             = 'Estimates';
$lang['approvalflow_entity_proposals']             = 'Proposals';
$lang['approvalflow_entity_contracts']             = 'Contracts';
$lang['approvalflow_entity_expenses']              = 'Expenses';

// ── Rules
$lang['approvalflow_rule_name']                    = 'Rule name';
$lang['approvalflow_rule_entity']                  = 'Entity type';
$lang['approvalflow_rule_approver']                = 'Approver';
$lang['approvalflow_rule_priority']                = 'Priority';
$lang['approvalflow_rule_priority_help']           = 'Lower numbers are evaluated first. Use this only if multiple rules can match the same document.';
$lang['approvalflow_rule_active']                  = 'Active';
$lang['approvalflow_rule_conditions']              = 'Conditions';
$lang['approvalflow_rule_conditions_help']         = 'Conditions are combined with AND. A request is created only when every defined condition matches.';
$lang['approvalflow_rule_add_condition']           = 'Add condition';
$lang['approvalflow_rule_remove_condition']        = 'Remove';
$lang['approvalflow_rule_select_entity_first']     = 'Select entity type first.';
$lang['approvalflow_rule_no_conditions_warning']   = 'This rule has no conditions. It will match ALL %s documents. Continue?';
$lang['approvalflow_rule_in_use_warning']          = 'This rule has pending approval requests. Deactivate it instead of deleting to keep audit trail intact.';
$lang['approvalflow_rule_advanced_options']        = 'Advanced options';
$lang['approvalflow_rule_preview_heading']         = 'What this rule will do';
$lang['approvalflow_rule_preview_template']        = 'When a %s is created with %s, it will require approval from %s.';
$lang['approvalflow_rule_preview_no_conditions']   = 'When any %s is created, it will require approval from %s.';
$lang['approvalflow_rule_preview_pending']         = 'configure the rule to see a live preview here.';
$lang['approvalflow_rule_created_successfully']    = 'Rule created.';
$lang['approvalflow_rule_updated_successfully']    = 'Rule updated.';
$lang['approvalflow_rule_deleted_successfully']    = 'Rule deleted.';
$lang['approvalflow_rule_activated']               = 'Rule activated.';
$lang['approvalflow_rule_deactivated']             = 'Rule deactivated.';
$lang['approvalflow_rule_confirm_delete']          = 'Delete this rule? This cannot be undone.';
$lang['approvalflow_rule_no_approver']             = '(approver not set)';
$lang['approvalflow_rule_approver_deleted']        = 'Approver deleted';
$lang['approvalflow_rule_active_requests']         = '%d active request(s)';

// ── Multi-level approvals (v1.0.1)
$lang['approvalflow_rule_approval_levels']         = 'Approval Levels';
$lang['approvalflow_rule_level_1']                 = 'Level 1 (primary)';
$lang['approvalflow_rule_level_1_hint']            = 'set above';
$lang['approvalflow_rule_level_n']                 = 'Level %d';
$lang['approvalflow_rule_add_level']               = 'Add approval level';
$lang['approvalflow_rule_remove_level']            = 'Remove level';
$lang['approvalflow_rule_levels_help']             = 'Optional: add sequential approvers. Level 2 is notified only after Level 1 approves, Level 3 after Level 2, and so on. Rejecting any level cancels the rest.';
$lang['approvalflow_level_badge']                  = 'Level %d of %d';
$lang['approvalflow_banner_ml_progress']           = 'Approval progress: %d of %d levels completed';
$lang['approvalflow_banner_ml_current']            = 'Level %d — waiting for approval by %s';
$lang['approvalflow_banner_ml_completed']          = 'All %d approval levels completed';
$lang['approvalflow_history_action_level_activated'] = 'Level activated';

// ── Conditions (long labels for the builder + short labels for the list summary)
$lang['approvalflow_condition_amount_gt']                = 'Amount greater than';
$lang['approvalflow_condition_discount_percent_gt']      = 'Discount %% greater than';
$lang['approvalflow_condition_client']                   = 'Specific client';
$lang['approvalflow_condition_staff']                    = 'Specific staff creator';
$lang['approvalflow_condition_status']                   = 'Document status';
$lang['approvalflow_condition_contract_type']            = 'Contract type';
$lang['approvalflow_condition_expense_category']         = 'Expense category';
$lang['approvalflow_condition_amount_short']             = 'Amount';
$lang['approvalflow_condition_discount_short']           = 'Discount';
$lang['approvalflow_condition_client_short']             = 'Client';
$lang['approvalflow_condition_staff_short']              = 'Staff';
$lang['approvalflow_condition_status_short']             = 'Status';
$lang['approvalflow_condition_contract_type_short']      = 'Type';
$lang['approvalflow_condition_category_short']           = 'Category';
$lang['approvalflow_condition_value']                    = 'Value';
$lang['approvalflow_condition_none']                     = 'No conditions (matches all)';
$lang['approvalflow_condition_select_placeholder']       = '— select a condition —';
$lang['approvalflow_rule_select_entity_placeholder']     = '— select entity type —';
$lang['approvalflow_rule_select_approver_placeholder']   = '— select an approver —';
// Searchable dropdown placeholders for the four FK-flavoured conditions
$lang['approvalflow_condition_pick_client']              = '— pick a client —';
$lang['approvalflow_condition_pick_staff']               = '— pick a staff member —';
$lang['approvalflow_condition_pick_contract_type']       = '— pick a contract type —';
$lang['approvalflow_condition_pick_expense_category']    = '— pick an expense category —';
$lang['approvalflow_condition_search_placeholder']       = 'Search…';
$lang['approvalflow_condition_status_hint']              = 'Invoice statuses: 1=unpaid, 2=paid, 3=partially paid, 4=overdue, 5=cancelled. Estimate/proposal use their numeric status too.';

// ── Requests (columns and badges)
$lang['approvalflow_request_id']                   = 'Request #';
$lang['approvalflow_request_document']             = 'Document';
$lang['approvalflow_request_type']                 = 'Type';
$lang['approvalflow_request_client']               = 'Client';
$lang['approvalflow_request_amount']               = 'Amount';
$lang['approvalflow_request_requester']            = 'Requester';
$lang['approvalflow_request_approver']             = 'Approver';
$lang['approvalflow_request_status']               = 'Status';
$lang['approvalflow_request_created_at']           = 'Created';
$lang['approvalflow_request_resolved_at']          = 'Resolved';
$lang['approvalflow_request_resolution_time']      = 'Resolution time';
$lang['approvalflow_request_waiting_time']         = 'Waiting';
$lang['approvalflow_request_rule_used']            = 'Rule';
$lang['approvalflow_request_rule_deleted']         = 'Rule deleted';
$lang['approvalflow_request_rule_inactive']        = 'Rule inactive';
$lang['approvalflow_request_view_document']        = 'View document';
$lang['approvalflow_request_comment']              = 'Comment';
$lang['approvalflow_status_pending']               = 'Pending';
$lang['approvalflow_status_approved']              = 'Approved';
$lang['approvalflow_status_rejected']              = 'Rejected';
$lang['approvalflow_status_cancelled']             = 'Cancelled';
$lang['approvalflow_status_waiting']               = 'Waiting';
$lang['approvalflow_action_approve']               = 'Approve';
$lang['approvalflow_action_reject']                = 'Reject';
$lang['approvalflow_action_approve_confirm']       = 'Approve this approval request?';
$lang['approvalflow_action_reject_confirm']        = 'Reject this approval request?';
$lang['approvalflow_approved_successfully']        = 'Request approved.';
$lang['approvalflow_rejected_successfully']        = 'Request rejected.';
$lang['approvalflow_reject_modal_title']           = 'Reject approval request';
$lang['approvalflow_reject_modal_intro']           = "You're about to reject this request:";
$lang['approvalflow_reject_reason']                = 'Rejection comment';
$lang['approvalflow_reject_reason_placeholder']    = 'Provide a reason (visible to requester)…';
$lang['approvalflow_reject_reason_hint']           = 'Required. Minimum 3 characters.';
$lang['approvalflow_reject_submit']                = 'Reject request';
$lang['approvalflow_bulk_select_all']              = 'Select all on this page';
$lang['approvalflow_bulk_selected_n']              = '%d selected';
$lang['approvalflow_bulk_clear']                   = 'Clear selection';
$lang['approvalflow_bulk_approve']                 = 'Approve Selected';
$lang['approvalflow_bulk_approve_confirm_title']   = 'Approve selected requests';
$lang['approvalflow_bulk_approve_confirm_body']    = 'Approving %d request(s). Add an optional comment:';
$lang['approvalflow_bulk_done']                    = 'Bulk approve finished — %d approved, %d skipped.';
$lang['approvalflow_no_selection']                 = 'Select at least one request.';

// ── Dashboard / KPIs
$lang['approvalflow_kpi_pending']                  = 'Pending approvals';
$lang['approvalflow_kpi_approved_month']           = 'Approved this month';
$lang['approvalflow_kpi_rejected_month']           = 'Rejected this month';
$lang['approvalflow_kpi_avg_time']                 = 'Average approval time';
$lang['approvalflow_kpi_high_value']               = 'High-value approved';
$lang['approvalflow_kpi_high_value_help']          = 'Largest amount approved during the current month.';
$lang['approvalflow_kpi_top_requester']            = 'Top requester';
$lang['approvalflow_kpi_top_requester_help']       = 'Staff member who created the most approval requests this month.';
$lang['approvalflow_kpi_no_data']                  = 'No data';
$lang['approvalflow_kpi_hours']                    = 'h';
$lang['approvalflow_kpi_minutes']                  = 'm';
$lang['approvalflow_kpi_days']                     = 'd';
$lang['approvalflow_kpi_n_requests']               = '%d requests';
$lang['approvalflow_dashboard_recent_pending']     = 'Recent pending requests';
$lang['approvalflow_dashboard_recent_activity']    = 'Recent activity';
$lang['approvalflow_dashboard_view_all_pending']   = 'View all pending';

// ── Empty states
$lang['approvalflow_empty_pending_title']          = 'All caught up';
$lang['approvalflow_empty_pending_desc']           = 'No approval requests are pending right now.';
$lang['approvalflow_empty_history_title']          = 'Nothing yet in the audit log';
$lang['approvalflow_empty_history_desc']           = 'Approval requests and their resolutions will appear here over time.';
$lang['approvalflow_empty_rules_title']            = 'No approval rules defined';
$lang['approvalflow_empty_rules_desc']             = 'Create your first rule to start governing invoices, estimates, proposals, contracts and expenses.';
$lang['approvalflow_empty_rules_cta']              = 'Create your first rule';
$lang['approvalflow_empty_dashboard_title']        = 'Ready when you are';
$lang['approvalflow_empty_dashboard_desc']         = 'Create an approval rule and ApprovalFlow will start monitoring documents.';
$lang['approvalflow_empty_dashboard_cta']          = 'Create your first rule';

// ── Settings
$lang['approvalflow_setting_enabled_entities']       = 'Document types';
$lang['approvalflow_setting_enabled_entities_help']  = 'Only checked types will be evaluated against rules when a document is created.';
$lang['approvalflow_setting_enable_invoices']        = 'Monitor invoices';
$lang['approvalflow_setting_enable_estimates']       = 'Monitor estimates';
$lang['approvalflow_setting_enable_proposals']       = 'Monitor proposals';
$lang['approvalflow_setting_enable_contracts']       = 'Monitor contracts';
$lang['approvalflow_setting_enable_expenses']        = 'Monitor expenses';
$lang['approvalflow_setting_notifications']          = 'Notifications';
$lang['approvalflow_setting_notifications_internal'] = 'Internal notifications (bell icon)';
$lang['approvalflow_setting_notifications_email']    = 'Email notifications (requires SMTP configured in Perfex)';
$lang['approvalflow_setting_behavior']               = 'Behavior';
$lang['approvalflow_setting_require_reject_comment'] = 'Require comment when rejecting';
$lang['approvalflow_setting_require_reject_comment_hint'] = 'Recommended. Keeps an audit explanation for every rejection.';
$lang['approvalflow_setting_admin_auto_approve']     = 'Auto-approve documents created by admins';
$lang['approvalflow_setting_admin_auto_approve_hint']= 'Admins bypass approval — use with caution.';
$lang['approvalflow_setting_reminder_days']          = 'Reminder after X days';
$lang['approvalflow_setting_reminder_help']          = 'Re-notify the approver if a request is still pending after this many days. Set to 0 to disable reminders.';
$lang['approvalflow_setting_advanced']               = 'Advanced';
$lang['approvalflow_setting_debug']                  = 'Enable debug logging';
$lang['approvalflow_setting_debug_hint']             = 'Records every rule evaluation in the logs table. Off by default.';
$lang['approvalflow_setting_logs_retention']         = 'Log retention (days)';
$lang['approvalflow_setting_logs_retention_hint']    = 'Logs older than this are purged automatically by the cron tick.';
$lang['approvalflow_settings_saved']                 = 'Settings saved.';
$lang['approvalflow_save_settings']                  = 'Save settings';
$lang['approvalflow_settings_all_off_warning']       = 'All document types are disabled. No approvals will be triggered.';
// Settings page chrome (hero + section dividers + intro hints + shortcuts)
$lang['approvalflow_settings_eyebrow']               = 'ApprovalFlow';
$lang['approvalflow_settings_subtitle']              = 'Pick the document types that should require approval, choose how approvers get notified, and tune the rest of the workflow.';
$lang['approvalflow_setting_enabled_entities_hint']  = 'Toggle a type off to skip it entirely. Existing pending requests for that type stay visible in History.';
$lang['approvalflow_setting_notifications_hint']     = 'Approvers always see new requests in their inbox. The toggles below add complementary channels.';
$lang['approvalflow_setting_behavior_hint']          = 'Fine-tune what happens when a request is rejected or stays pending too long.';
$lang['approvalflow_setting_advanced_hint']          = 'For audit-heavy installs and troubleshooting. Defaults are safe for most teams.';
$lang['approvalflow_section_documents']              = 'Documents';
$lang['approvalflow_section_channels']               = 'Channels';
$lang['approvalflow_section_rules_audit']            = 'Audit';
$lang['approvalflow_section_reminders']              = 'Reminders';
$lang['approvalflow_section_diagnostics']            = 'Diagnostics';
$lang['approvalflow_section_retention']              = 'Retention';
$lang['approvalflow_section_shortcuts']              = 'Shortcuts';
$lang['approvalflow_open_dashboard']                 = 'Open dashboard';
$lang['approvalflow_view_pending']                   = 'View pending';
$lang['approvalflow_manage_rules']                   = 'Manage rules';

// ── Permissions
$lang['approvalflow_perm_view']                    = 'View pending approvals and rules';
$lang['approvalflow_perm_view_all']                = 'View all requests (not only own ones)';
$lang['approvalflow_perm_create_rules']            = 'Create rules';
$lang['approvalflow_perm_edit_rules']              = 'Edit rules';
$lang['approvalflow_perm_delete_rules']            = 'Delete rules';
$lang['approvalflow_perm_approve']                 = 'Approve assigned requests';
$lang['approvalflow_perm_reject']                  = 'Reject assigned requests';
$lang['approvalflow_perm_settings']                = 'Modify module settings';

// ── Notifications / emails (subjects + bodies in plain language)
$lang['approvalflow_notif_new_request']            = 'New approval request: %s';
$lang['approvalflow_notif_approved']               = 'Your request was approved: %s';
$lang['approvalflow_notif_rejected']               = 'Your request was rejected: %s';
$lang['approvalflow_notif_reminder']               = 'You have pending approvals';
$lang['approvalflow_email_subject_new']            = '[ApprovalFlow] New approval request — %s';
$lang['approvalflow_email_subject_approved']       = '[ApprovalFlow] Approved — %s';
$lang['approvalflow_email_subject_rejected']       = '[ApprovalFlow] Rejected — %s';
$lang['approvalflow_email_body_new']               = 'A new approval request needs your review: %s. Open ApprovalFlow to approve or reject.';
$lang['approvalflow_email_body_approved']          = 'Your request was approved by %s: %s.';
$lang['approvalflow_email_body_rejected']          = 'Your request was rejected by %s: %s.';

// ── Warnings / alerts (set_alert messages + banner copy)
$lang['approvalflow_warning_pending_approval']     = '%s #%s requires approval from %s before sending. The approver has been notified.';
$lang['approvalflow_warning_already_resolved']     = 'This request has already been resolved.';
$lang['approvalflow_warning_not_your_request']     = 'You are not the assigned approver for this request.';
$lang['approvalflow_warning_no_active_rules']      = 'There are no active rules for this entity type.';
$lang['approvalflow_banner_pending_title']         = 'Pending approval';
$lang['approvalflow_banner_pending_desc']          = 'This %s is waiting for review by %s.';
$lang['approvalflow_banner_pending_meta']          = 'Submitted %s · Rule: %s';
$lang['approvalflow_banner_approved_title']        = 'Approval granted';
$lang['approvalflow_banner_approved_desc']         = 'Approved by %s on %s.';
$lang['approvalflow_banner_rejected_title']        = 'Approval rejected';
$lang['approvalflow_banner_rejected_desc']         = 'Rejected by %s on %s.';

// ── Errors
$lang['approvalflow_error_invalid_rule']           = 'Invalid rule.';
$lang['approvalflow_error_invalid_request']        = 'Invalid request.';
$lang['approvalflow_error_not_authorized']         = 'You are not authorized to perform this action.';
$lang['approvalflow_error_comment_required']       = 'Rejection comment is required (minimum 3 characters).';
$lang['approvalflow_error_approver_required']      = 'Approver is required.';
$lang['approvalflow_error_entity_required']        = 'Entity type is required.';
$lang['approvalflow_error_name_required']          = 'Rule name is required.';
$lang['approvalflow_error_save_failed']            = 'Could not save. Please try again.';
$lang['approvalflow_error_delete_failed']          = 'Could not delete this rule.';
$lang['approvalflow_error_already_processed']      = 'This request was already processed by another user.';
$lang['approvalflow_error_entity_not_found']       = 'The original document could not be found.';
$lang['approvalflow_error_load_failed']            = 'Could not load data. Please refresh.';
$lang['approvalflow_error_csrf']                   = 'Session expired. Please refresh the page.';

// ── History view
$lang['approvalflow_history_subtitle']             = 'Complete audit trail of every approval request and its state transitions.';
$lang['approvalflow_history_actor']                = 'Performed by';
$lang['approvalflow_history_action']               = 'Action';
$lang['approvalflow_history_previous']             = 'Previous status';
$lang['approvalflow_history_new']                  = 'New status';
$lang['approvalflow_history_date']                 = 'Date';
$lang['approvalflow_history_system']               = 'System';
$lang['approvalflow_history_action_created']       = 'Request created';
$lang['approvalflow_history_action_approved']      = 'Approved';
$lang['approvalflow_history_action_rejected']      = 'Rejected';
$lang['approvalflow_history_action_cancelled']     = 'Cancelled';
$lang['approvalflow_history_action_reminder_sent'] = 'Reminder sent';
$lang['approvalflow_history_action_viewed_warning']= 'Warning viewed';
$lang['approvalflow_history_action_reassigned']    = 'Reassigned';
$lang['approvalflow_history_action_level_activated'] = 'Level activated';
$lang['approvalflow_history_filter_from']          = 'From';
$lang['approvalflow_history_filter_to']            = 'To';
$lang['approvalflow_history_clear_filters']        = 'Clear filters';

// ── Misc tab labels (estimate/proposal/contract fallback)
$lang['approvalflow_tab_pending_approval']         = 'Pending Approval';

// ── Hero eyebrow + ARIA labels (i18n compliance)
$lang['approvalflow_module_eyebrow']               = 'APPROVALFLOW';
$lang['approvalflow_aria_view_request']            = 'View request #%s';
$lang['approvalflow_aria_approve_request']         = 'Approve request #%s';
$lang['approvalflow_aria_reject_request']          = 'Reject request #%s';
$lang['approvalflow_aria_select_request']          = 'Select request #%s';

// ── Approval Pulse (v1.0.2): risk score, SLA/escalation, email digest
// Pending list — risk column + overdue badge
$lang['approvalflow_request_risk']                 = 'Risk';
$lang['approvalflow_risk_why']                     = 'Why this score';
$lang['approvalflow_risk_factor_amount']           = 'Amount';
$lang['approvalflow_risk_factor_aging']            = 'Waiting time';
$lang['approvalflow_risk_factor_discount']         = 'Discount';
$lang['approvalflow_risk_factor_depth']            = 'Approval level';
$lang['approvalflow_request_overdue']              = 'Overdue';
$lang['approvalflow_request_overdue_hint']         = 'This request has been pending longer than the configured SLA.';
// Escalation notifications
$lang['approvalflow_notif_escalated']              = 'Approval overdue — escalated to you: %s';
$lang['approvalflow_email_subject_escalated']      = '[ApprovalFlow] Overdue approval escalated — %s';
$lang['approvalflow_email_body_escalated']         = 'An approval request has passed its SLA and was escalated to you: %s. Open ApprovalFlow to review it.';
// Email digest
$lang['approvalflow_digest_email_subject']         = '[ApprovalFlow] Your pending approvals digest';
$lang['approvalflow_digest_email_body']            = "Hi %1\$s,\n\nYou have %2\$d pending approval(s), %3\$d overdue, totaling %4\$s, with the oldest waiting %5\$d day(s).";
$lang['approvalflow_email_cta_open']               = 'Open ApprovalFlow';
// Settings — Pulse card
$lang['approvalflow_setting_pulse']                = 'Approval Pulse';
$lang['approvalflow_setting_pulse_hint']           = 'Optional risk scoring, SLA escalation and email digests. Everything here is off by default.';
$lang['approvalflow_section_risk']                 = 'Risk score';
$lang['approvalflow_setting_risk_enabled']         = 'Show risk score';
$lang['approvalflow_setting_risk_enabled_hint']    = 'Adds a 0–100 risk column to the pending list.';
$lang['approvalflow_setting_risk_ceiling']         = 'Amount ceiling';
$lang['approvalflow_setting_risk_ceiling_hint']    = 'Amount that scores the maximum risk weight.';
$lang['approvalflow_section_sla']                  = 'SLA & escalation';
$lang['approvalflow_setting_sla_hours']            = 'SLA (hours)';
$lang['approvalflow_setting_sla_hours_hint']       = '0 disables overdue flags and escalation.';
$lang['approvalflow_setting_escalate_to']          = 'Escalate to';
$lang['approvalflow_setting_escalate_none']        = '— no escalation —';
$lang['approvalflow_setting_escalate_to_hint']     = 'Manager notified when a request passes the SLA.';
$lang['approvalflow_section_digest']               = 'Email digest';
$lang['approvalflow_setting_digest_enabled']       = 'Send digest';
$lang['approvalflow_setting_digest_enabled_hint']  = 'Emails each approver a summary of their pending approvals.';
$lang['approvalflow_setting_digest_freq']          = 'Frequency';
$lang['approvalflow_digest_freq_daily']            = 'Daily';
$lang['approvalflow_digest_freq_weekly']           = 'Weekly';
$lang['approvalflow_setting_digest_day']           = 'Day of week';
$lang['approvalflow_setting_digest_day_hint']      = 'Used only for the weekly digest.';
$lang['approvalflow_day_monday']                   = 'Monday';
$lang['approvalflow_day_tuesday']                  = 'Tuesday';
$lang['approvalflow_day_wednesday']                = 'Wednesday';
$lang['approvalflow_day_thursday']                 = 'Thursday';
$lang['approvalflow_day_friday']                   = 'Friday';
$lang['approvalflow_day_saturday']                 = 'Saturday';
$lang['approvalflow_day_sunday']                   = 'Sunday';
$lang['approvalflow_history_action_escalated']     = 'Escalated';
