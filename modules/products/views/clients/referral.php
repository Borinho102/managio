<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s section-heading section-products">
  <div class="panel-body">
    <h4 class="no-margin"><?php echo _l('product_referral_program'); ?></h4>
  </div>
</div>
<div class="panel_s">
  <div class="panel-body">
    <h4><?php echo _l('product_referral_program'); ?></h4>
    <p><?php echo _l('product_referral_desc'); ?></p>
    <?php
    $ref = $referral_code ?? null;
    if ($ref) {
        $link = site_url('products/client?ref=' . $ref->code);
    ?>
    <div class="form-group">
      <label><?php echo _l('product_referral_your_code'); ?></label>
      <div class="input-group">
        <input type="text" class="form-control" id="referral-code" value="<?php echo htmlspecialchars($ref->code); ?>" readonly>
        <span class="input-group-btn">
          <button class="btn btn-default" type="button" onclick="navigator.clipboard.writeText(document.getElementById('referral-code').value); alert_float('success','<?php echo _l('copied'); ?>');"><i class="fa fa-copy"></i></button>
        </span>
      </div>
    </div>
    <div class="form-group">
      <label><?php echo _l('product_referral_your_link'); ?></label>
      <div class="input-group">
        <input type="text" class="form-control" id="referral-link" value="<?php echo htmlspecialchars($link); ?>" readonly>
        <span class="input-group-btn">
          <button class="btn btn-default" type="button" onclick="navigator.clipboard.writeText(document.getElementById('referral-link').value); if(typeof alert_float==='function') alert_float('success','<?php echo _l('copied'); ?>');"><i class="fa fa-copy"></i></button>
        </span>
      </div>
    </div>
    <p class="text-success"><strong><?php echo _l('product_referral_total_earned'); ?>:</strong> <?php echo app_format_money($ref->total_earned, $base_currency->name); ?></p>
    <?php } else { ?>
    <p class="text-muted"><?php echo _l('product_referral_no_code'); ?></p>
    <?php } ?>
  </div>
</div>
