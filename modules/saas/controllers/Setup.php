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
        if (!empty($is_active)) {
            redirect('login');
        }

    }

    public function index()
    {

        error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
        ini_set('max_execution_time', 30000);
        $data['title'] = _l('welcome_to') . ' ' . get_option('saas_companyname');
        $data['step'] = 1;
        // get code from url by get method
        $code = $this->input->get('c', true);
        $domain = $this->input->get('d', true);
        if (!empty($code)) {
            $activation_token = url_decode($code);
        } else {
            $activation_token = $this->input->post('activation_token', true);
        }
        $activation_token = 'c9259ca4-50d9-4f67-a7fe-699ab0d6feaa';

        if (!empty($activation_token)) {
            $data['activation_token'] = $activation_token;
            $company_info = get_old_result('tbl_saas_companies', array('activation_code' => $activation_token), true);

            $data['subs_info'] = get_company_subscription_by_id($company_info->id);
            $company_id = $company_info->id;

            $post_data = $this->input->post();
            if (!empty($post_data)) {
                if (!empty(is_client_logged_in())) {
                    $subs_info = get_company_subscription_by_id(null, 'running');
                } else {
                    $subs_info = get_company_subscription(null, 'running');
                }
                $payment_method = get_old_result('tbl_saas_payment_methods', ['id' => $post_data['paymentmode']], false);
                if (empty($payment_method)) {
                    // redirect to previous page
                    $type = "warning";
                    $message = _l('payment_method_not_found');
                    set_alert($type, $message);
                    redirect($_SERVER['HTTP_REFERER']);
                }

                $data['frequency'] = str_replace('_price', '', $post_data['billing_cycle']);

                $gateway_name = $payment_method->gateway_name;
                $paymentGateway = 'Saas_' . ucfirst($gateway_name);
                $gateway = new $paymentGateway();
                $result = $gateway->getPaymentForm($post_data, $subs_info);

                if (!empty($result['paymentForm'])) {
                    $data['paymentForm'] = $result['paymentForm'];
                } else {

                    // redirect to previous page
                    $type = "warning";
                    $message = _l('payment_method_not_found');
                    set_alert($type, $message);
                    redirect($_SERVER['HTTP_REFERER']);
                }
                if (!empty(subdomain())) {
                    $front_end = true;
                }
                if (empty($data['package_id']) && !empty(subdomain())) {
                    $data['package_id'] = $post_data['package_id'];
                    $data['company_id'] = $post_data['companies_id'];
                }
                $package_info = get_old_result('tbl_saas_packages', array('id' => $post_data['package_id']), false);
                $data['title'] = _l('checkout') . ' ' . _l('payment') . ' ' . _l('for') . ' ' . $package_info->name;
                $data['package_info'] = $package_info;

                $subview = 'checkoutPaymentPage';

            } else {

                $data['package_id'] = $data['subs_info']->package_id;
                $data['frequency'] = 'monthly';

                if (empty($data['package_id']) && !empty(subdomain())) {
                    $data['package_id'] = $data['subs_info']->package_id;
                    $data['frequency'] = $data['subs_info']->frequency;
                }

                $package_info = get_old_result('tbl_saas_packages', array('id' => $data['package_id']), false);

                $data['title'] = _l('checkout') . ' ' . _l('payment') . ' ' . _l('for') . ' ' . $package_info->name;
                $data['package_info'] = $package_info;
                $data['all_packages'] = get_old_result('tbl_saas_packages', array('status' => 'published'));

                $subview = 'checkoutPaymentOpen';
                if (!empty(subdomain())) {
                    $data['payment_modes'] = $this->saas_model->get_payment_modes();
                    $subview = 'checkoutPaymentOpen';
                } else if (!empty($company_id)) {
                    $data['payment_modes'] = $this->saas_model->get_payment_modes();
                    $subview = 'checkoutPaymentOpen';
                }

            }
            $data['url'] = base_url('setup');
            $data['setup'] = true;
            $data['subview'] = $this->load->view('saas/packages/' . $subview, $data, TRUE);
            $this->load->view('_layout_package', $data);

//            $this->saas_model->login_as_company($company_info->id);


//            if (empty($company_info)) {
//                $data['step'] = 1;
//                $this->current_step = 1;
//                $data['activation_token_error'] = _l('invalid_activation_code');
//                $data['error'] = _l('invalid_activation_code');
//            } else {
//                $data['step'] = (!empty($_POST['step'])) ? $_POST['step'] : 1;
//
//                $data['company_info'] = $company_info;
//                $data['email'] = $company_info->email;
//                if (empty($data['error']) && isset($_POST['step']) && $_POST['step'] == 1) {
//                    // update company info and set active
//                    $this->complete_install($_POST);
//                    $data['step'] = 2;
//                    $this->current_step = $data['step'];
//                } elseif (isset($data['step']) && $data['step'] == 5) {
//                    redirect('admin');
//                }
//            }
        }
//        $form = new stdClass();
//        $form->language = get_option('active_language');
//        $form->recaptcha = 1;
//        $form->success_submit_msg = _l('success_submit_msg');
//        $data['form'] = $form;
//        $data['current_step'] = $this->current_step;
//        $data['steps'] = $this->steps();
//        $this->load->view('saas/settings/setup', $data);
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
        $package_type = $this->input->post('package_type');
        $package_type = (!empty($package_type)) ? $package_type : 'monthly_price';

        // cut _price from package_type
        $type = str_replace('_price', '', $package_type);
        $data['type_title'] = _l($type);
        if ($type == 'lifetime') {
            $data['renew_date'] = date('Y-m-d', strtotime('+100 year'));
        } elseif ($type == 'yearly') {
            $data['renew_date'] = date('Y-m-d', strtotime('+1 year'));
        } else {
            $data['renew_date'] = date('Y-m-d', strtotime('+1 month'));
        }

        $data['type'] = (!empty($package_type)) ? $package_type : 'monthly_price';
        $data['package_info'] = get_old_result('tbl_saas_packages', array('id' => $package_id), false);
        $data['package_info'] = apply_coupon($data['package_info']);
        $data['options'] = get_active_frequency(true);
        $data['company_id'] = $company_id;
        $data['front'] = $front;
        $data['other'] = str_replace('_price', '_offer', $data['type']);

        $_data['package_form_group'] = $this->load->view('saas/packages/package_billing', $data, true);
        $_data['package_details'] = $this->load->view('saas/packages/plain_package_details', $data, true);
        $_data['package_info'] = $data['package_info'];
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
                    $result['sub_total_text'] = display_money($sub_total, default_currency());
                    $result['sub_total_input'] = $sub_total;
                    $result['total_text'] = display_money($sub_total - $discount_amount, default_currency());
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
                    $thtml .= '<div class="col-sm-5"><div class="input-group"><span class="input-group-text">' . default_currency() . '</span>';
                    $thtml .= '<input type="text" class="form-control" name="total_amount" value="' . $result['total_input'] . '" readonly >';
                    $thtml .= '</div></div></div>';

                    if ($coupon_info->package_id == 0) {
                        $result['success'] = true;
                        $result['applied_discount'] = $html;
                        $result['total_amount'] = $thtml;
                        $result['discount_amount_text'] = display_money($discount_amount, default_currency());
                        $result['discount_amount_input'] = $discount_amount;
                    } elseif ($coupon_info->package_id == $package_id) {
                        $result['success'] = true;
                        $result['html'] = $html;
                        $result['message'] = '';
                        $result['discount_amount_text'] = display_money($discount_amount, default_currency());
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


            $this->saas_model->send_welcome_email($id);
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
