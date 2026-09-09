<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Rules list view
 *
 * Lists every configured rule with:
 *   - Active toggle (AJAX, no full submit)
 *   - Approver column (highlights deleted/inactive approvers)
 *   - Compact "conditions" summary built by approvalflow_format_conditions()
 *   - Priority (lower number = evaluated first)
 *   - Edit + Delete inline actions (delete soft-degrades to "deactivate"
 *     when the rule still has pending requests attached, per controller)
 *
 * Inputs (set by Approvalflow::rules()):
 *   $rules        joined rule rows (with approver_fn/ln + pending_count)
 *   $pager        server-side pagination payload
 *   $can_create   gates the "New rule" button
 *   $can_edit     gates the edit link + active toggle
 *   $can_delete   gates the delete button
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
                     SECTION 1 — Compact hero + "New rule" CTA
                     ════════════════════════════════════════════════════ -->
                <div class="approvalflow-hero approvalflow-hero--compact">
                    <div class="approvalflow-hero__icon">
                        <i class="fa fa-cog" aria-hidden="true"></i>
                    </div>
                    <div class="approvalflow-hero__body">
                        <div class="approvalflow-hero__eyebrow"><?php echo html_escape(_l('approvalflow_module_eyebrow')); ?></div>
                        <h4><?php echo html_escape(_l('approvalflow_rules')); ?></h4>
                    </div>
                    <div class="approvalflow-hero__actions">
                        <?php if ($can_create): ?>
                            <a href="<?php echo admin_url('approvalflow/rule_form'); ?>" class="btn btn-primary">
                                <i class="fa fa-plus" aria-hidden="true"></i>
                                <?php echo html_escape(_l('approvalflow_new_rule')); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php $this->load->view('admin/includes/alerts'); ?>

                <!-- ═════════════════════════════════════════════════════
                     SECTION 2 — Rules table (or empty state)
                     The active-toggle cell uses AJAX so admins can pause
                     a rule mid-day without leaving the page. Approver
                     column highlights staff that was deleted after the
                     rule was created.
                     ════════════════════════════════════════════════════ -->
                <div class="approvalflow-card">
                    <div class="approvalflow-card__body p-0">
                        <?php if (empty($rules)): ?>
                            <div class="approvalflow-empty">
                                <div class="approvalflow-empty__icon"><i class="fa fa-cog" aria-hidden="true"></i></div>
                                <h5><?php echo html_escape(_l('approvalflow_empty_rules_title')); ?></h5>
                                <p><?php echo html_escape(_l('approvalflow_empty_rules_desc')); ?></p>
                                <?php if ($can_create): ?>
                                    <a href="<?php echo admin_url('approvalflow/rule_form'); ?>" class="btn btn-primary">
                                        <i class="fa fa-plus" aria-hidden="true"></i>
                                        <?php echo html_escape(_l('approvalflow_empty_rules_cta')); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="approvalflow-table-wrap">
                                <table class="approvalflow-table approvalflow-table--stacked">
                                    <thead>
                                        <tr>
                                            <th><?php echo html_escape(_l('approvalflow_rule_name')); ?></th>
                                            <th><?php echo html_escape(_l('approvalflow_rule_entity')); ?></th>
                                            <th class="approvalflow-col-hide-sm"><?php echo html_escape(_l('approvalflow_rule_approver')); ?></th>
                                            <th class="approvalflow-col-hide-md"><?php echo html_escape(_l('approvalflow_rule_conditions')); ?></th>
                                            <th class="approvalflow-col-hide-md"><?php echo html_escape(_l('approvalflow_rule_priority')); ?></th>
                                            <th><?php echo html_escape(_l('approvalflow_active')); ?></th>
                                            <th class="approvalflow-actions-th"><?php echo html_escape(_l('approvalflow_actions')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rules as $rule): ?>
                                            <?php
                                                $appr_name = trim(($rule['approver_fn'] ?? '') . ' ' . ($rule['approver_ln'] ?? ''));
                                                if ($appr_name === '') $appr_name = _l('approvalflow_rule_no_approver');
                                                $approver_inactive = isset($rule['approver_active']) && (int) $rule['approver_active'] === 0;
                                                $cond_summary = approvalflow_format_conditions($rule['conditions']);
                                            ?>
                                            <tr data-id="<?php echo (int) $rule['id']; ?>">
                                                <td data-label="<?php echo html_escape(_l('approvalflow_rule_name')); ?>">
                                                    <strong><?php echo html_escape($rule['name']); ?></strong>
                                                    <?php if ((int) $rule['pending_count'] > 0): ?>
                                                        <div class="approvalflow-text-small approvalflow-text-info">
                                                            <?php echo sprintf(html_escape(_l('approvalflow_rule_active_requests')), (int) $rule['pending_count']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_rule_entity')); ?>">
                                                    <span class="approvalflow-entity-pill"><?php echo html_escape(_l('approvalflow_entity_' . $rule['entity_type'])); ?></span>
                                                </td>
                                                <td class="approvalflow-col-hide-sm" data-label="<?php echo html_escape(_l('approvalflow_rule_approver')); ?>">
                                                    <?php echo html_escape($appr_name); ?>
                                                    <?php if ($approver_inactive): ?>
                                                        <span class="approvalflow-badge approvalflow-badge--rejected">
                                                            <?php echo html_escape(_l('approvalflow_rule_approver_deleted')); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="approvalflow-col-hide-md" data-label="<?php echo html_escape(_l('approvalflow_rule_conditions')); ?>">
                                                    <span class="approvalflow-text-muted approvalflow-text-small"><?php echo html_escape($cond_summary); ?></span>
                                                </td>
                                                <td class="approvalflow-col-hide-md" data-label="<?php echo html_escape(_l('approvalflow_rule_priority')); ?>">
                                                    <?php echo (int) $rule['priority']; ?>
                                                </td>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_active')); ?>">
                                                    <label class="approvalflow-switch <?php echo $can_edit ? '' : 'approvalflow-switch--readonly'; ?>">
                                                        <input type="checkbox"
                                                               class="approvalflow-rule-toggle"
                                                               data-id="<?php echo (int) $rule['id']; ?>"
                                                               <?php echo (int) $rule['active'] === 1 ? 'checked' : ''; ?>
                                                               <?php echo $can_edit ? '' : 'disabled'; ?>>
                                                        <span class="approvalflow-switch__slider"></span>
                                                    </label>
                                                </td>
                                                <td class="approvalflow-actions-td" data-label="<?php echo html_escape(_l('approvalflow_actions')); ?>">
                                                    <?php if ($can_edit): ?>
                                                        <a href="<?php echo admin_url('approvalflow/rule_form/' . (int) $rule['id']); ?>"
                                                           class="approvalflow-btn-action approvalflow-btn-action--view"
                                                           aria-label="Edit rule #<?php echo (int) $rule['id']; ?>">
                                                            <i class="fa fa-pencil" aria-hidden="true"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($can_delete): ?>
                                                        <button type="button"
                                                                class="approvalflow-btn-action approvalflow-btn-action--reject approvalflow-rule-delete"
                                                                data-id="<?php echo (int) $rule['id']; ?>"
                                                                aria-label="Delete rule #<?php echo (int) $rule['id']; ?>">
                                                            <i class="fa fa-trash" aria-hidden="true"></i>
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

<?php init_tail(); ?>
<script>
window.ApprovalFlowConfig = {
    csrf_token_name: '<?php echo $this->security->get_csrf_token_name(); ?>',
    csrf_hash: '<?php echo $this->security->get_csrf_hash(); ?>',
    urls: {
        rule_toggle: '<?php echo admin_url('approvalflow/rule_toggle/'); ?>',
        rule_delete: '<?php echo admin_url('approvalflow/rule_delete/'); ?>'
    },
    lang: {
        confirm_delete: <?php echo json_encode(_l('approvalflow_rule_confirm_delete')); ?>,
        load_failed:    <?php echo json_encode(_l('approvalflow_error_load_failed')); ?>
    }
};
</script>
</body>
</html>
