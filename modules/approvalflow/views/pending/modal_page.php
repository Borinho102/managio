<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — View request (full-page fallback)
 *
 * Rendered when the user navigates directly to /view_request/<id> from
 * a notification deep link, email or browser history — bypassing the
 * AJAX modal path. Reuses pending/modal_view.php for the body to keep
 * the markup consistent regardless of entry path.
 *
 * Inputs (set by Approvalflow::view_request()):
 *   $request, $history, $doc_url — same payload as the AJAX fragment.
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
                <div class="approvalflow-hero approvalflow-hero--compact">
                    <div class="approvalflow-hero__icon">
                        <i class="fa fa-circle-check" aria-hidden="true"></i>
                    </div>
                    <div class="approvalflow-hero__body">
                        <div class="approvalflow-hero__eyebrow"><?php echo html_escape(_l('approvalflow_module_eyebrow')); ?></div>
                        <h4><?php echo html_escape(_l('approvalflow_view_request')); ?> #<?php echo (int) $request['id']; ?></h4>
                    </div>
                    <div class="approvalflow-hero__actions">
                        <a href="<?php echo admin_url('approvalflow/pending'); ?>" class="btn btn-default">
                            <?php echo html_escape(_l('approvalflow_back')); ?>
                        </a>
                    </div>
                </div>
                <div class="approvalflow-card">
                    <div class="approvalflow-card__body">
                        <?php $this->load->view('approvalflow/pending/modal_view', [
                            'request' => $request,
                            'history' => $history,
                            'doc_url' => $doc_url,
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
