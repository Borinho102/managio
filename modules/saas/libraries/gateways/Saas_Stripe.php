<?php
defined('BASEPATH') or exit('No direct script access allowed');

require APP_MODULES_PATH . 'saas/vendor/autoload.php';

use Stripe\StripeClient;

class Saas_Stripe extends Saas_payment
{
    public bool $processingFees = true;
    protected $provider;
    protected $gateway = 'stripe';
    protected $type = 'package';
    protected $currency = 'USD';

    protected $ci;

    /**
     * @throws Throwable
     */

    public function __construct()
    {
        $this->ci = &get_instance();
    }

    public function setProvider()
    {
        if (!empty(ConfigItems('stripe_secret_key'))) {
            $this->provider = new StripeClient(
                ConfigItems('stripe_secret_key')
            );
        }
    }


    // syncProducts
    public function syncProducts()
    {
        $this->setProvider();

        $customersId = [];
        $customers = $this->provider->customers->all()->data;

        foreach ($customers as $customer) {
            $customersId[] = $customer->id;
        }
        // all company
        $companies = get_old_result('tbl_saas_companies');
        if (!empty($companies)) {
            foreach ($companies as $company) {
                // get all stipe compay from
                $stripe_company = get_old_result('tbl_saas_gateway_subscriptions', ['company_id' => $company->id, 'gateway_name' => 'stripe'], false);
                if (!empty($stripe_company)) {
                    if (!in_array($stripe_company->customer_id, $customersId)) {
                        // create stripe customer
                        $this->createCustomer($company);
                    }
                } else {
                    $this->createCustomer($company);
                }
            }
        }

        $result = [];
        if ((!empty($package))) {
            $packages = [$package];
        } else {
            $packages = all_packages();
        }
        foreach ($packages as $package) {
            $product = $this->getProduct($package->id);
            if (!empty($product->id)) {
                // update product
                $result[] = $this->updateProduct($product->id, $package);
            } else {
                // create product
                $result[] = $this->createProduct($package);
            }
        }

        $isError = false;
        foreach ($result as $res) {
            if (!empty($res['status']) && $res['status'] == 'error') {
                $isError = true;
                break;
            }
        }
        if (!$isError) {
            $this->createWebhook();
        }

        return $result;
    }

    // syncProducts
    public function syncModuleProducts($package = null)
    {

        $result = [];
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

            $product = $this->getProduct($package->id, 'module');
            if (!empty($product->id)) {
                // update product
                $result[] = $this->updateProduct($product->id, $package);
            } else {
                // create product
                $result[] = $this->createProduct($package, 'module');
            }
        }

        $isError = false;
        foreach ($result as $res) {
            if (!empty($res['status']) && $res['status'] == 'error') {
                $isError = true;
                break;
            }
        }
        if (!$isError) {
            $this->createWebhook('module');
        }

