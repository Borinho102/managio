<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Payin_gateway extends App_gateway
{
    public function __construct()
    {
        parent::__construct();
        $this->setId(PAYIN_MODULE_GATEWAY_ID);
        $this->setName('PayIn Wallet');
        hooks()->add_action('before_render_payment_gateway_settings', [$this, 'settingsNotice']);
        $this->setSettings([
            [
                'name'             => 'api_base_url',
                'label'            => 'PayIn API Base URL',
                'type'             => 'input',
                'field_attributes' => ['readonly' => true],
            ],
            [
                'name'             => 'client_id',
                'label'            => 'PayIn Client ID',
                'type'             => 'input',
                'field_attributes' => ['readonly' => true],
            ],
            [
                'name'             => 'client_secret',
                'label'            => 'PayIn Client Secret',
                'encrypted'        => true,
                'type'             => 'input',
                'input_type'       => 'password',
                'field_attributes' => ['readonly' => true],
            ],
            [
                'name'          => 'enable_wallet',
                'label'         => 'Enable PayIn Wallet on checkout',
                'type'          => 'yes_no',
                'default_value' => 1,
            ],
            [
                'name'          => 'enable_pawapay',
                'label'         => 'Enable Pawapay on checkout',
                'type'          => 'yes_no',
                'default_value' => 1,
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'XAF',
            ],
            [
                'name'             => 'payin_user_id',
                'label'            => 'PayIn User ID',
                'type'             => 'input',
                'field_attributes' => ['readonly' => true],
            ],
            [
                'name'             => 'payin_merchant_id',
                'label'            => 'PayIn Merchant ID',
                'type'             => 'input',
                'field_attributes' => ['readonly' => true],
                'after'            => $this->settingsAfterHtml(),
            ],
        ]);
    }

    public function process_payment(array $data): void
    {
        $invoiceId = (int) $data['invoiceid'];
        $hash = $data['invoice']->hash;
        $amount = (float) $data['amount'];
        $currency = Payin_client::normalizeCurrency($data['invoice']->currency_name ?? $this->getSetting('currencies'));

        try {
            $client = $this->makeClient();
            $token = bin2hex(random_bytes(20));
            $pending = [
                'invoice_id' => $invoiceId,
                'hash'       => $hash,
                'amount'     => $amount,
                'currency'   => $currency,
            ];
            $this->ci->session->set_userdata('payin_pending_' . $token, $pending);
            add_option('payin_pending_' . $token, json_encode($pending), 0);

            $successUrl = site_url('payin/success/' . $invoiceId . '/' . $hash . '/' . $token);
            $cancelUrl = site_url('payin/cancel/' . $invoiceId . '/' . $hash . '/' . $token);
            $approvedUrl = $client->createExpressCheckout(
                $amount,
                $currency,
                $successUrl,
                $cancelUrl,
                $this->getSetting('enable_wallet') != '0',
                $this->getSetting('enable_pawapay') != '0'
            );
            redirect($approvedUrl);
        } catch (Throwable $e) {
            log_message('error', 'PayIn invoice checkout failed: ' . $e->getMessage());
            set_alert('danger', 'PayIn: ' . $e->getMessage());
            redirect(site_url('invoice/' . $invoiceId . '/' . $hash));
        }
    }

    public function makeClient(): Payin_client
    {
        if (!class_exists('Payin_client', false)) {
            require_once module_dir_path(PAYIN_MODULE_NAME) . 'libraries/Payin_client.php';
        }

        $client = Payin_client::fromSsoConfig();
        $base = $this->getSetting('api_base_url') ?: $client->baseUrl();
        $client->setMerchantCredentials(
            $this->getSetting('client_id'),
            $this->decryptSetting('client_secret'),
            $base
        );

        return $client;
    }

    private function settingsAfterHtml(): string
    {
        $connect = site_url('payin/connect');
        $open = site_url('payin/open');
        $sync = site_url('payin/sync');

        return '<div class="tw-mt-4 tw-mb-2">'
            . '<a href="' . html_escape($connect) . '" class="btn btn-primary tw-mr-2"><i class="fa fa-link"></i> Connect PayIn</a>'
            . '<a href="' . html_escape($open) . '" class="btn btn-default tw-mr-2" target="_blank"><i class="fa fa-wallet"></i> Open wallet</a>'
            . '<a href="' . html_escape($sync) . '" class="btn btn-default"><i class="fa fa-refresh"></i> Sync</a>'
            . '</div>'
            . '<p class="text-muted tw-mt-3">'
            . 'Label and API credentials are filled by Connect / Sync. PayIn SSO is configured by super-admin only.'
            . '</p>';
    }

    public function settingsNotice($gateway): void
    {
        if (($gateway['id'] ?? '') !== $this->getId()) {
            return;
        }

        echo '<div class="alert alert-info">'
            . 'Use <strong>Connect PayIn</strong> or <strong>Sync</strong> to set the wallet label, client ID, client secret, user ID, and merchant ID. '
            . 'Tenants cannot type these values. Super-admin configures the PayIn API URL and SSO keys under SaaS settings.'
            . '</div>';
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var names = [
                    "settings[paymentmethod_payin_label]",
                    "settings[paymentmethod_payin_api_base_url]",
                    "settings[paymentmethod_payin_client_id]",
                    "settings[paymentmethod_payin_client_secret]",
                    "settings[paymentmethod_payin_payin_user_id]",
                    "settings[paymentmethod_payin_payin_merchant_id]"
                ];
                names.forEach(function(name) {
                    var el = document.querySelector("[name=\'" + name + "\']");
                    if (el) {
                        el.setAttribute("readonly", "readonly");
                    }
                });
            });
        </script>';
    }
}
