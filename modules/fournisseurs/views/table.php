<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'company',
    'contact_fullname',
    'phonenumber',
    'email',
    'category',
    'active',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'fournisseurs';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], ['id']);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $company = '<a href="' . admin_url('fournisseurs/fournisseur/' . $aRow['id']) . '" class="tw-font-medium">' . e($aRow['company']) . '</a>';
    $company .= '<div class="row-options">';
    $company .= '<a href="' . admin_url('fournisseurs/fournisseur/' . $aRow['id']) . '">' . _l('view') . '</a>';
    if (staff_can('delete', 'fournisseurs')) {
        $company .= ' | <a href="' . admin_url('fournisseurs/delete/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    }
    $company .= '</div>';
    $row[] = $company;

    $row[] = e($aRow['contact_fullname'] ?: '-');
    $row[] = e($aRow['phonenumber'] ?: '-');

    if (!empty($aRow['email'])) {
        $row[] = '<a href="mailto:' . e($aRow['email']) . '">' . e($aRow['email']) . '</a>';
    } else {
        $row[] = '-';
    }

    $row[] = e(format_fournisseur_category($aRow['category'] ?? ''));

    $checked = $aRow['active'] == 1 ? 'checked' : '';
    $disabled = staff_cant('edit', 'fournisseurs') ? 'disabled' : '';
    $row[] = '<div class="onoffswitch">
        <input type="checkbox" ' . $disabled . ' data-switch-url="' . admin_url() . 'fournisseurs/change_status" name="onoffswitch" class="onoffswitch-checkbox" id="fr_' . $aRow['id'] . '" data-id="' . $aRow['id'] . '" ' . $checked . '>
        <label class="onoffswitch-label" for="fr_' . $aRow['id'] . '"></label>
    </div>';

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
