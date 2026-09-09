<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin">
              <?php echo htmlspecialchars($title);?>
            </h4>
            <hr class="hr-panel-heading" />
            <?php echo form_open_multipart($this->uri->uri_string()); ?>
            <div class="row">
              <div class="col-md-6">
                <?php echo render_input('code', 'coupon_code', $coupon->code ?? ''); ?>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <?php echo render_input('amount', 'coupon_amount', $coupon->amount ?? '', 'number'); ?>
              </div>
              <div class="col-md-6">
                <label for="type" class="control-label">
                  <?php echo _l('coupon_add_edit_type'); ?>
                </label>
                <select class="selectpicker"
                    data-width="100%"
                    name="type"
                    required="required"
                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                  <?php for ($i = 0; $i <= 1; ++$i) { ?>
                    <?php
                      $selected = '';
                      if (isset($coupon)) {
                        if (('%' == $coupon->type && $i == 0) || ('fixed' == $coupon->type && $i == 1)) {
                          $selected = 'selected';
                        }
                      }
                      if (0 == $i) {
                        $type_string =  _l('coupon_add_edit_type_percent');
                      } elseif (1 == $i) {
                        $type_string =  _l('coupon_add_edit_type_fixed');
                      }
                    ?>
                    <option value="<?php echo ($i == 0 ? '%' : 'fixed'); ?>" <?php echo $selected; ?>><?php echo $type_string; ?></option>
                  <?php } ?>
                </select>
              </div>
            </div>
            <?php
              // A fixed amount and a minimum spend are figures in one currency,
              // so a multi-currency store must say which one they refer to.
              $coupon_currencies = products_enabled_currencies();
            ?>
            <?php if (products_multicurrency_enabled() && count($coupon_currencies) > 1) { ?>
            <div class="row">
              <div class="col-md-6">
                <label for="currency" class="control-label"><?php echo _l('product_coupon_currency'); ?></label>
                <select class="selectpicker" data-width="100%" name="currency"
                  data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                  <option value="0"<?php echo empty($coupon->currency) ? ' selected' : ''; ?>><?php echo _l('product_coupon_currency_any'); ?></option>
                  <?php foreach ($coupon_currencies as $coupon_currency) { ?>
                    <option value="<?php echo (int) $coupon_currency->id; ?>"<?php echo (isset($coupon) && (int) $coupon->currency === (int) $coupon_currency->id) ? ' selected' : ''; ?>>
                      <?php echo htmlspecialchars($coupon_currency->name); ?>
                    </option>
                  <?php } ?>
                </select>
                <p class="text-muted"><small><?php echo _l('product_coupon_currency_help'); ?></small></p>
              </div>
            </div>
            <?php } ?>
            <div class="row">
              <div class="col-md-6">
                <?php echo render_input('max_uses', 'coupon_max_uses', $coupon->max_uses ?? '0', 'number', ['min' => 0, 'placeholder' => '0 = unlimited']); ?>
              </div>
              <div class="col-md-6">
                <?php echo render_input('max_uses_per_client', 'coupon_max_uses_per_client', $coupon->max_uses_per_client ?? '0', 'number', ['min' => 0, 'placeholder' => '0 = unlimited']); ?>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <?php echo render_date_input('start_date', 'coupon_start_date', $coupon->start_date ?? '', ['placeholder' => _l('leave_blank_unlimited')]); ?>
              </div>
              <div class="col-md-6">
                <?php echo render_date_input('end_date', 'coupon_end_date', $coupon->end_date ?? '', ['placeholder' => _l('leave_blank_unlimited')]); ?>
              </div>
            </div>
            <?php if (isset($products) && isset($categories)) { ?>
            <div class="row">
              <div class="col-md-6">
                <label for="min_order_amount"><?php echo _l('coupon_min_order'); ?></label>
                <?php echo render_input('min_order_amount', '', $coupon->min_order_amount ?? '', 'number', ['min' => 0, 'placeholder' => _l('leave_blank_unlimited')]); ?>
              </div>
              <div class="col-md-6">
                <label><?php echo _l('products_categories'); ?></label>
                <?php
                $sel_cats = !empty($coupon->category_ids) ? explode(',', $coupon->category_ids) : [];
                echo render_select('category_ids[]', $categories, ['p_category_id', 'p_category_name'], '', $sel_cats, ['multiple' => true, 'data-none-selected-text' => _l('leave_blank_unlimited')], [], 'select_cat', '', false);
                ?>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <label><?php echo _l('products'); ?></label>
                <?php
                $sel_prods = !empty($coupon->product_ids) ? explode(',', $coupon->product_ids) : [];
                $prod_options = [];
                foreach ($products as $p) {
                    $pr = is_array($p) ? (object)$p : $p;
                    $prod_options[] = ['id' => $pr->id, 'product_name' => $pr->product_name];
                }
                echo render_select('product_ids[]', $prod_options, ['id', 'product_name'], '', $sel_prods, ['multiple' => true, 'data-none-selected-text' => _l('leave_blank_unlimited')], [], 'select_cat', '', false);
                ?>
              </div>
            </div>
            <?php } ?>
            <div class="row">
              <div class="col-md-12">
                <button type="submit" class="btn btn-info pull-right"><?php echo _l('submit'); ?></button>
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
<script type="text/javascript">
  $(function () {
    appValidateForm($('form'), {
      code                    : "required",
      amount                  : {required: true, min:0.01},
      type                    : "required",
      max_uses                : {required: false, min:0},
      max_uses_per_client     : {required: false, min:0},
    });
  });
</script>