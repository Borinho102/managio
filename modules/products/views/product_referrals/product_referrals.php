<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo _l('product_referral_program'); ?></h4>
            <hr />
            <?php if (has_permission('products', '', 'create')) { ?>
            <div class="panel panel-default mtop15">
              <div class="panel-heading"><?php echo _l('product_referral_settings'); ?></div>
              <div class="panel-body">
                <?php echo form_open(admin_url('products/product_referrals/settings')); ?>
                <div class="row">
                  <div class="col-md-4">
                    <label><?php echo _l('product_referral_commission_percent'); ?></label>
                    <input type="number" name="product_referral_commission_percent" class="form-control" value="<?php echo htmlspecialchars(get_option('product_referral_commission_percent') ?: 10); ?>" min="0" step="0.01">
                  </div>
                  <div class="col-md-4">
                    <label><?php echo _l('product_referral_commission_fixed'); ?></label>
                    <input type="number" name="product_referral_commission_fixed" class="form-control" value="<?php echo htmlspecialchars(get_option('product_referral_commission_fixed') ?: 0); ?>" min="0" step="0.01">
                  </div>
                  <div class="col-md-4" style="padding-top:25px;">
                    <button type="submit" class="btn btn-info"><?php echo _l('settings_save'); ?></button>
                  </div>
                </div>
                <?php echo form_close(); ?>
              </div>
            </div>
            <?php } ?>
            <div class="table-responsive mtop15">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th><?php echo _l('client'); ?></th>
                    <th><?php echo _l('product_referral_code'); ?></th>
                    <th><?php echo _l('product_referral_link'); ?></th>
                    <th><?php echo _l('product_referral_total_earned'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($codes as $c) {
                    $client = $this->db->where('userid', $c['client_id'])->get(db_prefix() . 'clients')->row();
                    $link = site_url('products/client?ref=' . $c['code']);
                  ?>
                  <tr>
                    <td><?php echo $client ? htmlspecialchars($client->company) : '-'; ?></td>
                    <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
                    <td><a href="<?php echo htmlspecialchars($link); ?>" target="_blank"><?php echo _l('product_referral_share_link'); ?></a></td>
                    <td><?php echo app_format_money($c['total_earned'], get_base_currency()->name); ?></td>
                  </tr>
                  <?php } ?>
                  <?php if (empty($codes)) { ?>
                  <tr><td colspan="4"><?php echo _l('no_data'); ?></td></tr>
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
<?php init_tail(); ?>
