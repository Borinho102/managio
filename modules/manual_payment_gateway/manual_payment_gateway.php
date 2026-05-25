<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module Name: Custom & Offline Payment Gateway
 * Description: Create your own payment methods and accept payments your way.
 * Version: 1.0.1
 * Requires at least: 3.1.0
 * Author: CodeOnString
 * Author URI: https://codecanyon.net/user/codeonstring
 */

require_once __DIR__ .'/vendor/autoload.php';

define('MANUAL_PAYMENT_GATEWAY_MODULE_NAME', 'manual_payment_gateway');
define('MANUAL_PAYMENT_GATEWAY_MODULE_PATH', module_dir_path(MANUAL_PAYMENT_GATEWAY_MODULE_NAME));

hooks()->add_action('admin_init', 'manual_payment_gateway_module_init');
hooks()->add_action('admin_init', 'manual_payment_gateway_permissions');
hooks()->add_action('app_admin_footer', 'mpg_admin_footer');
hooks()->add_action('app_customers_footer', 'mpg_customer_footer');
hooks()->add_action('app_admin_head', function () {
    echo '<link href="' . module_dir_url(MANUAL_PAYMENT_GATEWAY_MODULE_NAME, 'assets/css/style.css') . '" rel="stylesheet" type="text/css">';
});
hooks()->add_action('app_customers_head', function () {
    echo '<link href="' . module_dir_url(MANUAL_PAYMENT_GATEWAY_MODULE_NAME, 'assets/css/style.css') . '" rel="stylesheet" type="text/css">';
});

