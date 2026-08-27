<?php
$payment_modes = $payment_modes ?? [];
$requires_payment = !empty($requires_payment);
if (!is_array($payment_modes)) {
    $payment_modes = [];
}

$items = [];
foreach ($payment_modes as $mode) {
    $id = is_array($mode) ? ($mode['id'] ?? '') : ($mode->id ?? '');
    $gateway = is_array($mode) ? ($mode['gateway_name'] ?? '') : ($mode->gateway_name ?? '');
    if ($id === '' || $gateway === '') {
        continue;
    }
    $label = _l($gateway);
    if ($label === $gateway) {
        $label = $gateway === 'payin' ? 'PayIn' : ucfirst((string) $gateway);
    }
    $items[] = ['id' => $id, 'gateway' => $gateway, 'label' => $label];
}

if (empty($items)) {
    if ($requires_payment) {
        echo '<p class="text-danger m0">' . _l('no_payment_methods_for_package') . '</p>';
    }
    return;
}

$single = count($items) === 1;
echo '<div class="clearfix">';
foreach ($items as $item) {
    $checked = $single ? ' checked' : '';
    ?>
    <div class="radio radio-success online-payment-radio pull-left mright10">
        <input type="radio" value="<?php echo html_escape($item['id']); ?>"
               <?= $requires_payment ? 'required' : '' ?>
               <?= $checked ?>
               id="pm_<?php echo html_escape($item['id']); ?>" name="paymentmode">
        <label for="pm_<?php echo html_escape($item['id']); ?>"><?php echo $item['label']; ?></label>
    </div>
    <?php
}
echo '</div>';
