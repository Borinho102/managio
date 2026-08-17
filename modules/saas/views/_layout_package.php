<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo payment_gateway_head() ?>

<script>
    let list = null;
    let base_url = "<?php echo base_url(); ?>"
</script>
<?php

echo csrf_jquery_token()
?>
<?php echo payment_gateway_scripts(); ?>

<?php
$_ci_alert_types = ['success', 'danger', 'warning', 'info'];
$_ci_alert_html  = '';
foreach ($_ci_alert_types as $_ci_type) {
    $_ci_msg = get_instance()->session->flashdata('message-' . $_ci_type);
    if (!empty($_ci_msg)) {
        $_ci_alert_html .= '<div class="alert alert-' . $_ci_type . ' alert-dismissible" role="alert" style="margin-bottom:12px;">'
            . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
            . htmlspecialchars($_ci_msg)
            . '</div>';
    }
}
?>
<div class="row container tw-mx-auto tw-pt-24 ">
    <?php if (!empty($_ci_alert_html)) : ?>
        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-12">
                <?php echo $_ci_alert_html; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="">
            <?php echo $subview ?>
        </div>
    </div>
</div>

<?php $this->load->view('saas/_layout_modal'); ?>
