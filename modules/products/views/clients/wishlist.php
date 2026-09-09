<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s section-heading section-products">
    <div class="panel-body">
        <h4 class="no-margin"><?php echo _l('wishlist'); ?></h4>
    </div>
</div>
<div class="panel_s">
    <div class="panel-body">
        <?php if (empty($items)) { ?>
        <p class="text-muted"><?php echo _l('wishlist'); ?> <?php echo _l('no_products'); ?></p>
        <a href="<?php echo site_url('products/client'); ?>" class="btn btn-info"><?php echo _l('products'); ?></a>
        <?php } else { ?>
        <p class="mbot15">
            <button type="button" class="btn btn-success btn-add-all-wishlist"><i class="fa fa-shopping-cart"></i> <?php echo _l('product_wishlist_add_all'); ?></button>
        </p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo _l('product_name'); ?></th>
                        <th><?php echo _l('invoice_item_add_edit_rate_currency'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) {
                        $img = !empty($item['product_image']) ? products_get_product_image_url($item['product_image']) : products_get_no_image_url();
                        $product_url = !empty($item['slug']) ? site_url('products/client/product/' . $item['slug']) : site_url('products/client');
                    ?>
                    <tr data-product-id="<?php echo (int) $item['product_id']; ?>" data-variation-id="<?php echo htmlspecialchars($item['product_variation_id'] ?? ''); ?>">
                        <td>
                            <a href="<?php echo htmlspecialchars($product_url); ?>">
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="" style="max-width:50px; margin-right:10px;">
                            </a>
                            <a href="<?php echo htmlspecialchars($product_url); ?>"><?php echo htmlspecialchars($item['product_name']); ?></a>
                            <?php if (!empty($item['variation_display'])) { ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($item['variation_display']); ?></small>
                            <?php } ?>
                        </td>
                        <td><?php echo app_format_money($item['rate'], $base_currency->name); ?></td>
                        <td>
                            <button type="button" class="btn btn-success btn-sm add-wishlist-to-cart" data-product-id="<?php echo (int) $item['product_id']; ?>" data-variation-id="<?php echo htmlspecialchars($item['product_variation_id'] ?? ''); ?>"><?php echo _l('add_to_cart'); ?></button>
                            <button type="button" class="btn btn-danger btn-sm remove-wishlist"><?php echo _l('wishlist_remove'); ?></button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
</div>
<script>
$(function(){
    $('.btn-add-all-wishlist').on('click', function(){
        var items = [];
        $('tr[data-product-id]').each(function(){
            items.push({ product_id: $(this).data('product-id'), product_variation_id: $(this).data('variation-id') || '' });
        });
        if (items.length === 0) return;
        var idx = 0;
        function addNext(){
            if (idx >= items.length) {
                window.location.href = site_url + 'products/client/place_order';
                return;
            }
            var it = items[idx++];
            $.post(site_url+'products/client/add_cart', { product_id: it.product_id, product_variation_id: it.product_variation_id, quantity: 1 }, addNext);
        }
        addNext();
    });
    $('.add-wishlist-to-cart').on('click', function(){
        var pid = $(this).data('product-id');
        var vid = $(this).data('variation-id') || '';
        $.post(site_url+'products/client/add_cart', {product_id: pid, product_variation_id: vid, quantity: 1}, function(){
            window.location.href = site_url + 'products/client/place_order';
        });
    });
    $('.remove-wishlist').on('click', function(){
        var row = $(this).closest('tr');
        var pid = row.data('product-id');
        var vid = row.data('variation-id') || '';
        $.post(site_url+'products/client/remove_wishlist', {product_id: pid, product_variation_id: vid}, function(){
            row.fadeOut(function(){ $(this).remove(); });
        });
    });
});
</script>
