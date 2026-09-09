<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s " id="TableData">
          <div class="panel-body">
            <?php if (has_permission('products', '', 'create')) { ?>
            <a href="<?php echo admin_url('products/add_product'); ?>" class="btn btn-info pull-left display-block">
              <?php echo _l('new_product'); ?>
            </a>
            <?php } ?>
          </div>  
        </div>
        <div class="row">
          <div class="col-md-12" id="panel">
           <div class="panel_s">
              <div class="panel-body">
                <?php
                $table_data = [
                    _l('product_name'),
                    _l('product_image'),
                    _l('product_variations'),
                    _l('product_description'),
                    _l('products_categories'),
                    _l('invoice_item_add_edit_rate_currency'),
                    _l('quantity'),
                    _l('tax'),
                  ];
                  render_datatable($table_data, ($class ?? 'products')); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php if (!empty($products_debug_enabled) && isset($products_debug_time)) { ?>
<div class="products-debug-timing alert alert-info mtop15" style="margin:15px 0;padding:10px 15px;font-size:12px;">
  <i class="fa fa-clock-o"></i> Products list: page loaded in <strong><?php echo htmlspecialchars($products_debug_time); ?>s</strong>
</div>
<?php } ?>
  <?php init_tail(); ?>
<script type="text/javascript">
  $(function(){
    initDataTable('.table-products', window.location.href,'undefined','undefined','');
  });
</script>