function manual_payment_gateway_module_init()
{
    $CI = &get_instance();

    if (staff_can('view', MANUAL_PAYMENT_GATEWAY_MODULE_NAME) || staff_can('view_requests', MANUAL_PAYMENT_GATEWAY_MODULE_NAME) || staff_can('view_log', MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
    {
        $CI->app_menu->add_sidebar_menu_item('manual-payment-gateway', [
                'name' => _l('manual_gateway'),
                'icon' => 'fas fa-hand-holding-usd',
                'position' => 50,
                'collapse' => true,
        ]);
    }

    if (staff_can('view', MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
    {
        $CI->app_menu->add_sidebar_children_item('manual-payment-gateway', [
                'slug' => 'manage-gateways',
                'name' => _l('manage_gateways'),
                'href' => admin_url('manual_payment_gateway'),
        ]);
    }

    if (staff_can('view_requests', MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
    {
        $CI->app_menu->add_sidebar_children_item('manual-payment-gateway', [
                'slug' => 'payment-requests',
                'name' => _l('payment_requests'),
                'href' => admin_url('manual_payment_gateway/payment_requests'),
        ]);
    }

    if (staff_can('view_log', MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
    {
        $CI->app_menu->add_sidebar_children_item('manual-payment-gateway', [
                'slug' => 'payment-logs',
                'name' => _l('payment_logs'),
                'href' => admin_url('manual_payment_gateway/payment_logs'),
        ]);
    }

    register_activation_hook(MANUAL_PAYMENT_GATEWAY_MODULE_NAME, 'manual_payment_gateway_install');
    register_uninstall_hook(MANUAL_PAYMENT_GATEWAY_MODULE_NAME, 'manual_payment_gateway_uninstall');
}

function manual_payment_gateway_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
            'view' => _l('view'),
            'create' => _l('create'),
            'edit' => _l('edit'),
            'delete' => _l('delete'),
            'view_requests' => _l('mpg_view_requests'),
            'update_requests' => _l('mpg_update_requests'),
            'view_log' => _l('mpg_view_log'),
    ];

    register_staff_capabilities('manual_payment_gateway', $capabilities, _l('manual_payment_gateway'));
}

function mpg_admin_footer()
{
    $current_url = $_SERVER['REQUEST_URI'];
    if (preg_match('/invoices\/invoice/', $current_url))
    {
        $CI =& get_instance();

        $invoice_id = $CI->uri->segment($CI->uri->total_segments());

        $CI->db->where('id', $invoice_id);
        $invoice = $CI->db->get(db_prefix() . 'invoices')->row();

        $selected_modes = [];
        if ($invoice && !empty($invoice->allowed_payment_modes)) {
            $modes = @unserialize($invoice->allowed_payment_modes);
            if (is_array($modes)) {
                foreach ($modes as $mode) {
                    if (strpos($mode, 'mpg-') === 0) {
                        $selected_modes[] = $mode;
                    }
                }
            }
        }

        $CI->db->where('status', 1);
        $mgp_gateways = $CI->db->get(db_prefix() . 'manual_payment_gateways')->result();
        ?>
        <script>
            $(function ()
            {
                var $wrapper = $('.form-group.mbot15').has('label[for="allowed_payment_modes"]');
                var $select = $wrapper.find('select[name="allowed_payment_modes[]"]');

                if ($select.length === 0)
                {
                    $wrapper.contents().filter(function () {
                        return this.nodeType === 1 && this.tagName.toLowerCase() !== 'label';
                    }).remove();

                    var selectHtml = `
                        <br />
                        <select class="selectpicker"
                            name="allowed_payment_modes[]"
                            data-actions-box="true"
                            multiple="true"
                            data-width="100%"
                            data-title="<?= _l('dropdown_non_selected_tex'); ?>">
                        </select>
                    `;

                    $wrapper.append(selectHtml);
                    if ($.fn.selectpicker) {
                        $wrapper.find('select.selectpicker').selectpicker('refresh');
                    }
                }

                var mpgSelectedModes = <?php echo json_encode($selected_modes); ?>;
                var $selectBox = $('[name="allowed_payment_modes[]"]');
                <?php foreach ($mgp_gateways as $mgp_gateway) : ?>
                var mpgSelectValue = "mpg-<?php echo $mgp_gateway->id; ?>";
                var mpgIsSelected = mpgSelectedModes.includes(mpgSelectValue);
                var option = $("<option>", {
                    value: mpgSelectValue,
                    text: "<?php echo $mgp_gateway->name . ' (' . _l('manual_payment_gateway') . ')'; ?>",
                    selected: mpgIsSelected
                });
                $selectBox.append(option);
                <?php endforeach; ?>
                $selectBox.trigger('change');
            });
        </script>
    <?php
    }
    ?>
    <?php
    if (preg_match('/payments\/payment/', $current_url))
    {
        $hasPaymentName = isset($payment->name) && !empty($payment->name) ? 1 : 0;
        if(!$hasPaymentName)
        {
            ?>
            <script>
                $(function ()
                {
                    var paymentModeDiv = $('p.tw-text-neutral-600.tw-font-medium');
                    var paymentModeDivLen = paymentModeDiv.length;
                    if (paymentModeDivLen > 1)
                    {
                        var pmdText = paymentModeDiv.eq(1).find('.pull-right').text();
                        var newTextWithoutDash = pmdText.replace('-','');
                        paymentModeDiv.eq(1).find('.pull-right').text(newTextWithoutDash);
                    }
                });
            </script>
            <?php
        }
    }

    if (preg_match('/payments/', $current_url))
    {
        ?>
        <script>
            $(function ()
            {
                $(document).ajaxComplete(function ()
                {
                    fixTableThirdColumn();
                });

                function fixTableThirdColumn()
                {
                    $('#DataTables_Table_0 tr').each(function ()
                    {
                        var td = $(this).find('td').eq(2);
                        var text = td.text().trim();
                        var parts = text.split('-');

                        if (parts.length === 2 && parts[0].trim() === '')
                        {
                            td.text(parts[1].trim());
                        }
                    });
                }
            });
        </script>
        <?php
    }
}

function mpg_customer_footer()
{
    $CI =& get_instance();
    // for customer dashboard page only
    if ($CI->uri->total_segments() == 0 && is_client_logged_in())
    {
        $mpgAlerts = [];
        $uid = get_contact_user_id();
        $CI->db->where('user_id', $uid)->where('is_read', '0');
        $existingPaymentRequests = $CI->db->get(db_prefix() . 'manual_payment_requests')->result();
        foreach($existingPaymentRequests as $existingPaymentRequest)
        {
            if($existingPaymentRequest->status == 2)
            {
                $alert_html = '<div class="alert alert-info" role="alert"><button type="button" class="close mpg-alert-read-btn" data-id="'.$existingPaymentRequest->id.'"><span aria-hidden="true">&times;</span></button>'._l('mpg_payment_invoice_sent').'<strong> '.format_invoice_number($existingPaymentRequest->invoice_id).'</strong>, '._l('mpg_waiting_for_conformation').'</div>';
            }
            elseif ($existingPaymentRequest->status == 1)
            {
                $alert_html = '<div class="alert alert-success" role="alert"><button type="button" class="close mpg-alert-read-btn" data-id="'.$existingPaymentRequest->id.'"><span aria-hidden="true">&times;</span></button>'._l('mpg_payment_invoice_approved').'<strong> '.format_invoice_number($existingPaymentRequest->invoice_id).'</strong></div>';
            }
            else
            {
                $alert_html = '<div class="alert alert-danger" role="alert"><button type="button" class="close mpg-alert-read-btn" data-id="'.$existingPaymentRequest->id.'"><span aria-hidden="true">&times;</span></button>'._l('mpg_payment_invoice_rejected').'<strong> '.format_invoice_number($existingPaymentRequest->invoice_id).'</strong><br>'.$existingPaymentRequest->message.'</div>';
            }

            array_push($mpgAlerts, $alert_html);
        }
    }

    // for invoice page and payment page only
    if($CI->uri->segment(1) == 'invoice')
    {
        $invoice_hash = $CI->uri->segment($CI->uri->total_segments());
        $invoice_id = $CI->uri->segment($CI->uri->total_segments() - 1);

        $CI->db->where('id', $invoice_id);
        $invoice = $CI->db->get(db_prefix() . 'invoices')->row();

        $selected_modes = [];

        if ($invoice && !empty($invoice->allowed_payment_modes))
        {
            $modes = @unserialize($invoice->allowed_payment_modes);

            if (is_array($modes)) {
                foreach ($modes as $mode) {
                    if (strpos($mode, 'mpg-') === 0) {
                        // Extract ID from 'mpg-1' → 1
                        $id = (int) str_replace('mpg-', '', $mode);
                        if ($id > 0) {
                            $selected_modes[] = $id;
                        }
                    }
                }
            }
        }

        $CI->db->where('status', 1);
        if (!empty($selected_modes)) {
            $CI->db->where_in('id', $selected_modes);
        }
        $mgp_gateways = $CI->db->get(db_prefix() . 'manual_payment_gateways')->result();

        $CI->db->where('invoice_id', $invoice_id)->where('status', 2);
        $existingPayment = $CI->db->get(db_prefix() . 'manual_payment_requests')->row();
    }
    ?>
    <script>
        $(function ()
        {
            // for dashboard page only
            <?php if($CI->uri->total_segments() == 0 && is_client_logged_in()) {  ?>
                var alertMessages = <?= json_encode($mpgAlerts); ?>;

                var $dl = $('h3#greeting');
                $.each(alertMessages, function (index, html) {
                    $dl.after(html);
                });

                $(document).on('click', '.mpg-alert-read-btn', function(e) {
                    e.preventDefault();
                    var $this = $(this);
                    var alertId = $this.data('id');
                    $.ajax({
                        url: '<?php echo site_url('manual_payment_gateway/payment/mark_as_read'); ?>',
                        type: 'GET',
                        data: { id: alertId },
                        success: function(response)
                        {
                            $this.parent().fadeOut();
                        },
                        error: function(xhr, status, error)
                        {
                            console.error('Error:', error);
                        }
                    });
                });
            <?php } ?>

            // for invoice only
            <?php if($CI->uri->segment(1) == 'invoice') {  ?>
                var dueAmountElem = $('.table.text-right.tw-text-normal').find('span.text-danger');
                if(dueAmountElem.length === 0)
                {
                    return
                }

                var valueText = $('.table.text-right.tw-text-normal').find('span.text-danger').eq(1).text().trim();
                var numericValue = valueText.replace(/[^\d.]/g, '');
                var currencySymbol = valueText.replace(/[\d.,\s]/g, '');

                var hasGateways = <?= count($mgp_gateways) > 0 ? 'true' : 'false'; ?>;
                var offline_form_action_url_base = '<?= site_url('manual_payment_gateway/payment/process') ?>';
                var offline_form_action_url = offline_form_action_url_base+'/'+'<?= $invoice_id ?>'+'/'+'<?= $invoice_hash ?>'+'/'+'invoice_amount'+'/'+'payment_mode'

                if (!hasGateways)
                {
                    return;
                }

                if (!$('.invoice-html-offline-payments').length)
                {
                    var targetDiv = $('div.col-md-12.invoice-html-payments');
                    var nextColMd12 = targetDiv.nextAll('div.col-md-12').first();
                    if(!$('.online-payment-radio').length)
                    {
                        nextColMd12.find('div.row').append(`<div class="invoice-html-offline-payments col-md-12"><p class="tw-mb-2.5 tw-font-medium">
                                <?= _l('invoice_html_offline_payment'); ?>
                            </p></div>`);

                        var form_action_url = 'offline_payment_form';
                    }
                    else
                    {
                        nextColMd12.find('div.row').append(`<div class="invoice-html-offline-payments col-md-6 text-right"><p class="tw-mb-2.5 tw-font-medium">
                                <?= _l('invoice_html_offline_payment'); ?>
                            </p></div>`);

                        var form_action_url = 'online_payment_form';
                    }
                }

                $('#online_payment_form').append('<input type="hidden" name="make_payment" value="Pay Now">');
                let html = `<form id="offline_payment_form" action="${offline_form_action_url}">`;

            <?php foreach ($mgp_gateways as $gateway): ?>
                html +=`
                    <div class="radio radio-success offline-payment-radio">
                        <input type="radio" name="manual_payment_gateway" value="mpg-<?= $gateway->id; ?>" id="pm_mpg-<?= $gateway->id; ?>" style="margin: 0 5px 0 0;">
                        <label for="pm_mpg-<?= $gateway->id; ?>" style="margin: 0;">
                            <?= $gateway->name ?>
                        </label>
                    </div>`;
            <?php endforeach; ?>
                html += `</form>`;
                $('.invoice-html-offline-payments').append(html);

                var mpg_input_elem = $('.form-group input[name="amount"]').first();

                var mpg_input_amount = mpg_input_elem.val();
                var mpg_input_symbol = mpg_input_elem.siblings('span').first().html();

                var mpg_online_payment_form = $('#online_payment_form');

                mpg_online_payment_form.find('#pay_button').remove();
                mpg_online_payment_form.find('.form-group input[name="amount"]').parent().parent().remove();
                mpg_online_payment_form.append('<input type="hidden" name="amount" value="">');

                $('.col-md-10.col-md-offset-1').append(`
                    <div class="row"><hr>
                        <div class="col-md-6 form-group">
                            <label for="amount" class="control-label">
                                <?= _l('invoice_html_amount'); ?>
                            </label>
                            <div class="input-group">
                                <input type="number" name="amount" class="form-control"
                                       value="${numericValue}"
                                       id="new-amount-inp"
                                       max="${numericValue}"
                                       data-total="">
                                <span class="input-group-addon">${currencySymbol}</span>
                            </div>
                        </div>
                        <div class="col-md-12 form-group" id="new-amount-div">
                            <div id="pay_button">
                                <button id="pay_now" type="button" form="${form_action_url}" class="btn btn-success"><?= _l('invoice_html_online_payment_button_text'); ?></button>
                            </div>
                        </div>
                    </div>
                `);

                var invoice_amount = $('#new-amount-inp').val();
                var payment_mode = '';

                $(document).on('change', '.offline-payment-radio input[type="radio"]', function ()
                {
                    if ($(this).is(':checked'))
                    {
                        $('.online-payment-radio input[type="radio"]').prop('checked', false);
                        $('#pay_now').attr('form','offline_payment_form');
                        $('#mpg_invoice_amount').val(invoice_amount);
                        payment_mode = $(this).val();
                    }
                });

                $(document).on('change', '.online-payment-radio input[type="radio"]', function ()
                {
                    if ($(this).is(':checked'))
                    {
                        $('.offline-payment-radio input[type="radio"]').prop('checked', false);
                        $('#pay_now').attr('form','online_payment_form');
                        $('#online_payment_form input[name="amount"]').val($('#new-amount-inp').val());
                        $('#mpg_invoice_amount').val($('#new-amount-inp').val());
                    }
                });

                $('#new-amount-inp').on('input', function ()
                {
                    var newAmount = $(this).val();
                    $('#online_payment_form input[name="amount"]').val(newAmount);
                    $('#mpg_invoice_amount').val(newAmount);
                    invoice_amount = newAmount;
                });

                $(document).on('click', '#pay_now', function (e)
                {
                    e.preventDefault();

                    var form_type = $('#pay_now').attr('form');
                    if(form_type == 'online_payment_form')
                    {
                        $('#online_payment_form').submit();
                    }
                    else
                    {
                        offline_form_action_url = offline_form_action_url.replace('invoice_amount',invoice_amount);
                        offline_form_action_url = offline_form_action_url.replace('payment_mode',payment_mode);
                        $('#offline_payment_form').attr('action', offline_form_action_url);
                        $('#offline_payment_form').submit();
                    }
                });

                // invoice page alert
                <?php if($existingPayment) {  ?>
                    $('#new-amount-div').append(`<hr/><div class="alert alert-warning" role="alert">
                            <?php echo _l('mpg_already_pending_request') ?>
                        </div>`);
                <?php } ?>
            <?php } ?>
        });
    </script>
    <?php
}


register_language_files(MANUAL_PAYMENT_GATEWAY_MODULE_NAME, [MANUAL_PAYMENT_GATEWAY_MODULE_NAME]);

require_once(MANUAL_PAYMENT_GATEWAY_MODULE_PATH . 'install.php');