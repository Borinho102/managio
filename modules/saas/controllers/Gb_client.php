<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Gb_client extends ClientsController
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
    }

    /**
     * @throws Exception
     */
    public function assignPackage($company_id = null)
    {
        $data['title'] = _l('assign_package');
        isClientLogin($company_id);
        $subs = get_company_subscription_by_id();
        // Always show packages on this page — even when no subscription is linked yet.
        $data['current_package'] = !empty($subs->package_id) ? $subs->package_id : null;
        $data['current_package_paid'] = function_exists('saas_subscription_is_paid')
            ? saas_subscription_is_paid($subs)
            : (!empty($subs) && ($subs->status ?? '') === 'running');
        $data['all_packages'] = get_old_result('tbl_saas_packages', array('status' => 'published'));
        $this->set_layout($data, 'packages/assign_package');
    }

    /**
     * @throws Exception
     */
    public
    function checkoutPayment($package_id = null, $company_id = null)
    {
        if (!is_client_logged_in()) {
            redirect('authentication/login');
        }

        $subs_info = get_company_subscription_by_id();
        if (empty($subs_info)) {
            // Create/link company from the logged-in profile, then retry.
            if (!empty($package_id) && function_exists('saas_ensure_company_for_logged_in_client')) {
                $companyId = saas_ensure_company_for_logged_in_client($package_id);
                if (!empty($companyId)) {
                    redirect('proceedPackage/' . $package_id . '/' . url_encode($companyId));
                }
            }
            set_alert('warning', _l('no_subscription_found_for_this_account'));
            redirect('clients/dashboard');
        }

        $post_data = $this->input->post();
        if (!empty($post_data)) {
            $payment_method = get_old_result('tbl_saas_payment_methods', ['id' => $post_data['paymentmode'] ?? 0], false);
            if (empty($payment_method)) {
                set_alert('warning', _l('payment_method_not_found'));
                redirect($_SERVER['HTTP_REFERER']);
            }

            $packageForGateway = get_old_result('tbl_saas_packages', ['id' => $post_data['package_id'] ?? $subs_info->package_id], false);
            if (function_exists('saas_assert_package_checkout_gateway')) {
                saas_assert_package_checkout_gateway($payment_method->gateway_name, $packageForGateway);
            }

            $data['frequency'] = str_replace('_price', '', $post_data['billing_cycle'] ?? 'monthly_price');
            $gateway = saas_load_gateway($payment_method->gateway_name);
            if (empty($gateway)) {
                set_alert('warning', _l('payment_method_not_found'));
                redirect($_SERVER['HTTP_REFERER']);
            }

            $result = $gateway->getPaymentForm($post_data, $subs_info);
            if (empty($result['paymentForm'])) {
                set_alert('warning', _l('payment_method_not_found'));
                redirect($_SERVER['HTTP_REFERER']);
            }

            $package_info = $packageForGateway;
            $data['title'] = _l('checkout') . ' ' . _l('payment') . ' ' . _l('for') . ' ' . ($package_info->name ?? '');
            $data['package_info'] = $package_info;
            $data['package_id'] = $post_data['package_id'] ?? $package_id;
            $data['company_id'] = $post_data['companies_id'] ?? $subs_info->companies_id;
            $data['paymentForm'] = $result['paymentForm'];
            $this->set_layout($data, 'packages/checkoutPaymentPage');
            return;
        }

        $data['package_id'] = $package_id ?: $subs_info->package_id;
        $data['frequency'] = $subs_info->frequency ?: 'monthly';
        $package_info = get_old_result('tbl_saas_packages', array('id' => $data['package_id']), false);
        if (empty($package_info)) {
            set_alert('warning', _l('404_error'));
            redirect('clients/billings');
        }
        $data['title'] = _l('checkout') . ' ' . _l('payment') . ' ' . _l('for') . ' ' . $package_info->name;
        $data['package_info'] = $package_info;
        $data['all_packages'] = get_old_result('tbl_saas_packages', array('status' => 'published'));
        $data['subs_info'] = $subs_info;
        $data['payment_modes'] = $this->saas_model->get_payment_modes(false, $package_info);
        $data['url'] = site_url('clients/checkoutPayment/' . $data['package_id']);
        $this->set_layout($data, 'packages/checkoutPaymentOpen');
    }

    public function billings()
    {
        // check if not logged
        if (!is_client_logged_in()) {
            redirect('authentication/login');
        }
        if (function_exists('saas_clear_tenant_session')) {
            saas_clear_tenant_session();
        }
        $data['title'] = _l('billing');
        $subs = get_company_subscription_by_id();
        $data['current_package'] = !empty($subs->package_id) ? $subs->package_id : null;
        $data['current_package_paid'] = function_exists('saas_subscription_is_paid')
            ? saas_subscription_is_paid($subs)
            : (!empty($subs) && ($subs->status ?? '') === 'running');
        $data['all_packages'] = get_old_result('tbl_saas_packages', ['status' => 'published']);
        $this->set_layout($data, 'companies/billing');
    }

    /**
     * Logged-in client picks a package: reuse their profile (no re-register),
     * ensure a SaaS company exists, then open checkout.
     */
    public function subscribe($package_id = null)
    {
        if (!is_client_logged_in()) {
            redirect('authentication/login');
        }
        if (function_exists('saas_clear_tenant_session')) {
            saas_clear_tenant_session();
        }

        $package_id = (int) $package_id;
        $package = get_old_result('tbl_saas_packages', ['id' => $package_id, 'status' => 'published'], false);
        if (empty($package)) {
            // allow unpublished only if id exists and was passed explicitly
            $package = get_old_result('tbl_saas_packages', ['id' => $package_id], false);
        }
        if (empty($package)) {
            set_alert('warning', _l('package_not_found'));
            redirect('clients/dashboard');
        }

        $billing_cycle = $this->input->get('billing_cycle', true) ?: 'monthly_price';
        $companyId = saas_ensure_company_for_logged_in_client($package_id, $billing_cycle);
        if (empty($companyId)) {
            set_alert('danger', _l('something_went_wrong'));
            redirect('clients/dashboard');
        }

        redirect('proceedPackage/' . $package_id . '/' . url_encode($companyId));
    }

    public function referrals()
    {
        // check if not logged
        if (!is_client_logged_in()) {
            redirect('authentication/login');
        }
        // check if affiliate is enabled
        if (!get_option('enable_affiliate')) {
            redirect('clients/dashboard');
        }
        $id = $this->saas_model->get_affiliate_user_id();
        $data['affiliate_info'] = $data['user'] = $this->saas_model->getAffiliateUser($id);
        $data['states'] = $this->saas_model->get_affiliate_states($data['affiliate_info']);
        $data['commission_histories'] = get_order_by('tbl_saas_affiliates', array('referral_by' => $id), 'affiliate_id', null, 5);
        $data['payout_histories'] = get_order_by('tbl_saas_affiliate_payouts', array('user_id' => $id), 'affiliate_payout_id', null, 5);
        $data['subview'] = $this->load->view('affiliates/user/dashboard', $data, true);
        $data['title'] = _l('referrals');
        $data['payouts'] = true;
        $this->set_layout($data, 'companies/referrals');
    }


    public
    function upgrade()
    {
        $data['title'] = _l('upgrade') . ' ' . _l('plan');
        if (!empty($type)) {
            $data['type'] = $type;
        }
        $data['sub_info'] = get_company_subscription_by_id();
        $data['payment_modes'] = $this->saas_model->get_payment_modes(false, get_old_result('tbl_saas_packages', ['id' => $data['sub_info']->package_id ?? 0], false));
        $this->set_layout($data, 'settings/upgrade');
    }

    public
    function companyHistoryList($id = null)
    {
        // make datatable
        $this->db = config_db(null, true);
        $this->load->model('datatables');
        $this->datatables->table = 'tbl_saas_companies_history';
        $column = array('package_name', 'amount', 'frequency', 'created_at', 'validity', 'payment_method', 'status');
        $this->datatables->column_order = $column;
        $this->datatables->column_search = $column;
        $this->datatables->order = array('id' => 'desc');
        if ($id) {
            $where = array('tbl_saas_companies_history.companies_id' => $id);
        } else {
            $where = array();
        }
        $fetch_data = make_datatables($where, null, true);
        $data = array();
        $access = super_admin_access();
        foreach ($fetch_data as $_key => $v_history) {
            if ($v_history->active == 1) {
                $label = 'success';
                $status = 'active';
            } else {
                $label = 'warning';
                $status = 'inactive';
            }
            if ($v_history->frequency == 'monthly') {
                $frequency = _l('mo');
            } else if ($v_history->frequency == 'lifetime') {
                $frequency = _l('lt');
            } else if ($v_history->frequency == 'yearly') {
                $frequency = _l('yr');
            }
            $action = null;
            $sub_array = array();
            $name = '<a href="' . base_url('subs_package_details/' . $v_history->id . '/1') . '"  data-toggle="modal" data-target="#myModal" >' . $v_history->package_name . '</a>';
            if (!empty($access)) {
                $name .= '<div class="row-options">';
                if (!empty($access) && $v_history->active != 1) {
                    $name .= '<a 
                    data-toggle="tooltip" data-placement="top"
                    href="' . base_url('saas/gb/delete_companies_history/' . $v_history->id) . '"  title="' . _l('delete') . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }
                $name .= '</div>';
            }
            $sub_array[] = $name;
            $sub_array[] = display_money($v_history->amount, saas_apply_package_currency($v_history)) . ' /' . $frequency;
            $sub_array[] = _dt($v_history->created_at);
            $sub_array[] = (!empty($v_history->validity) ? $v_history->validity : '-');
            $sub_array[] = $v_history->payment_method;
            if (!empty($access)) {
                $sub_array[] = '<span class="label label-' . $label . '">' . _l($status) . '</span>';
            }
            $data[] = $sub_array;
        }

        render_table_old($data, $where);
    }


    public
    function companyPaymentList($id = null)
    {
        // make datatable
        $this->db = config_db(null, true);
        $this->load->model('datatables');
        $this->datatables->table = 'tbl_saas_companies_payment';
        $this->datatables->join_table = array('tbl_saas_companies', 'tbl_saas_companies_history');
        $this->datatables->join_where = array('tbl_saas_companies.id=tbl_saas_companies_payment.companies_id', 'tbl_saas_companies_history.id=tbl_saas_companies_payment.companies_history_id');

        $column = array('tbl_saas_companies_history.package_name', 'transaction_id', 'total_amount', 'payment_date', 'payment_method');
        $this->datatables->column_order = $column;
        $this->datatables->column_search = $column;
        $this->datatables->order = array('id' => 'desc');
        $this->datatables->select = ('tbl_saas_companies_payment.*,tbl_saas_companies_history.package_name,tbl_saas_companies.name as company_name');
        // select tbl_saas_companies_history.name
        if (!empty($id)) {
            $where = array('tbl_saas_companies_payment.companies_id' => $id);
        } else {
            $where = array();
        }
        $fetch_data = make_datatables($where);
        $access = super_admin_access();
        $data = array();
        foreach ($fetch_data as $_key => $v_history) {
            $action = null;
            $sub_array = array();

            if (!empty($access)) {
                $name = $v_history->company_name;

                $name .= '<div class="row-options">';
                $name .= '<a 
                    data-toggle="tooltip" data-placement="top"
                    href="' . base_url('saas/gb/delete_companies_payment/' . $v_history->id) . '"  title="' . _l('delete') . '" class="text-danger _delete">' . _l('delete') . '</a>';
                $name .= '</div>';
                $sub_array[] = $name;
            }
            $sub_array[] = '<a href="' . base_url('subs_package_details/' . $v_history->companies_history_id . '/1') . '"  data-toggle="modal" data-target="#myModal" >' . $v_history->package_name . '</a>';
            $sub_array[] = $v_history->transaction_id;
            $sub_array[] = display_money($v_history->total_amount, $v_history);
            $sub_array[] = _dt($v_history->payment_date);
            $sub_array[] = $v_history->payment_method;
            $data[] = $sub_array;
        }
        render_table_old($data, $where);
    }

    /**
     * @throws Exception
     */
    public function custom_domain($action = null, $id = null)
    {

        $data['title'] = _l('custom_domain');
        $data['company_info'] = get_company_subscription_by_id();
        if (!empty($action)) {
            if (!empty($id)) {
                $data['domain_info'] = get_old_result('tbl_saas_domain_requests', array('request_id' => $id), false);
            }
            if ($action == 'update') {
                // check already exist the domain request
                $where = array('company_id' => $data['company_info']->companies_id, 'status' => 'pending');
                if (!empty($id)) {
                    $where['request_id !='] = $id;
                }

                $check = get_old_result('tbl_saas_domain_requests', $where, false);
                if (!empty($check)) {
                    set_alert('warning', _l('already_request'));
                    redirect('clients/custom_domain');
                }

                $pdata['custom_domain'] = $this->input->post('custom_domain', true);
                $pdata['status'] = 'pending';
                $pdata['company_id'] = $data['company_info']->companies_id;
                $this->saas_model->_table_name = 'tbl_saas_domain_requests';
                $this->saas_model->_primary_key = 'request_id';
                $this->saas_model->save_old($pdata, $id);

                $superadmin = get_old_result(db_prefix() . 'staff', array('admin' => 1, 'role' => 4));
                $users = [];
                foreach ($superadmin as $key => $value) {
                    add_notification([
                        'description' => _l('not_domain_request', $pdata['custom_domain']),
                        'touserid' => $value->staffid,
                        'fromcompany' => true,
                        'link' => 'saas/domain/requests/',
                    ]);
                    $users[] = $value->staffid;
                }
                pusher_trigger_notification(array_unique($users));

                set_alert('success', _l('domain_request_updated_successfully'));
                redirect('clients/custom_domain');
            }
            if ($action == 'delete') {
                if ($data['domain_info']->company_id == $data['company_info']->companies_id) {
                    if ($data['domain_info']->status == 'approved') {
                        $this->saas_model->_table_name = 'tbl_saas_domain_requests';
                        $this->saas_model->_primary_key = 'request_id';
                        $this->saas_model->delete_old($id);

                        $this->saas_model->_table_name = 'tbl_saas_companies';
                        $this->saas_model->_primary_key = 'id';
                        $this->saas_model->save_old(array('domain_url' => ''), $data['company_info']->companies_id);


                    } else {
                        $this->saas_model->_table_name = 'tbl_saas_domain_requests';
                        $this->saas_model->_primary_key = 'request_id';
                        $this->saas_model->delete_old($id);
                    }
                    set_alert('success', _l('domain_request_deleted_successfully'));


                } else {
                    set_alert('warning', _l('404_error'));
                }
                redirect('clients/custom_domain');
            }

        }
        $data['action'] = $action;
        $data['id'] = $id;
        $data['c_url'] = 'clients/';
        $data['all_domain'] = get_old_result('tbl_saas_domain_requests', array('company_id' => $data['company_info']->companies_id));
        $this->set_layout($data, 'domain/custom_domain');

    }


    /**
     * @throws Exception
     */
    public function customizePackages($comp_id = null)
    {
        $data['title'] = _l('customize_packages');
        isClientLogin($comp_id);
        $data['companyInfo'] = get_company_subscription_by_id();
        if (!empty($data['companyInfo'])) {
            $customer_id = get_client_user_id();
            $data['packageInfo'] = get_usages($data['companyInfo']);
            $data['invoices_to_merge'] = $this->saas_model->check_for_merge_invoice($customer_id);
            $company_id = $data['companyInfo']->companies_id;
            $data['company_id'] = $company_id;
            $data['moduleInfo'] = get_old_result('tbl_saas_package_module');
            $data['payment_modes'] = $this->saas_model->get_payment_modes(false, get_old_result('tbl_saas_packages', ['id' => $data['companyInfo']->package_id ?? 0], false));
            $data['url'] = 'clients/';
            $this->set_layout($data, 'packages/customize_packages');
        } else {
            set_alert('warning', _l('404_error'));
            redirect('clients/dashboard');
        }
    }

    public function proceedPayment($payment_method = null)
    {
        $subs_info = get_company_subscription_by_id(null, 'running');
        $data = $_POST;
        if (!empty($subs_info) && !empty($data['paymentmode'])) {
            $this->saas_model->proceedPayment($subs_info);
        } else {
            set_alert('warning', _l('select_payment_method'));
            redirect('clients/customizePackages');
        }
    }

    public
    function get_expired_date($package_type)
    {
        $type_title = str_replace('_price', '', $package_type);
        if ($type_title == 'lifetime') {
            $renew_date = date('Y-m-d', strtotime('+100 year'));
        } elseif ($type_title == 'yearly') {
            $renew_date = date('Y-m-d', strtotime('+1 year'));
        } else {
            $renew_date = date('Y-m-d', strtotime('+1 month'));
        }
        return $renew_date;
    }

    public
    function get_modules($comp_id = null)
    {
        $data['title'] = _l('modules');
        isClientLogin($comp_id);
        $data['payment_modes'] = $this->saas_model->get_payment_modes(false, get_old_result('tbl_saas_packages', ['id' => get_company_subscription_by_id()->package_id ?? 0], false));
        $data['all_modules'] = get_old_result('tbl_saas_package_module', array('status' => 'published'));
        $this->set_layout($data, 'packages/modules/get_modules');
    }

    public
    function module_details($module)
    {
        $data['title'] = _l('customize_packages');
        $data['module'] = get_old_result('tbl_saas_package_module', array('module_name' => $module, 'status' => 'published'), false);
        if (empty($data['module'])) {
            set_alert('warning', _l('404_error'));
            redirect('clients/dashboard');
        }
        $this->set_layout($data, 'packages/modules/module_details');

    }

    private
    function set_layout($data, $view)
    {
        $this->data($data);
        $this->view($view);
        no_index_customers_area();
        $this->layout();
    }

    public function proceedPackage($package_id = null, $company_id = null)
    {
        $data['package_id'] = $package_id;
        $data['frequency'] = 'monthly';
        if (empty($data['package_id']) && !empty(is_client_logged_in())) {
            $subs_info = get_company_subscription_by_id();
            if (!empty($subs_info)) {
                $data['package_id'] = $subs_info->package_id;
                $data['frequency'] = $subs_info->frequency;
            }
        }
        $package_info = get_old_result('tbl_saas_packages', array('id' => $data['package_id']), false);
        if (empty($package_info)) {
            set_alert('warning', _l('404_error'));
            redirect('clients/billings');
        }
        $data['title'] = _l('checkout') . ' ' . _l('payment') . ' ' . _l('for') . ' ' . $package_info->name;
        $data['package_info'] = $package_info;
        $data['all_packages'] = get_old_result('tbl_saas_packages', array('status' => 'published'));
        $subview = 'checkoutPayment';
        if (!empty(is_client_logged_in())) {
            $data['subs_info'] = get_company_subscription_by_id();
            if (empty($data['subs_info']) && !empty($company_id)) {
                $decoded = url_decode($company_id);
                $data['subs_info'] = $this->saas_model->company_info($decoded);
                if (!empty($data['subs_info'])) {
                    $data['subs_info']->companies_id = $decoded;
                }
            }
            if (empty($data['subs_info'])) {
                redirect('clients/subscribe/' . $data['package_id']);
            }
            $data['payment_modes'] = $this->saas_model->get_payment_modes(false, $package_info);
            $data['url'] = site_url('clients/checkoutPayment/' . $data['package_id']);
            $subview = 'checkoutPaymentOpen';
        } else if (!empty($company_id)) {
            $company_id = url_decode($company_id);
            $data['subs_info'] = $this->saas_model->company_info($company_id);
            $data['subs_info']->companies_id = $company_id;
            $data['payment_modes'] = $this->saas_model->get_payment_modes(false, $package_info);
            $subview = 'checkoutPaymentOpen';
            $data['company_id'] = $company_id;
            $data['front'] = true;
        }

        $this->set_layout($data, 'packages/' . $subview);
    }

    // ------------------------------------------------------------------
    // Netim: Domain search (AJAX)
    // ------------------------------------------------------------------

    public function domain_search()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->library('Saas_Netim', null, 'netim');
        $domain = trim($this->input->post('domain', true));

        if (empty($domain)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a domain name.']);
            exit();
        }

        if (!$this->netim->isConfigured()) {
            echo json_encode(['success' => false, 'error' => 'Domain purchase not available at this time.']);
            exit();
        }

        $result = $this->netim->checkDomain($domain);
        echo json_encode($result);
        exit();
    }

    // ------------------------------------------------------------------
    // Netim: Buy domain (search + WHOIS form + submit request)
    // ------------------------------------------------------------------

    public function buy_domain($step = null)
    {
        $data['title']        = 'Buy a Domain';
        $data['company_info'] = get_company_subscription_by_id();
        $this->load->library('Saas_Netim', null, 'netim');
        $data['netim_configured'] = $this->netim->isConfigured();

        if ($step === 'contact' && $this->input->post()) {
            $company_id = $data['company_info']->companies_id;

            // Save contact to DB
            $contactData = [
                'company_id'   => $company_id,
                'netim_handle' => '',
                'first_name'   => $this->input->post('first_name', true),
                'last_name'    => $this->input->post('last_name', true),
                'email'        => $this->input->post('email', true),
                'phone'        => $this->input->post('phone', true),
                'address'      => $this->input->post('address', true),
                'city'         => $this->input->post('city', true),
                'state'        => $this->input->post('state', true),
                'zipcode'      => $this->input->post('zipcode', true),
                'country'      => $this->input->post('country', true),
                'legal_type'   => $this->input->post('legal_type', true) ?? 'INDIVIDUAL',
                'company_name' => $this->input->post('company_name', true),
            ];
            $domain_name = $this->input->post('domain_name', true);
            $price       = $this->input->post('price', true);
            $currency    = $this->input->post('currency', true) ?? 'USD';

            // Upsert contact for company
            $existing = get_old_result('tbl_saas_netim_contacts', ['company_id' => $company_id], false);
            $this->saas_model->_table_name  = 'tbl_saas_netim_contacts';
            $this->saas_model->_primary_key = 'contact_id';
            if ($existing) {
                $this->saas_model->save_old($contactData, $existing->contact_id);
                $contact_id = $existing->contact_id;
            } else {
                $this->saas_model->save_old($contactData);
                $contact_id = $this->db->insert_id();
            }

            // Create domain purchase request
            $this->db->insert('tbl_saas_netim_requests', [
                'company_id'  => $company_id,
                'domain_name' => $domain_name,
                'contact_id'  => $contact_id,
                'status'      => 'pending',
                'price'       => $price,
                'currency'    => $currency,
            ]);

            // If auto-register enabled, trigger directly (requires funded Netim account)
            if (get_option('netim_auto_register') == '1') {
                $this->load->library('Saas_Netim', null, 'netim2');
                // Contact creation handled by admin register flow
                log_message('debug', '[Gb_client::buy_domain] Auto-register is ON — admin still required to confirm.');
            }

            // Notify super admins
            $superadmins = get_old_result(db_prefix() . 'staff', ['admin' => 1, 'role' => 4]);
            $uids = [];
            foreach ($superadmins as $sa) {
                add_notification([
                    'description' => 'New domain purchase request: ' . $domain_name . ' from ' . $data['company_info']->name,
                    'touserid'    => $sa->staffid,
                    'fromcompany' => true,
                    'link'        => 'saas/netim_domains/requests',
                ]);
                $uids[] = $sa->staffid;
            }
            if (!empty($uids)) {
                pusher_trigger_notification(array_unique($uids));
            }

            log_activity('Domain Purchase Request Submitted [Domain: ' . $domain_name . ']');
            set_alert('success', 'Domain purchase request submitted! Our team will register ' . $domain_name . ' shortly.');
            redirect('clients/my-domains');
        }

        $data['existing_contact'] = get_old_result('tbl_saas_netim_contacts', ['company_id' => $data['company_info']->companies_id], false);
        $data['step'] = $step;
        $this->set_layout($data, 'domain/netim_buy');
    }

    // ------------------------------------------------------------------
    // Netim: My purchased domains list
    // ------------------------------------------------------------------

    public function my_domains()
    {
        $data['title']        = 'My Domains';
        $data['company_info'] = get_company_subscription_by_id();
        $company_id           = $data['company_info']->companies_id;

        $data['domains']   = get_old_result('tbl_saas_netim_domains', ['company_id' => $company_id]);
        $data['requests']  = get_old_result('tbl_saas_netim_requests', ['company_id' => $company_id]);

        $this->set_layout($data, 'domain/netim_my_domains');
    }

}
