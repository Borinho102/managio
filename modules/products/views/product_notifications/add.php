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
            <?php echo form_open($this->uri->uri_string()); ?>
            <div class="row">
              <div class="col-md-6">
                <?php echo render_input('name', 'product_notification_name', !empty($tpl) ? $tpl->name : ''); ?>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('product_notification_channel'); ?></label>
                  <select name="channel" class="selectpicker" data-width="100%">
                  <option value="whatsapp" <?php echo (!empty($tpl) && $tpl->channel == 'whatsapp') ? 'selected' : ''; ?>><?php echo _l('product_notification_channel_whatsapp'); ?></option>
                  <option value="sms" <?php echo (!empty($tpl) && $tpl->channel == 'sms') ? 'selected' : ''; ?>><?php echo _l('product_notification_channel_sms'); ?></option>
                  <option value="webhook" <?php echo (!empty($tpl) && $tpl->channel == 'webhook') ? 'selected' : ''; ?>><?php echo _l('product_notification_channel_webhook'); ?></option>
                </select>
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('product_notification_trigger'); ?></label>
                  <select name="trigger_event" class="selectpicker" data-width="100%">
                  <option value="order_placed" <?php echo (!empty($tpl) && $tpl->trigger_event == 'order_placed') ? 'selected' : ''; ?>><?php echo _l('product_notification_trigger_order_placed'); ?></option>
                  <option value="order_paid" <?php echo (!empty($tpl) && $tpl->trigger_event == 'order_paid') ? 'selected' : ''; ?>><?php echo _l('product_notification_trigger_order_paid'); ?></option>
                  <option value="abandoned_cart" <?php echo (!empty($tpl) && $tpl->trigger_event == 'abandoned_cart') ? 'selected' : ''; ?>><?php echo _l('product_notification_trigger_abandoned_cart'); ?></option>
                </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('product_notification_recipient'); ?></label>
                  <select name="recipient" class="selectpicker" data-width="100%">
                  <option value="client" <?php echo (!empty($tpl) && $tpl->recipient == 'client') ? 'selected' : ''; ?>><?php echo _l('product_notification_recipient_client'); ?></option>
                  <option value="staff" <?php echo (!empty($tpl) && $tpl->recipient == 'staff') ? 'selected' : ''; ?>><?php echo _l('product_notification_recipient_staff'); ?></option>
                </select>
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('product_notification_message'); ?></label>
                  <textarea name="message_template" class="form-control" rows="4"><?php echo htmlspecialchars(!empty($tpl) ? $tpl->message_template : ''); ?></textarea>
                  <p class="text-muted mtop5"><small><?php echo _l('product_notification_merge_help'); ?></small></p>
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <div class="form-group">
                  <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="use_global_gateway" value="1" <?php echo (empty($tpl) || (isset($tpl->use_global_gateway) && $tpl->use_global_gateway) || !isset($tpl->use_global_gateway)) ? 'checked' : ''; ?> id="use_global_gateway" />
                    <label for="use_global_gateway"><?php echo _l('product_notification_use_global_gateway'); ?></label>
                  </div>
                  <p class="text-muted mtop5"><small><?php echo _l('product_notification_use_global_gateway_help'); ?></small></p>
                </div>
              </div>
            </div>
            <div class="row mtop15 webhook_url_row">
              <div class="col-md-8">
                <?php echo render_input('webhook_url', 'product_notification_webhook_url', !empty($tpl) ? $tpl->webhook_url : '', 'text', ['placeholder' => 'https://...']); ?>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('product_notification_webhook_method'); ?></label>
                  <select name="webhook_method" class="selectpicker" data-width="100%">
                  <option value="POST" <?php echo (!empty($tpl) && $tpl->webhook_method == 'POST') ? 'selected' : ''; ?>>POST</option>
                  <option value="GET" <?php echo (!empty($tpl) && $tpl->webhook_method == 'GET') ? 'selected' : ''; ?>>GET</option>
                </select>
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <div class="form-group">
                  <label class="control-label"><?php echo _l('product_notification_webhook_body'); ?> <span class="text-muted">(<?php echo _l('optional'); ?>)</span></label>
                  <textarea name="webhook_body" class="form-control" rows="4" placeholder='{"to":"{contact_phonenumber}","body":{message}}'><?php echo htmlspecialchars(!empty($tpl) ? $tpl->webhook_body : ''); ?></textarea>
                  <p class="text-muted mtop5"><small><?php echo _l('product_notification_webhook_body_help'); ?></small></p>
                </div>
              </div>
            </div>
            <div class="row mtop15">
              <div class="col-md-12">
                <div class="form-group">
                  <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="active" value="1" <?php echo (empty($tpl) || !empty($tpl->active)) ? 'checked' : ''; ?> id="notif_active" />
                    <label for="notif_active"><?php echo _l('active'); ?></label>
                  </div>
                </div>
              </div>
            </div>
            <hr class="mtop20 mbot20" />
            <div class="mtop15">
            <button type="submit" class="btn btn-success"><?php echo _l('submit'); ?></button>
            <a href="<?php echo admin_url('products/product_notifications'); ?>" class="btn btn-default mleft5"><?php echo _l('cancel'); ?></a>
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
