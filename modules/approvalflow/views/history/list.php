<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — History (audit timeline) view
 *
 * Renders an immutable, filterable list of every state transition.
 * Design notes:
 *   - Comments are evidence-grade — never truncated in the DOM. Long
 *     comments are previewed (first 80 chars) and expanded inline on
 *     "more" click; the full body is always present, just hidden.
 *   - Five filters: entity type, action, actor, date_from, date_to. All
 *     are server-side (GET form) so the URL is shareable.
 *   - Pagination footer is the shared partials/pagination.php helper.
 *
 * Inputs (set by Approvalflow::history()):
 *   $history        joined history rows (with firstname/lastname of actor)
 *   $filters        active GET filters echoed back into the form
 *   $pager          pagination payload
 *   $staff_options  active staff for the actor filter dropdown
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
                     SECTION 1 — Hero
                     ════════════════════════════════════════════════════ -->
                <div class="approvalflow-hero approvalflow-hero--compact">
                    <div class="approvalflow-hero__icon">
                        <i class="fa fa-history" aria-hidden="true"></i>
                    </div>
                    <div class="approvalflow-hero__body">
                        <div class="approvalflow-hero__eyebrow"><?php echo html_escape(_l('approvalflow_module_eyebrow')); ?></div>
                        <h4><?php echo html_escape(_l('approvalflow_history')); ?></h4>
                        <p><?php echo html_escape(_l('approvalflow_history_subtitle')); ?></p>
                    </div>
                </div>

                <?php $this->load->view('admin/includes/alerts'); ?>

                <!-- ═════════════════════════════════════════════════════
                     SECTION 2 — Filter toolbar
                     Five fields: entity, action, date range. The wrap
                     modifier lets the toolbar break to two lines on
                     narrow screens without overflowing.
                     ════════════════════════════════════════════════════ -->
                <div class="approvalflow-card">
                    <div class="approvalflow-card__header approvalflow-card__header--toolbar">
                        <form method="get" action="<?php echo admin_url('approvalflow/history'); ?>" class="approvalflow-filters approvalflow-filters--wrap">
                            <select name="entity_type" class="approvalflow-form-control approvalflow-filter-input">
                                <option value=""><?php echo html_escape(_l('approvalflow_request_type')); ?>: <?php echo html_escape(_l('approvalflow_filter')); ?></option>
                                <?php foreach (['invoice','estimate','proposal','contract','expense'] as $et): ?>
                                    <option value="<?php echo $et; ?>" <?php echo ($filters['entity_type'] ?? '') === $et ? 'selected' : ''; ?>>
                                        <?php echo html_escape(_l('approvalflow_entity_' . $et)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="action" class="approvalflow-form-control approvalflow-filter-input">
                                <option value=""><?php echo html_escape(_l('approvalflow_history_action')); ?>: <?php echo html_escape(_l('approvalflow_filter')); ?></option>
                                <?php foreach (['created','approved','rejected','cancelled','reassigned','reminder_sent','escalated'] as $a): ?>
                                    <option value="<?php echo $a; ?>" <?php echo ($filters['action'] ?? '') === $a ? 'selected' : ''; ?>>
                                        <?php echo html_escape(_l('approvalflow_history_action_' . $a)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="date_from" class="approvalflow-form-control approvalflow-filter-input"
                                   placeholder="<?php echo html_escape(_l('approvalflow_history_filter_from')); ?>"
                                   value="<?php echo html_escape($filters['date_from'] ?? ''); ?>">
                            <input type="date" name="date_to" class="approvalflow-form-control approvalflow-filter-input"
                                   placeholder="<?php echo html_escape(_l('approvalflow_history_filter_to')); ?>"
                                   value="<?php echo html_escape($filters['date_to'] ?? ''); ?>">
                            <button type="submit" class="btn btn-default approvalflow-btn-filter">
                                <i class="fa fa-filter" aria-hidden="true"></i>
                                <?php echo html_escape(_l('approvalflow_filter')); ?>
                            </button>
                            <a href="<?php echo admin_url('approvalflow/history'); ?>" class="approvalflow-clear-link">
                                <?php echo html_escape(_l('approvalflow_history_clear_filters')); ?>
                            </a>
                        </form>
                    </div>
                    <!-- ═════════════════════════════════════════════════
                         SECTION 3 — Timeline table (or empty state)
                         ════════════════════════════════════════════════ -->
                    <div class="approvalflow-card__body p-0">
                        <?php if (empty($history)): ?>
                            <div class="approvalflow-empty">
                                <div class="approvalflow-empty__icon"><i class="fa fa-history" aria-hidden="true"></i></div>
                                <h5><?php echo html_escape(_l('approvalflow_empty_history_title')); ?></h5>
                                <p><?php echo html_escape(_l('approvalflow_empty_history_desc')); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="approvalflow-table-wrap">
                                <table class="approvalflow-table approvalflow-table--stacked">
                                    <thead>
                                        <tr>
                                            <th><?php echo html_escape(_l('approvalflow_history_date')); ?></th>
                                            <th><?php echo html_escape(_l('approvalflow_request_type')); ?></th>
                                            <th><?php echo html_escape(_l('approvalflow_request_document')); ?></th>
                                            <th><?php echo html_escape(_l('approvalflow_history_action')); ?></th>
                                            <th class="approvalflow-col-hide-sm"><?php echo html_escape(_l('approvalflow_history_actor')); ?></th>
                                            <th class="approvalflow-col-hide-md"><?php echo html_escape(_l('approvalflow_request_comment')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $h): ?>
                                            <?php
                                                $actor = trim(($h['firstname'] ?? '') . ' ' . ($h['lastname'] ?? ''));
                                                if ($actor === '') $actor = _l('approvalflow_history_system');
                                                $comment = (string) ($h['comment'] ?? '');
                                                $short = mb_substr($comment, 0, 80);
                                                $is_long = mb_strlen($comment) > 80;
                                            ?>
                                            <tr>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_history_date')); ?>">
                                                    <span class="approvalflow-text-muted"><?php echo html_escape($h['created_at']); ?></span>
                                                </td>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_request_type')); ?>">
                                                    <?php if (!empty($h['entity_type'])): ?>
                                                        <span class="approvalflow-entity-pill"><?php echo html_escape(_l('approvalflow_entity_' . $h['entity_type'])); ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_request_document')); ?>">
                                                    <?php if (!empty($h['entity_type']) && !empty($h['entity_id'])): ?>
                                                        <a href="<?php echo $this->approvalflow_model->entity_admin_url($h['entity_type'], (int) $h['entity_id']); ?>"
                                                           target="_blank" rel="noopener">#<?php echo (int) $h['entity_id']; ?></a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="<?php echo html_escape(_l('approvalflow_history_action')); ?>">
                                                    <span class="approvalflow-badge approvalflow-badge--<?php echo html_escape($h['new_status'] ?: 'pending'); ?>">
                                                        <i class="<?php echo html_escape(approvalflow_status_icon($h['new_status'] ?: 'pending')); ?>"
                                                           aria-hidden="true"></i>
                                                        <?php echo html_escape(_l('approvalflow_history_action_' . $h['action'], '', false) ?: $h['action']); ?>
                                                    </span>
                                                </td>
                                                <td class="approvalflow-col-hide-sm" data-label="<?php echo html_escape(_l('approvalflow_history_actor')); ?>">
                                                    <?php echo html_escape($actor); ?>
                                                </td>
                                                <td class="approvalflow-col-hide-md approvalflow-history-comment" data-label="<?php echo html_escape(_l('approvalflow_request_comment')); ?>">
                                                    <?php if ($comment === ''): ?>
                                                        <span class="approvalflow-text-muted">-</span>
                                                    <?php elseif ($is_long): ?>
                                                        <span class="approvalflow-comment-short"><?php echo html_escape($short); ?>… </span>
                                                        <a href="#" class="approvalflow-comment-toggle"><?php echo html_escape(_l('approvalflow_more')); ?></a>
                                                        <span class="approvalflow-comment-full" hidden><?php echo html_escape($comment); ?></span>
                                                    <?php else: ?>
                                                        <?php echo html_escape($comment); ?>
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
</body>
</html>
