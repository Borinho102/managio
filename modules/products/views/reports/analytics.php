<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo _l('analytics'); ?></h4>
            <hr>
            <form method="get" action="<?php echo admin_url('products/analytics'); ?>" class="form-inline mbot15">
              <div class="form-group">
                <label><?php echo _l('contract_start_date'); ?></label>
                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>">
              </div>
              <div class="form-group mleft10">
                <label><?php echo _l('contract_end_date'); ?></label>
                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>">
              </div>
              <button type="submit" class="btn btn-info mleft10"><?php echo _l('filter'); ?></button>
            </form>

            <div class="row">
              <div class="col-md-3 col-xs-6">
                <div class="panel_s">
                  <div class="panel-body text-center">
                    <h3 class="bold"><?php echo (int) $metrics['total_orders']; ?></h3>
                    <span class="text-muted"><?php echo _l('order_history'); ?></span>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-xs-6">
                <div class="panel_s">
                  <div class="panel-body text-center">
                    <h3 class="bold text-success"><?php echo app_format_money($metrics['total_revenue'], $base_currency->name); ?></h3>
                    <span class="text-muted"><?php echo _l('payable_amount'); ?></span>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-xs-6">
                <div class="panel_s">
                  <div class="panel-body text-center">
                    <h3 class="bold"><?php echo (float) $metrics['conversion_rate']; ?>%</h3>
                    <span class="text-muted"><?php echo _l('conversion_rate'); ?></span>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-xs-6">
                <div class="panel_s">
                  <div class="panel-body text-center">
                    <h3 class="bold text-warning"><?php echo (int) $metrics['abandoned_carts']; ?></h3>
                    <span class="text-muted"><?php echo _l('abandoned_carts'); ?></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="panel_s">
                  <div class="panel-body">
                    <h5><?php echo _l('revenue_by_product'); ?></h5>
                    <table class="table table-striped">
                      <thead>
                        <tr>
                          <th><?php echo _l('product_name'); ?></th>
                          <th><?php echo _l('quantity'); ?></th>
                          <th class="text-right"><?php echo _l('revenue'); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($revenue_by_product as $r) { ?>
                        <tr>
                          <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                          <td><?php echo (int) $r['units_sold']; ?></td>
                          <td class="text-right"><?php echo app_format_money($r['revenue'], $base_currency->name); ?></td>
                        </tr>
                        <?php } ?>
                        <?php if (empty($revenue_by_product)) { ?>
                        <tr><td colspan="3" class="text-center"><?php echo _l('no_products'); ?></td></tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="panel_s">
                  <div class="panel-body">
                    <h5><?php echo _l('abandoned_carts'); ?></h5>
                    <table class="table table-striped">
                      <thead>
                        <tr>
                          <th><?php echo _l('date'); ?></th>
                          <th class="text-right"><?php echo _l('payable_amount'); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($abandoned_carts as $a) { ?>
                        <tr>
                          <td><?php echo _dt($a['dateadded']); ?></td>
                          <td class="text-right"><?php echo app_format_money($a['cart_total'], $base_currency->name); ?></td>
                        </tr>
                        <?php } ?>
                        <?php if (empty($abandoned_carts)) { ?>
                        <tr><td colspan="2" class="text-center"><?php echo _l('no_products'); ?></td></tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
