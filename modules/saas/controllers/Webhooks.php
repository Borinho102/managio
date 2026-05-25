<?php use Stripe\StripeClient;

defined('BASEPATH') or exit('No direct script access allowed');

class Webhooks extends App_Controller
{

    protected $provider;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
    }

    public function stripe()
    {
        if (!empty(ConfigItems('stripe_secret_key'))) {
            $this->provider = new StripeClient(
                ConfigItems('stripe_secret_key')
            );
        }

        $webhookInfo = get_old_result('tbl_saas_gateway_webhooks', ['gateway_name' => 'stripe'], false);
        $webhook_secret = $webhookInfo->webhook_secret;

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;
        if ($sig_header) {
            try {
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $webhook_secret
                );
            } catch (\UnexpectedValueException $e) {
                http_response_code(400);
                exit();
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                http_response_code(400);
                exit();
            }
        }

        switch ($event->type) {
            case 'customer.subscription.updated':
            case 'invoice.payment_failed':
            case 'invoice.payment_succeeded':
            case 'customer.subscription.pending_update_expired':
            case 'customer.subscription.pending_update_applied':
            case 'customer.subscription.trial_will_end':
            case 'checkout.session.completed':
                $this->update_stripe_package($event);
                break;
            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $customer = \Stripe\Customer::retrieve($subscription->customer);
                $company_id = $customer->metadata->company_id;
                $this->saas_model->updateCompanyPackage($company_id, 'stripe');
                break;
            default:
                http_response_code(400);
                break;
        }
        http_response_code(200);
    }

    public function update_stripe_package($event)
    {
        $subscription = $event->data->object;
        $customer = $this->provider->customers->retrieve($subscription->customer);
        $company_id = $customer->metadata->company_id;
        $transaction_id = $subscription->latest_invoice;

        $company = get_old_result('tbl_saas_companies', array('id' => $company_id), false);
        $package_id = $company->package_id;
        $package = get_old_result('tbl_saas_packages', array('id' => $package_id), false);
        $data = [
            'package_id' => $package_id,
            'company_id' => $company_id,
            'package_name' => $package->name,
            'frequency' => $company->frequency,
            'amount' => $package->{$company->frequency . '_price'},
            'subscription_id' => $subscription->id,
            'transaction_id' => $transaction_id,
            'price_id' => $subscription->metadata->price_id,
            'payment_method' => 'stripe',
            'currency' => default_currency(),
            'mark_paid' => true,
        ];
        $this->saas_model->update_company_packages($data);
    }

    public function paypal()
    {

        $webhookInfo = get_old_result('tbl_saas_gateway_webhooks', ['gateway_name' => 'paypal'], false);
        // get request headers
        $headers = getallheaders();
        $headers = array_change_key_case($headers, CASE_UPPER);

        // get request body
        $webhook_body = json_decode(file_get_contents('php://input'));

        $paymentGateway = 'Saas_Paypal';
        $gateway = new $paymentGateway();

        $data = [
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'],
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'],
            'cert_url' => $headers['PAYPAL-CERT-URL'],
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'],
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'],
            'webhook_id' => $webhookInfo->webhook_id,
            'webhook_event' => $webhook_body
        ];
        $response = $gateway->verifyWebhook($data);
        $result = json_decode($response, true);

        $subscription = get_old_result('tbl_saas_gateway_subscriptions', ['subscription_id' => $webhook_body->resource->billing_agreement_id], false);
        $subscription->transaction_id = $webhook_body->resource->id;

        if ($result['verification_status'] == "SUCCESS") {
            $this->saas_model->_table_name = 'tbl_saas_companies';
            $this->saas_model->_primary_key = 'id';

            switch ($webhook_body->event_type) {
                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    $_data = [
                        'status' => 'suspended',
                    ];
                    $this->saas_model->save_old($_data, $subscription->company_id);
                    break;
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    $_data = [
                        'status' => 'terminated',
                    ];
                    $this->saas_model->save_old($_data, $subscription->company_id);
                    break;
                case 'BILLING.SUBSCRIPTION.EXPIRED':
                    $_data = [
                        'status' => 'expired',
                    ];
                    $this->saas_model->save_old($_data, $subscription->company_id);
                    break;
                case 'BILLING.SUBSCRIPTION.CREATED':
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                case 'BILLING.SUBSCRIPTION.RE-ACTIVATED':
                case 'PAYMENT.SALE.COMPLETED':
                case 'BILLING.SUBSCRIPTION.UPDATED':
                    $this->update_paypal_package($subscription);
                    break;
                default:
                    http_response_code(400);
                    break;
            }
        } else {
            http_response_code(400);
        }
    }

    public function update_paypal_package($subscription)
    {
        $company_id = $subscription->company_id;
        $company = get_old_result('tbl_saas_companies', array('id' => $company_id), false);
        $package_id = $company->package_id;
        $package = get_old_result('tbl_saas_packages', array('id' => $package_id), false);
        $data = [
            'package_id' => $package_id,
            'company_id' => $company_id,
            'package_name' => $package->name,
            'frequency' => $company->frequency,
            'amount' => $package->{$company->frequency . '_price'},
            'subscription_id' => $subscription->subscription_id,
            'transaction_id' => $subscription->transaction_id,
            'payment_method' => 'paypal',
            'currency' => default_currency(),
            'mark_paid' => true,
        ];
        $this->saas_model->update_company_packages($data);
    }


}
