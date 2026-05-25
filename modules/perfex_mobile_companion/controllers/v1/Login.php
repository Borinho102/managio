<?php defined('BASEPATH') or exit('No direct script access allowed');
require __DIR__ . '/../API_Controller.php';
class Login extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Authentication_model');
        $this->load->model('Staff_model');
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
        $data = $this->Authentication_model->login($email, $password, true, true);
        if ($data == 1) {
            $select_str = '*,CONCAT(firstname,\' \',lastname) as full_name';
            $select_str .= ',(SELECT COUNT(*) FROM ' . db_prefix() . 'notifications WHERE touserid=' . get_staff_user_id() . ' and isread=0) as total_unread_notifications, (SELECT COUNT(*) FROM ' . db_prefix() . 'todos WHERE finished=0 AND staffid=' . get_staff_user_id() . ') as total_unfinished_todos';

            $this->db->select($select_str);
            $staff = $this->db->where('email', $email)->get(db_prefix() . 'staff')->row();
            $staff->profile_image = staff_profile_image_url($staff->staffid);
            $staff->permissions = $this->staff_model->get_staff_permissions($staff->staffid);
            $staff->menu_items = [
                'customers' => has_permission('customers', '', 'view') || (have_assigned_customers() || (!have_assigned_customers() && has_permission('customers', '', 'create'))),
                'proposals' => (has_permission('proposals', '', 'view') || has_permission('proposals', '', 'view_own')) || (staff_has_assigned_proposals() && get_option('allow_staff_view_proposals_assigned') == 1),
                'estimates' => (has_permission('estimates', '', 'view') || has_permission('estimates', '', 'view_own')) || (staff_has_assigned_estimates() && get_option('allow_staff_view_estimates_assigned') == 1),
                'invoices' => (has_permission('invoices', '', 'view') || has_permission('invoices', '', 'view_own')) || (staff_has_assigned_invoices() && get_option('allow_staff_view_invoices_assigned') == 1),
                'payments' => has_permission('payments', '', 'view') || has_permission('invoices', '', 'view_own') || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices()),
                'credit_notes' => has_permission('credit_notes', '', 'view') || has_permission('credit_notes', '', 'view_own'),
                'items' => has_permission('items', '', 'view'),
                'subscriptions' => has_permission('subscriptions', '', 'view') || has_permission('subscriptions', '', 'view_own'),
                'expenses' => has_permission('expenses', '', 'view') || has_permission('expenses', '', 'view_own'),
                'contracts' => has_permission('contracts', '', 'view') || has_permission('contracts', '', 'view_own'),
                'projects' => true,
                'tasks' => true,
                'tickets' => (!is_staff_member() && get_option('access_tickets_to_none_staff_members') == 1) || is_staff_member(),
                'leads' => is_staff_member(),
                'staff' => has_permission('staff', '', 'view')
            ];
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
                        'user_id' => $staff->staffid,
                        'login_as' => 'staff',
                    ]
                )
            ) {
                $data = [
                    'login_as' => 'staff',
                    'userid' => $staff->staffid,
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
                                'data'  => $staff
                            ],
                        ],
                        200
                    );
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

    public function login_qr_code_otp_api()
    {
        header("Access-Control-Allow-Origin: *");
        // API Configuration
        $this->_apiConfig([
            'methods' => ['POST'],
        ]);

        if (!empty($this->input->post('qr_code_otp'))) {
            $data = $this->Api_model->adminLoginWithToken($this->input->post('qr_code_otp'), true, true);
            if ($data == 1) {
                $select_str = '*,CONCAT(firstname,\' \',lastname) as full_name';
                $select_str .= ',(SELECT COUNT(*) FROM ' . db_prefix() . 'notifications WHERE touserid=' . get_staff_user_id() . ' and isread=0) as total_unread_notifications, (SELECT COUNT(*) FROM ' . db_prefix() . 'todos WHERE finished=0 AND staffid=' . get_staff_user_id() . ') as total_unfinished_todos';

                $this->db->select($select_str);
                $staff = $this->db->where('staffid', get_staff_user_id())->get(db_prefix() . 'staff')->row();
                $staff->profile_image = staff_profile_image_url($staff->staffid);
                $staff->permissions = $this->staff_model->get_staff_permissions($staff->staffid);
                $staff->menu_items = [
                    'customers' => has_permission('customers', '', 'view') || (have_assigned_customers() || (!have_assigned_customers() && has_permission('customers', '', 'create'))),
                    'proposals' => (has_permission('proposals', '', 'view') || has_permission('proposals', '', 'view_own')) || (staff_has_assigned_proposals() && get_option('allow_staff_view_proposals_assigned') == 1),
                    'estimates' => (has_permission('estimates', '', 'view') || has_permission('estimates', '', 'view_own')) || (staff_has_assigned_estimates() && get_option('allow_staff_view_estimates_assigned') == 1),
                    'invoices' => (has_permission('invoices', '', 'view') || has_permission('invoices', '', 'view_own')) || (staff_has_assigned_invoices() && get_option('allow_staff_view_invoices_assigned') == 1),
                    'payments' => has_permission('payments', '', 'view') || has_permission('invoices', '', 'view_own') || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices()),
                    'credit_notes' => has_permission('credit_notes', '', 'view') || has_permission('credit_notes', '', 'view_own'),
                    'items' => has_permission('items', '', 'view'),
                    'subscriptions' => has_permission('subscriptions', '', 'view') || has_permission('subscriptions', '', 'view_own'),
                    'expenses' => has_permission('expenses', '', 'view') || has_permission('expenses', '', 'view_own'),
                    'contracts' => has_permission('contracts', '', 'view') || has_permission('contracts', '', 'view_own'),
                    'projects' => true,
                    'tasks' => true,
                    'tickets' => (!is_staff_member() && get_option('access_tickets_to_none_staff_members') == 1) || is_staff_member(),
                    'leads' => is_staff_member(),
                    'staff' => has_permission('staff', '', 'view')
                ];
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
                            'user_id' => $staff->staffid,
                        ]
                    )
                ) {
                    $data = [
                        'login_as' => 'staff',
                        'userid' => $staff->staffid,
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
                                    'data'  => $staff
                                ],
                            ],
                            200
                        );
                        die();
                    }
                } else {
                    $this->api_return([
                        'status' => FALSE,
                        'message' => 'Could not save the key'
                    ], 200); // INTERNAL_SERVER_ERROR (500) being the HTTP response code
                }
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

    /**
     * view method
     *
     * @link [api/user/view]
     * @method POST
     * @return Response|void
     */
    public function view()
    {
        header("Access-Control-Allow-Origin: *");

        // API Configuration [Return Array: User Token Data]
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

        $company_logo = get_option('company_logo');
        $company_logo_dark = get_option('company_logo_dark');

        $perfex_crm_url = parse_url(base_url());
        $this->api_return(
            [
                'status' => true,
                'color' => $color,
                'background' => $background,
                'domain' => $_SERVER['SERVER_NAME'],
                'identifier' => str_replace('/', '_', $perfex_crm_url['host'] . $perfex_crm_url['path']),
                'company_logo' => ($company_logo != '' ? base_url('uploads/company/' . $company_logo) : ''),
                'company_logo_dark' => ($company_logo_dark != '' ? base_url('uploads/company/' . $company_logo_dark) : ''),
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

    public function forgot_password()
    {
        header("Access-Control-Allow-Origin: *");

        $this->form_validation->set_rules('email', _l('admin_auth_login_email'), 'trim|required|valid_email|callback_email_exists');
        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $success = $this->Authentication_model->forgot_password($this->input->post('email'), true);
                if (is_array($success) && isset($success['memberinactive'])) {
                    $message = array(
                        'status' => FALSE,
                        'message' => _l('inactive_account')
                    );
                    $this->api_return($message, 200);
                } elseif ($success == true) {
                    $message = array(
                        'status' => TRUE,
                        'message' => _l('check_email_for_resetting_password')
                    );
                    $this->api_return($message, 200);
                } else {
                    $message = array(
                        'status' => FALSE,
                        'message' => _l('error_setting_new_password_key')
                    );
                    $this->api_return($message, 200);
                }
            } else {
                $message = array(
                    'status' => FALSE,
                    'error' => $this->form_validation->error_array(),
                    'message' => validation_errors()
                );
                $this->api_return($message, 200);
            }
        }
    }

    public function email_exists($email)
    {
        $total_rows = total_rows(db_prefix() . 'staff', [
            'email' => $email,
        ]);
        if ($total_rows == 0) {
            $this->form_validation->set_message('email_exists', _l('auth_reset_pass_email_not_found'));

            return false;
        }

        return true;
    }

    public function logout($user_id, $device_id)
    {
        header("Access-Control-Allow-Origin: *");

        $this->db
            ->where('user_id', $user_id)
            ->where('device_id', $device_id)
            ->delete(db_prefix() . 'push_notification_devices');

        if ($this->db->affected_rows() > 0) {
            // success
            $message = array(
                'status' => TRUE,
                'message' => 'Device Delete Successful.'
            );
            $this->api_return($message, 200);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => 'Device Delete Fail.'
            );
            $this->api_return($message, 200);
        }
    }
}
