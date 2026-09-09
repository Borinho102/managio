<?php defined('BASEPATH') or exit('No direct script access allowed');
$product = isset($product) ? $product : null;
if (!$product) {
    return;
}
$product_obj = is_array($product) ? (object) $product : $product;
$cart_data = $product_obj->cart_data ?? [];
$variations = $product_obj->variations ?? [];
$is_variation = !empty($product_obj->is_variation);
$tracks_stock = products_tracks_stock($product_obj);
$max_attr = $tracks_stock ? 'max="' . (int) ($product_obj->quantity_number ?? 0) . '"' : '';
$display_rate = isset($product_obj->sale_price) && $product_obj->sale_price ? $product_obj->sale_price : ($product_obj->rate ?? 0);
?>
<div class="panel_s section-heading section-products">
    <div class="panel-body">
        <a href="<?php echo site_url('products/client'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> <?php echo _l('products'); ?></a>
        <a href="<?php echo site_url('products/client/place_order'); ?>" class="btn btn-success btn-sm pull-right"><i class="fa fa-shopping-cart"></i> <?php echo _l('view_cart_and_checkout'); ?></a>
    </div>
</div>
<div class="panel_s">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-4 col-sm-5">
                <img src="<?php echo htmlspecialchars($product_image_url); ?>" alt="<?php echo htmlspecialchars($product_obj->product_name); ?>" class="img-responsive img-thumbnail" onerror="this.src='<?php echo htmlspecialchars($no_image_url); ?>'">
            </div>
            <div class="col-md-8 col-sm-7">
                <h2 class="no-margin"><?php echo htmlspecialchars($product_obj->product_name); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($product_obj->p_category_name ?? ''); ?></p>
                <div class="product-description"><?php echo nl2br(htmlspecialchars($product_obj->product_description)); ?></div>
                <hr>
                <div class="products-pricing">
                    <h3>
                        <span class="product-price"><?php echo products_price_display($display_rate, $product_obj, $base_currency); ?></span>
                    </h3>
                </div>
                <?php if ($is_variation && !empty($variations)) { ?>
                <div class="row variations product-variation-select">
                    <div class="col-md-6">
                        <label><?php echo _l('product_variation_table_heading'); ?></label>
                        <select class="form-control selectpicker variation_id">
                            <option value=""><?php echo _l('dropdown_non_selected_tex'); ?></option>
                            <?php
                            $seen = [];
                            foreach ($variations as $v) {
                                $vid = $v->variation_id ?? $v['variation_id'];
                                if (!in_array($vid, $seen)) {
                                    $seen[] = $vid;
                                    $vname = $v->variation_name ?? $v['variation_name'];
                                    echo '<option value="' . (int) $vid . '">' . htmlspecialchars($vname) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label><?php echo _l('product_variation_table_value'); ?></label>
                        <select class="form-control selectpicker variation_value_id" title="<?php echo htmlspecialchars(_l('product_variation_choose_option')); ?>"><option value=""><?php echo htmlspecialchars(_l('product_variation_choose_option')); ?></option></select>
                    </div>
                </div>
                <?php } ?>
                <?php $product_sold_count_val = (int) ($product_sold_count ?? 0);
                $is_out_of_stock = $tracks_stock && (int) ($product_obj->quantity_number ?? 0) < 1;
                ?>
                <!-- Primary action: Quantity + Add to Cart -->
                <div class="row input_data product-detail-primary-row mtop15">
                    <div class="col-md-4 products-pricing">
                        <label><?php echo _l('qty'); ?></label>
                        <input type="number" name="quantity" min="1" <?php echo $max_attr; ?> value="<?php echo !empty($cart_data['quantity']) ? (int) $cart_data['quantity'] : 1; ?>" class="form-control">
                        <input type="hidden" name="product_id" value="<?php echo (int) $product_obj->id; ?>">
                        <input type="hidden" name="product_variation_id" value="">
                        <input type="hidden" class="variation_quantity" <?php echo $max_attr; ?>>
                    </div>
                    <div class="col-md-8 products-pricing product-detail-action-col">
                        <?php if ($is_out_of_stock) { ?>
                        <div class="product-detail-action-align">
                            <button class="btn btn-danger btn-lg" disabled><?php echo _l('out_of_stock'); ?></button>
                        </div>
                        <?php } else { ?>
                        <div class="product-detail-action-align">
                            <button class="btn btn-success btn-lg add_cart">
                                <?php echo (!empty($cart_data) && empty($cart_data['product_variation_id'])) ? _l('update_cart') : _l('add_to_cart'); ?>
                            </button>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <!-- Secondary: Price drop (in-stock) or Back in stock (out-of-stock) - full-width -->
                <?php if ($is_out_of_stock && get_option('product_back_in_stock_enabled') == '1') { ?>
                <div class="row product-detail-secondary-row mtop15">
                    <div class="col-md-12">
                        <div class="product-detail-secondary-block">
                            <label><?php echo _l('product_notify_back_in_stock'); ?></label>
                            <div class="input-group product-detail-input-group">
                                <input type="email" class="form-control back-in-stock-email" placeholder="<?php echo htmlspecialchars(_l('product_notify_email_placeholder')); ?>" value="<?php echo is_client_logged_in() && function_exists('get_contact') ? htmlspecialchars(get_contact()->email ?? '') : ''; ?>">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-info back-in-stock-submit" data-product-id="<?php echo (int) $product_obj->id; ?>"><?php echo _l('product_notify_me'); ?></button>
                                </span>
                            </div>
                            <p class="text-muted small back-in-stock-msg mtop5"></p>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <?php if (!$is_out_of_stock && get_option('product_price_drop_enabled') == '1') { ?>
                <div class="row product-detail-secondary-row mtop15">
                    <div class="col-md-12">
                        <div class="product-detail-secondary-block">
                            <label><?php echo _l('product_notify_price_drop'); ?></label>
                            <div class="input-group product-detail-input-group">
                                <input type="email" class="form-control price-drop-email" placeholder="<?php echo htmlspecialchars(_l('product_notify_email_placeholder')); ?>" value="<?php echo is_client_logged_in() && function_exists('get_contact') ? htmlspecialchars(get_contact()->email ?? '') : ''; ?>">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default price-drop-submit" data-product-id="<?php echo (int) $product_obj->id; ?>"><?php echo _l('product_notify_me'); ?></button>
                                </span>
                            </div>
                            <p class="text-muted small price-drop-msg mtop5"></p>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <!-- Social proof: full-width -->
                <?php if (get_option('product_social_proof_enabled') == '1' && !$is_out_of_stock) { ?>
                <div class="row product-detail-secondary-row product-social-proof-row mtop15">
                    <div class="col-md-12">
                        <div class="product-detail-secondary-block product-social-proof-block">
                            <?php if (!empty($product_recent_purchases)) { ?>
                            <p class="text-muted small product-recent-purchase mbot5">
                                <i class="fa fa-check-circle text-success"></i> <?php echo htmlspecialchars($product_recent_purchases[0]['display_text']); ?>
                            </p>
                            <?php } ?>
                            <p class="text-muted small product-social-proof mbot0">
                                <i class="fa fa-shopping-cart"></i> <?php echo sprintf(_l('product_social_proof_sold'), (int) $product_sold_count_val); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php if (get_option('product_reviews_enabled') == '1') { ?>
<div class="panel_s">
    <div class="panel-body">
        <h4><?php echo _l('product_reviews'); ?></h4>
        <?php
        $stats = $review_stats ?? ['avg' => 0, 'count' => 0];
        if ($stats['count'] > 0) {
            $avg = $stats['avg'];
            echo '<div class="product-review-summary">';
            echo '<span class="stars">' . str_repeat('★', (int) $avg) . str_repeat('☆', 5 - (int) $avg) . '</span> ';
            echo '<span>' . $avg . '</span> (' . $stats['count'] . ' ' . _l('product_reviews') . ')</div>';
        }
        ?>
        <?php if (!empty($can_review)) { ?>
        <div class="product-review-form mtop15">
            <form class="submit-review-form">
                <input type="hidden" name="product_id" value="<?php echo (int) $product_obj->id; ?>">
                <label><?php echo _l('product_review_rating'); ?></label>
                <select name="rating" class="form-control" style="width:80px;">
                    <?php for ($i = 1; $i <= 5; $i++) { echo '<option value="' . $i . '">' . $i . ' ★</option>'; } ?>
                </select>
                <label class="mtop10"><?php echo _l('product_review_text'); ?></label>
                <textarea name="review_text" class="form-control" rows="3"></textarea>
                <button type="submit" class="btn btn-success mtop10"><?php echo _l('product_review_submit'); ?></button>
            </form>
        </div>
        <?php } elseif (is_client_logged_in()) { ?>
        <p class="text-muted small mtop10"><i class="fa fa-info-circle"></i> <?php echo _l('product_reviews_purchase_to_rate'); ?></p>
        <?php } ?>
        <div class="product-reviews-list mtop15">
            <?php
            $reviews_list = $reviews ?? [];
            if (empty($reviews_list)) {
                echo '<p class="text-muted"><i class="fa fa-star-o"></i> ' . _l('product_reviews_empty') . '</p>';
            } else {
                foreach ($reviews_list as $r) {
                    $r = is_object($r) ? $r : (object) $r;
                    echo '<div class="review-item mtop10"><div class="stars">' . str_repeat('★', (int) $r->rating) . str_repeat('☆', 5 - (int) $r->rating) . '</div>';
                    echo '<div class="review-text">' . nl2br(htmlspecialchars($r->review_text ?? '')) . '</div>';
                    echo '<div class="text-muted small">' . _d($r->datecreated) . '</div></div><hr>';
                }
            }
            ?>
        </div>
    </div>
</div>
<?php } ?>
<?php if (get_option('product_recommendations_enabled') == '1' && !empty($recommended_products)) { ?>
<div class="panel_s mtop15">
    <div class="panel-body">
        <h4><?php echo _l('product_recommendations'); ?></h4>
        <div class="row mtop15">
            <?php
            $no_img = products_get_no_image_url();
            foreach ($recommended_products as $rp) {
                $rp = is_object($rp) ? $rp : (object) $rp;
                $img = !empty($rp->product_image) ? products_get_product_image_url($rp->product_image) : $no_img;
                $url = !empty($rp->slug) ? site_url('products/client/product/' . $rp->slug) : site_url('products/client');
                $rate = isset($rp->sale_price) && $rp->sale_price ? $rp->sale_price : $rp->rate;
            ?>
            <div class="col-md-2 col-sm-4 col-xs-6 mtop10">
                <a href="<?php echo htmlspecialchars($url); ?>">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="" style="max-width:100%;height:120px;object-fit:contain;" onerror="this.src='<?php echo $no_img; ?>'">
                </a>
                <div class="text-center mtop5">
                    <a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($rp->product_name); ?></a>
                    <br>
                    <span class="text-success"><?php echo app_format_money($rate, $base_currency->name); ?></span>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php } ?>
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
var productVariations = <?php echo json_encode($variations); ?>;
var cartItems = <?php echo json_encode($cart_data ? [$cart_data] : []); ?>;
// Mirrors app_format_money on the client so a variation price picked in the
// browser is formatted exactly like the server-rendered one.
var productPriceFormat = <?php echo json_encode([
    'name'      => $base_currency->name ?? '',
    'symbol'    => $base_currency->symbol ?? '',
    'placement' => $base_currency->placement ?? 'before',
    'decimal'   => $base_currency->decimal_separator ?? '.',
    'thousand'  => $base_currency->thousand_separator ?? ',',
    'suffix'    => products_recurring_suffix($product_obj) . (products_has_taxes($product_obj, $base_currency) ? ' ' . _l('product_plus_tax') : ''),
]); ?>;
function productsFormatMoney(amount) {
    var n = parseFloat(amount);
    if (isNaN(n)) { return ''; }
    var parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, productPriceFormat.thousand);
    var num = parts.join(productPriceFormat.decimal);
    var money = productPriceFormat.placement === 'after'
        ? num + productPriceFormat.symbol
        : productPriceFormat.symbol + num;
    return (productPriceFormat.name ? productPriceFormat.name + ' ' : '') + money + productPriceFormat.suffix;
}
<?php if (get_option('product_remarketing_facebook_enabled') == '1' || get_option('product_remarketing_google_enabled') == '1') { ?>
var _productsRemarketingViewContent = {id: <?php echo (int) $product_obj->id; ?>, name: <?php echo json_encode($product_obj->product_name); ?>, value: <?php echo (float) (isset($product_obj->product_variation_id) ? $product_obj->variation_rate : $product_obj->rate); ?>};
<?php } ?>
</script>
<script type="text/javascript" src="<?php echo module_dir_url('products', 'assets/js/client_products.js'); ?>"></script>
<script type="text/javascript">
$(function(){
    var productId = <?php echo (int) $product_obj->id; ?>;
    var hasVariations = <?php echo $is_variation ? 'true' : 'false'; ?>;

    // Bootstrap-select renders its own markup over the real <select>. Replacing
    // the options underneath it leaves that markup stale, which is what made the
    // Variation Value field unclickable. Every change to the select has to be
    // followed by a refresh, exactly as the storefront grid already does.
    var refreshPicker = function ($el) {
        if (typeof $().selectpicker === 'function' && $el.data('selectpicker')) {
            $el.selectpicker('refresh');
        }
        return $el;
    };
    if (hasVariations && $('.variation_id').length) {
        var uniqueVids = [];
        productVariations.forEach(function(v){
            var vid = v.variation_id;
            if (vid && uniqueVids.indexOf(vid) === -1) uniqueVids.push(vid);
        });
        var selectVariationValues = function(){
            var varId = $('.variation_id').val();
            var opts = '<option value=""><?php echo addslashes(_l('product_variation_choose_option')); ?></option>';
            var minP = 0, maxP = 0;
            var valuesForVar = [];
            productVariations.forEach(function(v){
                var vid = v.variation_id;
                if (vid == varId) {
                    valuesForVar.push(v);
                    opts += '<option value="'+ (v.id) +'" data-quantity="'+ (v.quantity_number||0) +'" data-price="'+ (v.rate||0) +'">'+ (v.variation_value || '') +'</option>';
                    if (!minP || v.rate < minP) minP = v.rate;
                    if (v.rate > maxP) maxP = v.rate;
                }
            });
            refreshPicker($('.variation_value_id').html(opts));
            $('.product-price').text(minP != maxP ? productsFormatMoney(minP) + ' - ' + productsFormatMoney(maxP) : productsFormatMoney(minP));
            if (valuesForVar.length === 1) {
                refreshPicker($('.variation_value_id').val(valuesForVar[0].id));
                $('input[name="product_variation_id"]').val(valuesForVar[0].id);
                $('.variation_quantity').attr('max', valuesForVar[0].quantity_number || '');
                $('.product-price').text(productsFormatMoney(valuesForVar[0].rate));
            }
        };
        $('.variation_id').on('change', selectVariationValues);
        $('.variation_value_id').on('change', function(){
            var sel = $(this).find('option:selected');
            var pvid = sel.val();
            $('input[name="product_variation_id"]').val(pvid || '');
            $('.variation_quantity').attr('max', sel.data('quantity') || '');
            $('.product-price').text(productsFormatMoney(sel.data('price')));
        });
    }
    if (typeof appSelectPicker === 'function') appSelectPicker();
    if (hasVariations && $('.variation_id').length) {
        var uniqueVids = [];
        productVariations.forEach(function(v){
            var vid = v.variation_id;
            if (vid && uniqueVids.indexOf(vid) === -1) uniqueVids.push(vid);
        });
        if (uniqueVids.length === 1) {
            $('.variation_id').val(uniqueVids[0]);
            if (typeof $().selectpicker === 'function') $('.variation_id').selectpicker('refresh');
            $('.variation_id').trigger('change');
        }
    }
    if (typeof appSelectPicker === 'function') appSelectPicker();
    $('.add_cart').on('click', function(){
        var qty = $('input[name="quantity"]').val();
        var pvid = $('input[name="product_variation_id"]').val();
        if (hasVariations && !pvid) {
            if (typeof alert_float === 'function') alert_float('danger', '<?php echo addslashes(_l('product_please_choose_variation')); ?>');
            return;
        }
        var wasUpdate = $('.add_cart').text().toUpperCase().indexOf('<?php echo addslashes(strtoupper(_l('update_cart'))); ?>') !== -1;
        $.post(site_url+'products/client/add_cart', {
            quantity: qty,
            product_id: productId,
            product_variation_id: pvid || ''
        }, function(data){
            var msg = wasUpdate ? '<?php echo addslashes(_l('product_cart_updated_success')); ?>' : '<?php echo addslashes(_l('product_added_to_cart_success')); ?>';
            if (typeof alert_float === 'function') alert_float('success', msg);
            $('.add_cart').text('<?php echo addslashes(_l('update_cart')); ?>');
        });
    });
    $('.back-in-stock-submit').on('click', function(){
        var productId = $(this).data('product-id');
        var email = $('.back-in-stock-email').val();
        if (!email) {
            if (typeof alert_float === 'function') alert_float('danger', '<?php echo _l('product_notify_email_required'); ?>');
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(site_url+'products/client/subscribe_back_in_stock', { product_id: productId, email: email }, function(data){
            if (data && data.status === 'ok') {
                $('.back-in-stock-msg').text('<?php echo _l('product_notify_subscribed'); ?>').removeClass('text-danger').addClass('text-success');
            } else if (data && data.status === 'already') {
                $('.back-in-stock-msg').text('<?php echo _l('product_notify_already'); ?>').removeClass('text-danger').addClass('text-success');
            } else {
                $('.back-in-stock-msg').text(data && data.message ? data.message : '<?php echo _l('product_notify_error'); ?>').addClass('text-danger');
                $btn.prop('disabled', false);
            }
        }, 'json');
    });
    $('.price-drop-submit').on('click', function(){
        var productId = $(this).data('product-id');
        var email = $('.price-drop-email').val();
        if (!email) {
            if (typeof alert_float === 'function') alert_float('danger', '<?php echo _l('product_notify_email_required'); ?>');
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(site_url+'products/client/subscribe_price_drop', { product_id: productId, email: email }, function(data){
            if (data && (data.status === 'ok' || data.status === 'already')) {
                $('.price-drop-msg').text(data.status === 'ok' ? '<?php echo _l('product_notify_subscribed'); ?>' : '<?php echo _l('product_notify_already'); ?>').removeClass('text-danger').addClass('text-success');
            } else {
                $('.price-drop-msg').text(data && data.message ? data.message : '<?php echo _l('product_notify_error'); ?>').addClass('text-danger');
                $btn.prop('disabled', false);
            }
        }, 'json');
    });
    $('.submit-review-form').on('submit', function(e){
        e.preventDefault();
        var $f = $(this);
        $.post(site_url+'products/client/submit_review', $f.serialize(), function(data){
            if (data && data.success) {
                if (typeof alert_float === 'function') alert_float('success', data.message || '<?php echo _l('product_review_submitted'); ?>');
                location.reload();
            } else {
                if (typeof alert_float === 'function') alert_float('danger', data && data.message ? data.message : '<?php echo _l('product_review_error'); ?>');
            }
        }, 'json');
    });
});
</script>
