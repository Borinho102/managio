<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Dashboard extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('dashboard_model');
        $this->load->model('currencies_model');
        $this->load->model('invoices_model');
    }

    public function data_get()
    {

        $clientid = get_client_user_id();
        $client_filter = 'clientid = ' . $clientid;
        if (!has_contact_permission('invoices', get_contact_user_id())) {
            $client_filter .= ' AND ' . get_invoices_where_sql_for_client($clientid);
        }

        $total_invoices = total_rows(
            db_prefix() . 'invoices',
            'status NOT IN (5,6) AND ' . $client_filter
        );

        $total_invoices_awaiting_payment = total_rows(
            db_prefix() . 'invoices',
            'status NOT IN (2,5,6) AND ' . $client_filter
        );

        $percent_total_invoices_awaiting_payment = ($total_invoices > 0
            ? number_format(($total_invoices_awaiting_payment * 100) / $total_invoices, 2)
            : 0);

        $_where = '';
        $where = ['clientid' => get_client_user_id()];

        $total_projects = total_rows(db_prefix() . 'projects', $_where);
        $where = ($_where == '' ? '' : $_where . ' AND ') . 'status = 2';
        $total_projects_in_progress = total_rows(db_prefix() . 'projects', $where);
        $percent_in_progress_projects = ($total_projects > 0 ? number_format(($total_projects_in_progress * 100) / $total_projects, 2) : 0);

        $_where = '';

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
                    get_invoices_percent_by_status_for_client(6)['total_by_status'],
                    get_invoices_percent_by_status_for_client(1)['total_by_status'],
                    get_invoices_percent_by_status_for_client(3)['total_by_status'],
                    get_invoices_percent_by_status_for_client(4)['total_by_status'],
                    get_invoices_percent_by_status_for_client(2)['total_by_status'],
                ]
            ]
        ];

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

        $this->load->model('Invoice_api_model');

        $invoice_stats = $this->Invoice_api_model->get_invoices_total($_data);

        $invoice_stats['formatted_due'] = mpc_app_format_money($invoice_stats['due'], $invoice_stats['currency']);
        $invoice_stats['formatted_overdue'] = mpc_app_format_money($invoice_stats['overdue'], $invoice_stats['currency']);
        $invoice_stats['formatted_paid'] = mpc_app_format_money($invoice_stats['paid'], $invoice_stats['currency']);

        $this->response([
            'filters' => $_data,
            'finance_overview' => [
                'invoices' => $invoices,
            ],
            'invoices' => [
                'invoice_stats' => $invoice_stats,
                'total_invoices' => $total_invoices,
                'total_invoices_awaiting_payment' => $total_invoices_awaiting_payment,
                'percent_total_invoices_awaiting_payment' => $percent_total_invoices_awaiting_payment,
            ],
            'projects' => [
                'total_projects' => $total_projects,
                'total_projects_in_progress' => $total_projects_in_progress,
                'percent_in_progress_projects' => $percent_in_progress_projects,
                'data' => $this->projects_summary()
            ],

        ], REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    public function projects_summary()
    {
        $this->load->model('projects_model');
        $statuses = $this->projects_model->get_project_statuses();

        $_where = '';

        foreach ($statuses as $key => $status) {
            $value = $status['id'];
            if ($status['filter_default'] == false && !$this->input->get('status')) {
                $value = '';
            } else if ($this->input->get('status')) {
                $value = ($this->input->get('status') == $status['id'] ? $status['id'] : "");
            }

            $where = ($_where == '' ? '' : $_where . ' AND ') . 'status = ' . $status['id'] . ' AND clientid=' . get_client_user_id();
            $statuses[$key]['total'] = total_rows(db_prefix() . 'projects', $where);

            $statuses[$key]['color'] =  $status['color'];
        }

        return $statuses;
    }
}
