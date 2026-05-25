<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('insert')) {
    function insert($table_name, $insert_data)
    {
        $CI = &get_instance();
        return $CI->db->insert($table_name, $insert_data);
    }
}

if (!function_exists('get_relation_data_mpc_api')) {
    function get_relation_data_mpc_api($type, $search = '')
    {
        $CI = &get_instance();
        $q  = '';
        if ($search != '') {
            $q = $search;
            $q = trim($q);
        }
        $data = [];
        if ($type == 'customer' || $type == 'customers') {
            $where_clients = 'tblclients.active=1';

            if ($q) {
                $where_clients .= ' AND types = "customer" AND (company LIKE "%' . $q . '%" OR CONCAT(firstname, " ", lastname) LIKE "%' . $q . '%" OR email LIKE "%' . $q . '%")';
            }
            $data = $CI->clients_model->get('', $where_clients);
        } elseif ($type == 'ticket') {
            $search = $CI->api_model->_search_tickets($q, 0, true);
            $data   = $search['result'];
        } elseif ($type == 'lead' || $type == 'leads') {
            $search = $CI->api_model->_search_leads($q, 0, [
                'junk' => 0,
            ], true);
            $data = $search['result'];
        } elseif ($type == 'project') {

            $where_projects = '';
            if ($CI->input->post('customer_id')) {
                $where_projects .= '(clientid=' . $CI->input->post('customer_id') . ' or clientid in (select id from tblleads where client_id=' . $CI->input->post('customer_id') . ') )';
            }
            if ($CI->input->post('rel_type')) {
                $where_projects .= ' and rel_type="' . $CI->input->post('rel_type') . '" ';
            }
            $search = $CI->api_model->_search_projects($q, 0, $where_projects, $CI->input->post('rel_type'), true);

            $data   = $search['result'];
        } elseif ($type == 'staff') {
            $search = $CI->api_model->_search_staff($q, 0, true);
            $data   = $search['result'];
        } elseif ($type == 'tasks') {
            $search = $CI->api_model->_search_tasks($q, 0, true);
            $data   = $search['result'];
        }
        return $data;
    }
}

/**
 * Get weekdays as array
 * @return array
 */
if (!function_exists('mpc_get_short_weekdays')) {
    function mpc_get_short_weekdays()
    {
        return ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    }
}
/**
 * Format money/amount based on currency settings
 * @since  2.3.2
 * @param  mixed   $amount          amount to format
 * @param  mixed   $currency        currency db object or currency name (ISO code)
 * @param  boolean $excludeSymbol   whether to exclude to symbol from the format
 * @return string
 */
if (!function_exists('mpc_app_format_money')) {
    function mpc_app_format_money($amount, $currency, $excludeSymbol = false)
    {
        /**
         *  Check ewhether the amount is numeric and valid
         */
        if (!is_numeric($amount) && $amount != 0) {
            return $amount;
        }

        if (is_null($amount)) {
            $amount = 0;
        }

        /**
         * Check if currency is passed as Object from database or just currency name e.q. USD
         */
        if (is_string($currency)) {
            $dbCurrency = get_currency($currency);

            // Check of currency found in case does not exists in database
            if ($dbCurrency) {
                $currency = $dbCurrency;
            } else {
                $currency = [
                    'symbol'             => $currency,
                    'name'               => $currency,
                    'placement'          => 'before',
                    'decimal_separator'  => get_option('decimal_separator'),
                    'thousand_separator' => get_option('thousand_separator'),
                ];
                $currency = (object) $currency;
            }
        }

        /**
         * Determine the symbol
         * @var string
         */
        $symbol = !$excludeSymbol ? $currency->symbol : '';

        /**
         * Check decimal places
         * @var mixed
         */
        $d = get_option('remove_decimals_on_zero') == 1 && !is_decimal($amount) ? 0 : get_decimal_places();

        /**
         * Format the amount
         * @var string
         */
        $amountFormatted = str_replace($currency->decimal_separator, '<sup class="decimal_separator">' . $currency->decimal_separator, number_format($amount, $d, $currency->decimal_separator, $currency->thousand_separator)) . '</sup>';

        /**
         * Maybe add the currency symbol
         * @var string
         */
        $formattedWithCurrency = $currency->placement === 'after' ? $amountFormatted . ' <sup class="symbol">' . $symbol . '</sup>' : '<sup class="symbol">' . $symbol . '</sup> ' . $amountFormatted;

        return hooks()->apply_filters('app_format_money', $formattedWithCurrency, [
            'amount'         => $amount,
            'currency'       => $currency,
            'exclude_symbol' => $excludeSymbol,
            'decimal_places' => $d,
        ]);
    }
}

