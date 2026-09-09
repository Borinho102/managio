<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Request detail (AJAX modal body)
 *
 * Fragment loaded by approvalflow.js when clicking the "View" button
 * on a pending row. Renders:
 *   - A definition-list summary (request id, type, amount, requester,
 *     approver, current status, originating rule).
 *   - A vertical timeline of every state transition recorded for the
 *     request (compact variant — same component as the dashboard
 *     activity column but tighter spacing).
 *
 * Inputs (set by Approvalflow::view_request()):
 *   $request  joined request row (with requester/approver names + rule)
 *   $history  history_for_request() rows
 *   $doc_url  deep link to the native Perfex preview of the entity
 *
 * @package ApprovalFlow
 * @since   1.0.0
 */
$snapshot = $request['entity_snapshot_array'] ?? [];
$amount = isset($snapshot['total']) ? (float) $snapshot['total'] : 0;
$req_name = trim(($request['requester_fn'] ?? '') . ' ' . ($request['requester_ln'] ?? '')) ?: '-';
$appr_name = trim(($request['approver_fn'] ?? '') . ' ' . ($request['approver_ln'] ?? '')) ?: '-';
?>
<div class="approvalflow-app">
    <div class="approvalflow-modal-summary">
        <div class="approvalflow-modal-summary__row">
            <span class="approvalflow-modal-summary__label"><?php echo html_escape(_l('approvalflow_request_id')); ?></span>
            <span class="approvalflow-modal-summary__value">#<?php echo (int) $request['id']; ?></span>
        </div>
        <div class="approvalflow-modal-summary__row">
            <span class="approvalflow-modal-summary__label"><?php echo html_escape(_l('approvalflow_request_type')); ?></span>
            <span class="approvalflow-modal-summary__value">
                <?php echo html_escape(_l('approvalflow_entity_' . $request['entity_type'])); ?> #<?php echo (int) $request['entity_id']; ?>
                <a href="<?php echo $doc_url; ?>" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i></a>
            </span>
        </div>
        <div class="approvalflow-modal-summary__row">
            <span class="approvalflow-modal-summary__label"><?php echo html_escape(_l('approvalflow_request_amount')); ?></span>
            <span class="approvalflow-modal-summary__value approvalflow-money"><?php echo html_escape(app_format_money($amount, '')); ?></span>
        </div>
        <div class="approvalflow-modal-summary__row">
            <span class="approvalflow-modal-summary__label"><?php echo html_escape(_l('approvalflow_request_requester')); ?></span>
            <span class="approvalflow-modal-summary__value"><?php echo html_escape($req_name); ?></span>
        </div>
        <div class="approvalflow-modal-summary__row">
            <span class="approvalflow-modal-summary__label"><?php echo html_escape(_l('approvalflow_request_approver')); ?></span>
            <span class="approvalflow-modal-summary__value"><?php echo html_escape($appr_name); ?></span>
        </div>
        <div class="approvalflow-modal-summary__row">
            <span class="approvalflow-modal-summary__label"><?php echo html_escape(_l('approvalflow_request_status')); ?></span>
            <span class="approvalflow-modal-summary__value">
                <span class="approvalflow-badge approvalflow-badge--<?php echo html_escape($request['status']); ?>">
                    <i class="<?php echo html_escape(approvalflow_status_icon($request['status'])); ?>" aria-hidden="true"></i>
                    <?php echo html_escape(_l('approvalflow_status_' . $request['status'])); ?>
                </span>
            </span>
        </div>
        <?php if (!empty($request['rule_name'])): ?>
            <div class="approvalflow-modal-summary__row">
                <span class="approvalflow-modal-summary__label"><?php echo html_escape(_l('approvalflow_request_rule_used')); ?></span>
                <span class="approvalflow-modal-summary__value"><?php echo html_escape($request['rule_name']); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <h6 class="approvalflow-modal-section-title">
        <i class="fa fa-history" aria-hidden="true"></i>
        <?php echo html_escape(_l('approvalflow_history')); ?>
    </h6>
    <ol class="approvalflow-timeline approvalflow-timeline--compact">
        <?php foreach ($history as $h): ?>
            <?php
                $actor = trim(($h['firstname'] ?? '') . ' ' . ($h['lastname'] ?? ''));
                if ($actor === '') $actor = _l('approvalflow_history_system');
            ?>
            <li class="approvalflow-timeline__item approvalflow-timeline__item--<?php echo html_escape($h['action']); ?>">
                <span class="approvalflow-timeline__dot" aria-hidden="true"></span>
                <div class="approvalflow-timeline__body">
                    <span class="approvalflow-timeline__action">
                        <?php echo html_escape(_l('approvalflow_history_action_' . $h['action'], '', false) ?: $h['action']); ?>
                    </span>
                    <div class="approvalflow-timeline__meta">
                        <?php echo html_escape($actor); ?>
                        <?php if (!empty($h['comment'])): ?>
                            <div class="approvalflow-timeline__comment"><?php echo html_escape($h['comment']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <time class="approvalflow-timeline__time"><?php echo html_escape($h['created_at']); ?></time>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
