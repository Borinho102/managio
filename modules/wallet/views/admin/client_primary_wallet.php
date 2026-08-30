<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (!isset($client)) {
    return;
} ?>
<?php
$contact_id = wallet_resolve_client_contact_id($client->userid);

if (!$contact_id) {
    ?>
    <div class="alert alert-warning tw-mb-0">
        <p class="tw-mb-3"><?= _l('wallet_contact_not_found'); ?></p>
        <a href="<?= admin_url('clients/client/' . $client->userid . '?group=contacts&new_contact=true'); ?>"
            class="btn btn-primary">
            <?= _l('add_contact'); ?>
        </a>
    </div>
    <?php
    return;
}

$data                   = [];
$data['currency']       = get_base_currency();
$data['currency_code']  = $data['currency']->name;
$data['min_funding_amount'] = (int) get_option('wallet_min_funding_amount');
$data['max_funding_amount'] = (int) get_option('wallet_max_funding_amount');
$data['contact_id']     = $contact_id;
$data['contact']        = $this->clients_model->get_contact($contact_id);
$data['transactions']   = $this->wallet->ledger($contact_id);
$data['small_variant']  = true;
$this->load->view(WALLET_MODULE_NAME . '/wallet', $data);
