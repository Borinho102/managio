<?php
defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../API_Controller.php';
class Login extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Authentication_model');
        $this->load->model('credit_notes_model');
        $this->load->model('Clients_model');
        $this->load->model('Api_model');
        $this->load->config('rest');
    }

    public function login_api()
    {
        header("Access-Control-Allow-Origin: *");
        // API Configuration
        $this->_apiConfig([
            'methods' => ['POST'],
        ]);

        $email    = $this->input->post('email');
        $password = $this->input->post('password', false);

        if (empty($email)) {
            $entityBody = file_get_contents('php://input');
            $entityBody = json_decode($entityBody);
            $email    = $entityBody->email;
            $password = $entityBody->password;
        }

        $data = $this->Authentication_model->login($email, $password, false, false);


        if ($data) {

            $select_str = '*,CONCAT(firstname,\' \',lastname) as full_name';
            $this->db->select($select_str);
            $contact = $this->db->where('email', $email)->get(db_prefix() . 'contacts')->row();

            if ($contact->active == 0) {
                $this->api_return([
                    'status' => FALSE,
                    'message' => 'Inactive Account'
                ], 200);
                die();
            }

            $contact->profile_image = contact_profile_image_url($contact->id);

            $contact->company_name = get_company_name($contact->userid);

            $contact->permissions = [];

            $contact->permissions['invoice']    = has_contact_permission('invoices', $contact->id) ? 1 : 0;

            $contact->permissions['proposal']    = has_contact_permission('proposals', $contact->id) ? 1 : 0;

            $contact->permissions['estimate']    = has_contact_permission('estimates', $contact->id) ? 1 : 0;

            $contact->permissions['ticket']    = has_contact_permission('support', $contact->id) ? 1 : 0;

            $contact->permissions['contract']    = has_contact_permission('contracts', $contact->id) ? 1 : 0;

            $contact->permissions['project']    = has_contact_permission('projects', $contact->id) ? 1 : 0;

            $this->load->library('Authorization_Token');
            $key = $this->_generate_key();

            // If no key level provided, provide a generic key
            $level = 1;
            $ignore_limits = 1;

            // Insert the new key
            if (
                $this->_insert_key(
                    $key,
                    [
                        'level' => $level,
                        'ignore_limits' => $ignore_limits,
                        'user_id' => $contact->id,
                        'login_as' => 'contact',
                    ]
                )
            ) {
                $data = [
                    'login_as' => 'contact',
                    'userid' => $contact->id,
                    'expiration_date' => date('Y-m-d H:i:s', strtotime('+1 year'))
                ];
                $data['token'] = $this->authorization_token->generateToken($data);

                $data['expiration_date'] = to_sql_date($data['expiration_date'], true);
                $this->db->insert(db_prefix() . 'mobile_app_access_tokens', $data);
                $insert_id = $this->db->insert_id();
                if ($insert_id) {
                    $this->api_return(
                        [
                            'status' => true,
                            "result" => [
                                'token' => $data['token'],
                                'key'   => $key,
                                'data'  => $contact
                            ],
                        ],
                        200
                    );

                    hooks()->do_action('after_contact_login');
                    die();
                }
            } else {
                $this->api_return([
                    'status' => FALSE,
                    'message' => 'Could not save the key'
                ], 200); // INTERNAL_SERVER_ERROR (500) being the HTTP response code
            }
        }
        $this->api_return(
            [
                'status' => FALSE,
                "message" => 'Credentials not matched!',
            ],
            400
        );
    }

    /* Helper Methods */

    private function _generate_key()
    {
        do {
            $salt = base_convert(bin2hex($this->security->get_random_bytes(64)), 16, 36);

            // If an error occurred, then fall back to the previous method
            if ($salt === FALSE) {
                $salt = hash('sha256', time() . mt_rand());
            }

            $new_key = substr($salt, 0, config_item('rest_key_length'));
        } while ($this->_key_exists($new_key));

        return $new_key;
    }

    private function _key_exists($key)
    {
        return $this->db
            ->where(config_item('rest_key_column'), $key)
            ->count_all_results(config_item('rest_keys_table')) > 0;
    }

    private function _insert_key($key, $data)
    {
        $data[config_item('rest_key_column')] = $key;
        $data['date_created'] = function_exists('now') ? now() : time();

        return $this->db
            ->set($data)
            ->insert(config_item('rest_keys_table'));
    }

    private function _update_key($key, $data)
    {
        return $this->db
            ->where(config_item('rest_key_column'), $key)
            ->update(config_item('rest_keys_table'), $data);
    }

    private function _delete_key($key)
    {
        return $this->rest->db
            ->where(config_item('rest_key_column'), $key)
            ->delete(config_item('rest_keys_table'));
    }

    public function new_login_as_client($id)
    {
        header("Access-Control-Allow-Origin: *");
        if (is_admin()) {

            $select_str = '*,CONCAT(firstname,\' \',lastname) as full_name';
            $this->db->select($select_str);
            $contact = $this->db->where('userid', $id)->where('is_primary', 1)->get(db_prefix() . 'contacts')->row();

            if (!$contact) {

                echo json_encode([
                    'success' => false,
                    'message' => _l('no_primary_contact'),
                ]);
                die;
            } elseif ($contact->active == '0') {

                echo json_encode([
                    'success' => false,
                    'message' => 'Customer primary contact is not active, please set the primary contact as active in order to login as client',
                ]);
                die;
            }

            $contact->profile_image = contact_profile_image_url($contact->id);
            $contact->permissions = get_contact_permission($contact->id);
            $contact->company_name = get_company_name($contact->userid);

            $this->load->library('Authorization_Token');
            $key = $this->_generate_key();

            // If no key level provided, provide a generic key
            $level = 1;
            $ignore_limits = 1;

            // Insert the new key
            if (
                $this->_insert_key(
                    $key,
                    [
                        'level' => $level,
                        'ignore_limits' => $ignore_limits,
                        'user_id' => $contact->id,
                        'login_as' => 'contact',
                    ]
                )
            ) {
                $data = [
                    'login_as' => 'contact',
                    'userid' => $contact->id,
                    'expiration_date' => date('Y-m-d H:i:s', strtotime('+1 year'))
                ];
                $data['token'] = $this->authorization_token->generateToken($data);

                $data['expiration_date'] = to_sql_date($data['expiration_date'], true);
                $this->db->insert(db_prefix() . 'user_api', $data);
                $insert_id = $this->db->insert_id();
                if ($insert_id) {

                    $user_data = [
                        'client_user_id'      => $id,
                        'contact_user_id'     => $contact->id,
                        'client_logged_in'    => true,

                    ];

                    $this->session->set_userdata($user_data);
                    echo json_encode([
                        'success' => true,
                        'token' => $data['token'],
                        'key'   => $key,
                        'data'  => $contact
                    ]);
                    die;
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Something Went Wrong!',
                ]);
                die;
            }
        }

        echo json_encode([
            'success' => false,
            'message' => 'Something Went Wrong!',
        ]);
        die;
    }

    public function view()
    {
        header("Access-Control-Allow-Origin: *");

        $user_data = $this->_apiConfig([
            'methods' => ['POST'],
            'requireAuthorization' => true,
        ]);

        $this->api_return(
            [
                'status' => true,
                "result" => [
                    'user_data' => $user_data['token_data']
                ],
            ],
            200
        );
    }

    public function api_key()
    {
        $this->_APIConfig([
            'methods' => ['POST'],
            'key' => ['header', 'Set API Key'],
        ]);
    }

    public function validate_url()
    {
        header("Access-Control-Allow-Origin: *");
        $background = '#415165';
        $color = '#fff';
        foreach ($this->get_applied_styling() as $style) {
            if ($style->id == 'top-header') {
                $background = $style->color;
            }
            if ($style->id == 'top-header-link') {
                $color = $style->color;
            }
        }
        $this->api_return(
            [
                'status' => true,
                'color' => $color,
                'background' => $background,
                'domain' => $_SERVER['SERVER_NAME'],
                'identifier' => $_SERVER['SERVER_NAME'] . str_replace('/', '_', $_SERVER['REQUEST_URI']),
                "message" => 'api integration is valid.',
            ],
            200
        );
    }

    private function get_applied_styling()
    {
        $theme_style = get_option('theme_style');
        if ($theme_style == '') {
            return [];
        }
        $theme_style = json_decode($theme_style);

        return $theme_style;
    }

    public function logout_contact($clientid)
    {
        header('Access-Control-Allow-Origin: *');

        $authorization    = $this->input->post('authorization');
        $api_key = $this->input->post('api_key');

        if (empty($authorization)) {
            $entityBody = file_get_contents('php://input');
            $entityBody = json_decode($entityBody);
            $authorization    = $entityBody->authorization;
            $api_key = $entityBody->api_key;
        }
        $contact_id = $this->db->where('key', $api_key)->get(db_prefix() . 'keys')->row();

        $this->Authentication_model->logout_contact($authorization, $api_key);
        return true;
    }

    public function get_invoice_receipt($id)
    {

        header("Access-Control-Allow-Origin: *");
        if (!empty($id)) {
            $this->load->model('invoices_model');
            $data = $this->invoices_model->get($id);

            if ($data) {
                $template_name = 'invoice_send_to_customer';
                if ($data->sent == 1) {
                    $template_name = 'invoice_send_to_customer';
                }

                $data->email_data = prepare_mail_preview_data($template_name, $data->clientid);

                $data->invoice_number = format_invoice_number($id);
                $data->formatted_total = app_format_money($data->total, $data->currency_name);
                $data->formatted_date = _d($data->date);
                $data->formatted_duedate = _d($data->duedate);
                $data->allowed_payment_modes = unserialize($data->allowed_payment_modes);
                $data->formatted_total_left_to_pay = app_format_money($data->total_left_to_pay, $data->currency_name);
                $data->status_name = format_invoice_status($data->status, 'inline-block', false);
                $data->invoice_wallet = 0;
                $data->applied_credits = '$' . array_sum(array_column($this->credit_notes_model->get_applied_invoice_credits($id), 'amount'));

                $data->billing_street = (!empty($data->billing_street) ? $data->billing_street : $data->client->billing_street);
                $data->billing_city = (!empty($data->billing_city) ? $data->billing_city : $data->client->billing_city);
                $data->billing_state = (!empty($data->billing_state) ? $data->billing_state : $data->client->billing_state);
                $data->billing_zip = (!empty($data->billing_zip) ? $data->billing_zip : $data->client->billing_zip);
                $billingCountry = null;
                if (get_country($data->client->billing_country) != null) {
                    $billingCountry = get_country($data->client->billing_country)->short_name;
                }
                $databillingCountry = null;
                if (get_country($data->billing_country) != null) {
                    $databillingCountry = get_country($data->billing_country)->short_name;
                }
                $data->billing_country = (isset($databillingCountry) ? $databillingCountry : $billingCountry);

                $data->shipping_street = (!empty($data->shipping_street) ? $data->shipping_street : $data->client->shipping_street);
                $data->shipping_city = (!empty($data->shipping_city) ? $data->shipping_city : $data->client->shipping_city);
                $data->shipping_state = (!empty($data->shipping_state) ? $data->shipping_state : $data->client->shipping_state);
                $data->shipping_zip = (!empty($data->shipping_zip) ? $data->shipping_zip : $data->client->shipping_zip);

                $shippingCountry = null;
                if (get_country($data->client->shipping_country) != null) {
                    $shippingCountry = get_country($data->client->shipping_country)->short_name;
                }
                $datashippingCountry = null;
                if (get_country($data->shipping_country) != null) {
                    $datashippingCountry = get_country($data->shipping_country)->short_name;
                }
                $data->shipping_country = (isset($datashippingCountry) ? $datashippingCountry : $shippingCountry);

                $data->payment_recevied = 0;
                if (count($data->payments) > 0 && get_option('show_total_paid_on_invoice') == 1) {

                    $data->payment_recevied =  '-' . app_format_money(sum_from_table(db_prefix() . 'invoicepaymentrecords', array('field' => 'amount', 'where' => array('invoiceid' => $data->id))), $data->currency_name);
                }

                $attachmentsObj = [];
                if (is_array($data->attachments)) {

                    foreach ($data->attachments as $attachment) {

                        if ($attachment['visible_to_customer'] == 0) {
                            continue;
                        }

                        $path['link'] = site_url() . 'download/file/sales_attachment/' . $attachment['attachment_key'];
                        $path['file_name'] =  $attachment['file_name'];

                        array_push($attachmentsObj, $path);
                    }
                }

                $data->attachments = $attachmentsObj;

                $Taxes = get_items_table_data($data, 'invoice')->taxes();
                $data->taxes = [];
                if (!empty($Taxes)) {
                    foreach ($Taxes as $tax) {
                        $data->taxes[] = [
                            'taxname' => $tax['taxname'],
                            'taxrate' => $tax['taxrate'],
                            'total_tax' => app_format_money($tax['total_tax'], $data->currency_name),
                        ];
                    }
                }
                $data->duedays = get_total_days_overdue($data->duedate);
                foreach ($data->items as $key => $item) {

                    $item_taxes = $this->db
                        ->where('rel_type', 'invoice')
                        ->where('itemid', $item['id'])
                        ->get(db_prefix() . 'item_tax')
                        ->result_array();
                    $data->items[$key]['taxrate'] = [];
                    if (!empty($item_taxes)) {
                        foreach ($item_taxes as $item_tax) {
                            $data->items[$key]['taxrate'][] = [
                                'name' => $item_tax['taxname'],
                                'taxrate' => $item_tax['taxrate'],
                            ];
                        }
                    }
                }

                $alert_class = null;
                $msg = '';
                if ($this->session->flashdata('message-success')) {
                    $alert_class = 'success';
                    $msg = $this->session->flashdata('message-success');
                } elseif ($this->session->flashdata('message-warning')) {
                    $alert_class = 'warning';
                    $msg = $this->session->flashdata('message-warning');
                } elseif ($this->session->flashdata('message-info')) {
                    $alert_class = 'info';
                    $msg = $this->session->flashdata('message-info');
                } elseif ($this->session->flashdata('message-danger')) {
                    $alert_class = 'error';
                    $msg = $this->session->flashdata('message-danger');
                }

                if (!empty($alert_class)) {
                    $data->alert_msg = [$alert_class => $msg];
                }

                $date1 = new DateTime($data->date);
                $date2 = new DateTime($data->duedate);
                $data->payment_term_days = $date1->diff($date2)->days;

                $data = $this->Api_model->get_api_custom_data($data, "invoice", $id);
                $this->api_return($data, 200);
            } else {
                $this->api_return([
                    'status' => FALSE,
                    'message' => 'No data found'
                ], 200);
            }
        } else {
            $this->api_return([
                'status' => FALSE,
                'message' => 'Invoice Id Missing!'
            ], 200);
        }
    }

    public function get_invoice_download($id)
    {
        // Handle Invoice PDF generator
        header("Access-Control-Allow-Origin: *");
        if (!empty($id)) {
            $this->load->model('invoices_model');
            $invoice = $this->invoices_model->get($id);

            try {
                $pdf = invoice_pdf($invoice);
            } catch (Exception $e) {
                echo $e->getMessage();
                die;
            }

            $invoice_number = format_invoice_number($invoice->id);
            $companyname    = get_option('invoice_company_name');
            if ($companyname != '') {
                $invoice_number .= '-' . mb_strtoupper(slug_it($companyname), 'UTF-8');
            }
            $pdf->Output(mb_strtoupper(slug_it($invoice_number), 'UTF-8') . '.pdf', 'D');
            die();
        }
    }
    public function get_invoice_paynow($id, $hash)
    {
        // Handle Invoice PDF generator
        header("Access-Control-Allow-Origin: *");
        // API Configuration
        $this->_apiConfig([
            'methods' => ['POST'],
        ]);

        if (!empty($id)) {
            check_invoice_restrictions($id, $hash);
            $invoice = $this->invoices_model->get($id);

            $invoice = hooks()->apply_filters('before_client_view_invoice', $invoice);

            // Handle $_POST payment
            $this->load->model('payments_model');
            if (!$this->input->post('paymentmode')) {
                set_alert('warning', _l('invoice_html_payment_modes_not_selected'));
                redirect(site_url('invoice/' . $id . '/' . $hash));
            } elseif ((!$this->input->post('amount') || $this->input->post('amount') == 0) && get_option('allow_payment_amount_to_be_modified') == 1) {
                set_alert('warning', _l('invoice_html_amount_blank'));
                redirect(site_url('invoice/' . $id . '/' . $hash));
            }
            $this->payments_model->process_payment($this->input->post(), $id, true);
        }
    }

    public function forgot_password()
    {

        header("Access-Control-Allow-Origin: *");
        // API Configuration
        $this->_apiConfig([
            'methods' => ['POST'],
        ]);
        $user = 1;
        $forgot_password = $this->input->post();
        if ($forgot_password['email']) {
            $table = db_prefix() . 'contacts';
            $this->db->where('email', $forgot_password['email']);
            $email = $this->db->get($table)->row();
            if (empty($email)) {
                $user = 0;
                $this->api_return(
                    [
                        'status' => FALSE,
                        'message' => 'Email not found'
                    ],
                    200
                );
            }
        }

        if ($user == 1) {

            $success = $this->Authentication_model->forgot_password($forgot_password['email']);

            if (is_array($success) && isset($success['memberinactive'])) {

                $this->api_return(
                    [
                        'status' => FALSE,
                        'message' => 'Inactive Account'
                    ],
                    200
                );
            } elseif ($success == true) {

                $this->api_return(
                    [
                        'status' => TRUE,
                        'message' => 'Check your email for further instructions resetting your password'
                    ],
                    200
                );
            } else {

                $this->api_return(
                    [
                        'status' => FALSE,
                        'message' => 'Error setting new password'
                    ],
                    200
                );
            }
        }
    }

    public function reset_password()
    {

        $expire = 0;
        header("Access-Control-Allow-Origin: *");
        // API Configuration
        $this->_apiConfig([
            'methods' => ['POST'],
        ]);

        $data = $this->input->post();
        $staff = $data['staff'];
        $userid = $data['userid'];
        $new_pass_key = $data['new_pass_key'];
        if (empty($staff) && !is_numeric($staff)) {

            $this->api_return(
                [
                    'status' => FALSE,
                    'message' => 'Staff ID Not Found'
                ],
                200
            );
        }

        if (empty($userid) && !is_numeric($userid)) {
            $this->api_return(
                [
                    'status' => FALSE,
                    'message' => 'User ID Not Found'
                ],
                200
            );
        }
        if (empty($new_pass_key) && !is_numeric($new_pass_key)) {
            $this->api_return(
                [
                    'status' => FALSE,
                    'message' => 'Hash key ID Not Found'
                ],
                200
            );
        }

        if (!$this->Authentication_model->can_reset_password($staff, $userid, $new_pass_key)) {
            $expire = 1;
            $this->api_return(
                [
                    'status' => FALSE,
                    'message' => 'Password key expired or invalid user'
                ],
                200
            );
        }

        if ($userid && $expire == 0) {

            hooks()->do_action('before_user_reset_password', [
                'staff'  => $staff,
                'userid' => $userid,
            ]);
            $success = $this->Authentication_model->reset_password(
                0,
                $userid,
                $new_pass_key,
                $this->input->post('password', false)
            );
            if (is_array($success) && $success['expired'] == true) {

                $this->api_return(
                    [
                        'status' => FALSE,
                        'message' => 'Password key expired or invalid user'
                    ],
                    200
                );
            } elseif ($success == true) {
                hooks()->do_action('after_user_reset_password', [
                    'staff'  => $staff,
                    'userid' => $userid,
                ]);
                $this->api_return(
                    [
                        'status' => TRUE,
                        'message' => 'Your password has been reset. Please login now!'
                    ],
                    200
                );
            } else {
                $this->api_return(
                    [
                        'status' => FALSE,
                        'message' => 'Error resetting your password. Try again.'
                    ],
                    200
                );
            }
        }
    }

    public function get_customer_info_ai()
    {
        header("Access-Control-Allow-Origin: *");

        $this->_apiConfig([
            'methods' => ['POST'],
        ]);

        $rawData = file_get_contents('php://input');

        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            // If JSON, decode the data
            $ai_data = json_decode($rawData, true);
        } else {
            // If form data, parse it using your preferred method (e.g., $_POST)
            $ai_data = $this->input->post();
        }

        if (empty($ai_data)) {
            $entityBody = file_get_contents('php://input');
            $entityBody = json_decode($entityBody);
            $ai_data    = (array)$entityBody;
        }

        if (!empty($ai_data) && isset($ai_data['type_data']) && isset($ai_data['client_id'])) {

            if ($ai_data['type_data'] == 'user') {

                $dataProfile = $this->customer_profile($ai_data);

                echo json_encode($dataProfile);
                die;
            } elseif ($ai_data['type_data'] == 'invoices') {

                $dataInv = $this->customer_invoices($ai_data);

                echo json_encode($dataInv);
                die;
            } elseif ($ai_data['type_data'] == 'devices') {

                $dataInv = $this->customer_devices($ai_data);

                echo json_encode($dataInv);
                die;
            } elseif ($ai_data['type_data'] == 'sim_cards') {

                $dataInv = $this->customer_sim_cards($ai_data);

                echo json_encode($dataInv);
                die;
            } elseif ($ai_data['type_data'] == 'service_plans') {

                $dataInv = $this->customer_service_plans($ai_data);

                echo json_encode($dataInv);
                die;
            } else {

                echo json_encode([
                    'status' => FALSE,
                    'message' => 'No type data found'
                ]);
                die;
            }
        } else {
            $this->api_return([
                'status' => FALSE,
                'message' => 'Post Value Missing! Or incorrect'
            ], 400);
        }
    }

    private function customer_profile($ai_data)
    {
        if (isset($ai_data['client_id'])) {
            $this->db->select('*');
            $this->db->where('userid', $ai_data['client_id']);
            $contact_data =  $this->db->get(db_prefix() . 'contacts')->row();
        } else {
            return [
                'status' => FALSE,
                'message' => 'user id missing OR incorrect',
            ];
        }

        $response = [];

        if (!empty($contact_data)) {

            $response['firstname'] = $contact_data->firstname;
            $response['lastname'] = $contact_data->lastname;
            $response['email'] = $contact_data->email;

            echo json_encode($response);
            die;
        } else {
            return [
                'status' => true,
                'message' => 'user not found',
            ];
        }
    }

    private function customer_invoices($ai_data)
    {

        $this->load->model('invoices_model');
        $where = [];

        if (isset($ai_data['start_date']) && isset($ai_data['end_date'])) {
            $where = [
                'clientid' => $ai_data['client_id'],
                'datecreated >=' => $ai_data['start_date'],
                'datecreated <=' => $ai_data['end_date'],

            ];
        } else {

            $where = [
                'clientid' => $ai_data['client_id'],

            ];
        }

        $data = $this->invoices_model->get('', $where, true);

        if ($data) {
            foreach ($data as $key => $value) {
                $dataInv[$key]['invoice_number']               = format_invoice_number($value['id']);
                $dataInv[$key]['formatted_total']               = app_format_money($value['total'], $value['currency_name']);
                $dataInv[$key]['total_left_to_pay']           = get_invoice_total_left_to_pay($value['id'], $value['total']);
                $dataInv[$key]['status_name'] = format_invoice_status($value['status'], 'inline-block', false);
            }

            return $dataInv;
        } else {
            return [
                'status' => FALSE,
                'message' => 'No data found'
            ];
        }
    }

    private function customer_sim_cards($ai_data)
    {

        $this->load->model('sim_card_model');
        $where = [];

        if (isset($ai_data['start_date']) && isset($ai_data['end_date'])) {
            $where = [
                'clientid' => $ai_data['client_id'],
                'created_at >=' => $ai_data['start_date'],
                'created_at <=' => $ai_data['end_date'],

            ];
        } else {

            $where = [
                'clientid' => $ai_data['client_id'],

            ];
        }
        $sim_data = $this->sim_card_model->get('', $where, true);
        $simDataObj = array();
        if ($sim_data) {
            foreach ($sim_data as $value) {
                array_push($simDataObj, $this->create_sim_obj($value));
            }

            return $simDataObj;
        } else {
            return [
                'status' => FALSE,
                'message' => 'No data found'
            ];
        }
    }
}
