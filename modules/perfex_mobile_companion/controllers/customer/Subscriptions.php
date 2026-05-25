<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Subscriptions extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->library('stripe_subscriptions');
        $this->load->model('subscriptions_model');
        $this->load->model('currencies_model');
        $this->load->model('taxes_model');
        $this->load->model('Customer_api_model');
    }

    public function data_get($id = '')
    {
        $data = $this->Api_model->get_table('subscriptions', $id);

        if ($data) {
            if ($id !== '') {
                $data->formatted_date = _d($data->date);
                $data->project_name = get_project_name_by_id($data->project_id);
                if (empty($data->status)) {
                    $data->status_name = _l('subscription_not_subscribed');
                } else {
                    $data->status_name = _l('subscription_' . $data->status, '', false);
                }

                $language               = load_client_language($data->clientid);
                $data->locale         = get_locale_key($language);
                $data->publishableKey = $this->stripe_subscriptions->get_publishable_key();
                $plan                   = $this->stripe_subscriptions->get_plan($data->stripe_plan_id);

                check_stripe_subscription_environment($data);

                if (!empty($data->stripe_subscription_id) && !empty($data->publishableKey)) {
                    $data->stripeSubscription = $this->stripe_subscriptions->get_subscription([
                        'id'     => $data->stripe_subscription_id,
                        'expand' => ['latest_invoice'],
                    ]);
                }

                $upcomingInvoice                    = new stdClass();
                $upcomingInvoice->default_tax_rates = null;
                $upcomingInvoice->total             = $plan->amount * $data->quantity;
                $upcomingInvoice->subtotal          = $upcomingInvoice->total;
                $total                              = $upcomingInvoice->total;

                if (!empty($data->tax_percent) || !empty($data->tax_percent_2)) {
                    $totalTax                           = 0;
                    $upcomingInvoice->default_tax_rates = [];
                    if (!empty($data->tax_percent)) {
                        $tax1                                 = new stdClass();
                        $tax1->percentage                     = $data->tax_percent;
                        $tax1->display_name                   = $data->tax_name;
                        $upcomingInvoice->default_tax_rates[] = $tax1;
                        $totalTax += $upcomingInvoice->total * ($data->tax_percent / 100);
                    }

                    if (!empty($data->tax_percent_2)) {
                        $tax2                                 = new stdClass();
                        $tax2->percentage                     = $data->tax_percent_2;
                        $tax2->display_name                   = $data->tax_name_2;
                        $upcomingInvoice->default_tax_rates[] = $tax2;
                        $totalTax += $upcomingInvoice->total * ($data->tax_percent_2 / 100);
                    }

                    $upcomingInvoice->total += $totalTax;
                }

                $data->total = $upcomingInvoice->total;
                $product       = $this->stripe_subscriptions->get_product($plan->product);

                $upcomingInvoice->lines       = new stdClass();
                $upcomingInvoice->lines->data = [];

                $upcomingInvoice->lines->data[] = [
                    'description' => $this->lineProductDescription($product, $plan, $data->currency_name),
                    'amount'      => $plan->amount * $data->quantity,
                    'quantity'    => $data->quantity,
                ];

                $data->invoice        = subscription_invoice_preview_data($data, $upcomingInvoice);

                $data->bill_to = format_customer_info($data->invoice, 'invoice', 'billing');
                if (isset($data->invoice->include_shipping) &&  $data->invoice->include_shipping == 1 &&  $data->invoice->show_shipping_on_invoice == 1) {
                    $data->ship_to = format_customer_info($data->invoice, 'invoice', 'shipping');
                }
            }

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    protected function lineProductDescription($product, $plan, $currency)
    {
        $intervals = ['day', 'week', 'month', 'year'];
        $interval  = $plan->interval;

        foreach ($intervals as $stripeInterval) {
            if ($plan->interval === $stripeInterval && $plan->interval_count === 1) {
                $interval = _l($stripeInterval);
            } elseif ($plan->interval === $stripeInterval && $plan->interval_count > 1) {
                $interval = _l('frequency_every', $plan->interval_count . ' ' . _l($stripeInterval . 's'));
            }
        }

        $productName = (!empty($plan->nickname) ? $plan->nickname : $product->name);

        return $productName . ' (' . app_format_money(strcasecmp($plan->currency, 'JPY') == 0 ?
            $plan->amount :
            $plan->amount / 100, strtoupper($currency)) . ' / ' . $interval . ')';
    }

    public function data_plans_get()
    {
        $data['plans'] = [];

        try {
            $data['plans'] = $this->stripe_subscriptions->get_plans();

            foreach ($data['plans']->data as $key => $plan) {
                if (!empty($plan->nickname)) {
                    $data['plans']->data[$key]->nickname = $plan->nickname;
                } else if (isset($plan->product->name)) {
                    $data['plans']->data[$key]->nickname = $plan->product->name;
                } else {
                    $data['plans']->data[$key]->nickname = '[Plan Name Not Set in Stripe, ID:' . $plan->id . ']';
                }

                $subtext = app_format_money(strcasecmp($plan->currency, 'JPY') == 0 ? $plan->amount : $plan->amount / 100, strtoupper($plan->currency));
                if ($plan->interval_count == 1) {
                    $subtext .= ' / ' . $plan->interval;
                } else {
                    $subtext .= ' (every ' . $plan->interval_count . ' ' . $plan->interval . 's)';
                }

                $data['plans']->data[$key]->subtext = $subtext;
            }

            $this->load->library('stripe_core');
            $data['stripe_tax_rates'] = $this->stripe_core->get_tax_rates();

            foreach ($data['stripe_tax_rates'] as $key => $tax) {
                $data['stripe_tax_rates'][$key]['formated_dispaly_name'] = $tax->display_name;
                $data['stripe_tax_rates'][$key]['formated_dispaly_name'] .= (!empty($tax->jurisdiction) ? ' - ' . $tax->jurisdiction . ' ' : '') . ' (' . $tax->percentage . '%)';
                $data['stripe_tax_rates'][$key]['subtext'] = !empty($tax->country) ? $tax->country : '';
            }
            $data['status'] = true;
        } catch (Exception $e) {
            if ($this->stripe_subscriptions->has_api_key()) {
                $data['subscription_error'] = $e->getMessage();
            } else {
                $data['subscription_error'] = _l('api_key_not_set_error_message', '<a href="' . admin_url('settings?group=payment_gateways&tab=online_payments_stripe_tab') . '">Stripe Checkout</a>');
            }
            $data['status'] = false;
        }

        $this->response($data, REST_Controller::HTTP_OK);
    }

    public function data_search_get($key = '')
    {
        if ($this->input->get('search')) {
            $key = $this->input->get('search');
        }

        // If the id parameter doesn't exist return all the
        $data = $this->_search_subscription($key);

        if ($data) {

            foreach ($data as $key => $value) {

                $data[$key]['formatted_date'] = _d($value['date']);
                $data[$key]['project_name'] = get_project_name_by_id($value['project_id']);

                if (empty($value['status'])) {
                    $data[$key]['status_name'] = _l('subscription_not_subscribed');
                } else {
                    $data[$key]['status_name'] = _l('subscription_' . $value['status'], '', false);
                }

                if ($value['next_billing_cycle']) {
                    $data[$key]['formated_next_billing_cycle'] = _d(date('Y-m-d', $value['next_billing_cycle']));
                } else {
                    $data[$key]['formated_next_billing_cycle'] = '-';
                }
            }
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_post()
    {
        $data = $this->input->post();

        $this->form_validation->set_rules('stripe_plan_id', 'Billing Plan', 'trim|required|max_length[191]');
        $this->form_validation->set_rules('quantity', 'Quantity', 'trim|required|greater_than[0]');

        $this->form_validation->set_rules('name', 'Subscription Name', 'trim|required|max_length[191]');
        $this->form_validation->set_rules('clientid', 'Customer', 'trim|required|greater_than[0]');
        $this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');

        if ($this->form_validation->run() == FALSE) {
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $id = $this->subscriptions_model->create([
                'name'                => $this->input->post('name'),
                'description'         => nl2br($this->input->post('description')),
                'description_in_item' => $this->input->post('description_in_item') ? 1 : 0,
                'date'                => $this->input->post('date') ? to_sql_date($this->input->post('date')) : null,
                'clientid'            => $this->input->post('clientid'),
                'project_id'          => $this->input->post('project_id') ? $this->input->post('project_id') : 0,
                'stripe_plan_id'      => $this->input->post('stripe_plan_id'),
                'quantity'            => $this->input->post('quantity'),
                'terms'               => nl2br($this->input->post('terms')),
                'stripe_tax_id'       => $this->input->post('stripe_tax_id') ? $this->input->post('stripe_tax_id') : false,
                'stripe_tax_id_2'     => $this->input->post('stripe_tax_id_2') ? $this->input->post('stripe_tax_id_2') : false,
                'currency'            => $this->input->post('currency'),
            ]);

            if ($id > 0 && !empty($id)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('added_successfully', _l('subscription')),
                    'insert_id' => $id
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Subscription Add Fail'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_put($id = "")
    {
        $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);

        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $this->form_validation->set_data($_POST);

        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Subscription ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->form_validation->set_rules('stripe_plan_id', 'Billing Plan', 'trim|required|max_length[191]');
            $this->form_validation->set_rules('quantity', 'Quantity', 'trim|required|greater_than[0]');

            $this->form_validation->set_rules('name', 'Subscription Name', 'trim|required|max_length[191]');
            $this->form_validation->set_rules('clientid', 'Customer', 'trim|required|greater_than[0]');
            $this->form_validation->set_rules('currency', 'Currency', 'trim|required|max_length[255]');

            if ($this->form_validation->run() == FALSE) {
                $message = array(
                    'status' => FALSE,
                    'error' => $this->form_validation->error_array(),
                    'message' => validation_errors()
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $subscription = $this->subscriptions_model->get_by_id($id);
                if (!is_object($subscription)) {
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Subscription ID Doesn\'t Not Exist.'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
                if (is_object($subscription)) {

                    if ($subscription->in_test_environment === '1' && !get_instance()->stripe_gateway->is_test()) {
                        $_message = '<h2>This subscription was created in test environment, now the system has switched to live environment, hence, cannot be viewed.</h4>';
                        if (staff_can('delete', 'subscriptions')) {
                            $_message .= '<h3>Feel free to delete the subscription from the system.</h4>';
                        }
                        $message = array(
                            'status' => false,
                            'message' => $_message,
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    }

                    $update = [
                        'name'                => $this->input->post('name'),
                        'description'         => nl2br($this->input->post('description')),
                        'description_in_item' => $this->input->post('description_in_item') ? 1 : 0,
                        'clientid'            => $this->input->post('clientid'),
                        'date'                => $this->input->post('date') ? to_sql_date($this->input->post('date')) : null,
                        'project_id'          => $this->input->post('project_id') ? $this->input->post('project_id') : 0,
                        'stripe_plan_id'      => $this->input->post('stripe_plan_id'),
                        'terms'               => nl2br($this->input->post('terms')),
                        'quantity'            => $this->input->post('quantity'),
                        'stripe_tax_id'       => $this->input->post('stripe_tax_id') ? $this->input->post('stripe_tax_id') : false,
                        'stripe_tax_id_2'     => $this->input->post('stripe_tax_id_2') ? $this->input->post('stripe_tax_id_2') : false,
                        'currency'            => $this->input->post('currency'),
                    ];

                    if (!empty($stripeSubscriptionId)) {
                        unset($update['clientid']);
                        unset($update['date']);
                    }
                    $stripeSubscriptionId = $subscription->stripe_subscription_id;

                    try {
                        $prorate = $this->input->post('prorate') ? true : false;
                        $this->stripe_subscriptions->update_subscription($stripeSubscriptionId, $update, $subscription, $prorate);
                    } catch (Exception $e) {
                        $message = array(
                            'status' => false,
                            'message' => $e->getMessage(),
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    }

                    $success = $this->subscriptions_model->update($id, $update);

                    if ($success == true) {
                        $message = array(
                            'status' => TRUE,
                            'message' =>  _l('updated_successfully', _l('subscription')),
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    } else {
                        // error
                        $message = array(
                            'status' => FALSE,
                            'message' => 'Subscription Update Fail'
                        );
                        $this->response($message, REST_Controller::HTTP_OK);
                    }
                } else {
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Invalid Subscription ID'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            }
        }
    }

    public function data_delete($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Subscription ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('subscriptions_model');
            if ($subscription = $this->subscriptions_model->delete($id)) {
                try {
                    // In case already deleted in Stripe
                    $this->stripe_subscriptions->cancel($subscription->stripe_subscription_id);
                } catch (Exception $e) {
                }
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('deleted', _l('subscription'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => _l('problem_deleting', _l('subscription'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    private function _search_subscription($q, $limit = 0, $api = false)
    {
        $result = [
            'result'         => [],
            'type'           => 'subscriptions',
            'search_heading' => _l('subscriptions'),
        ];

        $this->db->select(db_prefix() . 'subscriptions.id as id, 
                stripe_tax_id, 
                stripe_tax_id_2, 
                terms, 
                in_test_environment, 
                date, 
                next_billing_cycle, 
                ' . db_prefix() . 'projects.name as project_name, 
                ' . db_prefix() . 'subscriptions.description, 
                ' . db_prefix() . 'subscriptions.project_id as project_id, 
                ' . db_prefix() . 'subscriptions.created_from as created_from, 
                ' . db_prefix() . 'subscriptions.name as name, 
                ' . db_prefix() . 'subscriptions.status as status,
                ' . db_prefix() . 'currencies.name as currency_name, 
                ' . db_prefix() . 'currencies.symbol, 
                ' . db_prefix() . 'taxes.name as tax_name, 
                ' . db_prefix() . 'taxes.taxrate as tax_percent, 
                ' . db_prefix() . 'taxes_2.name as tax_name_2, 
                ' . db_prefix() . 'taxes_2.taxrate as tax_percent_2, 
                currency, 
                ends_at, 
                date_subscribed, 
                stripe_plan_id, 
                stripe_subscription_id, 
                quantity, 
                hash, 
                description_in_item, 
                tax_id, 
                tax_id_2, 
                stripe_id as stripe_customer_id, 
                ' . get_sql_select_client_company());

        $this->db->where(db_prefix() . 'subscriptions.clientid', get_client_user_id());
        $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'subscriptions.currency');
        $this->db->join(db_prefix() . 'taxes', '' . db_prefix() . 'taxes.id = ' . db_prefix() . 'subscriptions.tax_id', 'left');
        $this->db->join('' . db_prefix() . 'taxes as ' . db_prefix() . 'taxes_2', '' . db_prefix() . 'taxes_2.id = ' . db_prefix() . 'subscriptions.tax_id_2', 'left');
        $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid=' . db_prefix() . 'subscriptions.clientid');
        $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id=' . db_prefix() . 'subscriptions.project_id', 'left');

        $this->db->group_start();
        $this->db->like(db_prefix() . 'subscriptions.name', $q);
        $this->db->or_like(db_prefix() . 'subscriptions.description', $q);
        $this->db->group_end();

        if (!empty($this->input->get('filters'))) {
            $filters = json_decode($this->input->get('filters'), true);

            if (!empty($filters['status'])) {
                $whereStatus = '';
                foreach ($filters['status'] as $status) {
                    if ($status == 'not_subscribed') continue;
                    $whereStatus .= db_prefix() . 'subscriptions.status="' . $status . '" OR ';
                }
                $whereStatus = rtrim($whereStatus, ' OR ');

                foreach ($filters['status'] as $status) {
                    if ($status == 'not_subscribed' && count($filters['status']) != 1) {
                        $whereStatus .= ' OR stripe_subscription_id IS NULL OR stripe_subscription_id = ""';
                    }

                    if ($status == 'not_subscribed' && count($filters['status']) == 1) {
                        $whereStatus .= 'stripe_subscription_id IS NULL OR stripe_subscription_id = ""';
                    }
                }

                $this->db->where($whereStatus);
            }

            if (!empty($filters['project_id'])) {
                $this->db->where(db_prefix() . 'subscriptions.project_id', $filters['project_id']);
            }
        }

        if (!empty($this->input->get('sort'))) {
            $sorting = json_decode($this->input->get('sort'), true);
            $this->db->order_by($sorting['sort_by'], $sorting['order']);
        } else {
            $this->db->order_by('id', 'DESC');
        }

        if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
            $this->db->limit($this->input->get('limit'));
            $this->db->offset($this->input->get('offset'));
        }

        $result['result'] = $this->db->get(db_prefix() . 'subscriptions')->result_array();

        return $result['result'];
    }

    public function canceled_get($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Subscription ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $subscription = $this->subscriptions_model->get_by_id($id);

            if (!empty($subscription->stripe_subscription_id) && $subscription->status != 'canceled' && $subscription->status != 'incomplete_expired' && empty($subscription->ends_at)) {

                $type    = $this->input->get('type');
                $ends_at = time();
                if ($type == 'immediately') {
                    $response =  $this->stripe_subscriptions->cancel($subscription->stripe_subscription_id);

                    // The mail sent via the webhook
                } elseif ($type == 'at_period_end') {
                    $ends_at = $this->stripe_subscriptions->cancel_at_end_of_billing_period($subscription->stripe_subscription_id);
                } else {
                    // throw new Exception('Invalid Cancelation Type', 1);
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Invalid Cancelation Type'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }

                $update = ['ends_at' => $ends_at];

                // Hook may be delayed and the status won't be cancelled upon refresh
                if ($type == 'immediately') {
                    $update['status'] = 'canceled';
                }
                $this->subscriptions_model->update($id, $update);

                $message = array(
                    'status' => TRUE,
                    'message' => 'subscription successfully canceled'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'No such subscription: ' . $subscription->stripe_subscription_id
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
