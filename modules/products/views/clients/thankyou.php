<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s">
    <div class="panel-body text-center">
        <p><?php echo _l('order_success'); ?></p>
        <?php if (!empty($upsell_products)) { ?>
        <p class="mtop15">
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#upsellModal"><?php echo _l('product_upsell_see_offers'); ?></button>
        </p>
        <?php } ?>
        <p><a href="<?php echo htmlspecialchars($redirect); ?>" class="btn btn-success"><?php echo _l('continue'); ?></a></p>
    </div>
</div>
<?php if (!empty($upsell_products)) { ?>
<div class="modal fade" id="upsellModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4><?php echo _l('product_upsell_title'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <?php
                    $no_img = products_get_no_image_url();
                    foreach ($upsell_products as $up) {
                        $up = is_object($up) ? $up : (object) $up;
                        $img = !empty($up->product_image) ? products_get_product_image_url($up->product_image) : $no_img;
                        $url = site_url('products/client/product/' . ($up->slug ?? ''));
                    ?>
                    <div class="col-md-6 mtop10">
                        <a href="<?php echo htmlspecialchars($url); ?>">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="" style="max-width:100px;height:80px;object-fit:contain;" onerror="this.src='<?php echo $no_img; ?>'">
                        </a>
                        <div>
                            <a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($up->product_name); ?></a>
                            <br><span class="text-success"><?php echo app_format_money($up->rate ?? 0, $base_currency->name ?? ''); ?></span>
                            <br><a href="<?php echo htmlspecialchars($url); ?>" class="btn btn-sm btn-success mtop5"><?php echo _l('add_to_cart'); ?></a>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<?php if (($remarketing['facebook'] || $remarketing['google_id']) && $order_id && $total > 0) { ?>
<script>
(function(){
    var fb = '<?php echo addslashes($remarketing['facebook'] ?? ''); ?>';
    var gid = '<?php echo addslashes($remarketing['google_id'] ?? ''); ?>';
    var glabel = '<?php echo addslashes($remarketing['google_label'] ?? ''); ?>';
    var total = <?php echo (float) $total; ?>;
    if (fb) {
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Purchase', {value: total, currency: 'USD'});
        }
    }
    if (gid) {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'conversion', {'send_to': gid + '/' + glabel, 'value': total});
        }
    }
    setTimeout(function(){ window.location.href = <?php echo json_encode($redirect); ?>; }, 1500);
})();
</script>
<?php } else { ?>
<script>setTimeout(function(){ window.location.href = <?php echo json_encode($redirect); ?>; }, 2000);</script>
<?php } ?>
