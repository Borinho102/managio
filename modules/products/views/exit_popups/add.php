<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo htmlspecialchars($title); ?></h4>
            <hr class="hr-panel-heading" />
            <?php echo form_open_multipart($this->uri->uri_string()); ?>
            <div class="row">
              <div class="col-md-6">
                <?php echo render_input('name', 'exit_popup_name', !empty($popup) ? $popup->name : ''); ?>
              </div>
              <div class="col-md-6">
                <?php echo render_input('title', 'exit_popup_title', !empty($popup) ? $popup->title : ''); ?>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('exit_popup_body'); ?></label>
                  <textarea name="body" class="form-control" rows="5"><?php echo htmlspecialchars(!empty($popup) ? $popup->body : ''); ?></textarea>
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('exit_popup_image'); ?></label>
                  <input type="file" name="popup_image" class="form-control" accept="image/*" />
                <?php if (!empty($popup) && !empty($popup->image_path)) { ?>
                  <p class="text-muted mtop5"><?php echo _l('current_file'); ?>: <?php echo htmlspecialchars($popup->image_path); ?></p>
                  <img src="<?php echo module_dir_url('products', 'uploads/exit_popups/' . $popup->image_path); ?>" style="max-width:200px;max-height:120px;" alt="" />
                <?php } ?>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group"><?php echo render_input('cta_text', 'exit_popup_cta_text', !empty($popup) ? $popup->cta_text : '', 'text', ['placeholder' => _l('optional')]); ?></div>
                <div class="form-group"><?php echo render_input('cta_url', 'exit_popup_cta_url', !empty($popup) ? $popup->cta_url : '', 'text', ['placeholder' => _l('optional')]); ?></div>
                <div class="form-group"><?php echo render_input('coupon_code', 'exit_popup_coupon_code', !empty($popup) ? $popup->coupon_code : '', 'text', ['placeholder' => _l('optional')]); ?></div>
              </div>
            </div>
            <hr class="mtop20 mbot20" />
            <div class="row mtop15">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('exit_popup_trigger'); ?></label>
                  <select name="trigger_type" class="selectpicker" data-width="100%">
                  <option value="exit_intent" <?php echo ((!empty($popup) ? $popup->trigger_type : '') == 'exit_intent') ? 'selected' : ''; ?>><?php echo _l('exit_popup_trigger_exit_intent'); ?></option>
                  <option value="time_delay" <?php echo ((!empty($popup) ? $popup->trigger_type : '') == 'time_delay') ? 'selected' : ''; ?>><?php echo _l('exit_popup_trigger_time_delay'); ?></option>
                  <option value="scroll" <?php echo ((!empty($popup) ? $popup->trigger_type : '') == 'scroll') ? 'selected' : ''; ?>><?php echo _l('exit_popup_trigger_scroll'); ?></option>
                </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('exit_popup_trigger_value'); ?></label>
                  <input type="number" name="trigger_value" class="form-control" value="<?php echo !empty($popup) ? (int) $popup->trigger_value : 0; ?>" min="0" placeholder="<?php echo _l('exit_popup_trigger_value_help'); ?>" />
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('exit_popup_target'); ?></label>
                  <select name="target_pages" class="selectpicker" data-width="100%">
                  <option value="all" <?php echo ((!empty($popup) ? $popup->target_pages : '') == 'all') ? 'selected' : ''; ?>><?php echo _l('exit_popup_target_all'); ?></option>
                  <option value="product_listing" <?php echo ((!empty($popup) ? $popup->target_pages : '') == 'product_listing') ? 'selected' : ''; ?>><?php echo _l('exit_popup_target_product_listing'); ?></option>
                  <option value="product_detail" <?php echo ((!empty($popup) ? $popup->target_pages : '') == 'product_detail') ? 'selected' : ''; ?>><?php echo _l('exit_popup_target_product_detail'); ?></option>
                  <option value="cart_checkout" <?php echo ((!empty($popup) ? $popup->target_pages : '') == 'cart_checkout') ? 'selected' : ''; ?>><?php echo _l('exit_popup_target_cart_checkout'); ?></option>
                </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group"><?php echo render_input('dont_show_days', 'exit_popup_dont_show_days', !empty($popup) ? $popup->dont_show_days : 7, 'number', ['min' => 0]); ?></div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-6">
                <label class="control-label"><?php echo _l('exit_popup_require_cart'); ?></label>
                <div class="checkbox checkbox-primary">
                  <input type="checkbox" name="require_cart_items" value="1" <?php echo !empty($popup) && !empty($popup->require_cart_items) ? 'checked' : ''; ?> id="require_cart_items" />
                  <label for="require_cart_items"><?php echo _l('exit_popup_require_cart_yes'); ?></label>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group"><?php echo render_input('sort_order', 'exit_popup_sort_order', !empty($popup) ? $popup->sort_order : 0, 'number', ['min' => 0]); ?></div>
                <div class="form-group">
                  <div class="checkbox checkbox-primary">
                  <input type="checkbox" name="active" value="1" <?php echo (empty($popup) || !empty($popup->active)) ? 'checked' : ''; ?> id="popup_active" />
                  <label for="popup_active"><?php echo _l('active'); ?></label>
                  </div>
                </div>
              </div>
            </div>
            <hr class="mtop20 mbot20" />
            <div class="mtop15">
            <button type="submit" class="btn btn-success"><?php echo _l('submit'); ?></button>
            <a href="<?php echo admin_url('products/exit_popups'); ?>" class="btn btn-default mleft5"><?php echo _l('cancel'); ?></a>
            </div>
            <?php echo form_close(); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>$(function(){ if (typeof init_selectpicker === 'function') init_selectpicker(); });</script>
