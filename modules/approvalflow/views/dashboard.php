<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Dashboard view
 *
 * Composition:
 *   1. Hero strip (icon + eyebrow + title + meta + CTA buttons)
 *   2. Empty state (when no rule has been configured yet)
 *   3. KPI tiles row — 3 KPIs for restricted-view staff, 6 for view_all
 *   4. Two columns: recent pending requests (8 cols) + recent activity (4)
 *
 * Inputs (set by Approvalflow::index()):
 *   $kpis             — dashboard_counts() result, 6 numeric/aggregate fields
 *   $recent_pending   — 5 latest pending requests (with joined staff names)
 *   $recent_activity  — 8 latest history rows for the timeline column
 *   $has_any_rule     — toggles the empty state vs the data layout
 *   $can_view_all     — controls which KPI tiles render
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
                     SECTION 1 — Hero strip
                     Module identity + summary KPIs + primary CTA buttons.
                     CTA visibility depends on pending count and rule perms.
                     ════════════════════════════════════════════════════ -->
                <div class="approvalflow-hero">
                    <div class="approvalflow-hero__icon">
                        <i class="fa fa-circle-check" aria-hidden="true"></i>
                    </div>
                    <div class="approvalflow-hero__body">
                        <div class="approvalflow-hero__eyebrow"><?php echo html_escape(_l('approvalflow_module_eyebrow')); ?></div>
                        <h4><?php echo html_escape(_l('approvalflow_dashboard')); ?></h4>
                        <p><?php echo html_escape(_l('approvalflow_dashboard_subtitle')); ?></p>
                        <div class="approvalflow-hero__meta">
                            <?php if ((int) $kpis['pending'] > 0): ?>
                                <span><i class="fa fa-hourglass-half" aria-hidden="true"></i>
                                    <?php echo sprintf(html_escape(_l('approvalflow_hero_meta_pending')), (int) $kpis['pending']); ?>
                                </span>
                            <?php else: ?>
                                <span><i class="fa fa-check-circle" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_hero_meta_all_clear')); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($can_view_all): ?>
                                <span class="approvalflow-hero__meta-sep" aria-hidden="true">·</span>
                                <span><i class="fa fa-calendar-o" aria-hidden="true"></i>
                                    <?php echo sprintf(html_escape(_l('approvalflow_hero_meta_this_month')),
                                        (int) $kpis['approved_month'], (int) $kpis['rejected_month']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="approvalflow-hero__actions">
                        <?php if ((int) $kpis['pending'] > 0): ?>
                            <a href="<?php echo admin_url('approvalflow/pending'); ?>" class="btn btn-primary approvalflow-cta-primary">
                                <span class="approvalflow-cta-primary__main">
                                    <i class="fa fa-hourglass-half" aria-hidden="true"></i>
                                    <span class="approvalflow-cta-primary__label"><?php echo html_escape(_l('approvalflow_review_pending')); ?></span>
                                    <span class="approvalflow-cta-primary__badge"><?php echo (int) $kpis['pending']; ?></span>
                                </span>
                                <span class="approvalflow-cta-primary__sub">
                                    <?php echo html_escape(_l('approvalflow_cta_pending_sub')); ?>
                                </span>
                            </a>
                        <?php endif; ?>
                        <?php if (staff_can('create_rules', APPROVALFLOW_MODULE_NAME)): ?>
                            <a href="<?php echo admin_url('approvalflow/rule_form'); ?>" class="btn btn-default approvalflow-btn-outline-light">
                                <i class="fa fa-plus" aria-hidden="true"></i>
                                <?php echo html_escape(_l('approvalflow_new_rule')); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php $this->load->view('admin/includes/alerts'); ?>

                <!-- ═════════════════════════════════════════════════════
                     SECTION 2 — Empty state (no rules configured)
                     Shown when the buyer activates the module but hasn't
                     authored any rule yet. KPI row would all be zeros so
                     we replace it with a single onboarding card.
                     ════════════════════════════════════════════════════ -->
                <?php if (!$has_any_rule): ?>
                    <div class="approvalflow-card approvalflow-empty">
                        <div class="approvalflow-empty__icon"><i class="fa fa-circle-check" aria-hidden="true"></i></div>
                        <h5><?php echo html_escape(_l('approvalflow_empty_dashboard_title')); ?></h5>
                        <p><?php echo html_escape(_l('approvalflow_empty_dashboard_desc')); ?></p>
                        <?php if (staff_can('create_rules', APPROVALFLOW_MODULE_NAME)): ?>
                            <a href="<?php echo admin_url('approvalflow/rule_form'); ?>" class="btn btn-primary">
                                <i class="fa fa-plus" aria-hidden="true"></i>
                                <?php echo html_escape(_l('approvalflow_empty_dashboard_cta')); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>

                    <!-- ═════════════════════════════════════════════════
                         SECTION 3 — KPI tiles
                         Restricted scope (no view_all) shows the 3 personal
                         KPIs only (pending, approved/rejected this month).
                         view_all shows the global 6-tile set including avg
                         time, high value and top requester.
                         ════════════════════════════════════════════════ -->
                    <div class="row approvalflow-kpi-row">
                        <?php
                            // [icon, lang_key, value, modifier-suffix]
                            // Modifier drives the icon background color per domain,
                            // following DupliGuard's pattern (different hue per KPI category).
                            $tiles = [
                                ['fa-hourglass-half', 'kpi_pending',        (int) $kpis['pending'],         'pending'],
                                ['fa-check-double',   'kpi_approved_month', (int) $kpis['approved_month'],  'approved'],
                                ['fa-times',          'kpi_rejected_month', (int) $kpis['rejected_month'],  'rejected'],
                            ];
                            if ($can_view_all) {
                                $tiles[] = ['fa-stopwatch', 'kpi_avg_time',      $kpis['avg_time_hours'], 'time'];
                                $tiles[] = ['fa-coins',     'kpi_high_value',   (float) $kpis['high_value'], 'money'];
                                $tiles[] = ['fa-trophy',    'kpi_top_requester', $kpis['top_requester'],    'leader'];
                            }
                            $col_class = $can_view_all ? 'col-md-2 col-sm-4' : 'col-md-4 col-sm-4';
                        ?>
                        <?php foreach ($tiles as $i => $t):
                            list($icon, $key, $val, $mod) = $t;
                        ?>
                            <div class="<?php echo $col_class; ?> approvalflow-kpi-col">
                                <div class="approvalflow-card approvalflow-kpi approvalflow-kpi--<?php echo html_escape($mod); ?>">
                                    <div class="approvalflow-kpi__top">
                                        <div class="approvalflow-kpi__icon">
                                            <i class="fa <?php echo html_escape($icon); ?>" aria-hidden="true"></i>
                                        </div>
                                        <div class="approvalflow-kpi__spark" aria-hidden="true"></div>
                                    </div>
                                    <div class="approvalflow-kpi__value">
                                        <?php
                                        if ($key === 'kpi_avg_time') {
                                            echo $val === null
                                                ? '<span class="approvalflow-kpi__nodata">' . html_escape(_l('approvalflow_kpi_no_data')) . '</span>'
                                                : html_escape($val) . '<small>' . html_escape(_l('approvalflow_kpi_hours')) . '</small>';
                                        } elseif ($key === 'kpi_high_value') {
                                            echo ((float) $val) > 0
                                                ? html_escape(app_format_money((float) $val, ''))
                                                : '<span class="approvalflow-kpi__nodata">' . html_escape(_l('approvalflow_kpi_no_data')) . '</span>';
                                        } elseif ($key === 'kpi_top_requester') {
                                            if (is_array($val) && !empty($val['staff_id'])) {
                                                echo html_escape(get_staff_full_name((int) $val['staff_id']));
                                            } else {
                                                echo '<span class="approvalflow-kpi__nodata">' . html_escape(_l('approvalflow_kpi_no_data')) . '</span>';
                                            }
                                        } else {
                                            echo (int) $val;
                                        }
                                        ?>
                                    </div>
                                    <div class="approvalflow-kpi__label">
                                        <?php echo html_escape(_l('approvalflow_' . $key)); ?>
                                    </div>
                                    <?php if ($key === 'kpi_top_requester' && is_array($val) && !empty($val['count'])): ?>
                                        <div class="approvalflow-kpi__sub">
                                            <?php echo sprintf(html_escape(_l('approvalflow_kpi_n_requests')), (int) $val['count']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ═════════════════════════════════════════════════
                         SECTION 4 — Two-column working area
                         Left (col-md-8): "Recent pending" — clickable rows
                         that link to native document preview in new tab.
                         Right (col-md-4): "Recent activity" — vertical
                         timeline of latest state transitions across all
                         requests in scope (or own, when scoped).
                         ════════════════════════════════════════════════ -->
                    <div class="row approvalflow-dashboard-row">
                        <div class="col-md-8 mb-3">
                            <div class="approvalflow-card">
                                <div class="approvalflow-card__header">
                                    <h6><i class="fa fa-hourglass-half" aria-hidden="true"></i>
                                        <?php echo html_escape(_l('approvalflow_dashboard_recent_pending')); ?>
                                    </h6>
                                    <a href="<?php echo admin_url('approvalflow/pending'); ?>" class="approvalflow-card__link">
                                        <?php echo html_escape(_l('approvalflow_dashboard_view_all_pending')); ?> <i class="fa fa-chevron-right"></i>
                                    </a>
                                </div>
                                <div class="approvalflow-card__body p-0">
                                    <?php if (empty($recent_pending)): ?>
                                        <div class="approvalflow-empty approvalflow-empty--inline">
                                            <div class="approvalflow-empty__icon approvalflow-empty__icon--success">
                                                <i class="fa fa-check-circle" aria-hidden="true"></i>
                                            </div>
                                            <h5><?php echo html_escape(_l('approvalflow_empty_pending_title')); ?></h5>
                                            <p><?php echo html_escape(_l('approvalflow_empty_pending_desc')); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <table class="approvalflow-table approvalflow-table--stacked">
                                            <thead>
                                                <tr>
                                                    <th><?php echo html_escape(_l('approvalflow_request_type')); ?></th>
                                                    <th><?php echo html_escape(_l('approvalflow_request_document')); ?></th>
                                                    <th class="approvalflow-money-th"><?php echo html_escape(_l('approvalflow_request_amount')); ?></th>
                                                    <th><?php echo html_escape(_l('approvalflow_request_requester')); ?></th>
                                                    <th class="approvalflow-col-hide-sm"><?php echo html_escape(_l('approvalflow_request_created_at')); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_pending as $r): ?>
                                                    <?php
                                                        $snapshot = $r['entity_snapshot'] ? json_decode($r['entity_snapshot'], true) : [];
                                                        $amount = isset($snapshot['total']) ? (float) $snapshot['total'] : 0;
                                                        $req_name = trim(($r['requester_fn'] ?? '') . ' ' . ($r['requester_ln'] ?? ''));
                                                        if ($req_name === '') $req_name = '-';
                                                    ?>
                                                    <tr>
                                                        <td data-label="<?php echo html_escape(_l('approvalflow_request_type')); ?>">
                                                            <span class="approvalflow-entity-pill"><?php echo html_escape(_l('approvalflow_entity_' . $r['entity_type'])); ?></span>
                                                        </td>
                                                        <td data-label="<?php echo html_escape(_l('approvalflow_request_document')); ?>">
                                                            <a href="<?php echo $this->approvalflow_model->entity_admin_url($r['entity_type'], (int) $r['entity_id']); ?>"
                                                               target="_blank" rel="noopener">
                                                                #<?php echo (int) $r['entity_id']; ?>
                                                                <i class="fa fa-external-link" aria-hidden="true"></i>
                                                            </a>
                                                        </td>
                                                        <td class="approvalflow-money" data-label="<?php echo html_escape(_l('approvalflow_request_amount')); ?>">
                                                            <?php echo html_escape(app_format_money($amount, '')); ?>
                                                        </td>
                                                        <td data-label="<?php echo html_escape(_l('approvalflow_request_requester')); ?>">
                                                            <?php echo html_escape($req_name); ?>
                                                        </td>
                                                        <td class="approvalflow-col-hide-sm" data-label="<?php echo html_escape(_l('approvalflow_request_created_at')); ?>">
                                                            <span class="approvalflow-text-muted"><?php echo html_escape($r['created_at']); ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="approvalflow-card">
                                <div class="approvalflow-card__header">
                                    <h6><i class="fa fa-history" aria-hidden="true"></i>
                                        <?php echo html_escape(_l('approvalflow_dashboard_recent_activity')); ?>
                                    </h6>
                                </div>
                                <div class="approvalflow-card__body p-0">
                                    <?php if (empty($recent_activity)): ?>
                                        <div class="approvalflow-empty approvalflow-empty--inline">
                                            <div class="approvalflow-empty__icon"><i class="fa fa-history" aria-hidden="true"></i></div>
                                            <p><?php echo html_escape(_l('approvalflow_empty_history_desc')); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <ol class="approvalflow-timeline">
                                            <?php foreach ($recent_activity as $h): ?>
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
                                                            <?php if (!empty($h['entity_type']) && !empty($h['entity_id'])): ?>
                                                                <?php echo html_escape(_l('approvalflow_entity_' . $h['entity_type'])); ?>
                                                                #<?php echo (int) $h['entity_id']; ?> ·
                                                            <?php endif; ?>
                                                            <?php echo html_escape($actor); ?>
                                                        </div>
                                                    </div>
                                                    <time class="approvalflow-timeline__time"><?php echo html_escape($h['created_at']); ?></time>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
window.ApprovalFlowConfig = {
    csrf_token_name: '<?php echo $this->security->get_csrf_token_name(); ?>',
    csrf_hash: '<?php echo $this->security->get_csrf_hash(); ?>',
    urls: { dashboard: '<?php echo admin_url('approvalflow'); ?>' }
};
</script>
</body>
</html>
