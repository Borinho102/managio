<?php defined('BASEPATH') or exit('No direct script access allowed');

return App_table::find('cf_expenses')
    ->outputUsing(function ($params) {
        extract($params);

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

        $businessId = !empty($buisness_id) ? $buisness_id : (!empty($data['buisness_id']) ? $data['buisness_id'] : '');
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

            $row[] = $aRow['id'];

            $expense = '<a href="' . admin_url('cash_flow/list_cf_expenses/' . $aRow['buisness_id'] . '/' . $aRow['id']) . '" onclick="init_cf_expense(' . $aRow['id'] . ');return false;">' . $aRow['expense_name'] . '</a>';
            $expense .= '<div class="row-options">';
            $expense .= '<a href="' . admin_url('cash_flow/list_cf_expenses/' . $aRow['buisness_id'] . '/' . $aRow['id']) . '" onclick="init_cf_expense(' . $aRow['id'] . ', ' . $aRow['buisness_id'] . ');return false;">' . _l('view') . '</a>';

            if (staff_can('edit', 'cash_flow')) {
                $expense .= ' | <a href="' . admin_url('cash_flow/expense/' . $aRow['buisness_id'] . '/' . $aRow['id']) . '">' . _l('edit') . '</a>';
            }

            if (staff_can('delete', 'cash_flow')) {
                $expense .= ' | <a href="' . admin_url('cash_flow/delete/' . $aRow['buisness_id'] . '/' . $aRow['id']) . '" class="_delete text-danger">' . _l('delete') . '</a>';
            }

            $expense .= '</div>';
            $row[] = $expense;

            $row[] = ucwords(str_replace('-', ' ', $aRow['operation']));
            $row[] = _d($aRow['date']);
            $row[] = !empty($aRow['clientid'])
                ? '<a href="' . admin_url('clients/client/' . $aRow['clientid']) . '">' . $aRow['company'] . '</a>'
                : '';
            $row[] = '#REF0' . $aRow['id'];

            $currency = !empty($aRow['currency_name']) ? $aRow['currency_name'] : '';
            $row[] = $aRow['operation'] == 'cash-in' ? app_format_money($aRow['amount'], $currency) : 0;
            $row[] = $aRow['operation'] == 'cash-out' ? app_format_money($aRow['amount'], $currency) : 0;
            $row[] = app_format_money($aRow['balance'], $currency);

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('category', 'MultiSelectRule')->label(_l('category'))
            ->options(function ($ci) {
                $ci->load->model('cash_flow_model');

                return collect($ci->cash_flow_model->get_category())->map(fn ($cat) => [
                    'value' => $cat['id'],
                    'label' => $cat['name'],
                ]);
            })
            ->raw(function ($value, $operator, $sqlOperator) {
                $ids = array_map('intval', (array) $value);

                return db_prefix() . 'cf_expenses.category IN (' . implode(', ', $ids) . ')';
            }),
        App_table_filter::new('clients', 'MultiSelectRule')->label(_l('client'))
            ->options(function ($ci) {
                return collect($ci->clients_model->get())->map(fn ($client) => [
                    'value' => $client['userid'],
                    'label' => $client['company'],
                ]);
            })
            ->raw(function ($value, $operator, $sqlOperator) {
                $ids = array_map('intval', (array) $value);

                return db_prefix() . 'cf_expenses.clientid IN (' . implode(', ', $ids) . ')';
            }),
        App_table_filter::new('operation', 'MultiSelectRule')->label(_l('operation'))
            ->options(function ($ci) {
                return collect(['cash-in', 'cash-out'])->map(fn ($cash) => [
                    'value' => $cash,
                    'label' => ucwords(str_replace('-', ' ', $cash)),
                ]);
            })
            ->raw(function ($value, $operator, $sqlOperator) {
                $values = array_map(function ($v) {
                    return "'" . str_replace("'", "''", $v) . "'";
                }, (array) $value);

                return db_prefix() . 'cf_expenses.operation IN (' . implode(',', $values) . ')';
            }),
        App_table_filter::new('date', 'DateRule')->label(_l('date')),
    ]);
