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
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
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

                try {
                    if (!empty($data->stripe_subscription_id)) {
                        $data->stripeSubscription = $this->stripe_subscriptions->get_subscription($data->stripe_subscription_id);

                        if ($data->status != 'canceled' && $data->status !== 'incomplete_expired') {
                            $data->upcoming_invoice = $this->stripe_subscriptions->get_upcoming_invoice($data->stripe_subscription_id);

                            $data->upcoming_invoice = subscription_invoice_preview_data($data, $data->upcoming_invoice, $data->stripeSubscription);
                            // Throwing errors when not set in the invoice preview area
                            if (!isset($data->upcoming_invoice->include_shipping)) {
                                $data->upcoming_invoice->include_shipping = 0;
                            }

                            foreach ($data->upcoming_invoice->items as $_key => $item) {

                                $item_taxes = $this->db
                                    ->where('rel_type', 'invoice')
                                    ->where('itemid', $item['id'])
                                    ->get(db_prefix() . 'item_tax')
                                    ->result_array();
                                $data->upcoming_invoice->items[$_key]['taxrate'] = [];
                                if (!empty($item_taxes)) {
                                    foreach ($item_taxes as $item_tax) {
                                        $data->upcoming_invoice->items[$_key]['taxrate'][] = [
                                            'name' => $item_tax['taxname'],
                                            'taxrate' => $item_tax['taxrate'],
                                        ];
                                    }
                                }
                            }

                            $data->upcoming_invoice->formatted_date        = _d($data->upcoming_invoice->date);
                            $data->upcoming_invoice->formatted_duedate     = _d($data->upcoming_invoice->duedate);
                            $data->upcoming_invoice->formatted_total = app_format_money($data->upcoming_invoice->total, $data->upcoming_invoice->currency_name);
                            $data->upcoming_invoice->formatted_subtotal = app_format_money($data->upcoming_invoice->subtotal, $data->upcoming_invoice->currency_name);
                            $data->upcoming_invoice->formatted_total_left_to_pay = app_format_money($data->upcoming_invoice->total_left_to_pay, $data->upcoming_invoice->currency_name);

                            $data->upcoming_invoice->bill_to  = format_customer_info((object) $data->upcoming_invoice, 'customer', 'billing');
                            $data->upcoming_invoice->ship_to = format_customer_info((object) $data->upcoming_invoice, 'customer', 'shipping');
                            /* if ($data->upcoming_invoice->include_shipping == 1 && $data->upcoming_invoice->show_shipping_on_invoice == 1) {
                                
                            } */
                        }
                    }
                } catch (Exception $e) {
                    if ($this->stripe_subscriptions->has_api_key()) {
                        $data->subscription_error = $e->getMessage();
                    } else {
                        $data->subscription_error = check_for_links(_l('api_key_not_set_error_message', admin_url('settings?group=payment_gateways&tab=online_payments_stripe_tab')));
                    }
                }

                $data->child_invoices = $this->subscriptions_model->get_child_invoices($id);
                $data->child_invoices = json_decode(json_encode($data->child_invoices), true);

                foreach ($data->child_invoices as $__key => $__value) {
                    $template_name = 'invoice_send_to_customer';
                    if ($__value['sent'] == 1) {
                        $template_name = 'invoice_send_to_customer_already_sent';
                    }

                    $data->child_invoices[$__key]['mail_data'] = prepare_mail_preview_data($template_name, $data->child_invoices[$__key]['clientid']);

                    $data->child_invoices[$__key]['invoice_number']              = format_invoice_number($__value['id']);
                    $data->child_invoices[$__key]['formatted_total']             = app_format_money($__value['total'], $__value['currency_name']);
                    $data->child_invoices[$__key]['formatted_date']              = _d($__value['date']);
                    $data->child_invoices[$__key]['formatted_duedate']           = _d($__value['duedate']);
                    $data->child_invoices[$__key]['allowed_payment_modes']       = unserialize($__value['allowed_payment_modes']);
                    $data->child_invoices[$__key]['formatted_total_left_to_pay'] = app_format_money($data->child_invoices[$__key]['total_left_to_pay'], $__value['currency_name']);

                    foreach ($data->child_invoices[$__key]['items'] as $_key => $item) {

                        $item_taxes = $this->db
                            ->where('rel_type', 'invoice')
                            ->where('itemid', $item['id'])
                            ->get(db_prefix() . 'item_tax')
                            ->result_array();
                        $data->child_invoices[$__key]['items'][$_key]['taxrate'] = [];
                        if (!empty($item_taxes)) {
                            foreach ($item_taxes as $item_tax) {
                                $data->child_invoices[$__key]['items'][$_key]['taxrate'][] = [
                                    'name' => $item_tax['taxname'],
                                    'taxrate' => $item_tax['taxrate'],
                                ];
                            }
                        }
                    }

                    $data->child_invoices[$__key]['deleteable'] = false;
                    if ((get_option('delete_only_on_last_invoice') == 1 && is_last_invoice($__value['id'])) || (get_option('delete_only_on_last_invoice') == 0)) {
                        if (has_permission('invoices', '', 'delete')) {
                            $data->child_invoices[$__key]['deleteable'] = true;
                        }
                    }
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

                try {
                    if (!empty($value['stripe_subscription_id'])) {
                        $data[$key]['stripeSubscription'] = $this->stripe_subscriptions->get_subscription($value['stripe_subscription_id']);

                        if ($value['status'] != 'canceled' && $value['status'] !== 'incomplete_expired') {
                            $data[$key]['upcoming_invoice'] = $this->stripe_subscriptions->get_upcoming_invoice($value['stripe_subscription_id']);

                            $data[$key]['upcoming_invoice'] = mpc_subscription_invoice_preview_data((object) $value, $data[$key]['upcoming_invoice'], $data[$key]['stripeSubscription']);

                            // Throwing errors when not set in the invoice preview area
                            if (!isset($data[$key]['upcoming_invoice']->include_shipping)) {
                                $data[$key]['upcoming_invoice']->include_shipping = 0;
                            }

                            foreach ($data[$key]['upcoming_invoice']->items as $_key => $item) {

                                $item_taxes = $this->db
                                    ->where('rel_type', 'invoice')
                                    ->where('itemid', $item['id'])
                                    ->get(db_prefix() . 'item_tax')
                                    ->result_array();
                                $data[$key]['upcoming_invoice']->items[$_key]['taxrate'] = [];
                                if (!empty($item_taxes)) {
                                    foreach ($item_taxes as $item_tax) {
                                        $data[$key]['upcoming_invoice']->items[$_key]['taxrate'][] = [
                                            'name' => $item_tax['taxname'],
                                            'taxrate' => $item_tax['taxrate'],
                                        ];
                                    }
                                }
                            }

                            $data[$key]['upcoming_invoice']->formatted_date        = _d($data[$key]['upcoming_invoice']->date);
                            $data[$key]['upcoming_invoice']->formatted_duedate     = _d($data[$key]['upcoming_invoice']->duedate);
                            $data[$key]['upcoming_invoice']->formatted_total = app_format_money($data[$key]['upcoming_invoice']->total, $data[$key]['upcoming_invoice']->currency_name);
                            $data[$key]['upcoming_invoice']->formatted_subtotal = app_format_money($data[$key]['upcoming_invoice']->subtotal, $data[$key]['upcoming_invoice']->currency_name);
                            $data[$key]['upcoming_invoice']->formatted_total_left_to_pay = app_format_money($data[$key]['upcoming_invoice']->total_left_to_pay, $data[$key]['upcoming_invoice']->currency_name);

                            $data[$key]['upcoming_invoice']->bill_to  = format_customer_info((object) $data[$key]['upcoming_invoice'], 'customer', 'billing');
                            $data[$key]['upcoming_invoice']->ship_to = format_customer_info((object) $data[$key]['upcoming_invoice'], 'customer', 'shipping');
                        }
                    }
                } catch (Exception $e) {
                    if ($this->stripe_subscriptions->has_api_key()) {
                        $data[$key]['subscription_error'] = $e->getMessage();
                    } else {
                        $data[$key]['subscription_error'] = check_for_links(_l('api_key_not_set_error_message', admin_url('settings?group=payment_gateways&tab=online_payments_stripe_tab')));
                    }
                }

                $data[$key]['child_invoices'] = $this->subscriptions_model->get_child_invoices($value['id']);
                $data[$key]['child_invoices'] = json_decode(json_encode($data[$key]['child_invoices']), true);

                foreach ($data[$key]['child_invoices'] as $__key => $__value) {
                    $template_name = 'invoice_send_to_customer';
                    if ($__value['sent'] == 1) {
                        $template_name = 'invoice_send_to_customer_already_sent';
                    }

                    $data[$key]['child_invoices'][$__key]['mail_data'] = prepare_mail_preview_data($template_name, $data[$key]['child_invoices'][$__key]['clientid']);

                    $data[$key]['child_invoices'][$__key]['invoice_number']              = format_invoice_number($__value['id']);
                    $data[$key]['child_invoices'][$__key]['formatted_total']             = app_format_money($__value['total'], $__value['currency_name']);
                    $data[$key]['child_invoices'][$__key]['formatted_date']              = _d($__value['date']);
                    $data[$key]['child_invoices'][$__key]['formatted_duedate']           = _d($__value['duedate']);
                    $data[$key]['child_invoices'][$__key]['allowed_payment_modes']       = unserialize($__value['allowed_payment_modes']);
                    $data[$key]['child_invoices'][$__key]['formatted_total_left_to_pay'] = app_format_money($data[$key]['child_invoices'][$__key]['total_left_to_pay'], $__value['currency_name']);

                    foreach ($data[$key]['child_invoices'][$__key]['items'] as $_key => $item) {
                        $item_taxes = $this->db
                            ->where('rel_type', 'invoice')
                            ->where('itemid', $item['id'])
                            ->get(db_prefix() . 'item_tax')
                            ->result_array();

                        $data[$key]['child_invoices'][$__key]['items'][$_key]['taxrate'] = [];

                        if (!empty($item_taxes)) {
                            foreach ($item_taxes as $item_tax) {
                                $data[$key]['child_invoices'][$__key]['items'][$_key]['taxrate'][] = [
                                    'name' => $item_tax['taxname'],
                                    'taxrate' => $item_tax['taxrate'],
                                ];
                            }
                        }
                    }

                    $data[$key]['child_invoices'][$__key]['deleteable'] = false;
                    if ((get_option('delete_only_on_last_invoice') == 1 && is_last_invoice($__value['id'])) || (get_option('delete_only_on_last_invoice') == 0)) {
                        if (has_permission('invoices', '', 'delete')) {
                            $data[$key]['child_invoices'][$__key]['deleteable'] = true;
                        }
                    }
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

        if (has_permission('subscriptions', '', 'view') || true == $api) {
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
                ' . db_prefix() . 'subscriptions.clientid, 
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
                // }

                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'subscriptions.project_id', $filters['project_id']);
                }
            }

            if (!has_permission('subscriptions', '', 'view')) {
                $this->db->where(db_prefix() . 'subscriptions.created_from=' . get_staff_user_id());
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
        }

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
