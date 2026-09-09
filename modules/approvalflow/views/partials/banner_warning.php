<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Warning banner partial
 *
 * Renders inline inside native Perfex preview templates via hooks
 * registered in the manifest. Supports both single-level (v1.0.0) and
 * multi-level (v1.0.1) approval chains. Variants: pending (amber),
 * approved (green), rejected (red).
 *
 * Inputs:
 *   $request       — active request row (joined with staff + rule)
 *   $all_requests  — all rows for this entity ordered by level ASC
 *   $entity_type   — invoice / estimate / proposal / contract / expense
 *   $entity_id     — primary key of the native document
 *   $is_multilevel — bool: true when there is more than one level
 *
 * @package ApprovalFlow
 */

// Inject the module CSS only once per page render.
static $approvalflow_banner_asset_pushed = false;
if (!$approvalflow_banner_asset_pushed) {
    $approvalflow_banner_asset_pushed = true;
    echo '<link rel="stylesheet" href="' . approvalflow_asset_url('assets/css/approvalflow.css') . '">';
}

$all_requests  = $all_requests  ?? [$request];
$is_multilevel = !empty($is_multilevel) && count($all_requests) > 1;

$status = $request['status'];
$variant_class = $status === 'approved'
    ? 'approvalflow-warning-banner--approved'
    : ($status === 'rejected' ? 'approvalflow-warning-banner--rejected' : '');
$icon = $status === 'approved'
    ? 'fa-check'
    : ($status === 'rejected' ? 'fa-times' : 'fa-exclamation-triangle');
