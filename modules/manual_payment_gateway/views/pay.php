<?php
defined('BASEPATH') or exit('No direct script access allowed');
echo payment_gateway_head(_l('payment_for_invoice') . ' ' . format_invoice_number($invoice->id)); ?>
<body class="gateway-stripe">
<div class="container">
    <div class="col-md-8 col-md-offset-2 mtop30">
        <div class="mbot30 text-center">
            <?php echo payment_gateway_logo(); ?>
        </div>
        <div class="row">
            <div class="panel_s">
                <div class="panel-heading">
                    <h3 class="no-margin bold text-center">
                        <?php echo _l('mpg_pay_using') . ' ' . $manual_payment_gateway->name; ?>
                    </h3>
                    <p class="text-center tw-mb-0 tw-pt-2"><?php echo _l('payment_for_invoice'); ?>
                    <a href="<?php echo site_url('invoice/' . $invoice->id . '/' . $invoice->hash); ?>">
                        <?php echo format_invoice_number($invoice->id); ?>
                    </a>
                </div>
                <div class="panel-body tw-pt-2">
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <h4><?php echo _l('payment_total', app_format_money($total, $invoice->currency_name)); ?></h4>
                            <hr>
                        </div>
                        <div class="col-md-12">
                            <?php if ($this->session->flashdata('custom_alert')) {
                                $alert = $this->session->flashdata('custom_alert'); ?>
                                <div class="alert alert-<?php echo $alert['type']; ?>" role="alert">
                                    <?php echo $alert['message']; ?>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-12">
                            <form action="<?php echo site_url('manual_payment_gateway/payment/submit_request') ?>" id="mpg-form" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>"/>
                                <input type="hidden" name="invoice_id" value="<?php echo $invoice->id; ?>"/>
                                <input type="hidden" name="invoice_hash" value="<?php echo $invoice->hash; ?>"/>
                                <input type="hidden" name="payment_id" value="<?php echo $manual_payment_gateway->id; ?>"/>
                                <input type="hidden" name="amount" value="<?php echo $total ?>"/>
                                <div class="row">
                                    <?php foreach ($formFields as $formField): ?>
                                        <?php if (!empty($formField['name'])): ?>
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
                                                    <label class="form-control-label" for="<?php echo $formField['name']; ?>">
                                                        <span class="text-danger"><?php echo $formField['required'] == 'true' ? ' * ' : ''; ?></span>
                                                        <?php echo $formField['label']; ?>
                                                    </label>
                                                    <?php if ($formField['type'] == 'textarea'): ?>
                                                        <textarea
                                                                class="form-control <?php echo $formField['required'] == 'true' ? 'required' : ''; ?>"
                                                                name="<?php echo $formField['name']; ?>"
                                                                id="<?php echo $formField['name']; ?>"
                                                        ><?php echo set_value($formField['name']); ?></textarea>

                                                    <?php elseif ($formField['type'] == 'file'): ?>
                                                        <input
                                                                type="file"
                                                                class="form-control <?php echo $formField['required'] == 'true' ? 'required' : ''; ?>"
                                                                id="<?php echo $formField['name']; ?>"
                                                                name="<?php echo $formField['name']; ?>">
                                                    <?php else: ?>
                                                        <input
                                                                class="form-control <?php echo $formField['required'] == 'true' ? 'required' : ''; ?>"
                                                                id="<?php echo $formField['name']; ?>"
                                                                type="<?php echo $formField['type']; ?>"
                                                                name="<?php echo $formField['name']; ?>"
                                                                value="<?php echo set_value($formField['name']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <div class="col-md-12 tw-pt-3">
                                        <button type="button" id="submit-btn" class="mpg-payment-button"><?php echo _l('mpg_submit'); ?></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-12">
                            <hr>
                            <h4 class="tw-pb-3"><?php echo _l('mpg_payment_instructions'); ?> : </h4>
                            <?php echo html_entity_decode($manual_payment_gateway->description); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo payment_gateway_scripts(); ?>
<script>
    $(document).on('click', '#submit-btn', function(e)
    {
        e.preventDefault();
        var i = 0;
        $(this).parents('form').find('input.required,select.required,textarea.required').each(function( value )
        {
            if($(this).val() == '')
            {
                $(this).focus();
                $(this).addClass('is-invalid');
                i = i + 1;
                return false;
            }
            else
            {
                $(this).removeClass('is-invalid');
            }
        });
        if(i == 0)
        {
            $(this).parents('form').submit();
        }
    });

    $(document).on('keyup', 'input.required', function()
    {
        if($(this).val() == '')
        {
            $(this).addClass('is-invalid');
        }
        else
        {
            $(this).removeClass('is-invalid');
        }
    });
</script>
<?php echo payment_gateway_footer(); ?>
