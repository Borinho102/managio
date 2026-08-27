<?php
$payment_modes = $payment_modes ?? [];
$requires_payment = !empty($requires_payment);
if (empty($payment_modes)) {
    if ($requires_payment) {
        echo '<p class="text-danger m0">' . _l('no_payment_methods_for_package') . '</p>';
    }
    return;
}
foreach ($payment_modes as $mode) {
    $id = $mode['id'] ?? '';
    $gateway = $mode['gateway_name'] ?? '';
    if ($id === '' || $gateway === '') {
        continue;
    }
    $label = _l($gateway);
    if ($label === $gateway) {
        $label = $gateway === 'payin' ? 'PayIn' : ucfirst($gateway);
    }
    ?>
    <div class="radio radio-success online-payment-radio pull-left mright10">
        <input type="radio" value="<?php echo html_escape($id); ?>"
               <?= $requires_payment ? 'required' : '' ?>
               id="pm_<?php echo html_escape($id); ?>" name="paymentmode">
        <label for="pm_<?php echo html_escape($id); ?>"><?php echo $label; ?></label>
    </div>
    <?php
}
