<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Classic DataTables feed for cash-flow expenses.
 * Kept as the production path because App_table output was HTTP 500 on tenants.
 */
if (!isset($this->ci->cash_flow_model)) {
    $this->ci->load->model('cash_flow_model');
}

$categories = [];
try {
    $categories = $this->ci->cash_flow_model->get_category();
    if (!is_array($categories)) {
        $categories = [];
    }
} catch (Throwable $e) {
    $categories = [];
}

$_categories = [];
$_operations = [
    ['id' => 'cash-in', 'name' => 'cashin'],
    ['id' => 'cash-out', 'name' => 'cashout'],
];

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

$join = [
    'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'cf_expenses.clientid',
    'LEFT JOIN ' . db_prefix() . 'expenses_categories ON ' . db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'cf_expenses.category',
    'LEFT JOIN ' . db_prefix() . 'projects ON ' . db_prefix() . 'projects.id = ' . db_prefix() . 'cf_expenses.project_id',
    'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'cf_expenses.currency',
];

$where  = [];
$filter = [];

foreach ($categories as $c) {
    if (empty($c['id'])) {
        continue;
    }
    if ($this->ci->input->post('expenses_by_category_' . $c['id'])) {
        $_categories[] = (int) $c['id'];
    }
}
if (count($_categories) > 0) {
    $filter[] = 'AND category IN (' . implode(', ', $_categories) . ')';
}

$_months = [];
for ($m = 1; $m <= 12; $m++) {
    if ($this->ci->input->post('expenses_by_month_' . $m)) {
        $_months[] = $m;
    }
}
if (count($_months) > 0) {
    $filter[] = 'AND MONTH(date) IN (' . implode(', ', $_months) . ')';
}

$years = [];
try {
    $years = $this->ci->cash_flow_model->get_expenses_years();
    if (!is_array($years)) {
        $years = [];
    }
} catch (Throwable $e) {
    $years = [];
}

$_years = [];
foreach ($years as $year) {
    if (empty($year['year'])) {
        continue;
    }
    if ($this->ci->input->post('year_' . $year['year'])) {
        $_years[] = (int) $year['year'];
    }
}
if (count($_years) > 0) {
    $filter[] = 'AND YEAR(date) IN (' . implode(', ', $_years) . ')';
}

foreach ($_operations as $operation) {
    if ($this->ci->input->post('expenses_by_operation_' . $operation['name'])) {
        $filter[] = 'AND operation = "' . $operation['id'] . '"';
    }
}

if (count($filter) > 0) {
    $where[] = 'AND (' . prepare_dt_filter($filter) . ')';
}

$businessId = '';
if (!empty($data['buisness_id'])) {
    $businessId = $data['buisness_id'];
} elseif (!empty($buisness_id)) {
    $businessId = $buisness_id;
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

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'cf_expenses';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    db_prefix() . 'currencies.name as currency_name',
    db_prefix() . 'cf_expenses.clientid',
    'buisness_id',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row    = [];
    $rowId  = isset($aRow['id']) ? $aRow['id'] : '';
    $bizId  = !empty($aRow['buisness_id']) ? $aRow['buisness_id'] : $businessId;
    $op     = isset($aRow['operation']) ? $aRow['operation'] : '';
    $amount = isset($aRow['amount']) ? $aRow['amount'] : 0;
    $balance = isset($aRow['balance']) ? $aRow['balance'] : 0;
    $currency = !empty($aRow['currency_name']) ? $aRow['currency_name'] : '';
    $company = isset($aRow['company']) ? $aRow['company'] : '';
    $clientId = isset($aRow['clientid']) ? $aRow['clientid'] : '';
    $expenseName = isset($aRow['expense_name']) ? $aRow['expense_name'] : '';

    $row[] = $rowId;

    $categoryOutput = '<a href="' . admin_url('cash_flow/list_cf_expenses/' . $bizId . '/' . $rowId) . '" onclick="init_cf_expense(' . (int) $rowId . ');return false;">' . $expenseName . '</a>';
    $categoryOutput .= '<div class="row-options">';
    $categoryOutput .= '<a href="' . admin_url('cash_flow/list_cf_expenses/' . $bizId . '/' . $rowId) . '" onclick="init_cf_expense(' . (int) $rowId . ', ' . (int) $bizId . ');return false;">' . _l('view') . '</a>';
    if (staff_can('edit', 'cash_flow')) {
        $categoryOutput .= ' | <a href="' . admin_url('cash_flow/expense/' . $bizId . '/' . $rowId) . '">' . _l('edit') . '</a>';
    }
    if (staff_can('delete', 'cash_flow')) {
        $categoryOutput .= ' | <a href="' . admin_url('cash_flow/delete/' . $bizId . '/' . $rowId) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    }
    $categoryOutput .= '</div>';
    $row[] = $categoryOutput;

    $row[] = ucwords(str_replace('-', ' ', $op));
    $row[] = !empty($aRow['date']) ? _d($aRow['date']) : '';
    $row[] = $clientId
        ? '<a href="' . admin_url('clients/client/' . $clientId) . '">' . $company . '</a>'
        : $company;
    $row[] = '#REF0' . $rowId;
    $row[] = $op === 'cash-in' ? app_format_money($amount, $currency) : 0;
    $row[] = $op === 'cash-out' ? app_format_money($amount, $currency) : 0;
    $row[] = app_format_money($balance, $currency);

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
