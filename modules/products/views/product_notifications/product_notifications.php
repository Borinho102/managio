<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s" id="TableData">
          <div class="panel-body">
            <?php if (has_permission('products', '', 'create')) { ?>
              <a href="<?php echo admin_url('products/product_notifications/add'); ?>" class="btn btn-info pull-left display-block">
                <?php echo _l('product_notification_new'); ?>
              </a>
            <?php } ?>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <div class="panel_s">
              <div class="panel-body">
                <?php
                render_datatable([
                  _l('product_notification_name'),
                  _l('product_notification_channel'),
                  _l('product_notification_trigger'),
                  _l('product_notification_recipient'),
                  _l('active'),
                ], 'product_notification');
                ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>
$(function(){
  initDataTable('.table-product_notification', '<?php echo admin_url('products/product_notifications'); ?>', undefined, undefined, 'undefined', [0, 'asc']);
});
</script>
