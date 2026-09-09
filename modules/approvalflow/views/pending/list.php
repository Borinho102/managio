<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Pending approvals list view
 *
 * Composition:
 *   1. Hero strip (icon + count of pending)
 *   2. Toolbar (entity-type filter + bulk-approve action bar)
 *   3. Data table (stacked on mobile via `approvalflow-table--stacked`)
 *      with inline view / approve / reject buttons per row
 *   4. Server-side pagination footer (50 rows/page)
 *   5. Reject modal + view-request modal shells
 *
 * Inputs (set by Approvalflow::pending()):
 *   $requests           array of joined request rows (with staff names,
 *                       rule_name, rule_active, entity_snapshot_array)
 *   $filters            current GET filters echoed back into the form
 *   $pager              server-side pagination payload (see pagination.php)
 *   $can_approve        gates checkboxes + Approve buttons
 *   $can_reject         gates Reject buttons
 *   $can_view_all       toggles the Approver column visibility
 *   $current_staff_id   used to render row-level "can act on this" checks
 *   $is_admin           bypasses approver checks
 *
 * @package ApprovalFlow
 * @since   1.0.0
 */
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content-page approvalflow-page">
        <div class="container-fluid">
            <div class="approvalflow-app">

                <!-- ═════════════════════════════════════════════════════
                     SECTION 1 — Compact hero
                     Identifies the page + surfaces the pending count.
                     ════════════════════════════════════════════════════ -->
                <div class="approvalflow-hero approvalflow-hero--compact">
                    <div class="approvalflow-hero__icon">
                        <i class="fa fa-hourglass-half" aria-hidden="true"></i>
                    </div>
                    <div class="approvalflow-hero__body">
                        <div class="approvalflow-hero__eyebrow"><?php echo html_escape(_l('approvalflow_module_eyebrow')); ?></div>
                        <h4><?php echo html_escape(_l('approvalflow_pending')); ?></h4>
                        <p><?php echo (int) ($pager['total'] ?? count($requests)); ?> <?php echo html_escape(_l('approvalflow_pending')); ?></p>
                    </div>
                </div>

                <?php $this->load->view('admin/includes/alerts'); ?>

                <!-- ═════════════════════════════════════════════════════
                     SECTION 2 — Toolbar (filters + bulk bar)
                     Left: entity-type filter (GET form, preserves URL).
                     Right: floating bulk-approve bar shown via JS when
                     at least one row checkbox is selected.
                     ════════════════════════════════════════════════════ -->
                <div class="approvalflow-card">
                    <div class="approvalflow-card__header approvalflow-card__header--toolbar">
                        <form method="get" action="<?php echo admin_url('approvalflow/pending'); ?>" class="approvalflow-filters">
                            <select name="entity_type" class="approvalflow-form-control approvalflow-filter-input">
                                <option value=""><?php echo html_escape(_l('approvalflow_filter')); ?>: <?php echo html_escape(_l('approvalflow_request_type')); ?></option>
                                <?php foreach (['invoice','estimate','proposal','contract','expense'] as $et): ?>
                                    <option value="<?php echo $et; ?>" <?php echo $filters['entity_type'] === $et ? 'selected' : ''; ?>>
                                        <?php echo html_escape(_l('approvalflow_entity_' . $et)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-default approvalflow-btn-filter">
                                <i class="fa fa-filter" aria-hidden="true"></i>
                                <?php echo html_escape(_l('approvalflow_filter')); ?>
                            </button>
                        </form>
                        <div class="approvalflow-bulk-bar" id="approvalflowBulkBar" hidden>
                            <span class="approvalflow-bulk-bar__count" id="approvalflowBulkCount">0 <?php echo html_escape(_l('approvalflow_bulk_selected_n', '', false) ?: 'selected'); ?></span>
                            <button type="button" class="btn btn-default approvalflow-btn-clear" id="approvalflowBulkClear">
                                <?php echo html_escape(_l('approvalflow_bulk_clear')); ?>
                            </button>
                            <?php if ($can_approve): ?>
                                <button type="button" class="btn btn-primary approvalflow-btn-bulk-approve" id="approvalflowBulkApprove">
                                    <i class="fa fa-check" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_bulk_approve')); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- ═════════════════════════════════════════════════
                         SECTION 3 — Data table or empty state
                         Empty state shows a success illustration — "all
                         caught up". Populated table uses stacked layout
                         on mobile (data-label="…" per cell).
                         ════════════════════════════════════════════════ -->
                    <div class="approvalflow-card__body p-0">
                        <?php if (empty($requests)): ?>
                            <div class="approvalflow-empty">
                                <div class="approvalflow-empty__icon approvalflow-empty__icon--success">
                                    <i class="fa fa-check-circle" aria-hidden="true"></i>
                                </div>
                                <h5><?php echo html_escape(_l('approvalflow_empty_pending_title')); ?></h5>
                                <p><?php echo html_escape(_l('approvalflow_empty_pending_desc')); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="approvalflow-table-wrap">
                                <table class="approvalflow-table approvalflow-table--stacked" id="approvalflowPendingTable">
                                    <thead>
                                        <tr>
                                            <?php if ($can_approve): ?>
                                                <th class="approvalflow-select-th">
                                                    <input type="checkbox" id="approvalflowSelectAll" aria-label="<?php echo html_escape(_l('approvalflow_bulk_select_all')); ?>">
                                                </th>
                                            <?php endif; ?>
                                            <th><?php echo html_escape(_l('approvalflow_request_type')); ?></th>
                                            <th><?php echo html_escape(_l('approvalflow_request_document')); ?></th>
                                            <th class="approvalflow-col-hide-sm"><?php echo html_escape(_l('approvalflow_request_client')); ?></th>
                                            <th class="approvalflow-money-th"><?php echo html_escape(_l('approvalflow_request_amount')); ?></th>
                                            <th class="approvalflow-col-hide-md"><?php echo html_escape(_l('approvalflow_request_requester')); ?></th>
                                            <?php if ($can_view_all): ?>
                                                <th class="approvalflow-col-hide-md"><?php echo html_escape(_l('approvalflow_request_approver')); ?></th>
                                            <?php endif; ?>
                                            <?php if (!empty($risk_enabled)): ?>
                                                <th class="approvalflow-risk-th"><?php echo html_escape(_l('approvalflow_request_risk')); ?></th>
                                            <?php endif; ?>
                                            <th><?php echo html_escape(_l('approvalflow_request_waiting_time')); ?></th>
                                            <th class="approvalflow-actions-th"><?php echo html_escape(_l('approvalflow_actions')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $r): ?>
                                            <?php
                                                $snapshot = $r['entity_snapshot_array'] ?? [];
                                                $amount = isset($snapshot['total']) ? (float) $snapshot['total'] : 0;
                                                $req_name = trim(($r['requester_fn'] ?? '') . ' ' . ($r['requester_ln'] ?? '')) ?: '-';
                                                $appr_name = trim(($r['approver_fn'] ?? '') . ' ' . ($r['approver_ln'] ?? '')) ?: '-';
                                                $client_id = (int) ($snapshot['clientid'] ?? 0);
                                                $client_label = $client_id > 0 ? '#' . $client_id : '-';
                                                $client_company = $client_id > 0 ? get_company_name($client_id) : '';
                                                if (!empty($client_company)) $client_label = $client_company;
                                                $waited = strtotime('now') - strtotime($r['created_at']);
                                                $waited_h = max(0, (int) floor($waited / 3600));
                                                $waited_d = (int) floor($waited_h / 24);
                                                $waited_label = $waited_d > 0
                                                    ? $waited_d . _l('approvalflow_kpi_days')
                                                    : ($waited_h > 0 ? $waited_h . _l('approvalflow_kpi_hours') : '<1' . _l('approvalflow_kpi_hours'));
                                                // Approval Pulse — SLA overdue flag + risk score (both opt-in)
                                                $is_overdue = !empty($sla_hours) && $waited > ((int) $sla_hours * 3600);
                                                $risk = (!empty($risk_enabled) && isset($r['risk'])) ? $r['risk'] : null;
                                                $can_act_self = is_admin() || (int) $r['approver_staff_id'] === $current_staff_id;
                                                $rule_inactive = isset($r['rule_active']) && $r['rule_active'] !== null && (int) $r['rule_active'] === 0;
                                                $rule_deleted = empty($r['rule_name']);
                                            ?>
                                            <tr data-id="<?php echo (int) $r['id']; ?>" data-entity-type="<?php echo html_escape($r['entity_type']); ?>" data-entity-id="<?php echo (int) $r['entity_id']; ?>">
                                                <?php if ($can_approve): ?>
                                                    <td class="approvalflow-select-td">
                                                        <?php if ($can_act_self): ?>
                                                            <input type="checkbox" class="approvalflow-row-check" value="<?php echo (int) $r['id']; ?>" aria-label="<?php echo html_escape(_l('approvalflow_aria_select_request', (int) $r['id'])); ?>">
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_request_type')); ?>">
                                                    <span class="approvalflow-entity-pill"><?php echo html_escape(_l('approvalflow_entity_' . $r['entity_type'])); ?></span>
                                                    <?php if ($rule_inactive): ?>
                                                        <span class="approvalflow-badge approvalflow-badge--cancelled" title="<?php echo html_escape(_l('approvalflow_request_rule_inactive')); ?>">
                                                            <?php echo html_escape(_l('approvalflow_request_rule_inactive')); ?>
                                                        </span>
                                                    <?php elseif ($rule_deleted): ?>
                                                        <span class="approvalflow-badge approvalflow-badge--cancelled">
                                                            <?php echo html_escape(_l('approvalflow_request_rule_deleted')); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_request_document')); ?>">
                                                    <a href="<?php echo $this->approvalflow_model->entity_admin_url($r['entity_type'], (int) $r['entity_id']); ?>"
                                                       target="_blank" rel="noopener" class="approvalflow-doc-link">
                                                        #<?php echo (int) $r['entity_id']; ?>
                                                        <i class="fa fa-external-link" aria-hidden="true"></i>
                                                    </a>
                                                </td>
                                                <td class="approvalflow-col-hide-sm" data-label="<?php echo html_escape(_l('approvalflow_request_client')); ?>">
                                                    <?php echo html_escape($client_label); ?>
                                                </td>
                                                <td class="approvalflow-money" data-label="<?php echo html_escape(_l('approvalflow_request_amount')); ?>">
                                                    <?php echo html_escape(app_format_money($amount, '')); ?>
                                                </td>
                                                <td class="approvalflow-col-hide-md" data-label="<?php echo html_escape(_l('approvalflow_request_requester')); ?>">
                                                    <?php echo html_escape($req_name); ?>
                                                </td>
                                                <?php if ($can_view_all): ?>
                                                    <td class="approvalflow-col-hide-md" data-label="<?php echo html_escape(_l('approvalflow_request_approver')); ?>">
                                                        <?php echo html_escape($appr_name); ?>
                                                    </td>
                                                <?php endif; ?>
                                                <?php if (!empty($risk_enabled)): ?>
                                                    <td class="approvalflow-risk-td" data-label="<?php echo html_escape(_l('approvalflow_request_risk')); ?>"
                                                        data-risk="<?php echo $risk ? (int) $risk['score'] : 0; ?>">
                                                        <?php if ($risk): ?>
                                                            <span class="approvalflow-risk-badge approvalflow-risk-badge--<?php echo html_escape($risk['band']); ?>"
                                                                  tabindex="0"
                                                                  data-toggle="tooltip"
                                                                  title="<?php
                                                                        $parts = [];
                                                                        foreach ($risk['factors'] as $f) {
                                                                            $parts[] = $f['label'] . ': +' . (int) $f['points'];
                                                                        }
                                                                        echo html_escape(_l('approvalflow_risk_why') . ' — ' . implode(' · ', $parts));
                                                                  ?>">
                                                                <?php echo (int) $risk['score']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="approvalflow-risk-badge approvalflow-risk-badge--low">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_request_waiting_time')); ?>"
                                                    title="<?php echo html_escape($r['created_at']); ?>">
                                                    <?php echo html_escape($waited_label); ?>
                                                    <?php if ($is_overdue): ?>
                                                        <span class="approvalflow-badge approvalflow-badge--overdue" title="<?php echo html_escape(_l('approvalflow_request_overdue_hint')); ?>">
                                                            <?php echo html_escape(_l('approvalflow_request_overdue')); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="approvalflow-actions-td" data-label="<?php echo html_escape(_l('approvalflow_actions')); ?>">
                                                    <button type="button"
                                                            class="approvalflow-btn-action approvalflow-btn-action--view"
                                                            data-action="view"
                                                            data-id="<?php echo (int) $r['id']; ?>"
                                                            aria-label="<?php echo html_escape(_l('approvalflow_aria_view_request', (int) $r['id'])); ?>">
                                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                                    </button>
                                                    <?php if ($can_approve && $can_act_self): ?>
                                                        <button type="button"
                                                                class="approvalflow-btn-action approvalflow-btn-action--approve"
                                                                data-action="approve"
                                                                data-id="<?php echo (int) $r['id']; ?>"
                                                                aria-label="<?php echo html_escape(_l('approvalflow_aria_approve_request', (int) $r['id'])); ?>">
                                                            <i class="fa fa-check" aria-hidden="true"></i>
                                                            <?php echo html_escape(_l('approvalflow_action_approve')); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($can_reject && $can_act_self): ?>
                                                        <button type="button"
                                                                class="approvalflow-btn-action approvalflow-btn-action--reject"
                                                                data-action="reject"
                                                                data-id="<?php echo (int) $r['id']; ?>"
                                                                data-entity-label="<?php echo html_escape(_l('approvalflow_entity_' . $r['entity_type']) . ' #' . (int) $r['entity_id']); ?>"
                                                                data-amount="<?php echo html_escape(app_format_money($amount, '')); ?>"
                                                                aria-label="<?php echo html_escape(_l('approvalflow_aria_reject_request', (int) $r['id'])); ?>">
                                                            <i class="fa fa-times" aria-hidden="true"></i>
                                                            <?php echo html_escape(_l('approvalflow_action_reject')); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php $this->load->view('approvalflow/partials/pagination', ['pager' => $pager]); ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════════════════════════════
     SECTION 4 — Modal shells
     Reject modal (inline form for the comment + AJAX submit) and the
     view-request shell whose body is fetched lazily via AJAX on row
     click. Kept outside the table wrapper so Bootstrap modal escape
     works regardless of overflow on parents.
     ════════════════════════════════════════════════════════════════════ -->
<?php $this->load->view('approvalflow/pending/modal_reject'); ?>
<?php $this->load->view('approvalflow/pending/modal_shell'); ?>

<?php init_tail(); ?>
<script>
window.ApprovalFlowConfig = {
    csrf_token_name: '<?php echo $this->security->get_csrf_token_name(); ?>',
    csrf_hash: '<?php echo $this->security->get_csrf_hash(); ?>',
    urls: {
        approve:      '<?php echo admin_url('approvalflow/approve/'); ?>',
        reject:       '<?php echo admin_url('approvalflow/reject/'); ?>',
        bulk_approve: '<?php echo admin_url('approvalflow/bulk_approve'); ?>',
        view_request: '<?php echo admin_url('approvalflow/view_request/'); ?>'
    },
    require_reject_comment: <?php echo (int) get_option('approvalflow_require_reject_comment') === 1 ? 'true' : 'false'; ?>,
    lang: {
        confirm_approve: <?php echo json_encode(_l('approvalflow_action_approve_confirm')); ?>,
        processing:      <?php echo json_encode(_l('approvalflow_processing')); ?>,
        load_failed:     <?php echo json_encode(_l('approvalflow_error_load_failed')); ?>,
        no_selection:    <?php echo json_encode(_l('approvalflow_no_selection')); ?>,
        comment_required:<?php echo json_encode(_l('approvalflow_error_comment_required')); ?>,
        bulk_confirm_title: <?php echo json_encode(_l('approvalflow_bulk_approve_confirm_title')); ?>,
        bulk_confirm_body:  <?php echo json_encode(_l('approvalflow_bulk_approve_confirm_body')); ?>,
        selected:        <?php echo json_encode(_l('approvalflow_bulk_selected_n')); ?>
    }
};
</script>
</body>
</html>
