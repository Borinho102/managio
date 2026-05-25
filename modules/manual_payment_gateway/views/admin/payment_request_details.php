<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css" />
<div id="wrapper">
    <div class="content">
        <?= form_open($this->uri->uri_string(), ['id' => 'mpg-form']); ?>
        <div class="tw-max-w-4xl tw-mx-auto">
            <div class="tw-flex tw-justify-between tw-mb-2">
                <div>
                    <h4 class="tw-my-0 tw-text-lg tw-font-bold tw-text-neutral-700">
                        <?= e($title); ?>
                    </h4>
                </div>
            </div>
            <div class="panel_s">
                <div class="panel-heading tw-bg-white text-right">
                    <?php echo $status; ?>
                </div>
                <div class="panel-body">
                    <form action="<?php echo admin_url('manual_payment_gateway/payment_requests/' . $request_data->id); ?>" method="POST">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>"/>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-control-label">
                                        <?= _l('payment_mode'); ?>
                                    </label>
                                    <input class="form-control" readonly type="text" value="<?php echo $request_data->gateway_name; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-control-label">
                                        <?= _l('amount'); ?>
                                    </label>
                                    <input class="form-control" readonly type="text" value="<?php echo $invoice_amount; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-control-label">
                                        <?= _l('date'); ?>
                                    </label>
                                    <input class="form-control" readonly type="text" value="<?php echo $created_at; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-control-label">
                                        <?= _l('invoice'); ?>
                                    </label>
                                    <input class="form-control" readonly type="text" value="<?php echo $invoice_id; ?>">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-control-label">
                                        <?= _l('customer'); ?>
                                    </label>
                                    <input class="form-control" readonly type="text" value="<?php echo $customer_name; ?>">
                                </div>
                            </div>
                            <?php foreach ($form_data as $formField): ?>
                                <?php if (!empty($formField['label'])): ?>
                                    <?php
                                    if ($formField['width'] == '25') {
                                        $class = 'col-md-4 col-sm-12';
                                    } elseif ($formField['width'] == '50') {
                                        $class = 'col-md-6 col-sm-12';
                                    } elseif ($formField['width'] == '75') {
                                        $class = 'col-md-8 col-sm-12';
                                    } else {
                                        $class = 'col-12';
                                    }
                                    ?>
                                    <div class="<?php echo $class; ?>">
                                        <div class="form-group">
                                            <?php if ($formField['type'] != 'file'): ?>
                                                <label class="form-control-label" for="<?php echo $formField['label']; ?>">
                                                    <?php echo $formField['label']; ?>
                                                </label>
                                            <?php endif; ?>
                                            <?php if ($formField['type'] == 'textarea'): ?>
                                                <textarea class="form-control" readonly><?php echo $formField['value']; ?></textarea>
                                            <?php elseif ($formField['type'] == 'file'): array_push($mpg_files, ['label' => $formField['label'], 'file_path' => $formField['value']]); ?>
                                            <?php else: ?>
                                                <input class="form-control" readonly type="<?php echo $formField['type']; ?>" value="<?php echo $formField['value']; ?>">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <div class="col-md-12 text-right">
                                <?php if (!empty($mpg_files)) : ?>
                                    <a data-fancybox="files-gallery" href="<?= base_url($mpg_files[0]['file_path']); ?>" data-caption="<?= $mpg_files[0]['label']; ?>" class="btn btn-default">
                                        <?= _l('mpg_view_files'); ?>
                                    </a>
                                    <?php for ($i = 1; $i < count($mpg_files); $i++) : ?>
                                        <a data-fancybox="files-gallery" href="<?= base_url($mpg_files[$i]['file_path']); ?>" data-caption="<?= $mpg_files[$i]['label']; ?>" style="display:none;"></a>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </div>
                            <?php if($request_data->status == '2' && staff_can('update_requests', MANUAL_PAYMENT_GATEWAY_MODULE_NAME)): ?>
                                <div class="col-md-12">
                                    <hr>
                                    <label class="form-control-label">
                                        <?= _l('status'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" value="1" id="approve">
                                        <label class="form-check-label" for="approve">
                                            <?php echo _l('mpg_approve'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" value="0" id="reject">
                                        <label class="form-check-label" for="reject">
                                            <?php echo _l('mpg_reject'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-12 tw-pt-2 hide" id="reject-div">
                                    <div class="form-group">
                                        <label class="form-control-label">
                                            <?= _l('mpg_rejection_reason'); ?>
                                        </label>
                                        <input class="form-control" type="text" name="message" id="message">
                                    </div>
                                </div>
                                <div class="col-md-12 text-right">
                                    <button type="submit" class="btn btn-primary">
                                        <?= _l('submit'); ?>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="col-md-12">
                                    <hr>
                                    <label class="form-control-label">
                                        <?= _l('status'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" value="1" id="approve" <?php echo $request_data->status == '1' ? 'checked' : ''; ?> disabled>
                                        <label class="form-check-label" for="approve">
                                            <?php echo _l('mpg_approve'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" value="0" id="reject" <?php echo $request_data->status == '0' ? 'checked' : ''; ?> disabled>
                                        <label class="form-check-label" for="reject">
                                            <?php echo _l('mpg_reject'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-12 tw-pt-2 <?php echo $request_data->status == '0' ? '' : 'hide'; ?>">
                                    <div class="form-group">
                                        <label class="form-control-label">
                                            <?= _l('mpg_rejection_reason'); ?>
                                        </label>
                                        <input class="form-control" type="text" name="message" id="message" value="<?php echo $request_data->message; ?>" <?php echo !empty($request_data->message) ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                            <?php endif ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    <?= form_close(); ?>
</div>
<?php init_tail(); ?>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
<script>
    $(document).ready(function ()
    {
        $(document).on("click", "input[name='status']", function () {
            if ($(this).val() == 0) {
                $('#reject-div').removeClass('hide');
            } else {
                $('#reject-div').addClass('hide');
            }
        });
    });
</script>
</body>
</html>