$approver_name = trim(($request['approver_fn'] ?? '') . ' ' . ($request['approver_ln'] ?? ''));
if ($approver_name === '') $approver_name = '#' . (int) $request['approver_staff_id'];
$entity_label = _l('approvalflow_entity_' . $entity_type);
$total_levels = (int) ($request['total_levels'] ?? 1);
$current_level = (int) ($request['level'] ?? 1);
?>
<div class="approvalflow-app">
    <div class="approvalflow-warning-banner <?php echo $variant_class; ?>">
        <div class="approvalflow-warning-banner__icon">
            <i class="fa <?php echo $icon; ?>" aria-hidden="true"></i>
        </div>
        <div class="approvalflow-warning-banner__body">

            <?php if ($is_multilevel): ?>
                <!-- ── Multi-level progress chain ── -->
                <?php
                $all_done = true;
                foreach ($all_requests as $r) {
                    if (in_array($r['status'], ['pending', 'waiting'], true)) {
                        $all_done = false;
                        break;
                    }
                }
                $approved_count = 0;
                foreach ($all_requests as $r) {
                    if ($r['status'] === 'approved') $approved_count++;
                }
                ?>
                <?php if ($status === 'rejected'): ?>
                    <p class="approvalflow-warning-banner__title">
                        <?php echo html_escape(_l('approvalflow_banner_rejected_title')); ?>
                    </p>
                    <p class="approvalflow-warning-banner__desc">
                        <?php echo sprintf(html_escape(_l('approvalflow_banner_rejected_desc')),
                            '<strong>' . html_escape($approver_name) . '</strong>',
                            html_escape($request['resolved_at'] ?: '')); ?>
                    </p>
                    <?php if (!empty($request['resolution_comment'])): ?>
                        <p class="approvalflow-warning-banner__meta">
                            <i class="fa fa-comment" aria-hidden="true"></i>
                            <?php echo html_escape($request['resolution_comment']); ?>
                        </p>
                    <?php endif; ?>
                <?php elseif ($all_done): ?>
                    <p class="approvalflow-warning-banner__title">
                        <?php echo html_escape(_l('approvalflow_banner_approved_title')); ?>
                    </p>
                    <p class="approvalflow-warning-banner__desc">
                        <?php echo sprintf(html_escape(_l('approvalflow_banner_ml_completed')), $total_levels); ?>
                    </p>
                <?php else: ?>
                    <p class="approvalflow-warning-banner__title">
                        <?php echo html_escape(_l('approvalflow_banner_pending_title')); ?>
                    </p>
                    <p class="approvalflow-warning-banner__desc">
                        <?php echo sprintf(html_escape(_l('approvalflow_banner_ml_current')),
                            $current_level, html_escape($approver_name)); ?>
                    </p>
                <?php endif; ?>

                <!-- Level progress dots -->
                <div class="approvalflow-level-progress" style="margin-top:8px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                    <?php foreach ($all_requests as $r):
                        $lvl_status  = $r['status'];
                        $lvl_appr    = trim(($r['approver_fn'] ?? '') . ' ' . ($r['approver_ln'] ?? ''));
                        if ($lvl_appr === '') $lvl_appr = '#' . (int) $r['approver_staff_id'];
                        $dot_class   = 'approvalflow-lvl-dot';
                        $dot_icon    = 'fa-circle-o';
                        if ($lvl_status === 'approved') { $dot_class .= ' approvalflow-lvl-dot--approved'; $dot_icon = 'fa-check-circle'; }
                        elseif ($lvl_status === 'rejected') { $dot_class .= ' approvalflow-lvl-dot--rejected'; $dot_icon = 'fa-times-circle'; }
                        elseif ($lvl_status === 'pending') { $dot_class .= ' approvalflow-lvl-dot--pending'; $dot_icon = 'fa-clock-o'; }
                        else { $dot_class .= ' approvalflow-lvl-dot--waiting'; }
                    ?>
                        <span class="<?php echo $dot_class; ?>"
                              title="<?php echo sprintf(html_escape(_l('approvalflow_level_badge')), (int) $r['level'], $total_levels) . ' — ' . html_escape($lvl_appr) . ' · ' . html_escape(_l('approvalflow_status_' . $lvl_status)); ?>">
                            <i class="fa <?php echo $dot_icon; ?>" aria-hidden="true"></i>
                            <span class="approvalflow-lvl-label">
                                <?php echo sprintf(html_escape(_l('approvalflow_level_badge')), (int) $r['level'], $total_levels); ?>
                            </span>
                        </span>
                        <?php if ((int) $r['level'] < $total_levels): ?>
                            <i class="fa fa-chevron-right approvalflow-lvl-arrow" aria-hidden="true"></i>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- ── Single-level banner (v1.0.0 compatible) ── -->
                <?php if ($status === 'pending'): ?>
                    <p class="approvalflow-warning-banner__title">
                        <?php echo html_escape(_l('approvalflow_banner_pending_title')); ?>
                    </p>
                    <p class="approvalflow-warning-banner__desc">
                        <?php echo sprintf(html_escape(_l('approvalflow_banner_pending_desc')),
                            html_escape($entity_label), '<strong>' . html_escape($approver_name) . '</strong>'); ?>
                    </p>
                    <p class="approvalflow-warning-banner__meta">
                        <?php echo sprintf(html_escape(_l('approvalflow_banner_pending_meta')),
                            html_escape($request['created_at']),
                            html_escape($request['rule_name'] ?? _l('approvalflow_request_rule_deleted'))
                        ); ?>
                    </p>
                <?php elseif ($status === 'approved'): ?>
                    <p class="approvalflow-warning-banner__title">
                        <?php echo html_escape(_l('approvalflow_banner_approved_title')); ?>
                    </p>
                    <p class="approvalflow-warning-banner__desc">
                        <?php echo sprintf(html_escape(_l('approvalflow_banner_approved_desc')),
                            '<strong>' . html_escape($approver_name) . '</strong>',
                            html_escape($request['resolved_at'] ?: '')); ?>
                    </p>
                <?php elseif ($status === 'rejected'): ?>
                    <p class="approvalflow-warning-banner__title">
                        <?php echo html_escape(_l('approvalflow_banner_rejected_title')); ?>
                    </p>
                    <p class="approvalflow-warning-banner__desc">
                        <?php echo sprintf(html_escape(_l('approvalflow_banner_rejected_desc')),
                            '<strong>' . html_escape($approver_name) . '</strong>',
                            html_escape($request['resolved_at'] ?: '')); ?>
                    </p>
                    <?php if (!empty($request['resolution_comment'])): ?>
                        <p class="approvalflow-warning-banner__meta">
                            <i class="fa fa-comment" aria-hidden="true"></i>
                            <?php echo html_escape($request['resolution_comment']); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div>
        <a class="approvalflow-warning-banner__action"
           href="<?php echo admin_url('approvalflow/view_request/' . (int) $request['id']); ?>">
            <?php echo html_escape(_l('approvalflow_view_request')); ?>
            <i class="fa fa-chevron-right" aria-hidden="true"></i>
        </a>
    </div>
</div>
