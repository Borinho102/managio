<?php
defined('BASEPATH') or exit('No direct script access allowed');
$aColumns = ['id', 'product_id', 'client_id', 'rating', 'review_text', 'approved', 'datecreated'];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'product_reviews';
$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], ['id']);
$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    $product = $this->db->where('id', $aRow['product_id'])->get(db_prefix() . 'product_master')->row();
    $row[] = $product ? $product->product_name : '-';
    $client = $this->db->where('userid', $aRow['client_id'])->get(db_prefix() . 'clients')->row();
    $row[] = $client ? $client->company : '-';
    $stars = str_repeat('★', (int) $aRow['rating']) . str_repeat('☆', 5 - (int) $aRow['rating']);
    $row[] = $stars;
    $row[] = strlen($aRow['review_text']) > 80 ? substr($aRow['review_text'], 0, 80) . '...' : $aRow['review_text'];
    $row[] = $aRow['approved'] ? '<span class="label label-success">' . _l('product_review_approved') . '</span>' : '<span class="label label-default">' . _l('product_review_pending') . '</span>';
    $row[] = _d($aRow['datecreated']);
    $options = '';
    if (!$aRow['approved']) {
        $options .= '<a href="' . admin_url('products/product_reviews/approve/' . $aRow['id']) . '" class="btn btn-success btn-icon">' . _l('approve') . '</a> ';
    } else {
        $options .= '<a href="' . admin_url('products/product_reviews/unapprove/' . $aRow['id']) . '" class="btn btn-default btn-icon">' . _l('unapprove') . '</a> ';
    }
    $options .= '<a href="' . admin_url('products/product_reviews/delete/' . $aRow['id']) . '" class="btn btn-danger btn-icon _delete">' . _l('delete') . '</a>';
    $row[] = $options;
    $output['aaData'][] = $row;
}
