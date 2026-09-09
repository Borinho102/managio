<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <?php
            render_datatable([
              _l('product_name'),
              _l('client'),
              _l('product_review_rating'),
              _l('product_review_text'),
              _l('product_review_approved'),
              _l('date_created'),
              _l('options'),
            ], 'product_review');
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>
$(function(){
  initDataTable('.table-product_review', '<?php echo admin_url('products/product_reviews'); ?>');
});
</script>
