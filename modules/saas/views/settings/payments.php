<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="horizontal-scrollable-tabs panel-full-width-tabs">
    <div class="scroller arrow-left tw-mt-px"><i class="fa fa-angle-left"></i></div>
    <div class="scroller arrow-right tw-mt-px"><i class="fa fa-angle-right"></i></div>
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <?php
            foreach ($payment_gateways as $gateway) {
                ?>
                <li role="presentation">
                    <a href="#online_payments_<?php echo $gateway->id; ?>_tab"
                       onclick="setTab('<?= $gateway->gateway_name ?>')"
                       aria-controls="online_payments_paypal_tab"
                       role="tab" data-toggle="tab">
                        <?php echo _l($gateway->gateway_name); ?>
                    </a>
                </li>
                <?php
            } ?>
        </ul>

    </div>
</div>

<div class="tab-content mtop30">
    <input type="hidden" name="_for" value="payment_gateway">
    <input type="hidden" name="gateway_name" id="_gateway_name">

    <?php

    foreach ($payment_gateways as $gateway) { ?>
        <div role="tabpanel" class="tab-pane" id="online_payments_<?php echo $gateway->id; ?>_tab">

            <?php
            for ($key = 1; $key <= 5; $key++) {
                $field = 'field_' . $key;
                $payment_field = $gateway->$field;
                if (!empty($payment_field) && strpos((string)$payment_field, '|') !== false) {
                    $fieldType = explode('|', $payment_field);
                } else {
                    // Handle case where | is not found or $payment_field is null
                    $fieldType = [$payment_field]; // or any other handling logic
                }
                if (!empty($fieldType[0])) {
                    if (!empty($fieldType[1])) {
                        if ($fieldType[1] === 'password') {
                            echo render_input('settings[' . $fieldType[0] . ']', $fieldType[0], get_option($fieldType[0]), 'password');
                        } elseif ($fieldType[1] === 'textarea') {
                            echo render_textarea('settings[' . $fieldType[0] . ']', $fieldType[0], get_option($fieldType[0]));
                        } elseif ($fieldType[1] === 'checkbox') {
                            echo render_yes_no_option(($fieldType[0]), $fieldType[0]);
                        } elseif ($fieldType[1] === 'select') {
                            $options = explode(',', $fieldType[2]);
                            $selectOptions = [];
                            foreach ($options as $option) {
                                $selectOptions[] = [
                                    'value' => $option,
                                    'label' => $option,
                                ];
                            }
                            echo render_select('settings[' . $fieldType[0] . ']', $selectOptions, ['value', 'label'], $fieldType[0], get_option($fieldType[0]));
                        }
                    } else {
                        echo render_input('settings[' . $fieldType[0] . ']', $fieldType[0], get_option($fieldType[0]));
                    }

                }

            }
            $gateway_name_status = 'payments_' . (strtolower($gateway->gateway_name)) . '_status';
            echo render_yes_no_option(($gateway_name_status), _l('status'));
            ?>
        </div>
        <?php
    } ?>
</div>
<script>
    function setTab(gateway_name) {
        $('#_gateway_name').val(gateway_name);
    }
</script>
