<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Setup extends CI_Controller
{
    protected $error = '';

    public $current_step = 1;

    public static $last_step = 4;

    public function __construct()
    {
        parent::__construct();


        $GLOBALS['EXT']->call_hook('pre_controller_constructor');

        $this->load->model('saas_model');
        $this->load->helper('saas');

        if (!class_exists('ForceUTF8\Encoding') && file_exists(APPPATH . 'vendor/autoload.php')) {
            require_once(APPPATH . 'vendor/autoload.php');
        }

        $this->db->reconnect();

        if (is_mobile()) {
            $this->session->set_userdata(['is_mobile' => true]);
        } else {
            $this->session->unset_userdata('is_mobile');
        }
        $timezone = get_option('saas_default_timezone');

        if ($timezone != '') {
            date_default_timezone_set($timezone);
        }
        load_admin_language();
        $vars = [];
        $vars['locale'] = $GLOBALS['locale'];
        $vars['language'] = $GLOBALS['language'];
        $this->load->vars($vars);

        $is_active = $this->saas_model->is_company_active();
        if (!empty($is_active) && !empty($is_active->db_name)) {
            redirect('login');
        }

    }

    public function index()
    {
        error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
        ini_set('max_execution_time', 30000);

        $company_info = $this->resolveSetupCompany();
        if (empty($company_info) || empty($company_info->id)) {
            $this->renderSetupError(_l('invalid_activation_code'));
            return;
        }

        $company_id = (int) $company_info->id;
        $activation_token = $company_info->activation_code ?? '';
        $data['activation_token'] = $activation_token;
        $data['subs_info'] = get_company_subscription_by_id($company_id);
        if (empty($data['subs_info'])) {
            $data['subs_info'] = $company_info;
        }

        $post_data = $this->input->post();
        if (!empty($post_data) && !empty($post_data['package_id'])) {
            $this->handleSetupCheckout($company_info, $data, $post_data);
            return;
        }

        $frequency = !empty($data['subs_info']->frequency)
            ? $data['subs_info']->frequency
            : 'monthly';
        $billing_cycle = function_exists('saas_normalize_billing_cycle')
            ? saas_normalize_billing_cycle($frequency)
            : (str_replace('_price', '', $frequency) . '_price');
        $data['frequency'] = str_replace('_price', '', $billing_cycle);
        $data['package_id'] = $data['subs_info']->package_id ?? $company_info->package_id ?? 0;

        $package_info = get_old_result('tbl_saas_packages', ['id' => $data['package_id']], false);
        if (empty($package_info)) {
            $this->renderSetupError(_l('package_not_found') ?: 'Package not found');
            return;
        }

        $data['title'] = _l('checkout') . ' ' . _l('payment') . ' ' . _l('for') . ' ' . $package_info->name;
        $data['package_info'] = $package_info;
        $data['all_packages'] = get_old_result('tbl_saas_packages', ['status' => 'published']);
        $data['payment_modes'] = $this->saas_model->get_payment_modes(false, $package_info, $billing_cycle);
        $data['requires_payment'] = function_exists('saas_package_requires_payment')
            ? saas_package_requires_payment($package_info, $billing_cycle)
            : ((float) ($package_info->monthly_price ?? 0) > 0);
        $data['url'] = base_url('setup');
        $data['setup'] = true;
        $data['subview'] = $this->load->view('saas/packages/checkoutPaymentOpen', $data, true);
        $this->load->view('_layout_package', $data);
    }

    /**
     * Resolve the pending company from activation code, domain query, or subdomain.
     */
    private function resolveSetupCompany()
    {
        $code = $this->input->get('c', true);
        $token = '';
        if (!empty($code)) {
            $token = function_exists('url_decode') ? url_decode($code) : $code;
        }
        if (empty($token)) {
            $token = $this->input->post('activation_token', true);
        }
        if (!empty($token)) {
            $company = get_old_result('tbl_saas_companies', ['activation_code' => $token], true);
            if (!empty($company) && !empty($company->id)) {
                return $company;
            }
        }

        $domain = $this->input->get('d', true);
        if (empty($domain) && function_exists('subdomain')) {
            $domain = subdomain();
        }
        if (!empty($domain)) {
            $company = get_old_result('tbl_saas_companies', ['domain' => $domain], true);
            if (!empty($company) && !empty($company->id)) {
                return $company;
            }
        }

        return null;
    }

    private function handleSetupCheckout($company_info, array $data, array $post_data)
    {
        $company_id = (int) $company_info->id;
        $package_id = $post_data['package_id'] ?? ($data['subs_info']->package_id ?? 0);
        $billing_cycle = function_exists('saas_normalize_billing_cycle')
            ? saas_normalize_billing_cycle($post_data['billing_cycle'] ?? 'monthly')
            : 'monthly_price';
        $frequency = str_replace('_price', '', $billing_cycle);
        $package_info = get_old_result('tbl_saas_packages', ['id' => $package_id], false);
        if (empty($package_info)) {
            set_alert('warning', _l('package_not_found') ?: 'Package not found');
            redirect(base_url('setup'));
            return;
        }

        $amount = function_exists('saas_package_cycle_price')
            ? saas_package_cycle_price($package_info, $billing_cycle)
            : (float) ($package_info->{$billing_cycle} ?? 0);
        $requires_payment = function_exists('saas_package_requires_payment')
            ? saas_package_requires_payment($package_info, $billing_cycle, $amount)
            : $amount > 0;

        if (!$requires_payment) {
            try {
                $result = $this->saas_model->update_company_packages([
                    'package_id'     => $package_info->id,
                    'company_id'     => $company_id,
                    'package_name'   => $package_info->name,
                    'frequency'      => $frequency,
                    'billing_cycle'  => $billing_cycle,
                    'amount'         => $amount,
                    'expired_date'   => $post_data['expired_date'] ?? null,
                    'payment_method' => 'free',
                    'currency'       => function_exists('saas_package_currency') ? saas_package_currency($package_info) : 'XAF',
                    'mark_paid'      => true,
                ]);
                if ($result === false) {
                    throw new RuntimeException(_l('create_database_error') ?: 'Could not activate this package.');
                }
                set_alert('success', _l('account_ready') ?: 'Your account is ready.');
                $fresh = get_old_result('tbl_saas_companies', ['id' => $company_id], true);
                $redirect = !empty($fresh->domain) && function_exists('companyUrl')
                    ? rtrim(companyUrl($fresh->domain), '/') . '/admin'
                    : base_url('login');
                redirect($redirect);
                return;
            } catch (Throwable $e) {
                log_message('error', '[setup] free activate: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                set_alert('danger', $e->getMessage());
                redirect(base_url('setup'));
                return;
            }
        }

        $payment_method = get_old_result('tbl_saas_payment_methods', ['id' => $post_data['paymentmode'] ?? 0], false);
        if (empty($payment_method)) {
            set_alert('warning', _l('payment_method_not_found'));
            redirect($_SERVER['HTTP_REFERER'] ?? base_url('setup'));
            return;
        }

        saas_assert_package_checkout_gateway($payment_method->gateway_name, $package_info, $billing_cycle);

        $post_data['companies_id'] = $post_data['companies_id'] ?? $company_id;
        $post_data['billing_cycle'] = $billing_cycle;
        $post_data['package_id'] = $package_id;
        $post_data['amount'] = $amount;

        $subs_info = $data['subs_info'];
        $gateway_name = $payment_method->gateway_name;
        $paymentGateway = 'Saas_' . ucfirst($gateway_name);
        if (!class_exists($paymentGateway)) {
            set_alert('warning', _l('payment_method_not_found'));
            redirect($_SERVER['HTTP_REFERER'] ?? base_url('setup'));
            return;
        }

        try {
            $gateway = new $paymentGateway();
            $result = $gateway->getPaymentForm($post_data, $subs_info);
        } catch (Throwable $e) {
            log_message('error', '[setup] payment form: ' . $e->getMessage());
            set_alert('danger', $e->getMessage());
            redirect($_SERVER['HTTP_REFERER'] ?? base_url('setup'));
            return;
        }

        if (empty($result['paymentForm'])) {
            set_alert('warning', !empty($result['error']) ? $result['error'] : _l('payment_method_not_found'));
            redirect($_SERVER['HTTP_REFERER'] ?? base_url('setup'));
            return;
        }

        $data['frequency'] = $frequency;
        $data['paymentForm'] = $result['paymentForm'];
        $data['package_id'] = $package_id;
        $data['company_id'] = $post_data['companies_id'] ?? $company_id;
        $data['title'] = _l('checkout') . ' ' . _l('payment') . ' ' . _l('for') . ' ' . $package_info->name;
        $data['package_info'] = $package_info;
        $data['url'] = base_url('setup');
        $data['setup'] = true;
        $data['subview'] = $this->load->view('saas/packages/checkoutPaymentPage', $data, true);
        $this->load->view('_layout_package', $data);
    }

    private function renderSetupError($message)
    {
        $data['title'] = _l('welcome_to') . ' ' . get_option('saas_companyname');
        $data['error'] = $message;
        $data['activation_token_error'] = $message;
        log_message('error', '[setup] ' . $message . ' host=' . ($_SERVER['HTTP_HOST'] ?? ''));
        $this->load->view('saas/settings/domain_not_registered', $data);
    }


    public function get_package_info()
    {
        // check is ajax request
        if (!$this->input->is_ajax_request()) {
            if (is_admin()) {
                redirect('admin/dashboard');
            } else {
                redirect('/register');
            }
        }

        $data['title'] = 'Dashboard';
        $package_id = $this->input->post('package_id') ?? 2;
        $front = $this->input->post('front');
        $company_id = $this->input->post('company_id', true);
        $package_type = function_exists('saas_normalize_billing_cycle')
            ? saas_normalize_billing_cycle($this->input->post('package_type') ?: 'monthly_price')
            : 'monthly_price';

        $type = str_replace('_price', '', $package_type);
        $data['type_title'] = $type;
        if ($type == 'lifetime') {
            $data['renew_date'] = date('Y-m-d', strtotime('+100 year'));
        } elseif ($type == 'yearly') {
            $data['renew_date'] = date('Y-m-d', strtotime('+1 year'));
        } else {
            $data['renew_date'] = date('Y-m-d', strtotime('+1 month'));
        }

        $data['type'] = $package_type;
        $data['package_info'] = get_old_result('tbl_saas_packages', array('id' => $package_id), false);
        if (empty($data['package_info'])) {
            echo json_encode(['error' => _l('package_not_found')]);
            exit();
        }
        $data['package_info'] = apply_coupon($data['package_info']);
        $data['options'] = get_active_frequency(true);
        $data['company_id'] = $company_id;
        $data['front'] = $front;
        $data['other'] = str_replace('_price', '_offer', $data['type']);

        $_data['package_form_group'] = $this->load->view('saas/packages/package_billing', $data, true);
        $_data['package_details'] = $this->load->view('saas/packages/plain_package_details', $data, true);
        $_data['package_info'] = $data['package_info'];
        $_data['requires_payment'] = function_exists('saas_package_requires_payment')
            ? saas_package_requires_payment($data['package_info'], $package_type)
            : ((float) ($data['package_info']->{$package_type} ?? 0) > 0);
        $payment_modes = $this->saas_model->get_payment_modes(false, $data['package_info'], $package_type);
        $_data['payment_methods_html'] = $this->load->view('saas/packages/payment_methods_radios', [
            'payment_modes' => $payment_modes,
            'requires_payment' => $_data['requires_payment'],
        ], true);
        echo json_encode($_data);
        exit();
    }


    public function check_coupon_code()
    {
        // check is ajax request
        if (!$this->input->is_ajax_request()) {
            if (is_admin()) {
                redirect('admin/dashboard');
            } else {
                redirect('/register');
            }
        }

        $coupon_code = $this->input->post('coupon_code', true);
        $package_id = $this->input->post('package_id', true);
        $billing_cycle = $this->input->post('billing_cycle', true);
        $email = $this->input->post('email', true);

        $where = array('code' => $coupon_code, 'status' => 'active');
        $coupon_info = get_old_result('tbl_saas_coupon', $where, false);

        if (!empty($coupon_info)) {
            // check coupon end date must be greater than or equal to current date
            if (strtotime($coupon_info->end_date) <= strtotime(date('Y-m-d'))) {
                $result['error'] = true;
                $result['message'] = _l('coupon_expired');
                $result['coupon_code_input'] = null;
            } else {
                $user_id = get_staff_user_id();
                if (!empty($user_id)) {
                    $where = array('user_id' => $user_id, 'coupon' => $coupon_code);
                } else {
                    $where = array('email' => $email, 'coupon' => $coupon_code);
                }

                $already_apply = get_old_result('tbl_saas_applied_coupon', $where, false);
                if (empty($already_apply)) {
                    $package_info = get_old_result('tbl_saas_packages', array('id' => $package_id), false);
                    $sub_total = $package_info->$billing_cycle;
                    $percentage = $coupon_info->amount;

                    if ($coupon_info->type == 1) {
                        $discount_amount = ($percentage / 100) * $sub_total;
                        $discount_percentage = $percentage . '%';
                    } else {
                        $discount_amount = $percentage;
                        $discount_percentage = $percentage;
                    }
                    $packageCurrency = saas_package_currency($package_info);
                    $result['sub_total_text'] = display_money($sub_total, $packageCurrency);
                    $result['sub_total_input'] = $sub_total;
                    $result['total_text'] = display_money($sub_total - $discount_amount, $packageCurrency);
                    $result['total_input'] = $sub_total - $discount_amount;
                    $result['discount_percentage'] = $discount_percentage;
                    $result['coupon_code_input'] = $coupon_code;

                    $html = '';
                    $html .= '<div class="form-group mt-2 mb-2">';
                    $html .= '<div class="input-group"><span class="input-group-text">(' . $discount_percentage . ')</span>';
                    $html .= '<input type="text" class="form-control" name="discount_amount" value="' . $discount_amount . '" readonly >';
                    $html .= '</div></div></div>';

                    $thtml = '';
                    $thtml .= '<div class="form-group mt-2 mb-2"><label class="col-sm-3 control-label">' . _l('total_amount') . '</label>';
                    $thtml .= '<div class="col-sm-5"><div class="input-group"><span class="input-group-text">' . $packageCurrency . '</span>';
                    $thtml .= '<input type="text" class="form-control" name="total_amount" value="' . $result['total_input'] . '" readonly >';
                    $thtml .= '</div></div></div>';

                    if ($coupon_info->package_id == 0) {
                        $result['success'] = true;
                        $result['applied_discount'] = $html;
                        $result['total_amount'] = $thtml;
                        $result['discount_amount_text'] = display_money($discount_amount, $packageCurrency);
                        $result['discount_amount_input'] = $discount_amount;
                    } elseif ($coupon_info->package_id == $package_id) {
                        $result['success'] = true;
                        $result['html'] = $html;
                        $result['message'] = '';
                        $result['discount_amount_text'] = display_money($discount_amount, $packageCurrency);
                        $result['discount_amount_input'] = $discount_amount;
                    } else {
                        $result['error'] = true;
                        $result['message'] = _l('the_coupon_code_is_invalid');
                        $result['coupon_code_input'] = null;
                    }
                } else {
                    $result['error'] = true;
                    $result['message'] = _l('the_coupon_code_already_used');
                    $result['coupon_code_input'] = null;
                }
            }
        } else {
            $result['error'] = true;
            $result['message'] = _l('coupon_not_exist');
            $result['coupon_code_input'] = null;
        }

        echo json_encode($result);
        exit();
    }

    public function steps()
    {
        $step = $this->current_step;
        return [
            [
                'id' => 1,
                'name' => 'Account',
                'status' => $step > 1 ? 'complete' : 'current',
            ],
            [
                'id' => 2,
                'name' => 'Ready to go',
                'status' => $step === 2 ? 'complete' : 'upcoming',
            ],
        ];
    }

    private function complete_install($data)
    {

        $company_info = get_row('tbl_saas_companies', array('activation_code' => $data['activation_token']));
        if (!empty($company_info)) {
            $id = $company_info->id;

            $fresh_db = (!empty($data['fresh_database']) ? $data['fresh_database'] : '');
            $fresh_db = (!empty($fresh_db) ? $fresh_db : '');
            $this->saas_model->create_database($id, $fresh_db);

            $c_data['status'] = 'running';
            $this->saas_model->_table_name = 'tbl_saas_companies';
            $this->saas_model->_primary_key = 'id';
            $this->saas_model->save($c_data, $id);

            $this->saas_model->save_client($id, $data['password']);


            $this->saas_model->send_welcome_email($id, true);
            return true;
        } else {
            return false;
        }
    }

    public function check_existing_activation_token_new($activation_token = null, $front = null)
    {

        if (!empty($this->input->post('name', true))) {
            $activation_token = $this->input->post('name', true);
        }
        if (!empty($activation_token)) {
            $check_token = get_row('tbl_saas_companies', array('activation_code' => $activation_token));
            if (!empty($check_token)) {
                $result['success'] = 1;
                $result['name'] = $check_token->name;
                $result['email'] = $check_token->email;
                // get first name and last name from name
                $name = explode(' ', $check_token->name);
                // if the name have three part then first name and other two part will be last name
                if (count($name) == 3) {
                    $result['first_name'] = $name[0];
                    $result['last_name'] = $name[1] . ' ' . $name[2];
                } else {
                    $result['first_name'] = $name[0];
                    if (isset($name[1])) {
                        $result['last_name'] = $name[1];
                    } else {
                        $result['last_name'] = '';
                    }
                }
            } else {
                $result['error'] = _l('we_did_not_found_your_token');
            }
            if (empty($front)) {
                echo json_encode($result);
                exit();
            } else {
                return $result;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function domain_not_available()
    {
        $sub_domain = subdomain();
        if (!empty($sub_domain)) {
            $domain_available = get_old_result('tbl_saas_companies', array('domain' => $sub_domain));
            $reserved = check_reserved_tenant($sub_domain);
            if (!empty($reserved)) {
                redirect(BaseUrl());
            }
            if (!empty($domain_available)) {
                redirect(config_item('default_controller'));
            } else {
                $data['title'] = _l('welcome_to') . ' ' . config_item('company_name');
                $this->load->view('saas/settings/domain_not_registered', $data);
            }
        } else {
            redirect(config_item('default_controller'));
        }
    }
}
