<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo _l('product_gift_cards'); ?></h4>
            <p class="text-muted"><?php echo _l('product_gift_cards_desc'); ?></p>
            <a href="<?php echo admin_url('products/product_gift_cards/templates'); ?>" class="btn btn-info mtop15"><?php echo _l('product_gift_card_templates'); ?></a>
            <div class="table-responsive mtop15">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th><?php echo _l('product_gift_card_code'); ?></th>
                    <th><?php echo _l('amount'); ?></th>
                    <th><?php echo _l('product_gift_card_balance'); ?></th>
                    <th><?php echo _l('product_gift_card_recipient'); ?></th>
                    <th><?php echo _l('date'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cards as $c) {
                    $c = is_array($c) ? (object) $c : $c;
                  ?>
                  <tr>
                    <td><code><?php echo htmlspecialchars($c->code); ?></code></td>
                    <td><?php echo app_format_money($c->amount, get_base_currency()->name); ?></td>
                    <td><?php echo app_format_money($c->balance, get_base_currency()->name); ?></td>
                    <td><?php echo htmlspecialchars($c->recipient_email ?? '-'); ?></td>
                    <td><?php echo _d($c->datecreated); ?></td>
                  </tr>
                  <?php } ?>
                  <?php if (empty($cards)) { ?>
                  <tr><td colspan="5"><?php echo _l('no_data'); ?></td></tr>
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