        return $result;
    }

    // createWebhook
    private function createWebhook($type = 'package')
    {

        $this->setProvider();

        if ($type == 'package') {
            $url = base_url('webhooks/stripe');
        } else {
            $url = base_url('webhooks/stripe_module');
        }

        $events = [
            'checkout.session.completed',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.paid',
            'invoice.payment_failed',
        ];

        $webhookInfo = get_old_result('tbl_saas_gateway_webhooks', ['gateway_name' => $this->gateway], false);

        $this->ci->saas_model->_table_name = 'tbl_saas_gateway_webhooks';
        $this->ci->saas_model->_primary_key = 'id';

        if (!empty($webhookInfo)) {
            // delete webhook from stripe
            $this->provider->webhookEndpoints->delete($webhookInfo->webhook_id);
            // create new webhook
            $webhook = $this->provider->webhookEndpoints->create([
                'url' => $url,
                'enabled_events' => $events,
            ]);

            if (!empty($webhook->id)) {
                $data = [
                    'webhook_id' => $webhook->id,
                    'type' => $type,
                    'webhook_secret' => $webhook->secret,
                ];
                $this->ci->saas_model->save_old($data, $webhookInfo->id);
            }
        } else {
            $webhook = $this->provider->webhookEndpoints->create([
                'url' => $url,
                'enabled_events' => $events,
            ]);

            if (!empty($webhook->id)) {
                $data = [
                    'webhook_id' => $webhook->id,
                    'webhook_secret' => $webhook->secret,
                    'type' => $type,
                    'gateway_name' => $this->gateway
                ];
                $this->ci->saas_model->save_old($data);
            }
        }
    }


    // updateProduct
    private function updateProduct($product_id, $package)
    {
        $product = get_old_result('tbl_saas_gateway_products', ['product_id' => $product_id, 'gateway_name' => $this->gateway], false);
        $plan_id = json_decode($product->price_id, true);

        $this->setProvider();

        $newPlan = [];
        if (isset($plan_id['monthly'])) {
            $priceID = $plan_id['monthly']['id'];
            $price = $plan_id['monthly']['price'];
            // check price is same or not
            if ($price != $package->monthly_price) {
                // delete price from stripe
                $this->provider->prices->update($priceID, [
                    'active' => false,
                ]);
                // create new price
                $monthlyPlan = $this->createPlan($product_id, $package->monthly_price);
                if (!empty($monthlyPlan->id)) {
                    $newPlan['monthly'] = ['id' => $monthlyPlan->id, 'price' => $package->monthly_price];
                }
            }
        }

        if (isset($plan_id['yearly'])) {
            $priceID = $plan_id['yearly']['id'];
            $price = $plan_id['yearly']['price'];

            // check price is same or not
            if ($price != $package->yearly_price) {
                // delete price from stripe
                $this->provider->prices->update($priceID, [
                    'active' => false,
                ]);
                // create new price
                $yearlyPlan = $this->createPlan($product_id, $package->yearly_price, 'yearly');
                if (!empty($yearlyPlan->id)) {
                    $newPlan['yearly'] = ['id' => $yearlyPlan->id, 'price' => $package->yearly_price];
                }
            }
        }
        if (isset($plan_id['lifetime'])) {
            $priceID = $plan_id['lifetime']['id'];
            $price = $plan_id['lifetime']['price'];
            // check price is same or not
            if ($price != $package->lifetime_price) {
                // delete price from stripe
                $this->provider->prices->update($priceID, [
                    'active' => false,
                ]);
                // create new price
                $lifetimePlan = $this->createPlan($product_id, $package->lifetime_price, 'lifetime');
                if (!empty($lifetimePlan->id)) {
                    $newPlan['lifetime'] = ['id' => $lifetimePlan->id, 'price' => $package->lifetime_price];
                }
            }
        }

        if (!empty($newPlan)) {
            // replace new plan  with old plan according to key (monthly, yearly, lifetime) in price_id if price is updated
            $newPlan = array_merge($plan_id, $newPlan);
            $this->ci->saas_model->updateProduct($product_id, json_encode($newPlan), $package, $this->gateway);
        }
    }

    // createProduct
    private function createProduct($package, $type = 'package')
    {
        $this->setProvider();

        $product = $this->provider->products->create([
            'name' => $package->name,
            'type' => 'service',
        ]);


        if (!empty($product->id)) {
            $monthly_price = $package->monthly_price;
            $yearly_price = (!empty($package->yearly_price)) ? $package->yearly_price : 0;
            $lifetime_price = (!empty($package->lifetime_price)) ? $package->lifetime_price : 0;
            $plan_id = [];
            $result = [];
            if ($monthly_price > 0) {
                $monthlyPlan = $this->createPlan($product->id, $monthly_price);
                if (!empty($monthlyPlan->id)) {
                    $plan_id['monthly'] = ['id' => $monthlyPlan->id, 'price' => $package->monthly_price];
                    $result[] = ['status' => 'success', 'message' => 'Plan created successfully'];
                } else {
                    $result[] = ['status' => 'error', 'message' => 'Plan not created'];
                }
            }
            if ($yearly_price > 0) {
                $yearlyPlan = $this->createPlan($product->id, $yearly_price, 'yearly');
                if (!empty($yearlyPlan->id)) {
                    $plan_id['yearly'] = ['id' => $yearlyPlan->id, 'price' => $package->yearly_price];
                    $result[] = ['status' => 'success', 'message' => 'Plan created successfully'];
                } else {
                    $result[] = ['status' => 'error', 'message' => 'Plan not created'];
                }
            }
            if ($lifetime_price > 0) {
                $lifetimePlan = $this->createPlan($product->id, $lifetime_price, 'lifetime');
                if (!empty($lifetimePlan->id)) {
                    $plan_id['lifetime'] = ['id' => $lifetimePlan->id, 'price' => $package->lifetime_price];
                    $result[] = ['status' => 'success', 'message' => 'Plan created successfully'];
                } else {
                    $result[] = ['status' => 'error', 'message' => 'Plan not created'];
                }
            }

            $this->ci->saas_model->updateProduct($product->id, json_encode($plan_id), $package, $this->gateway, $type);

            return $result;
        } else {
            return [
                'status' => 'error',
                'message' => 'Product not created',
            ];
        }
    }

    // createPlan
    private function createPlan($product_id, $price, $type = 'monthly')
    {
        $this->setProvider();

        $type = strtolower($type);
        if ($type == 'monthly') {
            $interval_unit = 'month';
        } elseif ($type == 'yearly') {
            $interval_unit = 'year';
        }

        $currency = get_option('stripe_default_currency') ? get_option('stripe_default_currency') : 'usd';

        $pData = [
            'unit_amount' => $price * 100,
            'currency' => $currency,
            'product' => $product_id,
            'nickname' => _l($type) . ' Plan',
        ];

        if ($type != 'lifetime') {
            $pData['recurring'] = [
                'interval' => $interval_unit,
            ];
        }
        return $this->provider->prices->create($pData);
    }


    public function createCustomer($company, $return = false)
    {

        $this->setProvider();

        $this->ci->saas_model->_table_name = 'tbl_saas_gateway_subscriptions';
        $this->ci->saas_model->_primary_key = 'id';

        $customer = $this->provider->customers->create([
            'email' => $company->email,
            'name' => $company->name,
            'phone' => $company->mobile,
            'address' => [
                "line1" => $company->address,
            ],
            'metadata' => [
                'company_id' => $company->id,
            ],
        ]);

        $data = [
            'company_id' => $company->id,
            'gateway_name' => $this->gateway,
            'type' => $this->type,
            'customer_id' => $customer->id,
        ];
        if ($this->type == 'module' && !empty($company->package_module_id)) {
            $data['module_id'] = $company->package_module_id;
        } else {
            $data['module_id'] = null;
        }

        $id = $this->ci->saas_model->save_old($data);

        if ($return) {
            return $id;
        }
    }

    public function getProduct($package_id, $type = 'package')
    {
        $this->setProvider();

        $product = get_old_result('tbl_saas_gateway_products', ['package_id' => $package_id, 'gateway_name' => $this->gateway, 'type' => $type], false);
        if ($product) {
            // get product from stripe
            return $this->provider->products->retrieve($product->product_id);
        }
        return false;
    }

    private function createdSubscription($stripe_company, $package, $post)
    {
        $this->setProvider();

        $this->ci->saas_model->_table_name = 'tbl_saas_gateway_subscriptions';
        $this->ci->saas_model->_primary_key = 'id';

        if ($post['frequency'] == 'lifetime') {
            // create lifetime subscription
            $paymentIntent = $this->provider->paymentIntents->create([
                'amount' => $post['amount'] * 100,
                'currency' => $this->currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'customer' => $stripe_company->customer_id,
                'metadata' => [
                    'product_id' => $post['product_id'],
                    'price_id' => $post['priceId'],
                    'package_id' => $post['package_id'],
                    'frequency' => 'lifetime',
                ],
            ]);
            return $paymentIntent;

        } else {

            $subscriptionInfo = [
                'customer' => $stripe_company->customer_id,
                'items' => [[
                    'price' => $post['priceId'],
                ]],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'product_id' => $post['product_id'],
                    'price_id' => $post['priceId'],
                    'package_id' => $post['package_id'],
                    'frequency' => $post['frequency'],
                ],
            ];

            if ($package->trial_period != 0) {
                $trial_period = $package->trial_period; // assuming $package->trial_period is an integer
                $date = new DateTime();
                $date->add(new DateInterval('P' . $trial_period . 'D'));

                $subscriptionInfo = [
                    'customer' => $stripe_company->customer_id,
                    'items' => [[
                        'price' => $post['priceId'],
                    ]],
                    'payment_behavior' => 'default_incomplete',
                    'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                    'expand' => ['latest_invoice.payment_intent'],
                    'metadata' => [
                        'product_id' => $post['product_id'],
                        'price_id' => $post['priceId'],
                        'package_id' => $post['package_id']
                    ],
                    'trial_end' => $date->getTimestamp(),
                    'billing_cycle_anchor' => $date->getTimestamp(),
                ];
            }
            // Create the subscription with the customer ID, price ID, and necessary options.
            $newSubscription = $this->provider->subscriptions->create($subscriptionInfo);

            $s_data = [
                'subscription_id' => $newSubscription->id,
                'status' => 'running',
                'temp' => json_encode($post),
            ];
            $this->ci->saas_model->save_old($s_data, $stripe_company->id);
            return $newSubscription;
        }

    }

    private function get_client_secret($stripe_company, $package, $newSubscription, $post)
    {
        if (!empty($newSubscription->metadata->frequency) && $newSubscription->metadata->frequency === 'lifetime') {
            $client_secret = $newSubscription->client_secret;
        } else if ($package->trial_period != 0) {
            if (!empty($newSubscription->pending_setup_intent)) {
                $setupIntent = $this->provider->setupIntents->retrieve($newSubscription->pending_setup_intent, []);
                $client_secret = $setupIntent->client_secret;
            } else if (!empty($newSubscription->latest_invoice->payment_intent->client_secret)) {
                $client_secret = $newSubscription->latest_invoice->payment_intent->client_secret;
            } else {
                $newSubscription = $this->createdSubscription($stripe_company, $package, $post);
                $client_secret = $this->get_client_secret($stripe_company, $package, $newSubscription, $post);
            }

        } else {
            if (!empty($newSubscription->latest_invoice->payment_intent->client_secret)) {
                $client_secret = $newSubscription->latest_invoice->payment_intent->client_secret;
            } else {
                $newSubscription = $this->createdSubscription($stripe_company, $package, $post);
                $client_secret = $this->get_client_secret($stripe_company, $package, $newSubscription, $post);
            }

        }
        return $client_secret;

    }

    // create subscriptions
    public function createSubscription($company, $package, $post)
    {
        $this->setProvider();

        $amount = $post['amount'] * 100;
        $stripe_company = $company->stripe_company;

        if (empty($stripe_company->subscription_id)) {
            $newSubscription = $this->createdSubscription($stripe_company, $package, $post);
        } else {
            $newSubscription = $this->provider->subscriptions->retrieve($stripe_company->subscription_id);
        }

        $client_secret = $this->get_client_secret($stripe_company, $package, $newSubscription, $post);

        $trial = false;
        if ($package->trial_period != 0) {
            $trial = true;
        }

        $paymentIntent = [
            'subscription_id' => $newSubscription->id,
            'gateway_subscription_id' => $stripe_company->id,
            'client_secret' => $client_secret,
            'trial' => $trial,
            'currency' => $this->currency,
            'amount' => $amount,
        ];

        return $paymentIntent;
    }

    public function getPaymentIntent($intent, $payment_intent = false)
    {
        $this->setProvider();

        if ($payment_intent) {
            $paymentIntent = $this->provider->paymentIntents->retrieve($intent, []);
        } else {
            $paymentIntent = $this->provider->setupIntents->retrieve($intent, []);
        }
        return $paymentIntent;
    }

    public function getPaymentForm($package)
    {
        $package_info = get_old_result('tbl_saas_packages', array('id' => $package['package_id']), false);
        $stripe_company = get_old_result('tbl_saas_gateway_subscriptions', array('type' => 'package', 'company_id' => $package['companies_id'], 'gateway_name' => $this->gateway), false);
        $company = get_old_result('tbl_saas_companies', array('id' => $package['companies_id']), false);

        // tbl_saas_gateway_products
        $product = get_old_result('tbl_saas_gateway_products', array('type' => 'package', 'package_id' => $package['package_id'], 'gateway_name' => $this->gateway), false);
        $price_id = json_decode($product->price_id, true);
        $package['product_id'] = $product->product_id;
        $package['frequency'] = str_replace('_price', '', $package['billing_cycle']);
        $package['priceId'] = $price_id[$package['frequency']]['id'];


        if (empty($stripe_company)) {
            $this->createCustomer($company, true);
            $stripe_company = get_old_result('tbl_saas_gateway_subscriptions', array('type' => 'package', 'company_id' => $package['companies_id'], 'gateway_name' => $this->gateway), false);
        }

        $company->stripe_company = $stripe_company;
        $subscription = $this->createSubscription($company, $package_info, $package);
        if (!empty($subscription['error'])) {
            $data = [
                'error' => $subscription['error'],
            ];
            return $data;
        } else {
            $data = [
                'package' => $package_info,
                'stripe_company' => $stripe_company,
                'subscription' => $subscription,
                'company' => $company,
                'company_id' => $package['companies_id'],
                'priceId' => $package['priceId'],
                'amount' => $package['amount'],
                'currency' => $this->currency,
                'client_secret' => $subscription['client_secret'],
            ];
            $p['gateway_id'] = encrypt($subscription['gateway_subscription_id']);
            $data['gateway_id'] = $p['gateway_id'];
            $p['company_id'] = encrypt($package['companies_id']);
            $data['postURL'] = http_build_query($p);
            $paymentForm = $this->ci->load->view('companies/payments/stripe', $data, true);
            $_data['paymentForm'] = $paymentForm;

            return $_data;
        }
    }

    public function subscribe($data)
    {

        $package_module_id = $data['package_module_id'];

        $company = get_old_result('tbl_saas_companies', array('id' => $data['companies_id']), false);
        $package_info = get_old_result('tbl_saas_packages', array('id' => $company->package_id), false);
        $stripe_company = get_old_result('tbl_saas_gateway_subscriptions', array('module_id' => $package_module_id, 'type' => 'module', 'company_id' => $data['companies_id'], 'gateway_name' => $this->gateway), false);
        $product = get_old_result('tbl_saas_gateway_products', array('type' => 'module', 'package_id' => $package_module_id, 'gateway_name' => $this->gateway), false);

        $price_id = json_decode($product->price_id, true);
        $data['product_id'] = $product->product_id;
        $data['frequency'] = 'monthly';

        if (empty($price_id[$data['frequency']]['id'])) {
            return false;
        }

        $data['priceId'] = $price_id[$data['frequency']]['id'];


        if (empty($stripe_company)) {
            $this->type = 'module';
            $company->package_module_id = $package_module_id;
            $this->createCustomer($company, true);
            $stripe_company = get_old_result('tbl_saas_gateway_subscriptions', array('module_id' => $package_module_id, 'type' => 'module', 'company_id' => $data['companies_id'], 'gateway_name' => $this->gateway), false);
        }

        $company->stripe_company = $stripe_company;
        $package_info->trial_period = 0;
        $data['package_id'] = $package_module_id;

        $subscription = $this->createSubscription($company, $package_info, $data);

        if (!empty($subscription['error'])) {
            $data = [
                'error' => $subscription['error'],
            ];
            return $data;
        } else {
            $s_data = [
                'package' => $package_info,
                'stripe_company' => $stripe_company,
                'subscription' => $subscription,
                'company' => $company,
                'company_id' => $data['companies_id'],
                'priceId' => $data['priceId'],
                'amount' => $data['amount'],
                'currency' => $this->currency,
                'client_secret' => $subscription['client_secret'],
            ];
            $p['gateway_id'] = encrypt($subscription['gateway_subscription_id']);
            $s_data['gateway_id'] = $p['gateway_id'];
            $p['company_id'] = encrypt($data['companies_id']);
            $s_data['postURL'] = http_build_query($p);
            $paymentForm = $this->ci->load->view('companies/payments/stripe', $s_data, true);
            $_data['paymentForm'] = $paymentForm;

            return $_data;
        }
    }

    public function cancel_subscription($company_subs)
    {
        $this->setProvider();

        $subscription_id = $company_subs->subscription_id;
        try {
            $subscription = $this->provider->subscriptions->update($subscription_id, [
                'cancel_at_period_end' => true,
            ]);
            return ['status' => 'success', 'message' => 'Subscription canceled successfully'];
        } catch (\Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    // resume_subscription
    public function resume_subscription($company_subs)
    {
        $this->setProvider();

        $subscription_id = $company_subs->subscription_id;
        try {
            $subscription = $this->provider->subscriptions->update($subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            return ['status' => 'success', 'message' => 'Subscription resumed successfully'];
        } catch (\Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }


    public function getSubscription($subscription_id)
    {
        $this->setProvider();
        return $this->provider->subscriptions->retrieve($subscription_id);
    }


}