if (!function_exists('mpc_subscription_invoice_preview_data')) {
    function mpc_subscription_invoice_preview_data($subscription, $upcomingInvoice = null, $stripeSubscription = null)
    {
        $CI = &get_instance();

        if (!isset($upcomingInvoice)) {
            $upcomingInvoice = $CI->stripe_subscriptions->get_upcoming_invoice($subscription->stripe_subscription_id);
        }

        $newInvoiceData = create_subscription_invoice_data($subscription, $upcomingInvoice);

        $itemsArray = $newInvoiceData['newitems'];
        $itemsArray = array_values($itemsArray);

        foreach ($itemsArray as $key => $item) {
            $itemsArray[$key]['id']       = 0;
            $itemsArray[$key]['rel_id']   = 0;
            $itemsArray[$key]['rel_type'] = 'invoice';

            if (isset($item['taxname']) && is_array($item['taxname'])) {
                foreach ($item['taxname'] as $keyTax => $tax) {
                    $taxArray                                        = explode('|', $tax);
                    $itemsArray[$key]['taxname'][$keyTax]            = [];
                    $itemsArray[$key]['taxname'][$keyTax]['taxname'] = $tax; // NAME|PERCENT
                    $itemsArray[$key]['taxname'][$keyTax]['taxrate'] = $taxArray[1];
                }
            }
        }

        $upcomingInvoice = create_subscription_invoice_data($subscription, $upcomingInvoice);
        $upcomingInvoice = array_to_object($upcomingInvoice);

        $upcomingInvoice->items = $itemsArray;

        // Fake data
        if (isset($stripeSubscription->current_period_end)) {
            $date                  = date('Y-m-d', $stripeSubscription->current_period_end);
            $upcomingInvoice->date = _d($date);

            if (get_option('invoice_due_after') != 0) {
                $upcomingInvoice->duedate = _d(date('Y-m-d', strtotime('+' . get_option('invoice_due_after') . ' DAY', strtotime($date))));
            }
        }
        $upcomingInvoice->client      = $CI->clients_model->get($subscription->clientid);
        $upcomingInvoice->id          = 0;
        $upcomingInvoice->payments    = [];
        $upcomingInvoice->attachments = [];
        $upcomingInvoice->status      = 2;

        $upcomingInvoice->project_id = $subscription->project_id;
        if ($subscription->project_id != 0) {
            if (class_exists('projects_model')) {
                $CI->load->model('projects_model');
            }
            $upcomingInvoice->project_data = $CI->projects_model->get($subscription->project_id);
        }
        $upcomingInvoice->sale_agent        = 0;
        $upcomingInvoice->total_left_to_pay = $upcomingInvoice->total;
        $upcomingInvoice->discount_percent  = 0;
        $upcomingInvoice->discount_total    = 0;
        $upcomingInvoice->recurring         = 0;
        $upcomingInvoice->is_recurring_from = null;

        $currency                       = get_currency($subscription->currency);
        $upcomingInvoice->symbol        = $currency->symbol;
        $upcomingInvoice->currency_name = $currency->name;

        $GLOBALS['items_preview_transaction'] = $upcomingInvoice;

        return $upcomingInvoice;
    }
}

if (!function_exists('mpc_get_user_id_by_contact_id')) {

    function mpc_get_user_id_by_contact_id($id)
    {
        $CI = &get_instance();

        $userid = $CI->app_object_cache->get('user-id-by-contact-id-' . $id);
        if (!$userid) {
            $CI->db->select('userid')
                ->where('id', $id);
            $client = $CI->db->get(db_prefix() . 'contacts')->row();

            if ($client) {
                $userid = $client->userid;
                $CI->app_object_cache->add('user-id-by-contact-id-' . $id, $userid);
            }
        }

        return $userid;
    }
}

