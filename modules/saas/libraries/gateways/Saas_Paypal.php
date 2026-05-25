<?php
defined('BASEPATH') or exit('No direct script access allowed');

require APP_MODULES_PATH . 'saas/vendor/autoload.php';

use Srmklive\PayPal\Services\PayPal as PayPalClient;

class Saas_Paypal extends Saas_payment
{
    public bool $processingFees = true;
    protected $provider;
    protected $gateway = 'paypal';
    protected $currency = 'USD';

    protected $ci;

    /**
     * @throws Throwable
     */

    public function __construct()
    {
        $this->ci = &get_instance();
    }

    public function accessToken(): void
    {
        $paypal_live = ConfigItems('paypal_live');
        $paypal_client_id = ConfigItems('paypal_client_id');
        $paypal_secret = ConfigItems('paypal_secret_key');
        $app_id = ConfigItems('paypal_app_id');

        $config = [
            'mode' => $paypal_live ? 'live' : 'sandbox',
            'live' => [
                'client_id' => $paypal_client_id,
                'client_secret' => $paypal_secret,
                'app_id' => $app_id,
            ],
            'sandbox' => [
                'client_id' => $paypal_client_id,
                'client_secret' => $paypal_secret,
                'app_id' => $app_id,
            ],

            'payment_action' => 'Sale',
            'currency' => $this->currency,
            'notify_url' => site_url('paypal/notify'),
            'locale' => ConfigItems('default_locale') ? ConfigItems('default_locale') : 'en_US',
            'validate_ssl' => (bool)$paypal_live,
        ];


        $this->provider = new PayPalClient($config);
        $this->provider->getAccessToken();

    }


