<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Cash-flow expenses DataTable (App_table).
 * Hardened for tenant DBs: no empty IN (), safe joins, classic closures (PHP 7.3+).
 */
return App_table::find('cf_expenses')
    ->outputUsing(function ($params) {
        extract($params);

        try {
            $aColumns = [
                db_prefix() . 'cf_expenses.id as id',
                'expense_name',
                'operation',
                'date',
                get_sql_select_client_company(),
                db_prefix() . 'cf_expenses.id as reference_id',
                'amount',
                'amount as disbursement_amount',
                'balance',
            ];

            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'cf_expenses';

            $join = [
                'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'cf_expenses.clientid',
                'LEFT JOIN ' . db_prefix() . 'expenses_categories ON ' . db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'cf_expenses.category',
                'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'cf_expenses.currency',
                'LEFT JOIN ' . db_prefix() . 'cf_buisness_types ON ' . db_prefix() . 'cf_buisness_types.id = ' . db_prefix() . 'cf_expenses.buisness_id',
            ];

            $where = [];

            if ($filtersWhere = $this->getWhereFromRules()) {
                $where[] = $filtersWhere;
            }

            $businessId = '';
            if (!empty($buisness_id)) {
                $businessId = $buisness_id;
            } elseif (!empty($data['buisness_id'])) {
                $businessId = $data['buisness_id'];
            }

            if ($businessId !== '' && $businessId !== null) {
                $where[] = 'AND ' . db_prefix() . 'cf_expenses.buisness_id=' . $this->ci->db->escape_str($businessId);
            }

            if (!empty($clientid)) {
                $where[] = 'AND ' . db_prefix() . 'cf_expenses.clientid=' . $this->ci->db->escape_str($clientid);
            }

            if (!staff_can('view', 'cash_flow')) {
                $where[] = 'AND ' . db_prefix() . 'cf_expenses.addedfrom=' . get_staff_user_id();
            }

            $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
                db_prefix() . 'currencies.name as currency_name',
                db_prefix() . 'cf_expenses.clientid',
                'buisness_id',
            ]);

            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $row = [];
                $rowId = isset($aRow['id']) ? $aRow['id'] : '';
                $bizId = isset($aRow['buisness_id']) ? $aRow['buisness_id'] : $businessId;
                $expenseName = isset($aRow['expense_name']) ? $aRow['expense_name'] : '';

                $row[] = $rowId;

                $expense = '<a href="' . admin_url('cash_flow/list_cf_expenses/' . $bizId . '/' . $rowId) . '" onclick="init_cf_expense(' . (int) $rowId . ');return false;">' . $expenseName . '</a>';
                $expense .= '<div class="row-options">';
                $expense .= '<a href="' . admin_url('cash_flow/list_cf_expenses/' . $bizId . '/' . $rowId) . '" onclick="init_cf_expense(' . (int) $rowId . ', ' . (int) $bizId . ');return false;">' . _l('view') . '</a>';

                if (staff_can('edit', 'cash_flow')) {
                    $expense .= ' | <a href="' . admin_url('cash_flow/expense/' . $bizId . '/' . $rowId) . '">' . _l('edit') . '</a>';
                }

                if (staff_can('delete', 'cash_flow')) {
                    $expense .= ' | <a href="' . admin_url('cash_flow/delete/' . $bizId . '/' . $rowId) . '" class="_delete text-danger">' . _l('delete') . '</a>';
                }

                $expense .= '</div>';
                $row[] = $expense;

                $operation = isset($aRow['operation']) ? $aRow['operation'] : '';
                $row[] = ucwords(str_replace('-', ' ', $operation));
                $row[] = !empty($aRow['date']) ? _d($aRow['date']) : '';
                $company = isset($aRow['company']) ? $aRow['company'] : '';
                $clientId = isset($aRow['clientid']) ? $aRow['clientid'] : '';
                $row[] = $clientId
                    ? '<a href="' . admin_url('clients/client/' . $clientId) . '">' . $company . '</a>'
                    : $company;
                $row[] = '#REF0' . $rowId;

                $currency = !empty($aRow['currency_name']) ? $aRow['currency_name'] : '';
                $amount = isset($aRow['amount']) ? $aRow['amount'] : 0;
                $balance = isset($aRow['balance']) ? $aRow['balance'] : 0;
                $row[] = $operation == 'cash-in' ? app_format_money($amount, $currency) : 0;
                $row[] = $operation == 'cash-out' ? app_format_money($amount, $currency) : 0;
                $row[] = app_format_money($balance, $currency);

                $row['DT_RowClass'] = 'has-row-options';
                $output['aaData'][] = $row;
            }

            return $output;
        } catch (Throwable $e) {
            log_message('error', '[cf_expenses table] ' . $e->getMessage());

            return [
                'draw'            => (int) $this->ci->input->post('draw'),
                'iTotalRecords'   => 0,
                'iTotalDisplayRecords' => 0,
                'aaData'          => [],
                'error'           => $e->getMessage(),
            ];
        }
    })->setRules([
        App_table_filter::new('category', 'MultiSelectRule')->label(_l('category'))
            ->options(function ($ci) {
                $ci->load->model('cash_flow_model');
                $cats = $ci->cash_flow_model->get_category();
                $out = [];
                foreach ((array) $cats as $cat) {
                    $out[] = [
                        'value' => $cat['id'],
                        'label' => $cat['name'],
                    ];
                }

                return collect($out);
            })
            ->raw(function ($value, $operator, $sqlOperator) {
                $ids = array_filter(array_map('intval', (array) $value));
                if (empty($ids)) {
                    return '1=1';
                }

                return db_prefix() . 'cf_expenses.category IN (' . implode(', ', $ids) . ')';
            }),
        App_table_filter::new('clients', 'MultiSelectRule')->label(_l('client'))
            ->options(function ($ci) {
                $clients = $ci->clients_model->get();
                $out = [];
                foreach ((array) $clients as $client) {
                    $out[] = [
                        'value' => $client['userid'],
                        'label' => $client['company'],
                    ];
                }

                return collect($out);
            })
            ->raw(function ($value, $operator, $sqlOperator) {
                $ids = array_filter(array_map('intval', (array) $value));
                if (empty($ids)) {
                    return '1=1';
                }

                return db_prefix() . 'cf_expenses.clientid IN (' . implode(', ', $ids) . ')';
            }),
        App_table_filter::new('operation', 'MultiSelectRule')->label(_l('operation'))
            ->options(function ($ci) {
                return collect([
                    ['value' => 'cash-in', 'label' => 'Cash In'],
                    ['value' => 'cash-out', 'label' => 'Cash Out'],
                ]);
            })
            ->raw(function ($value, $operator, $sqlOperator) {
                $values = [];
                foreach ((array) $value as $v) {
                    $v = preg_replace('/[^a-z\-]/', '', strtolower((string) $v));
                    if ($v === 'cash-in' || $v === 'cash-out') {
                        $values[] = "'" . $v . "'";
                    }
                }
                if (empty($values)) {
                    return '1=1';
                }

                return db_prefix() . 'cf_expenses.operation IN (' . implode(',', $values) . ')';
            }),
        App_table_filter::new('date', 'DateRule')->label(_l('date')),
    ]);
