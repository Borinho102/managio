<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Common extends REST_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function data_get($type = "")
    {
        $allowed_type = ["expenses_summary", "leads_summary", "tickets_summary", "projects_summary", "tasks_summary", "subscriptions_summary", "estimates_summary", "invoices_summary", "customers_summary", "settings", "expense_category", "payment_mode", "tax_data", "languages", "get_relation_data"];
        if (empty($type) || !in_array($type, $allowed_type)) {
            $this->response([
                'status' => FALSE,
                'message' => 'Not valid data'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        $data = $this->{$type}();
        if (empty($data)) {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code  
    }

    public function customers_summary()
    {
        if (has_permission('customers', '', 'view') || have_assigned_customers()) {
            $where_summary = '';
            if (!has_permission('customers', '', 'view')) {
                $where_summary = ' AND userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_admins WHERE staff_id=' . get_staff_user_id() . ')';
            }
            return [
                'total' => total_rows(db_prefix() . 'clients', ($where_summary != '' ? substr($where_summary, 5) : '')),
                'active' => total_rows(db_prefix() . 'clients', 'active=1' . $where_summary),
                'inactive' => total_rows(db_prefix() . 'clients', 'active=0' . $where_summary),
                'contacts_active' => total_rows(db_prefix() . 'contacts', 'active=1' . $where_summary),
                'contacts_inactive' => total_rows(db_prefix() . 'contacts', 'active=0' . $where_summary),
                'contacts_last_login' => total_rows(db_prefix() . 'contacts', 'last_login LIKE "' . date('Y-m-d') . '%"' . $where_summary)
            ];
        }

        return [];
    }

    public function invoices_summary()
    {
        $this->load->model('currencies_model');
        $this->load->model('invoices_model');

        $wps_currency = 'undefined';
        $_data = [];

        if (is_using_multiple_currencies()) {
            $base_currency = $this->currencies_model->get_base_currency();
            $wps_currency = $base_currency->id;
            $_data['invoices_total_currencies'] = $this->currencies_model->get();
        }

        $_data['invoices_years'] = $this->invoices_model->get_invoices_years();
        if (
            count($_data['invoices_years']) >= 1
            && !\app\services\utilities\Arr::inMultidimensional($_data['invoices_years'], 'year', date('Y'))
        ) {
            array_unshift($_data['invoices_years'], ['year' => date('Y')]);
        }

        $invoice_stats = $this->invoices_model->get_invoices_total($_data);
        $invoice_stats['formatted_due'] = mpc_app_format_money($invoice_stats['due'], $invoice_stats['currency']);
        $invoice_stats['formatted_overdue'] = mpc_app_format_money($invoice_stats['overdue'], $invoice_stats['currency']);
        $invoice_stats['formatted_paid'] = mpc_app_format_money($invoice_stats['paid'], $invoice_stats['currency']);

        $where_all = '';
        $has_permission_view = has_permission('invoices', '', 'view');
        if (isset($project)) {
            $where_all .= 'project_id=' . $project->id . ' AND ';
        }
        if (!$has_permission_view) {
            $where_all .= get_invoices_where_sql_for_staff(get_staff_user_id());
        }
        $where_all = trim($where_all);
        if (endsWith($where_all, ' AND')) {
            $where_all = substr_replace($where_all, '', -4);
        }
        $total_invoices = total_rows(db_prefix() . 'invoices', $where_all);

        $invoices = [
            'xAxis' => [
                'data' => [
                    format_invoice_status(6, '', false),
                    format_invoice_status(1, '', false),
                    'Partial', // format_invoice_status(3, '', false),
                    format_invoice_status(4, '', false),
                    format_invoice_status(2, '', false)
                ]
            ],
            'series' => [
                'color' => [
                    '#DCDCDC',
                    // '#DCDCDC',
                    '#FF8686',
                    '#FFB286',
                    '#FFBE5B',
                    '#8CE39A'
                ],
                'data' => [
                    $this->get_invoice_percentage_by_status(6, $total_invoices),
                    $this->get_invoice_percentage_by_status(1, $total_invoices),
                    $this->get_invoice_percentage_by_status(3, $total_invoices),
                    $this->get_invoice_percentage_by_status(4, $total_invoices),
                    $this->get_invoice_percentage_by_status(2, $total_invoices),
                ]
            ]
        ];
        return [
            'chart' => $invoices,
            'stats' => $invoice_stats
        ];
    }

    public function estimates_summary()
    {
        $this->load->model('currencies_model');
        $this->load->model('estimates_model');
        $this->load->model('invoices_model');

        $wps_currency = 'undefined';
        $_data = [];

        if (is_using_multiple_currencies()) {
            $base_currency = $this->currencies_model->get_base_currency();
            $wps_currency = $base_currency->id;
            $_data['invoices_total_currencies'] = $this->currencies_model->get();
        }

        $_data['invoices_years'] = $this->invoices_model->get_invoices_years();
        if (
            count($_data['invoices_years']) >= 1
            && !\app\services\utilities\Arr::inMultidimensional($_data['invoices_years'], 'year', date('Y'))
        ) {
            array_unshift($_data['invoices_years'], ['year' => date('Y')]);
        }

        $estimate_stats = $this->estimates_model->get_estimates_total($this->input->post());
        $statuses = [];
        foreach ($estimate_stats as $key => $value) {
            if (!is_array($value)) continue;

            $value['formatted_total'] = mpc_app_format_money($value['total'], $estimate_stats['currencyid']);
            $value['status_class'] = estimate_status_color_class($value['status']);
            $value['status_name'] = format_estimate_status($value['status'], '', false);
            array_push($statuses, $value);
        }

        $estimates = [
            'xAxis' => [
                'data' => []
            ],
            'series' => [
                'color' => [],
                'data' => []
            ]
        ];

        $estimate_statuses = $this->estimates_model->get_statuses();
        foreach ($estimate_statuses as $status) {
            $percent_data = get_estimates_percent_by_status($status, (isset($project) ? $project->id : null));
            array_push($estimates['xAxis']['data'], format_estimate_status($status, '', false));
            array_push($estimates['series']['color'], $this->estimate_status_color_class($status));
            array_push($estimates['series']['data'], $percent_data['total_by_status']);
        }

        return [
            'chart' => $estimates,
            'stats' => $statuses
        ];
    }

    public function subscriptions_summary()
    {
        $subscriptions = [];
        foreach (subscriptions_summary() as $key => $summary) {
            $subscriptions[$key] =  $summary;
            $subscriptions[$key]['name'] =  _l('subscription_' . $summary['id']);
            $subscriptions[$key]['color'] =  $summary['color'];
        }

        return $subscriptions;
    }

    public function tasks_summary()
    {
        return tasks_summary_data((isset($rel_id) ? $rel_id : null), (isset($rel_type) ? $rel_type : null));
    }

    public function projects_summary()
    {
        $this->load->model('projects_model');
        $statuses = $this->projects_model->get_project_statuses();

        $_where = '';
        if (!has_permission('projects', '', 'view')) {
            $_where = 'id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')';
        }

        foreach ($statuses as $key => $status) {
            $value = $status['id'];
            if ($status['filter_default'] == false && !$this->input->get('status')) {
                $value = '';
            } else if ($this->input->get('status')) {
                $value = ($this->input->get('status') == $status['id'] ? $status['id'] : "");
            }

            $where = ($_where == '' ? '' : $_where . ' AND ') . 'status = ' . $status['id'];
            $statuses[$key]['total'] = total_rows(db_prefix() . 'projects', $where);
            $statuses[$key]['color'] =  $status['color'];
        }

        return $statuses;
    }

    public function tickets_summary()
    {
        $this->load->model('tickets_model');
        $this->load->model('departments_model');

        $statuses             = $this->tickets_model->get_ticket_status();
        $staff_deparments_ids = $this->departments_model->get_staff_departments(get_staff_user_id(), true);

        $where = '';
        if (!is_admin()) {
            if (get_option('staff_access_only_assigned_departments') == 1) {
                $departments_ids = array();
                if (count($staff_deparments_ids) == 0) {
                    $departments = $this->departments_model->get();
                    foreach ($departments as $department) {
                        array_push($departments_ids, $department['departmentid']);
                    }
                } else {
                    $departments_ids = $staff_deparments_ids;
                }
                if (count($departments_ids) > 0) {
                    $where = 'AND department IN (SELECT departmentid FROM ' . db_prefix() . 'staff_departments WHERE departmentid IN (' . implode(',', $departments_ids) . ') AND staffid="' . get_staff_user_id() . '")';
                }
            }
        }

        foreach ($statuses as $key => $status) {
            $_where = '';
            if ($where == '') {
                $_where = 'status=' . $status['ticketstatusid'];
            } else {
                $_where = 'status=' . $status['ticketstatusid'] . ' ' . $where;
            }
            if (isset($project_id)) {
                $_where = $_where . ' AND project_id=' . $project_id;
            }
            $statuses[$key]['total'] = total_rows(db_prefix() . 'tickets', $_where);
            $statuses[$key]['color'] = $status['statuscolor'];
            $statuses[$key]['name']  = ticket_status_translate($status['ticketstatusid']);
        }

        return $statuses;
    }

    public function leads_summary()
    {
        return get_leads_summary();
    }

    public function expenses_summary()
    {
        $this->load->model('expenses_model');
        $totals = $this->get_expenses_total($this->input->post());
        return $totals;
    }

    private function estimate_status_color_class($id, $replace_default_by_muted = false)
    {
        $class = '';
        if ($id == 1) {
            $class = '#DCDCDC';
            if ($replace_default_by_muted == true) {
                $class = '#DCDCDC';
            }
        } elseif ($id == 2) {
            $class = '#03a9f4';
        } elseif ($id == 3) {
            $class = '#FF8686';
        } elseif ($id == 4) {
            $class = '#8CE39A';
        } elseif ($id == 5) {
            // status 5
            $class = '#FFB286';
        } else {
            if (!is_numeric($id)) {
                if ($id == 'not_sent') {
                    $class = '#DCDCDC';
                    if ($replace_default_by_muted == true) {
                        $class = '#DCDCDC';
                    }
                }
            }
        }

        return hooks()->apply_filters('estimate_status_color_code', $class, $id); // can be used to change color for colors
    }

    private function get_invoice_percentage_by_status($status, $total_invoices)
    {
        $has_permission_view = has_permission('invoices', '', 'view');
        $where = array('status' => $status);
        if (isset($project)) {
            $where['project_id'] = $project->id;
        }
        if (!$has_permission_view) {
            $where['addedfrom'] = get_staff_user_id();
        }
        $total_by_status = total_rows(db_prefix() . 'invoices', $where);
        return $total_by_status;
    }

    public function settings()
    {
        $company_logo = get_option('company_logo');
        $company_logo_dark = get_option('company_logo_dark');

        $data = [
            'company_logo' => ($company_logo != '' ? base_url('uploads/company/' . $company_logo) : ''),
            'company_logo_dark' => ($company_logo_dark != '' ? base_url('uploads/company/' . $company_logo_dark) : ''),

            'invoice_prefix' => get_option('invoice_prefix'),
            'next_invoice_number' => str_pad(get_option('next_invoice_number'), get_option('number_padding_prefixes'), '0', STR_PAD_LEFT),
            'invoice_number_format' => get_option('invoice_number_format'),
            'predefined_clientnote_invoice' => get_option('predefined_clientnote_invoice'),
            'predefined_terms_invoice' => get_option('predefined_terms_invoice'),

            'credit_note_prefix' => get_option('credit_note_prefix'),
            'next_credit_note_number' => str_pad(get_option('next_credit_note_number'), get_option('number_padding_prefixes'), '0', STR_PAD_LEFT),
            'credit_note_number_format' => get_option('credit_note_number_format'),
            'predefined_clientnote_credit_note' => get_option('predefined_clientnote_credit_note'),
            'predefined_terms_credit_note' => get_option('predefined_terms_credit_note'),

            'estimate_prefix' => get_option('estimate_prefix'),
            'next_estimate_number' => str_pad(get_option('next_estimate_number'), get_option('number_padding_prefixes'), '0', STR_PAD_LEFT),
            'estimate_number_format' => get_option('estimate_number_format'),
            'predefined_clientnote_estimate' => get_option('predefined_clientnote_estimate'),
            'predefined_terms_estimate' => get_option('predefined_terms_estimate'),

            'proposal_prefix' => get_option('proposal_prefix'),
            'next_proposal_number' => str_pad(get_option('next_proposal_number'), get_option('number_padding_prefixes'), '0', STR_PAD_LEFT),
            'proposal_number_format' => get_option('proposal_number_format'),
            'localization' => [
                'dateformat' =>  get_option('dateformat'),
                'time_format' => get_option('time_format'),
                'default_timezone' => get_option('default_timezone'),
                'active_language' => get_option('active_language')
            ],
            'sales' => [
                'decimal_separator' =>  get_option('decimal_separator'),
                'thousand_separator' => get_option('thousand_separator'),
                'number_padding_prefixes' => get_option('number_padding_prefixes'),
                'show_tax_per_item' => get_option('show_tax_per_item'),
                'remove_tax_name_from_item_table' => get_option('remove_tax_name_from_item_table'),
                'items_table_amounts_exclude_currency_symbol' => get_option('items_table_amounts_exclude_currency_symbol'),
                'default_tax' => unserialize(get_option('default_tax')),
                'remove_decimals_on_zero' => get_option('remove_decimals_on_zero'),
                'total_to_words_enabled' => get_option('total_to_words_enabled'),
                'total_to_words_lowercase' => get_option('total_to_words_lowercase'),
                'invoices' => [
                    'show_credits_applied_on_invoice' => get_option('show_credits_applied_on_invoice')
                ]
            ],
            'perfex_current_version' => $this->current_db_version,
            'pusher' => [
                'pusher_app_key' => get_option('pusher_app_key'),
                'pusher_cluster' => get_option('pusher_cluster')
            ]
        ];

        if (!extension_loaded('curl')) {
            $data['update_errors'][] = 'CURL Extension not enabled';
            $data['latest_version']  = 0;
            $data['update_info']     = json_decode('');
        } else {
            $data['update_info'] = $this->app->get_update_info();
            if (strpos($data['update_info'], 'Curl Error -') !== false) {
                $data['update_errors'][] = $data['update_info'];
                $data['latest_version']  = 0;
                $data['update_info']     = json_decode('');
            } else {
                $data['update_info']    = json_decode($data['update_info']);
                $data['latest_version'] = $data['update_info']->latest_version;
                $data['update_errors']  = [];
            }
        }

        if (!extension_loaded('zip')) {
            $data['update_errors'][] = 'ZIP Extension not enabled';
        }

        if (file_exists(APP_MODULES_PATH . '/mpc_ai_chatbot/config/token.php')) {
            $token    = file_get_contents(APP_MODULES_PATH . '/mpc_ai_chatbot/config/token.php');
            $data['secret_key'] = $token;
        }

        $this->load->library('app_modules');
        $data['get_activated'] = $this->app_modules->get_activated();

        return $data;
    }

    public function expense_category()
    {
        $this->load->model('expenses_model');
        return $this->expenses_model->get_category();
    }

    public function payment_mode()
    {
        $this->load->model('payment_modes_model');
        return $this->payment_modes_model->get('', [
            'invoices_only !=' => 1,
        ]);
    }

    public function tax_data()
    {
        $this->load->model('taxes_model');
        return $this->taxes_model->get();
    }

    public function languages()
    {
        return $this->app->get_available_languages();
    }

    public function get_relation_data()
    {
        $type = $this->input->get('type');
        $key = $this->input->get('q');
        $data = $this->Api_model->search($type, $key);
        if ($this->input->get('rel_id')) {
            $rel_id = $this->input->get('rel_id');
        } else {
            $rel_id = '';
        }

        return init_relation_options($data, $type, $rel_id);
    }

    private function get_expenses_total($data)
    {
        $this->load->model('currencies_model');
        $base_currency     = $this->currencies_model->get_base_currency()->id;
        $base              = true;
        $currency_switcher = false;
        if (isset($data['currency'])) {
            $currencyid        = $data['currency'];
            $currency_switcher = true;
        } elseif (isset($data['customer_id']) && $data['customer_id'] != '') {
            $currencyid = $this->clients_model->get_customer_default_currency($data['customer_id']);
            if ($currencyid == 0) {
                $currencyid = $base_currency;
            } else {
                if (total_rows(db_prefix() . 'expenses', [
                    'currency' => $base_currency,
                    'clientid' => $data['customer_id'],
                ])) {
                    $currency_switcher = true;
                }
            }
        } elseif (isset($data['project_id']) && $data['project_id'] != '') {
            $this->load->model('projects_model');
            $currencyid = $this->projects_model->get_currency($data['project_id'])->id;
        } else {
            $currencyid = $base_currency;
            if (total_rows(db_prefix() . 'expenses', [
                'currency !=' => $base_currency,
            ])) {
                $currency_switcher = true;
            }
        }

        $currency = get_currency($currencyid);

        $has_permission_view = has_permission('expenses', '', 'view');
        $_result             = [];

        for ($i = 1; $i <= 5; $i++) {
            $this->db->select('amount,tax,tax2,invoiceid');
            $this->db->where('currency', $currencyid);

            if (isset($data['years']) && count($data['years']) > 0) {
                $this->db->where('YEAR(date) IN (' . implode(', ', array_map(function ($year) {
                    return get_instance()->db->escape_str($year);
                }, $data['years'])) . ')');
            } else {
                $this->db->where('YEAR(date) = ' . date('Y'));
            }
            if (isset($data['customer_id']) && $data['customer_id'] != '') {
                $this->db->where('clientid', $data['customer_id']);
            }
            if (isset($data['project_id']) && $data['project_id'] != '') {
                $this->db->where('project_id', $data['project_id']);
            }

            if (!$has_permission_view) {
                $this->db->where('addedfrom', get_staff_user_id());
            }
            switch ($i) {
                case 1:
                    $key = 'all';

                    break;
                case 2:
                    $key = 'billable';
                    $this->db->where('billable', 1);

                    break;
                case 3:
                    $key = 'non_billable';
                    $this->db->where('billable', 0);

                    break;
                case 4:
                    $key = 'billed';
                    $this->db->where('billable', 1);
                    $this->db->where('invoiceid IS NOT NULL');
                    $this->db->where('invoiceid IN (SELECT invoiceid FROM ' . db_prefix() . 'invoices WHERE status=2 AND id=' . db_prefix() . 'expenses.invoiceid)');

                    break;
                case 5:
                    $key = 'unbilled';
                    $this->db->where('billable', 1);
                    $this->db->where('invoiceid IS NULL');

                    break;
            }
            $all_expenses = $this->db->get(db_prefix() . 'expenses')->result_array();
            $_total_all   = [];
            $cached_taxes = [];
            foreach ($all_expenses as $expense) {
                $_total = $expense['amount'];
                if ($expense['tax'] != 0) {
                    if (!isset($cached_taxes[$expense['tax']])) {
                        $tax                           = get_tax_by_id($expense['tax']);
                        $cached_taxes[$expense['tax']] = $tax;
                    } else {
                        $tax = $cached_taxes[$expense['tax']];
                    }
                    $_total += ($_total / 100 * $tax->taxrate);
                }
                if ($expense['tax2'] != 0) {
                    if (!isset($cached_taxes[$expense['tax2']])) {
                        $tax                            = get_tax_by_id($expense['tax2']);
                        $cached_taxes[$expense['tax2']] = $tax;
                    } else {
                        $tax = $cached_taxes[$expense['tax2']];
                    }
                    $_total += ($expense['amount'] / 100 * $tax->taxrate);
                }
                array_push($_total_all, $_total);
            }
            $_result[$key]['total'] = mpc_app_format_money(array_sum($_total_all), $currency);
        }
        $_result['currency_switcher'] = $currency_switcher;
        $_result['currencyid']        = $currencyid;

        return $_result;
    }
}
