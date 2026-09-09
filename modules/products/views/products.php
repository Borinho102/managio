<?php

defined('BASEPATH') or exit('No direct script access allowed');
$aColumns = [
    'product_name',
    'product_description',
    'p_category_name',
    'rate',
    'quantity_number',
    'product_image',
    'taxes',
];
$sIndexColumn = 'id';
$sTable       = db_prefix().'product_master';
$filter       = [];
$where        = [];
$statusIds    = [];
$join         = [
    'LEFT JOIN '.db_prefix().'product_categories ON '.db_prefix().'product_categories.p_category_id='.db_prefix().'product_master.product_category_id',
];
$extra_product_columns = ['id', 'is_digital', 'is_variation'];
$CI_products_table     = &get_instance();
if ($CI_products_table->db->field_exists('product_type', db_prefix().'product_master')) {
    $extra_product_columns[] = 'product_type';
}
$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $extra_product_columns);
$output  = $result['output'];
$rResult = $result['rResult'];
$CI      = &get_instance();

$CI->load->model(['currencies_model']);
$base_currency = $CI->currencies_model->get_base_currency();
\modules\products\core\Apiinit::ease_of_mind('products');
\modules\products\core\Apiinit::the_da_vinci_code('products');

foreach ($rResult as $aRow) {
    $row        = [];
    $outputName = '<a href="#">'.$aRow['product_name'].'</a>';
    $outputName .= '<div class="row-options">';
    if (has_permission('products', '', 'delete')) {
        $outputName .= ' <a href="'.admin_url('products/edit/'.$aRow['id']).'" class="_edit">'._l('edit').'</a>';
        $outputName .= '| <a href="'.admin_url('products/delete/'.$aRow['id']).'" class="text-danger _delete">'._l('delete').'</a>';
    }
    // Ready-made link for the merchant's own sales page: adds the product to the
    // cart and sends the visitor straight to checkout.
    $buy_link = site_url('products/client/buy/'.$aRow['id']);
    $outputName .= '| <a href="'.$buy_link.'" class="product-buy-link" data-buy-link="'.$buy_link.'" target="_blank" title="'.htmlspecialchars(_l('product_buy_link_hint')).'">'._l('product_buy_link').'</a>';
    $outputName .= '</div>';
    $row[]              = $outputName;
    $imgUrl = !empty($aRow['product_image']) ? products_get_product_image_url($aRow['product_image']) : products_get_no_image_url();
    $row[]              = "<img src='".$imgUrl."' class='img-thumbnail img-responsive zoom' onerror=\"this.src='".products_get_no_image_url()."'\">";
    $row[]              = get_product_variation_values($aRow['id']);
    $row[]              = $aRow['product_description'];
    $row[]              = $aRow['p_category_name'];
    $row[]              = $aRow['is_variation'] ?  get_product_variation_price($aRow['id']) : app_format_money($aRow['rate'], $base_currency->name);
    // Services and digital products are unlimited, so a stock number is noise.
    $row[]              = products_tracks_stock($aRow) ? $aRow['quantity_number'] : _l('digital_product');
    $row[]              = (!empty($aRow['taxes'])) ? print_taxes($aRow['taxes']) : '';
    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}

function print_taxes($taxes): string
{
    $unserialize_taxes = unserialize($taxes);
    if (is_array($unserialize_taxes) && !empty($unserialize_taxes)) {
        return implode(' ', $unserialize_taxes);
    }
}
