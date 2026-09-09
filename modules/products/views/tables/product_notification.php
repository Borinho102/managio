<?php

defined('BASEPATH') or exit('No direct script access allowed');
$aColumns = ['name', 'channel', 'trigger_event', 'recipient', 'active'];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'product_notification_templates';
$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], ['id']);
$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    $name = '<a href="' . admin_url('products/product_notifications/edit/' . $aRow['id']) . '">' . htmlspecialchars($aRow['name']) . '</a>';
    $name .= '<div class="row-options">';
    $name .= ' <a href="' . admin_url('products/product_notifications/edit/' . $aRow['id']) . '">' . _l('edit') . '</a>';
    $name .= ' | <a href="' . admin_url('products/product_notifications/delete/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    $name .= '</div>';
    $row[] = $name;
    $row[] = _l('product_notification_channel_' . $aRow['channel']);
    $row[] = _l('product_notification_trigger_' . $aRow['trigger_event']);
    $row[] = _l('product_notification_recipient_' . $aRow['recipient']);
    $row[] = $aRow['active'] ? '<span class="label label-success">' . _l('active') . '</span>' : '<span class="label label-default">' . _l('inactive') . '</span>';
    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
