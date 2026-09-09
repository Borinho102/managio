<?php defined('BASEPATH') or exit('No direct script access allowed');

    return App_table::find('cf_expenses')
    ->outputUsing(function ($params) {

        extract($params);

        $aColumns = [
            '1',
            db_prefix().'cf_expenses.id',
            db_prefix().'cf_expenses.buisness_id',
            'operation',
            'date',
            'expense_name',
            'amount',
            get_sql_select_client_company(),
            'balance',
        ];

        $sIndexColumn = db_prefix().'cf_expenses.id';
        $sTable = db_prefix().'cf_expenses';

        $join = [
            'LEFT JOIN '.db_prefix().'clients ON '.db_prefix().'clients.userid = '.db_prefix().'cf_expenses.clientid',
            'JOIN '.db_prefix().'expenses_categories ON '.db_prefix().'expenses_categories.id = '.db_prefix().'cf_expenses.category',
            'LEFT JOIN '.db_prefix().'currencies ON '.db_prefix().'currencies.id = '.db_prefix().'cf_expenses.currency',
            'LEFT JOIN '.db_prefix().'cf_buisness_types ON '.db_prefix().'cf_buisness_types.id = '.db_prefix().'cf_expenses.buisness_id',

        ];

        $where = [];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns,$sIndexColumn,$sTable,$join,$where,[
            db_prefix().'currencies.name as currency_name',
            db_prefix().'cf_expenses.clientid'
        ]);

        $output  = $result['output'];
        $rResult = $result['rResult'];

        foreach ($rResult as $aRow) {

            $row = [];

            $row[] = $aRow[db_prefix().'cf_expenses.id'];

            $expense = '<a href="'.admin_url('cash_flow/list_cf_expenses/'.$aRow[db_prefix().'cf_expenses.id']).'" onclick="init_cf_expense('.$aRow[db_prefix().'cf_expenses.id'].');return false;">'.$aRow['expense_name'].'</a>';

            $expense .= '<div class="row-options">';
            $expense .= '<a href="'.admin_url('cash_flow/list_cf_expenses/'.$aRow[db_prefix().'cf_expenses.buisness_id'].'/'.$aRow[db_prefix().'cf_expenses.id']).'">'._l('view').'</a>';

            if (staff_can('edit','cash_flow')) {
                $expense .= ' | <a href="'.admin_url('cash_flow/expense/'.$aRow[db_prefix().'cf_expenses.buisness_id'].'/'.$aRow[db_prefix().'cf_expenses.id']).'">'._l('edit').'</a>';
            }

            if (staff_can('delete','cash_flow')) {
                $expense .= ' | <a href="'.admin_url('cash_flow/delete/'.$aRow[db_prefix().'cf_expenses.buisness_id'].'/'.$aRow[db_prefix().'cf_expenses.id']).'" class="_delete text-danger">'._l('delete').'</a>';
            }

            $expense .= '</div>';

            $row[] = $expense;

            $row[] = ucwords(str_replace('-',' ',$aRow['operation']));
            $row[] = _d($aRow['date']);
            $row[] = '<a href="'.admin_url('clients/client/'.$aRow['clientid']).'">'.$aRow['company'].'</a>';
            $row[] = '#REF0'.$aRow[db_prefix().'cf_expenses.id'];

            $row[] = $aRow['operation']=='cash-in' ? app_format_money($aRow['amount'],$aRow['currency_name']) : 0;
            $row[] = $aRow['operation']=='cash-out' ? app_format_money($aRow['amount'],$aRow['currency_name']) : 0;
            $row[] = app_format_money($aRow['balance'],$aRow['currency_name']);

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
            return db_prefix() . 'cf_expenses.category IN (SELECT id FROM ' . db_prefix() . 'expenses_categories WHERE id ' . $sqlOperator['operator'] . ' (' . implode(', ', $value) . '))';
        }),
        App_table_filter::new('clients', 'MultiSelectRule')->label(_l('client'))
        ->options(function ($ci) {
            return collect($ci->clients_model->get())->map(fn ($client) => [
                'value' => $client['userid'],
                'label' => $client['company'],
            ]);
        })
        ->raw(function ($value, $operator, $sqlOperator) {
            return db_prefix() . 'cf_expenses.clientid IN (SELECT userid FROM ' . db_prefix() . 'clients WHERE userid ' . $sqlOperator['operator'] . ' (' . implode(', ', $value) . '))';
        }),
        App_table_filter::new('operation', 'MultiSelectRule')->label(_l('operation'))
        ->options(function ($ci) {
            return collect([['cash-in'], ['cash-out']])->map(fn ($cash) => [
                'value' => $cash,
                'label' => $cash,
            ]);
        })
        ->raw(function ($value, $operator, $sqlOperator) {

            $values = "'" . implode("','", $value) . "'";

            return db_prefix() . "cf_expenses.operation IN ($values)";
        }),
        App_table_filter::new('date', 'DateRule')->label(_l('date'))
    ]);