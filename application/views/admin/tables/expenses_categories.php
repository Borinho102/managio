<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'id',
    'name',
    'description',
];
$sIndexColumn = 'id';
$sTable       = db_prefix() . 'expenses_categories';

$additionalSelect = [];
if ($this->ci->db->field_exists('budget_category_id', db_prefix() . 'expenses_categories')) {
    $additionalSelect[] = 'budget_category_id';
}

$result       = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], $additionalSelect);
$output       = $result['output'];
$rResult      = $result['rResult'];

$budgetNames = [];
if ($this->ci->db->table_exists(db_prefix() . 'acc_project_budget_categories')) {
    foreach ($this->ci->db->get(db_prefix() . 'acc_project_budget_categories')->result_array() as $bc) {
        $budgetNames[(int) $bc['id']] = $bc['name'];
    }
}

$mappings = [];
if ($this->ci->db->table_exists(db_prefix() . 'acc_expense_category_mappings')) {
    foreach ($this->ci->db->get(db_prefix() . 'acc_expense_category_mappings')->result_array() as $map) {
        $mappings[(int) $map['category_id']] = $map;
    }
}

foreach ($rResult as $aRow) {
    $row = [];
    $budgetCategoryId = isset($aRow['budget_category_id']) ? (int) $aRow['budget_category_id'] : 0;
    $map = $mappings[(int) $aRow['id']] ?? null;
    $paymentAccount = $map['payment_account'] ?? '';
    $depositTo = $map['deposit_to'] ?? '';

    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];
        if ($aColumns[$i] == 'name' || $aColumns[$i] == 'id') {
            $_data = '<a href="#" class="tw-font-medium" onclick="edit_category(this,' . $aRow['id'] . '); return false;"'
                . ' data-name="' . e($aRow['name']) . '"'
                . ' data-description="' . e(clear_textarea_breaks($aRow['description'])) . '"'
                . ' data-budget-category-id="' . e((string) $budgetCategoryId) . '"'
                . ' data-payment-account="' . e((string) $paymentAccount) . '"'
                . ' data-deposit-to="' . e((string) $depositTo) . '">'
                . e($_data) . '</a>';
            if ($aColumns[$i] == 'name' && $budgetCategoryId > 0 && isset($budgetNames[$budgetCategoryId])) {
                $_data .= '<br><span class="text-muted tw-text-sm">' . e(_l('acc_budget_category') . ': ' . $budgetNames[$budgetCategoryId]) . '</span>';
            }
            if ($aColumns[$i] == 'name' && $map) {
                $_data .= '<br><span class="text-success tw-text-sm">' . e(_l('expense_category_accounting_mapped')) . '</span>';
            }
        } elseif ($aColumns[$i] == 'description') {
            $_data = process_text_content_for_display($_data);
        }
        $row[] = $_data;
    }

    $options = '<div class="tw-flex tw-items-center tw-space-x-2">';
    $options .= '<a href="#" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700" onclick="edit_category(this,' . $aRow['id'] . '); return false;"'
        . ' data-name="' . e($aRow['name']) . '"'
        . ' data-description="' . e(clear_textarea_breaks($aRow['description'])) . '"'
        . ' data-budget-category-id="' . e((string) $budgetCategoryId) . '"'
        . ' data-payment-account="' . e((string) $paymentAccount) . '"'
        . ' data-deposit-to="' . e((string) $depositTo) . '">
        <i class="fa-regular fa-pen-to-square fa-lg"></i>
    </a>';

    $options .= '<a href="' . admin_url('expenses/delete_category/' . $aRow['id']) . '"
    class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete">
        <i class="fa-regular fa-trash-can fa-lg"></i>
    </a>';
    $options .= '</div>';

    $row[]              = $options;
    $output['aaData'][] = $row;
}
