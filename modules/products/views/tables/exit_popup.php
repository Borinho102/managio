<?php

defined('BASEPATH') or exit('No direct script access allowed');
$aColumns = ['name', 'title', 'trigger_type', 'target_pages', 'active', 'impressions', 'clicks'];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'product_exit_popups';
$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], ['id', 'sort_order']);
$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    $name = '<a href="' . admin_url('products/exit_popups/edit/' . $aRow['id']) . '">' . htmlspecialchars($aRow['name']) . '</a>';
    $name .= '<div class="row-options">';
    $name .= ' <a href="#" class="exit-popup-preview" data-id="' . (int) $aRow['id'] . '">' . _l('preview') . '</a>';
    $name .= ' | <a href="' . admin_url('products/exit_popups/edit/' . $aRow['id']) . '">' . _l('edit') . '</a>';
    $name .= ' | <a href="' . admin_url('products/exit_popups/delete/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    $name .= '</div>';
    $row[] = $name;
    $row[] = htmlspecialchars($aRow['title']);
    $row[] = _l('exit_popup_trigger_' . $aRow['trigger_type']);
    $row[] = _l('exit_popup_target_' . $aRow['target_pages']);
    $row[] = $aRow['active'] ? '<span class="label label-success">' . _l('active') . '</span>' : '<span class="label label-default">' . _l('inactive') . '</span>';
    $row[] = (int) $aRow['impressions'];
    $row[] = (int) $aRow['clicks'];
    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
