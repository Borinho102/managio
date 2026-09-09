<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Reject modal partial
 *
 * Confirmation dialog opened from the pending list when a user clicks
 * "Reject". Hardening choices:
 *   - Static backdrop + keyboard:false → prevents accidental dismiss
 *     (a stray click outside or Esc keypress would otherwise lose the
 *     comment the user just typed).
 *   - Submit button stays disabled until the textarea has ≥3 chars
 *     when the `require_reject_comment` setting is on (validated again
 *     server-side — never trust client gating alone).
 *   - 500-char max + live counter for UX clarity.
 *
 * Included once at the bottom of pending/list.php. JS in approvalflow.js
 * populates #approvalflowRejectSummary with the entity label + amount
 * before showing the modal.
 *
 * @package ApprovalFlow
 * @since   1.0.0
 */
?>
<div class="modal fade approvalflow-modal-shell" id="approvalflowRejectModal"
     tabindex="-1" role="dialog"
     data-backdrop="static" data-keyboard="false"
     aria-labelledby="approvalflowRejectModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content approvalflow-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo html_escape(_l('approvalflow_close')); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="approvalflowRejectModalTitle">
                    <i class="fa fa-times-circle approvalflow-text-danger" aria-hidden="true"></i>
                    <?php echo html_escape(_l('approvalflow_reject_modal_title')); ?>
                </h4>
            </div>
            <div class="modal-body">
                <p class="approvalflow-modal-intro"><?php echo html_escape(_l('approvalflow_reject_modal_intro')); ?></p>
                <div class="approvalflow-modal-summary" id="approvalflowRejectSummary">
                    <!-- populated by JS with the entity label + amount -->
                </div>
                <label class="approvalflow-form-label" for="approvalflowRejectComment">
                    <?php echo html_escape(_l('approvalflow_reject_reason')); ?> *
                </label>
                <textarea id="approvalflowRejectComment"
                          rows="4"
                          maxlength="500"
                          class="approvalflow-form-control"
                          placeholder="<?php echo html_escape(_l('approvalflow_reject_reason_placeholder')); ?>"></textarea>
                <div class="approvalflow-modal-meta">
                    <small class="approvalflow-form-hint"><?php echo html_escape(_l('approvalflow_reject_reason_hint')); ?></small>
                    <small class="approvalflow-form-counter" id="approvalflowRejectCounter">0 / 500</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?php echo html_escape(_l('approvalflow_cancel')); ?>
                </button>
                <button type="button" id="approvalflowRejectSubmit"
                        class="approvalflow-btn-action approvalflow-btn-action--reject approvalflow-btn-action--solid"
                        disabled>
                    <i class="fa fa-times" aria-hidden="true"></i>
                    <span class="approvalflow-btn-label"><?php echo html_escape(_l('approvalflow_reject_submit')); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>
