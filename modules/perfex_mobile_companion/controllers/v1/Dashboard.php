<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Dashboard extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('dashboard_model');
        $this->load->model('currencies_model');
        $this->load->model('invoices_model');
    }

    public function data_get()
    {

        $total_invoices = total_rows(db_prefix() . 'invoices', 'status NOT IN (5,6)' . (!has_permission('invoices', '', 'view') ? ' AND ' . get_invoices_where_sql_for_staff(get_staff_user_id()) : ''));
        $total_invoices_awaiting_payment = total_rows(db_prefix() . 'invoices', 'status NOT IN (2,5,6)' . (!has_permission('invoices', '', 'view') ? ' AND ' . get_invoices_where_sql_for_staff(get_staff_user_id()) : ''));
        $percent_total_invoices_awaiting_payment = ($total_invoices > 0 ? number_format(($total_invoices_awaiting_payment * 100) / $total_invoices, 2) : 0);

        $where = '';
        if (!is_admin()) {
            $where .= '(addedfrom = ' . get_staff_user_id() . ' OR assigned = ' . get_staff_user_id() . ')';
        }
        // Junk leads are excluded from total
        $total_leads = total_rows(db_prefix() . 'leads', ($where == '' ? 'junk=0' : $where .= ' AND junk =0'));
        if ($where == '') {
            $where .= 'status=1';
        } else {
            $where .= ' AND status =1';
        }
        $total_leads_converted = total_rows(db_prefix() . 'leads', $where);
        $percent_total_leads_converted = ($total_leads > 0 ? number_format(($total_leads_converted * 100) / $total_leads, 2) : 0);

        $_where = '';
        $project_status = get_project_status_by_id(2);
        if (!has_permission('projects', '', 'view')) {
            $_where = 'id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')';
        }
        $total_projects = total_rows(db_prefix() . 'projects', $_where);
        $where = ($_where == '' ? '' : $_where . ' AND ') . 'status = 2';
        $total_projects_in_progress = total_rows(db_prefix() . 'projects', $where);
        $percent_in_progress_projects = ($total_projects > 0 ? number_format(($total_projects_in_progress * 100) / $total_projects, 2) : 0);

        $_where = '';
        if (!has_permission('tasks', '', 'view')) {
            $_where = db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid = ' . get_staff_user_id() . ')';
        }
        $total_tasks = total_rows(db_prefix() . 'tasks', $_where);
        $where = ($_where == '' ? '' : $_where . ' AND ') . 'status != ' . Tasks_model::STATUS_COMPLETE;
        $total_not_finished_tasks = total_rows(db_prefix() . 'tasks', $where);
        $percent_not_finished_tasks = ($total_tasks > 0 ? number_format(($total_not_finished_tasks * 100) / $total_tasks, 2) : 0);

        $invoices = [];

        $percent_data = get_invoices_percent_by_status(6);
        array_push($invoices, [
            'status' => format_invoice_status(6, '', false),
            'total' => $percent_data['total_by_status'],
            'percent' => $percent_data['percent'],
            'percent_decimal' => ($percent_data['percent'] / 100),
            'color' => 'dark'
        ]);

        $percent_data = get_invoices_percent_by_status('not_sent');
        array_push($invoices, [
            'status' => format_invoice_status('not_sent', '', false),
            'total' => $percent_data['total_by_status'],
            'percent' => $percent_data['percent'],
            'percent_decimal' => ($percent_data['percent'] / 100),
            'color' => 'dark'
        ]);

        $percent_data = get_invoices_percent_by_status(1);
        array_push($invoices, [
            'status' => format_invoice_status(1, '', false),
            'total' => $percent_data['total_by_status'],
            'percent' => $percent_data['percent'],
            'percent_decimal' => ($percent_data['percent'] / 100),
            'color' => 'danger'
        ]);

        $percent_data = get_invoices_percent_by_status(3);
        array_push($invoices, [
            'status' => format_invoice_status(3, '', false),
            'total' => $percent_data['total_by_status'],
            'percent' => $percent_data['percent'],
            'percent_decimal' => ($percent_data['percent'] / 100),
            'color' => 'warning'
        ]);

        $percent_data = get_invoices_percent_by_status(4);
        array_push($invoices, [
            'status' => format_invoice_status(4, '', false),
            'total' => $percent_data['total_by_status'],
            'percent' => $percent_data['percent'],
            'percent_decimal' => ($percent_data['percent'] / 100),
            'color' => 'warning'
        ]);

        $percent_data = get_invoices_percent_by_status(2);
        array_push($invoices, [
            'status' => format_invoice_status(2, '', false),
            'total' => $percent_data['total_by_status'],
            'percent' => $percent_data['percent'],
            'percent_decimal' => ($percent_data['percent'] / 100),
            'color' => 'success'
        ]);

        $estimates = [];

        $this->load->model('estimates_model');
        $estimate_statuses = $this->estimates_model->get_statuses();

        array_splice($estimate_statuses, 1, 0, 'not_sent');
        foreach ($estimate_statuses as $status) {
            $percent_data = get_estimates_percent_by_status($status);
            array_push($estimates, [
                'status' => format_estimate_status($status, '', false),
                'total' => $percent_data['total_by_status'],
                'percent' => $percent_data['percent'],
                'percent_decimal' => ($percent_data['percent'] / 100),
                'color' => (estimate_status_color_class($status) == 'default' ? 'dark' : (estimate_status_color_class($status) == 'info' ? 'secondary' : estimate_status_color_class($status)))
            ]);
        }

        $this->load->model('proposals_model');
        $proposal_statuses = $this->proposals_model->get_statuses();
        $proposals = [];
        foreach ($proposal_statuses as $status) {
            $percent_data = get_proposals_percent_by_status($status);
            array_push($proposals, [
                'status' => format_proposal_status($status, '', false),
                'total' => $percent_data['total_by_status'],
                'percent' => $percent_data['percent'],
                'percent_decimal' => ($percent_data['percent'] / 100),
                'color' => (proposal_status_color_class($status, true) == 'muted' ? 'dark' : (proposal_status_color_class($status, true) == 'info' ? 'secondary' : proposal_status_color_class($status, true)))
            ]);
        }

        $wps_currency = 'undefined';
        $_data = [];

        if (is_using_multiple_currencies()) {
            $base_currency = $this->currencies_model->get_base_currency();
            $wps_currency = $base_currency->id;
            $_data['invoices_total_currencies'] = $this->currencies_model->get();
        }

        $invoice_stats = $this->invoices_model->get_invoices_total($_data);
        $invoice_stats['formatted_due'] = app_format_money($invoice_stats['due'], $invoice_stats['currency']);
        $invoice_stats['formatted_overdue'] = app_format_money($invoice_stats['overdue'], $invoice_stats['currency']);
        $invoice_stats['formatted_paid'] = app_format_money($invoice_stats['paid'], $invoice_stats['currency']);

        $this->load->model('todo_api_model');
        $todo = $this->todo_api_model->get_todo_items(0);
        // Only show last 5 finished todo items
        $this->todo_api_model->setTodosLimit(5);
        $todos_finished = $this->todo_api_model->get_todo_items(1);

        $this->response([
            'todo' => $todo,
            'todos_finished' => $todos_finished,
            'finance_overview' => [
                'invoices' => $invoices,
                'estimates' => $estimates,
                'proposals' => $proposals
            ],
            'invoices' => [
                'invoice_stats' => $invoice_stats,
                'total_invoices' => $total_invoices,
                'total_invoices_awaiting_payment' => $total_invoices_awaiting_payment,
                'percent_total_invoices_awaiting_payment' => $percent_total_invoices_awaiting_payment,
            ],
            'leads' => [
                'total_leads' => $total_leads,
                'total_leads_converted' => $total_leads_converted,
                'percent_total_leads_converted' => $percent_total_leads_converted
            ],
            'projects' => [
                'total_projects' => $total_projects,
                'total_projects_in_progress' => $total_projects_in_progress,
                'percent_in_progress_projects' => $percent_in_progress_projects
            ],
            'tasks' => [
                'total_tasks' => $total_tasks,
                'total_not_finished_tasks' => $total_not_finished_tasks,
                'percent_not_finished_tasks' => $percent_not_finished_tasks
            ],
            'weekly_payment_stats' => $this->get_weekly_payments_statistics($wps_currency)
        ], REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    private function get_todo_items($finished, $page = '')
    {
        $this->db->select();
        $this->db->from(db_prefix() . 'todos');
        $this->db->where('finished', $finished);
        $this->db->where('staffid', get_staff_user_id());
        $this->db->order_by('item_order', 'asc');
        if ($page != '' && $this->input->post('todo_page')) {
            $position = ($page * $this->todo_limit);
            $this->db->limit($this->todo_limit, $position);
        } else {
            $this->db->limit($this->todo_limit);
        }
        $todos = $this->db->get()->result_array();
        // format date
        $i = 0;
        foreach ($todos as $todo) {
            $todos[$i]['dateadded']    = _dt($todo['dateadded']);
            $todos[$i]['datefinished'] = _dt($todo['datefinished']);
            $todos[$i]['description']  = check_for_links($todo['description']);
            $i++;
        }

        return $todos;
    }

    /**
     * @param  mixed
     * @return array
     * Used in home dashboard page, currency passed from javascript (undefined or integer)
     * Displays weekly payment statistics (chart)
     */
    private function get_weekly_payments_statistics($currency)
    {
        $all_payments                 = [];
        $has_permission_payments_view = has_permission('payments', '', 'view');
        $this->db->select(db_prefix() . 'invoicepaymentrecords.id, amount,' . db_prefix() . 'invoicepaymentrecords.date');
        $this->db->from(db_prefix() . 'invoicepaymentrecords');
        $this->db->join(db_prefix() . 'invoices', '' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid');
        $this->db->where('YEARWEEK(' . db_prefix() . 'invoicepaymentrecords.date) = YEARWEEK(CURRENT_DATE)');
        $this->db->where('' . db_prefix() . 'invoices.status !=', 5);
        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE addedfrom=' . get_staff_user_id() . ' and addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        // Current week
        $all_payments[] = $this->db->get()->result_array();
        $this->db->select(db_prefix() . 'invoicepaymentrecords.id, amount,' . db_prefix() . 'invoicepaymentrecords.date');
        $this->db->from(db_prefix() . 'invoicepaymentrecords');
        $this->db->join(db_prefix() . 'invoices', '' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid');
        $this->db->where('YEARWEEK(' . db_prefix() . 'invoicepaymentrecords.date) = YEARWEEK(CURRENT_DATE - INTERVAL 7 DAY) ');

        $this->db->where('' . db_prefix() . 'invoices.status !=', 5);
        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE addedfrom=' . get_staff_user_id() . ' and addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        // Last Week
        $all_payments[] = $this->db->get()->result_array();

        $chart = [
            'xAxis'    => [
                'data' => get_weekdays()
            ],
            'series' => [
                [
                    'name'            => _l('this_week_payments'),
                    'type'            => 'bar',
                    'barWidth'        => '60%',
                    'color'           => 'rgba(37,155,35,0.2)',
                    'borderColor'     => '#84c529',
                    'borderWidth'     => 1,
                    'tension'         => false,
                    'data'            => [0, 0, 0, 0, 0, 0, 0],
                ],
                [
                    'name'            => _l('last_week_payments'),
                    'type'            => 'bar',
                    'barWidth'        => '60%',
                    'color'           => 'rgba(197, 61, 169, 0.5)',
                    'borderColor'     => '#c53da9',
                    'borderWidth'     => 1,
                    'tension'         => false,
                    'data'            => [0, 0, 0, 0, 0, 0, 0],
                ],
            ],
        ];

        for ($i = 0; $i < count($all_payments); $i++) {
            foreach ($all_payments[$i] as $payment) {
                $payment_day = date('l', strtotime($payment['date']));
                $x           = 0;
                foreach (get_weekdays_original() as $day) {
                    if ($payment_day == $day) {
                        $chart['series'][$i]['data'][$x] += $payment['amount'];
                    }
                    $x++;
                }
            }
        }

        return $chart;
    }
}
