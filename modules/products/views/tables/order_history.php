<?php

defined('BASEPATH') or exit('No direct script access allowed');
$aColumns = [
    'invoice_id',
    get_sql_select_client_company(),
    'order_date',
    db_prefix().'order_master.total as total',
    db_prefix().'order_master.status as status',
];
$join = [
    'LEFT JOIN '.db_prefix().'clients ON '.db_prefix().'clients.userid = '.db_prefix().'order_master.clientid',
    'LEFT JOIN '.db_prefix().'invoices ON '.db_prefix().'invoices.id = '.db_prefix().'order_master.invoice_id',
];
$sIndexColumn = 'id';
$sTable       = db_prefix().'order_master';
$CI           = &get_instance();

// Orders placed in a foreign currency must be shown in that currency, not the
// base one. Guarded so the table still renders before the migration has run.
$has_order_currency = $CI->db->field_exists('currency', db_prefix().'order_master');
$extra_columns      = [
    db_prefix().'order_master.id',
    db_prefix().'order_master.clientid',
    'deleted_customer_name',
];
if ($has_order_currency) {
    $extra_columns[] = db_prefix().'order_master.currency as order_currency';
}

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, [], $extra_columns);
$output  = $result['output'];
$rResult = $result['rResult'];
$CI->load->model(['currencies_model']);
$base_currency = $CI->currencies_model->get_base_currency();
foreach ($rResult as $aRow) {
    $row   = [];
    $row[] = '<a href="'.admin_url('invoices/list_invoices/'.$aRow['invoice_id']).'" target="_blank">'.format_invoice_number($aRow['invoice_id']).'</a>';
    if (empty($aRow['deleted_customer_name'])) {
        $row[] = '<a href="'.admin_url('clients/client/'.$aRow['clientid']).'">'.$aRow['company'].'</a>';
    } else {
        $row[] = $aRow['deleted_customer_name'];
    }
    $row[]              = _d($aRow['order_date']);
    $order_currency     = !empty($aRow['order_currency']) ? get_currency($aRow['order_currency']) : null;
    $row[]              = app_format_money($aRow['total'], $order_currency ? $order_currency->name : $base_currency->name);
    $row[]              = format_invoice_status($aRow['status']);
    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
