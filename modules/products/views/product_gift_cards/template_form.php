<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo $title; ?></h4>
            <?php echo form_open_multipart($this->uri->uri_string()); ?>
            <div class="row mtop15">
              <div class="col-md-6">
                <label><?php echo _l('name'); ?></label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars(!empty($template) ? $template->name : ''); ?>" required>
              </div>
              <div class="col-md-6">
                <label><?php echo _l('product_gift_card_design_image'); ?></label>
                <input type="file" name="design_image" class="form-control" accept="image/*">
                <?php if (!empty($template) && !empty($template->design_image)) { ?>
                <p class="mtop5"><img src="<?php echo module_dir_url('products', 'uploads') . '/' . htmlspecialchars($template->design_image); ?>" style="max-width:150px;max-height:100px;"></p>
                <?php } ?>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <label><?php echo _l('product_gift_card_design_html'); ?></label>
                <p class="text-muted"><?php echo _l('product_gift_card_merge_fields'); ?>: {gift_card_code}, {gift_card_amount}, {recipient_name}, {sender_name}, {message}, {expiry_date}, {companyname}</p>
                <textarea name="design_html" class="form-control" rows="12" placeholder="<div>Your HTML design with merge fields</div>"><?php echo htmlspecialchars(!empty($template) ? $template->design_html : '<div style="padding:20px;border:2px solid #ccc;text-align:center;"><h2>Gift Card</h2><p>Code: {gift_card_code}</p><p>Amount: {gift_card_amount}</p><p>To: {recipient_name}</p></div>'); ?></textarea>
                <input type="hidden" name="merge_fields_info" value="">
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <div class="checkbox">
                  <label>
                    <input type="checkbox" name="active" value="1" <?php echo (empty($template) || !empty($template->active)) ? 'checked' : ''; ?>>
                    <?php echo _l('active'); ?>
                  </label>
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <button type="submit" class="btn btn-success"><?php echo _l('save'); ?></button>
                <a href="<?php echo admin_url('products/product_gift_cards/templates'); ?>" class="btn btn-default"><?php echo _l('cancel'); ?></a>
              </div>
            </div>
            <?php echo form_close(); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
