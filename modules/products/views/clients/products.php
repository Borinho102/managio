<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s section-heading section-products">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-4 col-sm-6 col-xs-6">
                <?php echo render_select('product_categories', $product_categories, ['p_category_id', 'p_category_name'], '', '', ['multiple'=>true, 'data-none-selected-text'=>_l('products_categories'), 'multiple data-actions-box'=>'true'], [], 'select_cat', '', false); ?>
            </div>
            <div class="col-md-8 col-sm-6 col-xs-6">
                <a href="<?php echo site_url('products/client/place_order'); ?>" class="btn btn-success pull-right"><i class="fa fa-shopping-cart"></i> <?php echo _l('view_cart_and_checkout'); ?></a>
                <?php
                  // Currency switcher, shown only when there is more than one
                  // currency to choose between.
                  $show_switcher = products_multicurrency_enabled()
                    && get_option('product_currency_switcher_enabled') != '0'
                    && !empty($selling_currencies) && count($selling_currencies) > 1;
                ?>
                <?php if ($show_switcher) { ?>
                <div class="btn-group pull-right mright10 products-currency-switcher">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa fa-money"></i>
                        <?php echo htmlspecialchars($active_currency->name ?? ''); ?>
                        <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach ($selling_currencies as $selling_currency) { ?>
                        <li<?php echo (!empty($active_currency) && $active_currency->id == $selling_currency->id) ? ' class="active"' : ''; ?>>
                            <a href="<?php echo site_url('products/client/set_currency/' . (int) $selling_currency->id); ?>">
                                <?php echo htmlspecialchars($selling_currency->name); ?>
                                <?php if (!empty($selling_currency->symbol)) { ?>
                                    <span class="text-muted">(<?php echo htmlspecialchars($selling_currency->symbol); ?>)</span>
                                <?php } ?>
                            </a>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<br>
<div class="products-grid-container">
    <div class="row">
        <div class="col-md-12 text-center no_product hidden">
            <br><br><img src="<?php echo module_dir_url('products', 'uploads').'/no-product.png'; ?>" class="img1 img-responsive">
        </div>
    </div>
    <div id="filter_html" class="row products-grid-row"></div>
</div>
<?php
	if (!is_client_logged_in()) {
		if (1 == get_option('nlu_hiddenprices_disabled')) {
			echo '<style>.products-pricing { display: none; }</style>';
		}
	}
	
	if (1 == get_option('b2bmode_disabled')) {
        echo '<style>.products-pricing { display: none; }</style>';
	}
?>
<script type="text/javascript">
// Currency formatting for the grid, so prices render as "CAD $7.00" not "7".
var PRODUCT_CURRENCY_FORMAT = <?php echo json_encode([
    'name'      => $active_currency->name ?? '',
    'symbol'    => $active_currency->symbol ?? '',
    'placement' => $active_currency->placement ?? 'before',
    'decimal'   => $active_currency->decimal_separator ?? '.',
    'thousand'  => $active_currency->thousand_separator ?? ',',
]); ?>;
var PRODUCT_DETAIL_ENABLED = <?php echo get_option('product_detail_pages_enabled') != '0' ? 'true' : 'false'; ?>;
var PRODUCT_WISHLIST_ENABLED = <?php echo (get_option('product_wishlist_enabled') != '0' && is_client_logged_in()) ? 'true' : 'false'; ?>;
var product_wishlist_label = <?php echo json_encode(_l('wishlist')); ?>;
var product_wishlist_added = <?php echo json_encode(_l('wishlist_added')); ?>;
var product_wishlist_removed = <?php echo json_encode(_l('wishlist_removed')); ?>;
var product_low_stock_label = <?php echo json_encode(_l('product_low_stock')); ?>;
var product_variation_table_heading = <?php echo json_encode(_l('product_variation_table_heading')); ?>;
var product_variation_table_value = <?php echo json_encode(_l('product_variation_table_value')); ?>;
var product_please_choose_variation = <?php echo json_encode(_l('product_please_choose_variation')); ?>;
var product_added_to_cart_label = <?php echo json_encode(_l('product_added_to_cart_success')); ?>;
var product_cart_updated_label = <?php echo json_encode(_l('product_cart_updated_success')); ?>;
var product_update_cart_label = <?php echo json_encode(_l('update_cart')); ?>;
</script>
<script type="text/javascript" src="<?php echo module_dir_url('products', 'assets/js/client_products.js'); ?>"></script>
