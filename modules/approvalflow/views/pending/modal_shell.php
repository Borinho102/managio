<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Empty shell for the "View request" modal
 *
 * Static modal scaffold rendered with the pending list. The body
 * #approvalflowViewRequestBody starts with a loading spinner and is
 * replaced lazily by the AJAX fragment from
 * Approvalflow::view_request() on row click. Keeping the shell here
 * — rather than building it on every click — avoids reflow flicker
 * and works with the Perfex modal CSS straight away.
 *
 * @package ApprovalFlow
 * @since   1.0.0
 */
?>
<div class="modal fade approvalflow-modal-shell" id="approvalflowViewRequestModal"
     tabindex="-1" role="dialog"
     aria-labelledby="approvalflowViewRequestTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content approvalflow-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo html_escape(_l('approvalflow_close')); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="approvalflowViewRequestTitle">
                    <?php echo html_escape(_l('approvalflow_view_request')); ?>
                </h4>
            </div>
            <div class="modal-body" id="approvalflowViewRequestBody">
                <div class="approvalflow-modal-loading">
                    <span class="approvalflow-spinner" aria-hidden="true"></span>
                    <?php echo html_escape(_l('approvalflow_loading')); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?php echo html_escape(_l('approvalflow_close')); ?>
                </button>
            </div>
        </div>
    </div>
</div>
