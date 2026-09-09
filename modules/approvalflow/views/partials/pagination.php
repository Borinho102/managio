<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Pagination partial
 *
 * Renders a server-side paginator below the data tables of pending,
 * rules and history. Expected vars (from controller via $pager):
 *   - page         (int)    current 1-based page
 *   - per_page     (int)    rows per page
 *   - total        (int)    total matching rows across all pages
 *   - total_pages  (int)    max(1, ceil(total / per_page))
 *   - base_url     (string) absolute admin URL of the listing
 *   - query        (array)  filter key→value to preserve in pager links
 *
 * Only renders if total > per_page (single-page listings don't need a pager).
 *
 * @package ApprovalFlow
 */

if (!isset($pager) || !is_array($pager)) { return; }
$p_total = (int) ($pager['total'] ?? 0);
$p_per   = max(1, (int) ($pager['per_page'] ?? 50));
$p_pages = max(1, (int) ($pager['total_pages'] ?? 1));
$p_page  = max(1, min($p_pages, (int) ($pager['page'] ?? 1)));
if ($p_total <= $p_per) { return; }

$p_base  = (string) ($pager['base_url'] ?? '');
$p_query = is_array($pager['query'] ?? null) ? $pager['query'] : [];

// Window from-to of the current page (1-based, inclusive on both sides)
$from = (($p_page - 1) * $p_per) + 1;
$to   = min($p_total, $p_page * $p_per);

// URL builder — preserves filters and overrides the page param.
$build_url = function ($target_page) use ($p_base, $p_query) {
    $qs = $p_query;
    $qs['page'] = (int) $target_page;
    return $p_base . '?' . http_build_query($qs);
};

// Window of page links to render: current ± 2 + first/last.
$window = [];
$start = max(1, $p_page - 2);
$end   = min($p_pages, $p_page + 2);
for ($i = $start; $i <= $end; $i++) { $window[] = $i; }
?>
<nav class="approvalflow-pager" aria-label="<?php echo html_escape(_l('approvalflow_pagination_page_of', '', false) ?: 'Pagination'); ?>">
    <div class="approvalflow-pager__count">
        <?php echo sprintf(html_escape(_l('approvalflow_pagination_showing')), (int) $from, (int) $to, (int) $p_total); ?>
    </div>
    <ul class="approvalflow-pager__list">
        <li class="approvalflow-pager__item">
            <?php if ($p_page > 1): ?>
                <a class="approvalflow-pager__link" href="<?php echo html_escape($build_url(1)); ?>" aria-label="<?php echo html_escape(_l('approvalflow_pagination_first')); ?>">
                    <i class="fa fa-angle-double-left" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="approvalflow-pager__link approvalflow-pager__link--disabled" aria-disabled="true">
                    <i class="fa fa-angle-double-left" aria-hidden="true"></i>
                </span>
            <?php endif; ?>
        </li>
        <li class="approvalflow-pager__item">
            <?php if ($p_page > 1): ?>
                <a class="approvalflow-pager__link" href="<?php echo html_escape($build_url($p_page - 1)); ?>" rel="prev">
                    <i class="fa fa-angle-left" aria-hidden="true"></i>
                    <span class="approvalflow-pager__link-label"><?php echo html_escape(_l('approvalflow_pagination_prev')); ?></span>
                </a>
            <?php else: ?>
                <span class="approvalflow-pager__link approvalflow-pager__link--disabled" aria-disabled="true">
                    <i class="fa fa-angle-left" aria-hidden="true"></i>
                    <span class="approvalflow-pager__link-label"><?php echo html_escape(_l('approvalflow_pagination_prev')); ?></span>
                </span>
            <?php endif; ?>
        </li>

        <?php if ($start > 1): ?>
            <li class="approvalflow-pager__item approvalflow-pager__item--ellipsis" aria-hidden="true">…</li>
        <?php endif; ?>

        <?php foreach ($window as $n): ?>
            <li class="approvalflow-pager__item">
                <?php if ($n === $p_page): ?>
                    <span class="approvalflow-pager__link approvalflow-pager__link--active" aria-current="page"><?php echo (int) $n; ?></span>
                <?php else: ?>
                    <a class="approvalflow-pager__link" href="<?php echo html_escape($build_url($n)); ?>"><?php echo (int) $n; ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>

        <?php if ($end < $p_pages): ?>
            <li class="approvalflow-pager__item approvalflow-pager__item--ellipsis" aria-hidden="true">…</li>
        <?php endif; ?>

        <li class="approvalflow-pager__item">
            <?php if ($p_page < $p_pages): ?>
                <a class="approvalflow-pager__link" href="<?php echo html_escape($build_url($p_page + 1)); ?>" rel="next">
                    <span class="approvalflow-pager__link-label"><?php echo html_escape(_l('approvalflow_pagination_next')); ?></span>
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="approvalflow-pager__link approvalflow-pager__link--disabled" aria-disabled="true">
                    <span class="approvalflow-pager__link-label"><?php echo html_escape(_l('approvalflow_pagination_next')); ?></span>
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </span>
            <?php endif; ?>
        </li>
        <li class="approvalflow-pager__item">
            <?php if ($p_page < $p_pages): ?>
                <a class="approvalflow-pager__link" href="<?php echo html_escape($build_url($p_pages)); ?>" aria-label="<?php echo html_escape(_l('approvalflow_pagination_last')); ?>">
                    <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="approvalflow-pager__link approvalflow-pager__link--disabled" aria-disabled="true">
                    <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                </span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
