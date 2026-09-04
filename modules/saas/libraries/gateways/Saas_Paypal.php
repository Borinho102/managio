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
        $paypal_live      = ConfigItems('paypal_live');
        $paypal_client_id = ConfigItems('paypal_client_id');
        $paypal_secret    = ConfigItems('paypal_secret_key');
        $app_id           = ConfigItems('paypal_app_id');

        log_message('debug', '[PAYPAL_LIB::accessToken] mode=' . ($paypal_live ? 'live' : 'sandbox') . ' client_id=' . ($paypal_client_id ? substr($paypal_client_id, 0, 8) . '...' : 'EMPTY') . ' secret=' . ($paypal_secret ? 'SET' : 'EMPTY'));

        $config = [
            'mode' => $paypal_live ? 'live' : 'sandbox',
            'live' => [
                'client_id'     => $paypal_client_id,
                'client_secret' => $paypal_secret,
                'app_id'        => $app_id,
            ],
            'sandbox' => [
                'client_id'     => $paypal_client_id,
                'client_secret' => $paypal_secret,
                'app_id'        => $app_id,
            ],

            'payment_action' => 'Sale',
            'currency'       => $this->currency,
            'notify_url'     => site_url('paypal/notify'),
            'locale'         => ConfigItems('default_locale') ? ConfigItems('default_locale') : 'en_US',
            'validate_ssl'   => (bool)$paypal_live,
        ];

        $this->provider = new PayPalClient($config);

        try {
            $this->provider->getAccessToken();
            log_message('debug', '[PAYPAL_LIB::accessToken] Access token obtained successfully');
        } catch (\Throwable $th) {
            log_message('error', '[PAYPAL_LIB::accessToken] Failed to get access token: ' . $th->getMessage());
            throw $th;
        }
    }


    /**
     * @throws \Throwable
     */
    public function syncProducts($package = null): array
    {
        log_message('debug', '[PAYPAL_LIB::syncProducts] Starting sync package=' . ($package ? json_encode($package) : 'ALL'));

        $result = [];
        if (!empty($package)) {
            $packages = [(object) $package];
        } else {
            $packages = all_packages();
        }

        log_message('debug', '[PAYPAL_LIB::syncProducts] Package count=' . count($packages));

        foreach ($packages as $package) {
            $product = $this->getProduct($package->id);

            if (!empty($product['id'])) {
                log_message('debug', '[PAYPAL_LIB::syncProducts] Updating existing product id=' . $product['id'] . ' package_id=' . $package->id);
                $result[] = $this->updateProduct($product['id'], $package);
            } else {
                log_message('debug', '[PAYPAL_LIB::syncProducts] Creating new product for package_id=' . $package->id . ' name=' . $package->name);
                $result[] = $this->createProduct($package);
            }
        }

        $isError = false;
        foreach ($result as $res) {
            if ($res['status'] == 'error') {
                $isError = true;
                log_message('error', '[PAYPAL_LIB::syncProducts] Error in result: ' . json_encode($res));
                break;
            }
        }

        if (!$isError) {
            log_message('debug', '[PAYPAL_LIB::syncProducts] All products synced, creating webhook');
            $this->createWebhook();
        }

        log_message('debug', '[PAYPAL_LIB::syncProducts] Done result=' . json_encode($result));
        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function syncModuleProducts($package = null): array
    {
        log_message('debug', '[PAYPAL_LIB::syncModuleProducts] Starting sync module package=' . ($package ? json_encode($package) : 'ALL'));

        $result = [];
        if ((!empty($package))) {
            $packages = [(object)$package];
        } else {
            $packages = get_old_result('tbl_saas_package_module', ['status' => 'published'], 'object');
        }

        log_message('debug', '[PAYPAL_LIB::syncModuleProducts] Module count=' . count($packages));

        foreach ($packages as $package) {
            $package = is_array($package) ? (object) $package : $package;
            $package_id = $package->package_module_id ?? $package->id ?? null;
            if (empty($package_id)) {
                log_message('error', '[PAYPAL_LIB::syncModuleProducts] Missing module id for gateway sync payload.');
                continue;
            }

            $package->name         = $package->module_title ?? $package->name ?? 'Module';
            $package->id           = $package_id;
            $package->package_module_id = $package_id;
            $package->monthly_price = (!empty($package->monthly_price)) ? $package->monthly_price : ($package->price ?? 0);
            $package->trial_period = (!empty($package->trial_period)) ? $package->trial_period : 0;

            $product = $this->getProduct($package->id, 'module');
            if (!empty($product['id'])) {
                log_message('debug', '[PAYPAL_LIB::syncModuleProducts] Updating module product id=' . $product['id'] . ' module_id=' . $package->id);
                $result[] = $this->updateProduct($product['id'], $package);
            } else {
                log_message('debug', '[PAYPAL_LIB::syncModuleProducts] Creating module product module_id=' . $package->id . ' name=' . $package->name);
                $result[] = $this->createProduct($package, 'module');
            }
        }

        $isError = false;
        foreach ($result as $res) {
            if (!empty($res['status']) && $res['status'] == 'error') {
                $isError = true;
                log_message('error', '[PAYPAL_LIB::syncModuleProducts] Error in result: ' . json_encode($res));
                break;
            }
        }

        if (!$isError) {
            log_message('debug', '[PAYPAL_LIB::syncModuleProducts] All modules synced, creating webhook');
            $this->createWebhook('module');
        }

        log_message('debug', '[PAYPAL_LIB::syncModuleProducts] Done result=' . json_encode($result));
        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function createWebhook($type = 'package')
    {
        $url = ($type == 'package') ? base_url('webhooks/paypal') : base_url('webhooks/paypal_module');

        log_message('debug', '[PAYPAL_LIB::createWebhook] type=' . $type . ' url=' . $url);

        $events = [
            'PAYMENT.SALE.COMPLETED',
            'PAYMENT.SALE.REFUNDED',
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.SUSPENDED',
            'BILLING.SUBSCRIPTION.EXPIRED',
            'BILLING.SUBSCRIPTION.RENEWED',
            'BILLING.SUBSCRIPTION.CREATED',
        ];

        $webhookInfo = get_old_result('tbl_saas_gateway_webhooks', ['gateway_name' => $this->gateway, 'type' => $type], false);

        log_message('debug', '[PAYPAL_LIB::createWebhook] existing webhook=' . ($webhookInfo ? 'id=' . $webhookInfo->id . ' webhook_id=' . $webhookInfo->webhook_id : 'NONE'));

        $this->accessToken();

        $this->ci->saas_model->_table_name  = 'tbl_saas_gateway_webhooks';
        $this->ci->saas_model->_primary_key = 'id';

        if (!empty($webhookInfo)) {
            log_message('debug', '[PAYPAL_LIB::createWebhook] Deleting old webhook_id=' . $webhookInfo->webhook_id);
            $this->provider->deleteWebhook($webhookInfo->webhook_id);

            $webhook = $this->provider->createWebhook($url, $events);
            log_message('debug', '[PAYPAL_LIB::createWebhook] Re-created webhook response: ' . json_encode($webhook));

            if (!empty($webhook['id'])) {
                $data = ['webhook_id' => $webhook['id']];
                $this->ci->saas_model->save_old($data, $webhookInfo->id);
                log_message('debug', '[PAYPAL_LIB::createWebhook] Saved new webhook_id=' . $webhook['id']);
            } else {
                log_message('error', '[PAYPAL_LIB::createWebhook] Failed to re-create webhook: ' . json_encode($webhook));
            }
        } else {
            $webhooks = $this->provider->listWebhooks();
            log_message('debug', '[PAYPAL_LIB::createWebhook] listWebhooks count=' . count($webhooks['webhooks'] ?? []));

            if (!empty($webhooks['webhooks'])) {
                $webhooks = $webhooks['webhooks'];
            } else {
                $webhooks = [];
            }

            foreach ($webhooks as $webhook) {
                if ($webhook['url'] == $url) {
                    log_message('debug', '[PAYPAL_LIB::createWebhook] Found duplicate webhook, deleting id=' . $webhook['id']);
                    $this->provider->deleteWebhook($webhook['id']);
                    break;
                }
            }

            $webhook = $this->provider->createWebhook($url, $events);
            log_message('debug', '[PAYPAL_LIB::createWebhook] Created webhook response: ' . json_encode($webhook));

            if (!empty($webhook['id'])) {
                $data = [
                    'webhook_id'   => $webhook['id'],
                    'type'         => $type,
                    'gateway_name' => $this->gateway,
                ];
                $this->ci->saas_model->save_old($data);
                log_message('debug', '[PAYPAL_LIB::createWebhook] Saved new webhook record webhook_id=' . $webhook['id']);
            } else {
                log_message('error', '[PAYPAL_LIB::createWebhook] Failed to create webhook: ' . json_encode($webhook));
            }
        }

        return $webhook;
    }

    /**
     * @throws \Throwable
     */
    public function getProduct($package_id, $type = 'package')
    {
        log_message('debug', '[PAYPAL_LIB::getProduct] package_id=' . $package_id . ' type=' . $type);

        $product = get_old_result('tbl_saas_gateway_products', ['package_id' => $package_id, 'gateway_name' => $this->gateway, 'type' => $type], false);
        if ($product) {
            log_message('debug', '[PAYPAL_LIB::getProduct] Found local product_id=' . $product->product_id . ' Fetching from PayPal...');
            $this->accessToken();
            $result = $this->provider->showProductDetails($product->product_id);
            log_message('debug', '[PAYPAL_LIB::getProduct] PayPal response: ' . json_encode($result));
            return $result;
        }

        log_message('debug', '[PAYPAL_LIB::getProduct] No local product found for package_id=' . $package_id);
        return false;
    }

    public function createProduct($package, $type = 'package')
    {
        log_message('debug', '[PAYPAL_LIB::createProduct] package_id=' . $package->id . ' name=' . $package->name . ' type=' . $type);

        try {
            $this->accessToken();

            $data = [
                'name'        => $package->name,
                'description' => 'Billing for ' . $package->name,
                'type'        => 'SERVICE',
                'category'    => 'SOFTWARE',
            ];

            $request_id = uniqid();
            log_message('debug', '[PAYPAL_LIB::createProduct] Calling createProduct API request_id=' . $request_id);
            $product = $this->provider->createProduct($data, $request_id);
            log_message('debug', '[PAYPAL_LIB::createProduct] PayPal response: ' . json_encode($product));

            if (!empty($product['id'])) {
                log_message('debug', '[PAYPAL_LIB::createProduct] Product created product_id=' . $product['id'] . ' Creating plans...');
                $this->createPlan($product['id'], $package, $type);
                return [
                    'status'  => 'success',
                    'message' => 'Product created successfully',
                ];
            } else {
                $errorMsg = $product['error']['message'] ?? json_encode($product);
                log_message('error', '[PAYPAL_LIB::createProduct] Failed: ' . $errorMsg);
                return [
                    'status'  => 'error',
                    'message' => $errorMsg,
                ];
            }
        } catch (\Throwable $th) {
            log_message('error', '[PAYPAL_LIB::createProduct] Exception: ' . $th->getMessage());
            return [
                'status'  => 'error',
                'message' => $th->getMessage(),
            ];
        }
    }

    /**
     * @throws \Throwable
     */
    public function updateProduct($product_id, $package)
    {
        log_message('debug', '[PAYPAL_LIB::updateProduct] product_id=' . $product_id . ' package_id=' . $package->id . ' name=' . $package->name);

        $product  = get_old_result('tbl_saas_gateway_products', ['product_id' => $product_id, 'gateway_name' => $this->gateway], false);
        $plan_id  = json_decode($product->price_id, true);

        log_message('debug', '[PAYPAL_LIB::updateProduct] price_id JSON: ' . $product->price_id);

        $result = [
            'status'  => 'success',
            'message' => 'Product updated successfully',
        ];

        if (isset($plan_id['monthly'])) {
            $price = $plan_id['monthly']['price'];
            if ($price != $package->monthly_price) {
                log_message('debug', '[PAYPAL_LIB::updateProduct] Monthly price changed old=' . $price . ' new=' . $package->monthly_price . ' plan_id=' . $plan_id['monthly']['id']);
                $res = $this->updatePlan($plan_id['monthly']['id'], $package->monthly_price, 2);
                log_message('debug', '[PAYPAL_LIB::updateProduct] Monthly plan update response: ' . json_encode($res));
                if (!empty($res['error'])) {
                    $error  = json_decode($res['error'], true);
                    $result = ['status' => 'error', 'message' => $error['message']];
                    log_message('error', '[PAYPAL_LIB::updateProduct] Monthly plan update failed: ' . $error['message']);
                }
            } else {
                log_message('debug', '[PAYPAL_LIB::updateProduct] Monthly price unchanged=' . $price . ', skip');
            }
        }

        if (isset($plan_id['yearly'])) {
            $price = $plan_id['yearly']['price'];
            if ($price != $package->yearly_price) {
                log_message('debug', '[PAYPAL_LIB::updateProduct] Yearly price changed old=' . $price . ' new=' . $package->yearly_price . ' plan_id=' . $plan_id['yearly']['id']);
                $res = $this->updatePlan($plan_id['yearly']['id'], $package->yearly_price);
                log_message('debug', '[PAYPAL_LIB::updateProduct] Yearly plan update response: ' . json_encode($res));
                if (!empty($res['error'])) {
                    $error  = json_decode($res['error'], true);
                    $result = ['status' => 'error', 'message' => $error['message']];
                    log_message('error', '[PAYPAL_LIB::updateProduct] Yearly plan update failed: ' . $error['message']);
                }
            } else {
                log_message('debug', '[PAYPAL_LIB::updateProduct] Yearly price unchanged=' . $price . ', skip');
            }
        }

        if (isset($plan_id['lifetime'])) {
            $price = $plan_id['lifetime']['price'];
            if ($price != $package->lifetime_price) {
                log_message('debug', '[PAYPAL_LIB::updateProduct] Lifetime price changed old=' . $price . ' new=' . $package->lifetime_price . ' plan_id=' . $plan_id['lifetime']['id']);
                $res = $this->updatePlan($plan_id['lifetime']['id'], $package->lifetime_price);
                log_message('debug', '[PAYPAL_LIB::updateProduct] Lifetime plan update response: ' . json_encode($res));
                if (!empty($res['error'])) {
                    $error  = json_decode($res['error'], true);
                    $result = ['status' => 'error', 'message' => $error['message']];
                    log_message('error', '[PAYPAL_LIB::updateProduct] Lifetime plan update failed: ' . $error['message']);
                }
            } else {
                log_message('debug', '[PAYPAL_LIB::updateProduct] Lifetime price unchanged=' . $price . ', skip');
            }
        }

        log_message('debug', '[PAYPAL_LIB::updateProduct] Done result=' . json_encode($result));
        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function updatePlan($plan_id, $price, $sequence = 1)
    {
        log_message('debug', '[PAYPAL_LIB::updatePlan] plan_id=' . $plan_id . ' price=' . $price . ' sequence=' . $sequence);

        $data = [
            [
                'billing_cycle_sequence' => $sequence,
                'pricing_scheme'         => [
                    'fixed_price' => [
                        'value'         => $price,
                        'currency_code' => $this->currency,
                    ],
                ],
            ],
        ];

        $this->accessToken();
        $response = $this->provider->updatePlanPricing($plan_id, $data);
        log_message('debug', '[PAYPAL_LIB::updatePlan] Response: ' . json_encode($response));

        return $response;
    }


    /**
     * @throws \Throwable
     */
    private function createdPlan($data, $trial_period = null)
    {
        log_message('debug', '[PAYPAL_LIB::createdPlan] product_id=' . $data['product_id'] . ' name=' . $data['name'] . ' type=' . $data['type'] . ' price=' . $data['price'] . ' trial_period=' . ($trial_period ?? 'none'));

        $interval_unit = 'DAY';
        if ($data['type'] == 'Monthly') {
            $interval_unit = 'MONTH';
        } elseif ($data['type'] == 'Yearly') {
            $interval_unit = 'YEAR';
        }

        $sequence = 1;
        if (!empty($trial_period)) {
            $sequence      = 2;
            $billingTrial  = [
                'frequency' => [
                    'interval_unit'  => 'DAY',
                    'interval_count' => 1,
                ],
                'tenure_type'    => 'TRIAL',
                'sequence'       => 1,
                'total_cycles'   => $trial_period ? $trial_period : 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value'         => $data['price'],
                        'currency_code' => $this->currency,
                    ],
                ],
            ];
        }

        $billingCycle = [
            'frequency' => [
                'interval_unit'  => $interval_unit,
                'interval_count' => 1,
            ],
            'tenure_type'    => 'REGULAR',
            'sequence'       => $sequence,
            'total_cycles'   => $trial_period ? $trial_period : 0,
            'pricing_scheme' => [
                'fixed_price' => [
                    'value'         => $data['price'],
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
            'auto_bill_outstanding'    => true,
            'setup_fee'                => [
                'value'         => 0,
                'currency_code' => $this->currency,
            ],
            'setup_fee_failure_action' => 'CANCEL',
            'payment_failure_threshold' => 3,
        ];

        if ($trial_period) {
            $paymentPreferences['auto_bill_outstanding'] = false;
        }

        $this->accessToken();

        $request_id = uniqid();
        $planPayload = [
            'product_id'         => $data['product_id'],
            'name'               => $data['name'] . ' ' . $data['type'],
            'description'        => $data['description'] . ' ' . $data['type'],
            'status'             => 'ACTIVE',
            'billing_cycles'     => $billingCycle,
            'payment_preferences' => $paymentPreferences,
        ];

        log_message('debug', '[PAYPAL_LIB::createdPlan] Calling createPlan API request_id=' . $request_id . ' payload=' . json_encode($planPayload));

        $plan = $this->provider->createPlan($planPayload, $request_id);

        log_message('debug', '[PAYPAL_LIB::createdPlan] PayPal response: ' . json_encode($plan));

        if (empty($plan['id'])) {
            log_message('error', '[PAYPAL_LIB::createdPlan] Plan creation failed for type=' . $data['type'] . ' product_id=' . $data['product_id']);
        }

        return $plan;
    }

    public function createPlan($product_id, $package, $type = 'package')
    {
        log_message('debug', '[PAYPAL_LIB::createPlan] product_id=' . $product_id . ' package_id=' . $package->id . ' type=' . $type . ' trial_period=' . ($package->trial_period ?? 0));

        $data = [
            'product_id'  => $product_id,
            'name'        => $package->name,
            'description' => 'Billing for ' . $package->name,
            'type'        => 'Monthly',
            'price'       => round($package->monthly_price, 2),
        ];

        $plan_id = [];
        $result  = [];

        log_message('debug', '[PAYPAL_LIB::createPlan] Creating Monthly plan price=' . $data['price']);
        $monthlyPlan = $this->createdPlan($data, $package->trial_period);

        if (!empty($monthlyPlan['id'])) {
            $plan_id['monthly'] = ['id' => $monthlyPlan['id'], 'price' => $package->monthly_price];
            $result[]           = ['status' => 'success', 'message' => 'Plan created successfully'];
            log_message('debug', '[PAYPAL_LIB::createPlan] Monthly plan created plan_id=' . $monthlyPlan['id']);
        } else {
            $errorMsg = $monthlyPlan['error']['message'] ?? json_encode($monthlyPlan);
            $result[] = ['status' => 'error', 'message' => $errorMsg];
            log_message('error', '[PAYPAL_LIB::createPlan] Monthly plan failed: ' . $errorMsg);
        }

        if (!empty($package->yearly_price)) {
            $data['type']  = 'Yearly';
            $data['price'] = $package->yearly_price;
            log_message('debug', '[PAYPAL_LIB::createPlan] Creating Yearly plan price=' . $data['price']);
            $yearlyPlan = $this->createdPlan($data);

            if (!empty($yearlyPlan['id'])) {
                $plan_id['yearly'] = ['id' => $yearlyPlan['id'], 'price' => $package->yearly_price];
                $result[]          = ['status' => 'success', 'message' => 'Plan created successfully'];
                log_message('debug', '[PAYPAL_LIB::createPlan] Yearly plan created plan_id=' . $yearlyPlan['id']);
            } else {
                $errorMsg = $yearlyPlan['error']['message'] ?? json_encode($yearlyPlan);
                $result[] = ['status' => 'error', 'message' => $errorMsg];
                log_message('error', '[PAYPAL_LIB::createPlan] Yearly plan failed: ' . $errorMsg);
            }
        }

        if (!empty($package->lifetime_price)) {
            $data['type']  = 'Lifetime';
            $data['price'] = $package->lifetime_price;
            log_message('debug', '[PAYPAL_LIB::createPlan] Creating Lifetime plan price=' . $data['price']);
            $lifetimePlan = $this->createdPlan($data);

            if (!empty($lifetimePlan['id'])) {
                $plan_id['lifetime'] = ['id' => $lifetimePlan['id'], 'price' => $package->lifetime_price];
                $result[]            = ['status' => 'success', 'message' => 'Plan created successfully'];
                log_message('debug', '[PAYPAL_LIB::createPlan] Lifetime plan created plan_id=' . $lifetimePlan['id']);
            } else {
                $errorMsg = $lifetimePlan['error']['message'] ?? json_encode($lifetimePlan);
                $result[] = ['status' => 'error', 'message' => $errorMsg];
                log_message('error', '[PAYPAL_LIB::createPlan] Lifetime plan failed: ' . $errorMsg);
            }
        }

        log_message('debug', '[PAYPAL_LIB::createPlan] Saving to DB plan_id=' . json_encode($plan_id));
        $this->ci->saas_model->updateProduct($product_id, json_encode($plan_id), $package, $this->gateway, $type);

        log_message('debug', '[PAYPAL_LIB::createPlan] Done result=' . json_encode($result));
        return $result;
    }

    public function getPaymentForm($package)
    {
        log_message('debug', '[PAYPAL_LIB::getPaymentForm] package_id=' . ($package['package_id'] ?? 'EMPTY') . ' billing_cycle=' . ($package['billing_cycle'] ?? 'EMPTY'));

        $product  = get_old_result('tbl_saas_gateway_products', array('package_id' => $package['package_id'], 'gateway_name' => $this->gateway), false);

        if (empty($product)) {
            log_message('error', '[PAYPAL_LIB::getPaymentForm] No gateway product found for package_id=' . $package['package_id']);
            return false;
        }

        $price_id = json_decode($product->price_id, true);
        log_message('debug', '[PAYPAL_LIB::getPaymentForm] product_id=' . $product->product_id . ' price_id JSON: ' . $product->price_id);

        $package['product_id'] = $product->product_id;
        $package['frequency']  = str_replace('_price', '', $package['billing_cycle']);

        if (empty($price_id[$package['frequency']]['id'])) {
            log_message('error', '[PAYPAL_LIB::getPaymentForm] No price_id for frequency=' . $package['frequency'] . ' available keys=' . implode(',', array_keys($price_id ?? [])));
            return false;
        }

        $package['priceId'] = $price_id[$package['frequency']]['id'];
        log_message('debug', '[PAYPAL_LIB::getPaymentForm] priceId=' . $package['priceId'] . ' frequency=' . $package['frequency']);

        $package['post']      = $package;
        $paymentForm          = $this->ci->load->view('companies/payments/paypal', $package, true);
        $_data['paymentForm'] = $paymentForm;

        log_message('debug', '[PAYPAL_LIB::getPaymentForm] Payment form loaded successfully');
        return $_data;
    }

    public function verifyWebhook($data)
    {
        log_message('debug', '[PAYPAL_LIB::verifyWebhook] Verifying webhook data keys=' . implode(',', array_keys($data)));

        $this->accessToken();
        $result = $this->provider->verifyWebHook($data);

        log_message('debug', '[PAYPAL_LIB::verifyWebhook] Result: ' . json_encode($result));
        return $result;
    }

    public function resume_subscription($subscription)
    {
        log_message('debug', '[PAYPAL_LIB::resume_subscription] subscription_id=' . $subscription->subscription_id);

        $this->accessToken();
        $response = $this->provider->activateSubscription($subscription->subscription_id, 'Re-activated');

        log_message('debug', '[PAYPAL_LIB::resume_subscription] Response: ' . json_encode($response));

        if (!empty($response['error'])) {
            $response = json_decode($response['error'], true);
            log_message('error', '[PAYPAL_LIB::resume_subscription] Failed: ' . $response['message']);
            return ['status' => 'error', 'message' => $response['message']];
        } else {
            log_message('debug', '[PAYPAL_LIB::resume_subscription] Success subscription_id=' . $subscription->subscription_id);
            return ['status' => 'success', 'message' => 'Subscription re-activated successfully'];
        }
    }

    public function cancel_subscription($subscription)
    {
        log_message('debug', '[PAYPAL_LIB::cancel_subscription] subscription_id=' . $subscription->subscription_id);

        $this->accessToken();
        $response = $this->provider->suspendSubscription($subscription->subscription_id, 'Suspended');

        log_message('debug', '[PAYPAL_LIB::cancel_subscription] Response: ' . json_encode($response));

        if (!empty($response['error'])) {
            $response = json_decode($response['error'], true);
            log_message('error', '[PAYPAL_LIB::cancel_subscription] Failed: ' . $response['message']);
            return ['status' => 'error', 'message' => $response['message']];
        } else {
            log_message('debug', '[PAYPAL_LIB::cancel_subscription] Success subscription_id=' . $subscription->subscription_id);
            return ['status' => 'success', 'message' => 'Subscription Suspended successfully'];
        }
    }

    public function subscribe($data)
    {
        $package_module_id = $data['package_module_id'];
        log_message('debug', '[PAYPAL_LIB::subscribe] package_module_id=' . $package_module_id);

        $product  = get_old_result('tbl_saas_gateway_products', array('package_id' => $package_module_id, 'type' => 'module', 'gateway_name' => $this->gateway), false);

        if (empty($product)) {
            log_message('error', '[PAYPAL_LIB::subscribe] No gateway product found for module package_module_id=' . $package_module_id);
            return false;
        }

        $price_id          = json_decode($product->price_id, true);
        $data['product_id'] = $product->product_id;
        $data['frequency'] = 'monthly';

        log_message('debug', '[PAYPAL_LIB::subscribe] product_id=' . $product->product_id . ' price_id JSON: ' . $product->price_id);

        if (empty($price_id[$data['frequency']]['id'])) {
            log_message('error', '[PAYPAL_LIB::subscribe] No price_id for frequency=monthly available keys=' . implode(',', array_keys($price_id ?? [])));
            return false;
        }

        $data['priceId'] = $price_id[$data['frequency']]['id'];
        log_message('debug', '[PAYPAL_LIB::subscribe] priceId=' . $data['priceId']);

        $data['post']         = $data;
        $paymentForm          = $this->ci->load->view('companies/payments/paypal', $data, true);
        $_data['paymentForm'] = $paymentForm;

        log_message('debug', '[PAYPAL_LIB::subscribe] Payment form loaded successfully');
        return $_data;
    }
}
