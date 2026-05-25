
<div class="row">
    <div class="col-sm-12" data-spy="scroll" data-offset="0">
        <div class="panel panel-custom">
            <div class="panel-heading">
                <div class="panel-title"><?= lang('update'); ?></div>
            </div>
            <div class="panel-body">
                <div class="col-md-12">
                    <?php echo render_input('purchase_key', 'purchase_key', '', 'text', ['data-ays-ignore' => true]); ?>
                </div>
                <div class="col-md-12">
                    <?php echo render_input('buyer', 'envato_username','', 'text', ['data-ays-ignore' => true]); ?>
                </div>

                <div class="col-md-6 text-center">
                    <div class="alert alert-<?php echo $latest_version > $current_version ? 'danger' : 'info'; ?>">
                        <h4 class="tw-font-bold !tw-text-base tw-mb-1"><?php echo _l('your_version'); ?></h4>
                        <p class="tw-font-semibold tw-mb-0"><?php echo wordwrap($current_version, 1, '.', true); ?></p>
                    </div>
                </div>

                <div class="col-md-6 text-center">
                    <div class="alert alert-<?php if ($latest_version > $current_version) {
                        echo 'success';
                    } elseif ($latest_version == $current_version) {
                        echo 'info';
                    } ?>">
                        <h4 class="tw-font-bold !tw-text-base tw-mb-1"><?php echo _l('latest_version'); ?></h4>
                        <p class="tw-font-semibold tw-mb-0"><?php echo wordwrap($latest_version, 1, '.', true); ?></p>
                        <?php echo form_hidden('latest_version', $latest_version); ?>
                    </div>
                </div>

                <div class="clearfix"></div>
                <hr/>
                <div class="col-md-12 text-center">
                    <?php if ($current_version != $latest_version && $latest_version > $current_version) { ?>
                        <div class="alert alert-warning">
                            Before performing an update, it is <b>strongly recommended to create a full backup</b> of
                            your current
                            installation <b>(files and database)</b> and review the changelog.
                        </div>
                        <h3 class="bold text-center mbot20"><i class="fa-solid fa-circle-exclamation"
                                                               aria-hidden="true"></i>
                            <?php echo _l('update_available'); ?></h3>
                        <div class="update_app_wrapper" data-wait-text="<?php echo _l('wait_text'); ?>"
                             data-original-text="<?php echo _l('update_now'); ?>">
                            <br/>
                            <a href="#" id="update_app" class="btn btn-success">Download files</a>
                        </div>
                        <div id="update_messages" class="mtop25 text-left"></div>
                    <?php } else { ?>
                        <h3 class="tw-font-medium text-success">
                            <?php echo _l('using_latest_version'); ?>
                        </h3>
                    <?php } ?>
                    <?php if (count($update_errors) > 0) { ?>
                        <div class="tw-mt-5">
                            <p class="text-danger">Please fix the errors listed below.</p>
                            <?php foreach ($update_errors as $error) { ?>
                                <div class="alert alert-danger">
                                    <?php echo $error; ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (isset($update_info->additional_data)) { ?>
                        <div class="tw-mt-5">
                            <?php echo $update_info->additional_data; ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(function () {
        $('#update_app').on('click', function (e) {
            e.preventDefault();
            $('input[name="purchase_key"]').parents('.form-group').removeClass('has-error');
            $('input[name="buyer"]').parents('.form-group').removeClass('has-error');

            var purchase_key = $('input[name="purchase_key"]').val();
            var buyer = $('input[name="buyer"]').val();
            var latest_version = $('input[name="latest_version"]').val();
            var update_errors;
            if (purchase_key != '' && buyer != '') {
                var ubtn = $(this);
                ubtn.html('Please wait...');
                ubtn.addClass('disabled');
                $.post('<?= base_url() ?>saas/auto_update', {
                    purchase_key: purchase_key,
                    latest_version: latest_version,
                    buyer: buyer,
                    auto_update: true
                }).done(function (res) {
                    console.log('res', res);
                    if (res) {
                        var result = JSON.parse(res);
                        $('#update_messages').html('<div class="alert alert-danger mt-lg"></div>');
                        $('#update_messages .alert').append('<p>' + result.message + '</p>');
                        ubtn.removeClass('disabled');
                        ubtn.html($('.update_app_wrapper').data('original-text'));
                    } else {
                        $.post('<?= base_url() ?>saas/auto_update/database', {
                            auto_update: true
                        }).done(function (res) {
                            $('#update_messages').html('<div class="alert alert-success mt-lg"></div>');
                            $('#update_messages .alert').append('<p>' + res + '</p>');
                            ubtn.removeClass('disabled');
                            ubtn.html($('.update_app_wrapper').data('original-text'));
                            setTimeout(function () {
                                window.location.reload();
                            }, 5000);
                        }).fail(function (response) {
                            $('#update_messages').html('<div class="alert alert-danger mt-lg"></div>');
                            $('#update_messages .alert').append('<p>' + response + '</p>');
                            ubtn.removeClass('disabled');
                            ubtn.html($('.update_app_wrapper').data('original-text'));
                        });
                    }
                }).fail(function (response) {
                    $('#update_messages').html('<div class="alert alert-danger mt-lg"></div>');
                    $('#update_messages .alert').append('<p>' + response.responseText + '</p>');
                    ubtn.removeClass('disabled');
                    ubtn.html($('.update_app_wrapper').data('original-text'));
                });
            } else if (purchase_key != '' && buyer == '') {
                $('input[name="purchase_key"]').parents('.form-group').removeClass('has-error');
                $('input[name="buyer"]').parents('.form-group').addClass('has-error');
            } else if (buyer != '' && purchase_key == '') {
                $('input[name="purchase_key"]').parents('.form-group').addClass('has-error');
                $('input[name="buyer"]').parents('.form-group').removeClass('has-error');
            } else {
                $('input[name="purchase_key"]').parents('.form-group').addClass('has-error');
                $('input[name="buyer"]').parents('.form-group').addClass('has-error');
            }
        });
    });
</script>