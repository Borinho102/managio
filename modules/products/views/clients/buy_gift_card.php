<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s section-heading section-products">
  <div class="panel-body">
    <h4 class="no-margin"><?php echo _l('product_buy_gift_card'); ?></h4>
  </div>
</div>
<div class="panel_s">
  <div class="panel-body">
    <?php echo form_open('products/client/purchase_gift_card'); ?>
    <div class="form-group">
      <label><?php echo _l('product_gift_card_amount'); ?> *</label>
      <input type="number" name="amount" class="form-control" min="<?php echo (float) get_option('product_gift_card_min_amount') ?: 1; ?>" step="0.01" required>
    </div>
    <div class="form-group">
      <label><?php echo _l('product_gift_card_recipient_email'); ?></label>
      <input type="email" name="recipient_email" class="form-control">
    </div>
    <div class="form-group">
      <label><?php echo _l('product_gift_card_recipient_name'); ?></label>
      <input type="text" name="recipient_name" class="form-control">
    </div>
    <div class="form-group">
      <label><?php echo _l('product_gift_card_message'); ?></label>
      <textarea name="message" class="form-control" rows="3"></textarea>
    </div>
    <div class="form-group">
      <label><?php echo _l('product_gift_card_template'); ?></label>
      <select name="template_id" class="form-control">
        <option value=""><?php echo _l('product_gift_card_default'); ?></option>
        <?php foreach ($templates as $t) { ?>
        <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
        <?php } ?>
      </select>
    </div>
    <button type="submit" class="btn btn-success"><?php echo _l('product_gift_card_proceed_pay'); ?></button>
    <?php echo form_close(); ?>
  </div>
</div>
