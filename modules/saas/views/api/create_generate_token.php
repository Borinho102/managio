<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php

if (!empty($token->token)) {
    $generate_token = $token->token;
} else {
    $generate_token = bin2hex(random_bytes(16));
}

echo form_open(base_url() . 'saas/api/save_token/' . (!empty($token->id) ? $token->id : ''), array('id' => 'creatives_heading_form'));
?>
<div class="panel panel-custom" data-collapsed="0">
    <div class="panel-heading">
        <div class="panel-title"><?= _l('generate') . ' ' . _l('token') ?></div>
    </div>

    <div class="modal-body">
        <div class="form-group clearfix">
            <label for="" class="control-label"><?= _l('title'); ?> <span class="required">*</span></label>
            <input type="text" value="<?= !empty($token->title) ? $token->title : '' ?>"
                   name="title"
                   class="form-control" required>
        </div>

        <div class="form-group">
            <label for="" class="control-label"><?= _l('token'); ?></label>
            <div class="input-group">
                <input type="text" value="<?= !empty($generate_token) ? $generate_token : '' ?>"
                       name="token"
                       class="form-control">
                <span class="input-group-btn">
                        <button type="button" class="btn btn-info" id="generate_token">
                            <i class="fa fa-refresh"></i>
                            <?= _l('generate') ?>
                        </button>
                    </span>
            </div>
        </div>
        <div class="form-group">
            <label for="" class="control-label "><?= _l('status') ?></label>
            <div class="">
                <div class="material-switch tw-mt-2">
                    <input name="status" id="ext_url" type="checkbox" value="1" <?php
                    if (!empty($token)) {
                        if ($token->status == 1) {
                            echo 'checked';
                        }
                    } ?> />
                    <label for="ext_url" class="label-success"></label>
                </div>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close') ?></button>
        <button type="submit" class="btn btn-primary"><?= _l('save') ?></button>
    </div>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    // click generate token button and generate token and set in input field
    $(document).on('click', '#generate_token', function () {
        $.ajax({
            url: base_url + 'saas/api/generated_token',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                $('input[name="token"]').val(response.token);
            }
        });
    });

</script>
