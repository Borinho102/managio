<link rel="stylesheet" type="text/css" id="tailwind-css" href="<?= base_url('assets/builds/tailwind.css') ?>">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<title>Verify Purchase</title>
<?php
$ci = &get_instance();
echo form_open($ci->uri->uri_string(), array('id' => 'purchase_verify', 'class' => 'tw-form-horizontal disable-on-submit')); ?>

<div class="tw-max-w-4xl tw-w-full tw-mx-auto tw-my-6">

    <div id="logo" class="tw-py-2 tw-px-2 tw-h-[63px] tw-flex tw-items-center">
        <?php echo get_company_logo(get_admin_uri() . '/', '!tw-mt-0 tw-block tw-mx-auto') ?>
    </div>

    <div class="tw-bg-white tw-rounded tw-px-4 tw-py-6 tw-border tw-border-solid tw-border-neutral-200">
        <div class="col-md-12">
            <?php echo render_input('purchase_key', 'purchase_key', '', 'text', ['data-ays-ignore' => true]); ?>
        </div>
        <div class="col-md-12">
            <?php echo render_input('buyer', 'Envato Username', '', 'text', ['data-ays-ignore' => true]); ?>
        </div>
        <div class="col-md-12 text-danger">
            <?php
            if (count($errorList) > 0) {
                echo implode("</span><span style='color: red'>", $errorList);
            }
            ?>
        </div>
        <div class="row">
            <div class="col-md-12 m-2">
                <input type="submit" id="next" onclick="installDB();" value="<?= _l('Install') ?>" name="install"
                       class="btn btn-primary pull-right"/>
            </div>
        </div>
    </div>
</div>
<script>
    function installDB() {
        document.getElementById('next').disabled = true;
        document.getElementById('next').value = '<?= _l('Installing...') ?>';
        document.getElementById("purchase_verify").submit();
    }
</script>