function get_invoices_where_sql_for_client($clientid)
{
    $CI = &get_instance();
    $has_permission_view_own = has_contact_permission('invoices');

    $whereUser = '';
    if ($has_permission_view_own) {
        $whereUser = db_prefix() . 'invoices.clientid = ' . $CI->db->escape(get_client_user_id()) . '  AND ' . db_prefix() . 'invoices.clientid IN ( SELECT userid  FROM ' . db_prefix() . 'contact_permissions WHERE userid = ' . $CI->db->escape(get_contact_user_id()) . ')';
    }

    return $whereUser;
}

function get_invoices_percent_by_status_for_client($status)
{
    $has_permission_view = has_contact_permission('invoices', get_contact_user_id(), 'view_own');
    $total_invoices      = total_rows(db_prefix() . 'invoices', 'status NOT IN(5)' . (!$has_permission_view ? ' AND (' . get_invoices_where_sql_for_client(get_contact_user_id()) . ')' : ''));

    $data            = [];
    $total_by_status = 0;
    if (!is_numeric($status)) {
        if ($status == 'not_sent') {
            $total_by_status = total_rows(db_prefix() . 'invoices', 'sent=0 AND status NOT IN(2,5)' . (!$has_permission_view ? ' AND (' . get_invoices_where_sql_for_client(get_contact_user_id()) . ')' : ''));
        }
    } else {
        $total_by_status = total_rows(db_prefix() . 'invoices', 'status = ' . $status . ' AND status NOT IN(5)' . (!$has_permission_view ? ' AND (' . get_invoices_where_sql_for_client(get_contact_user_id()) . ')' : ''));
    }
    $percent                 = ($total_invoices > 0 ? number_format(($total_by_status * 100) / $total_invoices, 2) : 0);
    $data['total_by_status'] = $total_by_status;
    $data['percent']         = $percent;
    $data['total']           = $total_invoices;

    return $data;
}

/**
 * Calculate estimates percent by status
 * @param  mixed $status          estimate status
 * @return array
 */
function get_estimates_percent_by_status_for_client($status, $project_id = null)
{
    $has_permission_view = has_contact_permission('estimates', get_contact_user_id(), 'view_own');
    $where               = '';

    if (isset($project_id)) {
        $where .= 'project_id=' . get_instance()->db->escape_str($project_id) . ' AND ';
    }
    if (!$has_permission_view) {
        $where .= get_estimates_where_sql_for_client(get_contact_user_id());
    }

    $where = trim($where);

    if (endsWith($where, ' AND')) {
        $where = substr_replace($where, '', -3);
    }

    $total_estimates = total_rows(db_prefix() . 'estimates', $where);

    $data            = [];
    $total_by_status = 0;

    if (!is_numeric($status)) {
        if ($status == 'not_sent') {
            $total_by_status = total_rows(db_prefix() . 'estimates', 'sent=0 AND status NOT IN(2,3,4)' . ($where != '' ? ' AND (' . $where . ')' : ''));
        }
    } else {
        $whereByStatus = 'status=' . $status;
        if ($where != '') {
            $whereByStatus .= ' AND (' . $where . ')';
        }
        $total_by_status = total_rows(db_prefix() . 'estimates', $whereByStatus);
    }

    $percent                 = ($total_estimates > 0 ? number_format(($total_by_status * 100) / $total_estimates, 2) : 0);
    $data['total_by_status'] = $total_by_status;
    $data['percent']         = $percent;
    $data['total']           = $total_estimates;

    return $data;
}

function get_estimates_where_sql_for_client($clientid)
{
    $CI                                  = &get_instance();
    $has_permission_view_own             = has_contact_permission('estimates', get_contact_user_id(), 'view_own');

    $whereUser                           = '';
    if ($has_permission_view_own) {
        $whereUser = '((' . db_prefix() . 'estimates.clientid=' . $CI->db->escape_str($clientid) . ' AND ' . db_prefix() . 'estimates.clientid IN (SELECT userid FROM ' . db_prefix() . 'contact_permissions WHERE userid = ' . $CI->db->escape_str($clientid) . '))';
        $whereUser .= ')';
    }

    return $whereUser;
}