    /**
     * @throws \Throwable
     */
    public function syncProducts($package = null): array
    {
        $result = [];
        // get all packages from database which are published
        if ((!empty($package))) {
            $packages = [$package];
        } else {
            $packages = all_packages();
        }
        foreach ($packages as $package) {
            // check package is already exist or not
            $product = $this->getProduct($package->id);

            if (!empty($product['id'])) {
                // update product
                $result[] = $this->updateProduct($product['id'], $package);
            } else {
                // create product
                $result[] = $this->createProduct($package);
            }

        }

        // check if any error in result
        $isError = false;
        foreach ($result as $res) {
            if ($res['status'] == 'error') {
                $isError = true;
                break;
            }
        }
        if (!$isError) {
            // create webhook for PayPal
            $this->createWebhook();
        }

        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function syncModuleProducts($package = null): array
    {

        $result = [];
        // get all packages from database which are published
        if ((!empty($package))) {
            $packages = [(object)$package];
        } else {
            $packages = get_old_result('tbl_saas_package_module', ['status' => 'published'], 'object');
        }


        foreach ($packages as $package) {
            $package->name = $package->module_title;
            $package->id = $package->package_module_id;
            $package->monthly_price = (!empty($package->monthly_price)) ? $package->monthly_price : $package->price;
            $package->trial_period = (!empty($package->trial_period)) ? $package->trial_period : 0;

            // check package is already exist or not
            $product = $this->getProduct($package->id, 'module');
            if (!empty($product['id'])) {
                // update product
                $result[] = $this->updateProduct($product['id'], $package);
            } else {
                // create product
                $result[] = $this->createProduct($package, 'module');
            }
        }

        // check if any error in result
        $isError = false;
        foreach ($result as $res) {
            if (!empty($res['status']) && $res['status'] == 'error') {
                $isError = true;
                break;
            }
        }
        if (!$isError) {
            // create webhook for PayPal
            $this->createWebhook('module');
        }

        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function createWebhook($type = 'package')
    {
        if ($type == 'package') {
            $url = base_url('webhooks/paypal');
        } else {
            $url = base_url('webhooks/paypal_module');
        }

        $events = [
            'PAYMENT.SALE.COMPLETED',
            'PAYMENT.SALE.REFUNDED',
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.SUSPENDED',
            'BILLING.SUBSCRIPTION.EXPIRED',
            'BILLING.SUBSCRIPTION.RENEWED',
            'BILLING.SUBSCRIPTION.CREATED',
        ];
        // check webhook is already exist or not
        $webhookInfo = get_old_result('tbl_saas_gateway_webhooks', ['gateway_name' => $this->gateway, 'type' => $type], false);

        $this->accessToken();

        $this->ci->saas_model->_table_name = 'tbl_saas_gateway_webhooks';
        $this->ci->saas_model->_primary_key = 'id';

        if (!empty($webhookInfo)) {
            // delete webhook
            $this->provider->deleteWebhook($webhookInfo->webhook_id);

            // create new webhook
            $webhook = $this->provider->createWebhook($url, $events);

            if (!empty($webhook['id'])) {
                $data = [
                    'webhook_id' => $webhook['id'],
//                    'webhook_secret' => $webhook['webhook_secret'],
                ];
                $this->ci->saas_model->save_old($data, $webhookInfo->id);
            }

        } else {
            // check webhook is already exist or not
            $webhooks = $this->provider->listWebhooks();
            if (!empty($webhooks['webhooks'])) {
                $webhooks = $webhooks['webhooks'];
            } else {
                $webhooks = [];
            }
            foreach ($webhooks as $webhook) {
                if ($webhook['url'] == $url) {
                    $this->provider->deleteWebhook($webhook['id']);
                    break;
                }
            }

            $webhook = $this->provider->createWebhook($url, $events);

            if (!empty($webhook['id'])) {
                $data = [
                    'webhook_id' => $webhook['id'],
                    'type' => $type,
//                    'webhook_secret' => $webhook['webhook_secret'],
                    'gateway_name' => $this->gateway
                ];
                $this->ci->saas_model->save_old($data);
            }
        }
        return $webhook;
    }

    /**
     * @throws \Throwable
     */
    public function getProduct($package_id, $type = 'package')
    {
        $product = get_old_result('tbl_saas_gateway_products', ['package_id' => $package_id, 'gateway_name' => $this->gateway, 'type' => $type], false);
        if ($product) {
            $this->accessToken();
            return $this->provider->showProductDetails($product->product_id);
        }
        return false;
    }

    public function createProduct($package, $type = 'package')
    {
        try {

            $this->accessToken();

            $data = [
                'name' => $package->name,
                'description' => 'Billing for ' . $package->name,
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ];

            $request_id = uniqid();
            $product = $this->provider->createProduct($data, $request_id);


            if (!empty($product['id'])) {
                $this->createPlan($product['id'], $package, $type);
                return [
                    'status' => 'success',
                    'message' => 'Product created successfully',
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => $product['error']['message'],
                ];

            }
        } catch (\Throwable $th) {
            return [
                'status' => 'error',
                'message' => $th->getMessage(),
            ];
        }
    }

    /**
     * @throws \Throwable
     */
    public function updateProduct($product_id, $package)
    {

        $product = get_old_result('tbl_saas_gateway_products', ['product_id' => $product_id, 'gateway_name' => $this->gateway], false);
        $plan_id = json_decode($product->price_id, true);
        $result = [
            'status' => 'success',
            'message' => 'Product updated successfully',
        ];
        if (isset($plan_id['monthly'])) {
            $price = $plan_id['monthly']['price'];
            // check price is same or not
            if ($price != $package->monthly_price) {
                $res = $this->updatePlan($plan_id['monthly']['id'], $package->monthly_price, 2);
                if (!empty($res['error'])) {
                    $error = json_decode($res['error'], true);
                    $result = [
                        'status' => 'error',
                        'message' => $error['message'],
                    ];
                }
            }
        }

        if (isset($plan_id['yearly'])) {
            $price = $plan_id['yearly']['price'];
            // check price is same or not
            if ($price != $package->yearly_price) {
                $res = $this->updatePlan($plan_id['yearly']['id'], $package->yearly_price);
                if (!empty($res['error'])) {
                    $error = json_decode($res['error'], true);
                    $result = [
                        'status' => 'error',
                        'message' => $error['message'],
                    ];
                }
            }
        }

        if (isset($plan_id['lifetime'])) {
            $price = $plan_id['lifetime']['price'];
            // check price is same or not
            if ($price != $package->lifetime_price) {
                $res = $this->updatePlan($plan_id['lifetime']['id'], $package->lifetime_price);
                if (!empty($res['error'])) {
                    $error = json_decode($res['error'], true);
                    $result = [
                        'status' => 'error',
                        'message' => $error['message'],
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function updatePlan($plan_id, $price, $sequence = 1)
    {
        $data = [
            [
                'billing_cycle_sequence' => $sequence,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $price,
                        'currency_code' => $this->currency
                    ],
                ],
            ],
        ];

        $this->accessToken();

        return $this->provider->updatePlanPricing($plan_id, $data);
    }


    /**
     * @throws \Throwable
     */
    private function createdPlan($data, $trial_period = null)
    {

        $interval_unit = 'DAY';
        if ($data['type'] == 'Monthly') {
            $interval_unit = 'MONTH';
        } elseif ($data['type'] == 'Yearly') {
            $interval_unit = 'YEAR';
        }

        $sequence = 1;
        if (!empty($trial_period)) {
            $sequence = 2;
            $billingTrial = [
                'frequency' => [
                    'interval_unit' => 'DAY',
                    'interval_count' => 1,
                ],
                'tenure_type' => 'TRIAL',
                'sequence' => 1,
                'total_cycles' => $trial_period ? $trial_period : 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $data['price'],
                        'currency_code' => $this->currency,
                    ],
                ],
            ];
        }

        $billingCycle = [
            'frequency' => [
                'interval_unit' => $interval_unit,
                'interval_count' => 1,
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => $sequence,
            'total_cycles' => $trial_period ? $trial_period : 0,
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => $data['price'],
                    'currency_code' => $this->currency,
                ],
            ],
        ];
        if (!empty($billingTrial)) {
            $billingCycle = [$billingTrial, $billingCycle];
        } else {
            $billingCycle = [$billingCycle];
        }

        $paymentPreferences = [
            'auto_bill_outstanding' => true,
            'setup_fee' => [
                'value' => 0,
                'currency_code' => $this->currency,
            ],
            'setup_fee_failure_action' => 'CANCEL',
            'payment_failure_threshold' => 3,
        ];

        if ($trial_period) {
            $paymentPreferences['auto_bill_outstanding'] = false; // Disable auto-billing during trial
        }

        $this->accessToken();

        $request_id = uniqid();
        $plan = $this->provider->createPlan([
            'product_id' => $data['product_id'],
            'name' => $data['name'] . ' ' . $data['type'],
            'description' => $data['description'] . ' ' . $data['type'],
            'status' => 'ACTIVE',
            'billing_cycles' => $billingCycle,
            'payment_preferences' => $paymentPreferences,
        ], $request_id);

        return $plan;
    }

    public function createPlan($product_id, $package, $type = 'package')
    {

        $data = [
            'product_id' => $product_id,
            'name' => $package->name,
            'description' => 'Billing for ' . $package->name,
            'type' => 'Monthly',
            'price' => round($package->monthly_price, 2),
        ];


        $plan_id = [];
        $result = [];
        $monthlyPlan = $this->createdPlan($data, $package->trial_period);


        if (!empty($monthlyPlan['id'])) {
            $plan_id['monthly'] = ['id' => $monthlyPlan['id'], 'price' => $package->monthly_price];
            $result[] = ['status' => 'success', 'message' => 'Plan created successfully'];
        } else {
            $result[] = ['status' => 'error', 'message' => $monthlyPlan['error']['message']];
        }

        if (!empty($package->yearly_price)) {
            $data['type'] = 'Yearly';
            $data['price'] = $package->yearly_price;
            $yearlyPlan = $this->createdPlan($data);

            if (!empty($yearlyPlan['id'])) {
                $plan_id['yearly'] = ['id' => $yearlyPlan['id'], 'price' => $package->yearly_price];
                $result[] = ['status' => 'success', 'message' => 'Plan created successfully'];
            } else {
                $result[] = ['status' => 'error', 'message' => $yearlyPlan['error']['message']];
            }
        }

        if (!empty($package->lifetime_price)) {
            $data['type'] = 'Lifetime';
            $data['price'] = $package->lifetime_price;
            $lifetimePlan = $this->createdPlan($data);
            if (!empty($lifetimePlan['id'])) {
                $plan_id['lifetime'] = ['id' => $lifetimePlan['id'], 'price' => $package->lifetime_price];
                $result[] = ['status' => 'success', 'message' => 'Plan created successfully'];
            } else {
                $result[] = ['status' => 'error', 'message' => $lifetimePlan['error']['message']];
            }
        }

        $this->ci->saas_model->updateProduct($product_id, json_encode($plan_id), $package, $this->gateway, $type);

        return $result;
    }

    public function getPaymentForm($package)
    {
        $product = get_old_result('tbl_saas_gateway_products', array('package_id' => $package['package_id'], 'gateway_name' => $this->gateway), false);
        $price_id = json_decode($product->price_id, true);
        $package['product_id'] = $product->product_id;
        $package['frequency'] = str_replace('_price', '', $package['billing_cycle']);
        if (empty($price_id[$package['frequency']]['id'])) {
            return false;
        }
        $package['priceId'] = $price_id[$package['frequency']]['id'];
        $package['post'] = $package;
        $paymentForm = $this->ci->load->view('companies/payments/paypal', $package, true);
        $_data['paymentForm'] = $paymentForm;
        return $_data;
    }

    public function verifyWebhook($data)
    {
        $this->accessToken();
        return $this->provider->verifyWebHook($data);
    }

    // resume_subscription
    public function resume_subscription($subscription)
    {
        $this->accessToken();
        $response = $this->provider->activateSubscription($subscription->subscription_id, 'Re-activated');

        if (!empty($response['error'])) {
            $response = json_decode($response['error'], true);
            return ['status' => 'error', 'message' => $response['message']];
        } else {
            return ['status' => 'success', 'message' => 'Subscription re-activated successfully'];
        }
    }

    // cancel_subscription
    public function cancel_subscription($subscription)
    {
        $this->accessToken();
        // call paypal api to suspend subscription
        $response = $this->provider->suspendSubscription($subscription->subscription_id, 'Suspended');


        if (!empty($response['error'])) {
            $response = json_decode($response['error'], true);
            return ['status' => 'error', 'message' => $response['message']];
        } else {
            return ['status' => 'success', 'message' => 'Subscription Suspended successfully'];
        }
    }

    public function subscribe($data)
    {
        $package_module_id = $data['package_module_id'];
        $product = get_old_result('tbl_saas_gateway_products', array('package_id' => $package_module_id, 'type' => 'module', 'gateway_name' => $this->gateway), false);

        $price_id = json_decode($product->price_id, true);
        $data['product_id'] = $product->product_id;
        $data['frequency'] = 'monthly';

        if (empty($price_id[$data['frequency']]['id'])) {
            return false;
        }
        $data['priceId'] = $price_id[$data['frequency']]['id'];
        $data['post'] = $data;

        $paymentForm = $this->ci->load->view('companies/payments/paypal', $data, true);
        $_data['paymentForm'] = $paymentForm;
        return $_data;
    }
}