function get_proposals_percent_by_status_for_client($status, $total_proposals = '')
{
    $has_permission_view                 = has_contact_permission('proposals', get_contact_user_id(), 'view');
    $has_permission_view_own             = has_contact_permission('proposals', get_contact_user_id(), 'view_own');
    $clientid                             = get_client_user_id();

    $whereUser = '';
    if (!$has_permission_view) {
        if ($has_permission_view_own) {
            $whereUser = '(rel_id=' . $clientid . ' AND (rel_type=customer)';
            $whereUser .= ')';
        }
    }

    if (!is_numeric($total_proposals)) {
        $total_proposals = total_rows(db_prefix() . 'proposals', $whereUser);
    }

    $data            = [];
    $total_by_status = 0;
    $where           = 'status=' . get_instance()->db->escape_str($status);
    if (!$has_permission_view) {
        $where .= ' AND (' . $whereUser . ')';
    }

    $total_by_status = total_rows(db_prefix() . 'proposals', $where);
    $percent         = ($total_proposals > 0 ? number_format(($total_by_status * 100) / $total_proposals, 2) : 0);

    $data['total_by_status'] = $total_by_status;
    $data['percent']         = $percent;
    $data['total']           = $total_proposals;

    return $data;
}

function get_invoices_total_client($data)
{
    $CI = &get_instance();
    $CI->load->model('currencies_model');

    if (isset($data['currency'])) {
        $currencyid = $data['currency'];
    } elseif (isset($data['customer_id']) && $data['customer_id'] != '') {
        $currencyid = $CI->clients_model->get_customer_default_currency($data['customer_id']);
        if ($currencyid == 0) {
            $currencyid = $CI->currencies_model->get_base_currency()->id;
        }
    } elseif (isset($data['project_id']) && $data['project_id'] != '') {
        $CI->load->model('projects_model');
        $currencyid = $CI->projects_model->get_currency($data['project_id'])->id;
    } else {
        $currencyid = $CI->currencies_model->get_base_currency()->id;
    }

    $clientid = get_client_user_id();

    $result = ['due' => [], 'paid' => [], 'overdue' => []];
    $has_permission_view = has_contact_permission('invoices', get_contact_user_id(), 'view_own');
    $noPermissionsQuery = get_invoices_where_sql_for_client(get_contact_user_id());

    for ($i = 1; $i <= 3; $i++) {
        $select = 'id,total';
        if ($i == 1) {
            $select .= ', (SELECT total - (SELECT COALESCE(SUM(amount),0) FROM ' . db_prefix() . 'invoicepaymentrecords WHERE invoiceid = ' . db_prefix() . 'invoices.id) - (SELECT COALESCE(SUM(amount),0) FROM ' . db_prefix() . 'credits WHERE ' . db_prefix() . 'credits.invoice_id=' . db_prefix() . 'invoices.id)) as outstanding';
        } elseif ($i == 2) {
            $select .= ',(SELECT SUM(amount) FROM ' . db_prefix() . 'invoicepaymentrecords WHERE invoiceid=' . db_prefix() . 'invoices.id) as total_paid';
        }

        $CI->db->select($select);
        $CI->db->from(db_prefix() . 'invoices');
        $CI->db->where('status !=', 5);
        $CI->db->where('status !=', 6);
        $CI->db->where('clientid', $clientid);

        if ($i == 3) {
            $CI->db->where('status', 4);
        } elseif ($i == 1) {
            $CI->db->where('status !=', 2);
        }

        if (isset($data['years']) && count($data['years']) > 0) {
            $CI->db->where_in('YEAR(date)', $data['years']);
        } else {
            $CI->db->where('YEAR(date)', date('Y'));
        }

        if (!$has_permission_view) {
            $CI->db->where('(' . $noPermissionsQuery . ')');
        }

        $invoices = $CI->db->get()->result_array();

        foreach ($invoices as $invoice) {
            if ($i == 1) {
                $result['due'][] = $invoice['outstanding'];
            } elseif ($i == 2) {
                $result['paid'][] = $invoice['total_paid'];
            } elseif ($i == 3) {
                $result['overdue'][] = $invoice['total'];
            }
        }
    }
    $currency             = get_currency($currencyid);
    $result['due'] = array_sum($result['due']);
    $result['paid'] = array_sum($result['paid']);
    $result['overdue'] = array_sum($result['overdue']);
    $result['currency']   = $currency;
    $result['currencyid'] = $currencyid;

    return $result;
}

function add_mobile_app_menu_item($slug, $item)
{
    $CI = &get_instance();
    $CI->app_menu->add($slug, $item, 'mobile_app_menu');
}

function register_api_route($module, $route, $method, $callback)
{
    $GLOBALS['api_routes'][$module][$route][$method] = $callback;
}

function api_response($data, $status = 200)
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}
