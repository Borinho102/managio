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
              <div class="col-md-5">
                <?php echo render_select('product_category_id', $product_categories, ['p_category_id', 'p_category_name'], 'products_categories', !empty(set_value('product_category_id')) ? set_value('product_category_id') : $product->product_category_id ?? ''); ?>
              </div>
              <div class="col-md-7">
                <?php echo render_input('product_name', 'product_name', $product->product_name ?? ''); ?>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <?php echo render_textarea('product_description', 'product_description', $product->product_description ?? ''); ?>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <?php
                  $base_currency_label = _l('invoice_item_add_edit_rate_currency');
                  if (!empty($base_currency)) {
                    $base_currency_label .= ' (' . $base_currency->name . ')';
                  }
                  echo render_input('rate', $base_currency_label, $product->rate ?? '', 'number',['min'=>"0.00"]);
                ?>
              </div>
              <div class="col-md-3">
                <label>Tax</label>
                <?php
                  $selected_taxes ='';
                  if (!empty($product->taxes)) {
                    $selected_taxes = (!empty(($product->taxes))) ? unserialize($product->taxes) : '';
                  }
                  echo $this->misc_model->get_taxes_dropdown_template('taxes[]', $selected_taxes);
                ?>
              </div>
              <div class="col-md-2">
                <?php echo render_input('quantity_number', 'quantity', $product->quantity_number ?? '', 'number'); ?>
              </div>
              <?php if (empty($has_product_type)) { ?>
              <div class="col-md-4">
                <label for="is_digital"><?php echo _l('no_qty_digital_product'); ?></label>
                <div class="checkbox checkbox-danger">
                  <input type="checkbox" name="is_digital" id="is_digital" value="<?php echo isset($product) ? $product->is_digital : "" ?>"  <?php echo isset($product) ? ($product->is_digital == '1') ? "checked" : "" : "" ?> >
                  <label></label>
                </div>
              </div>
              <?php } ?>
              <?php if (!empty($has_product_type)) { ?>
              <div class="col-md-4">
                <label for="product_type"><?php echo _l('product_type'); ?></label>
                <select name="product_type" id="product_type" class="selectpicker" data-width="100%">
                  <option value="physical" <?php echo (isset($product) && ($product->product_type ?? '') == 'physical') ? 'selected' : ''; ?>><?php echo _l('product_type_physical'); ?></option>
                  <option value="digital" <?php echo (isset($product) && ($product->product_type ?? '') == 'digital') ? 'selected' : ''; ?>><?php echo _l('product_type_digital'); ?></option>
                  <option value="service" <?php echo (isset($product) && ($product->product_type ?? '') == 'service') ? 'selected' : ''; ?>><?php echo _l('product_type_service'); ?></option>
                </select>
              </div>
              <?php } ?>
            </div>
            <?php
              // Additional selling currencies. The base currency is priced by the
              // "rate" field above, so it is excluded here to avoid two inputs
              // for the same number.
              $extra_currencies = [];
              if (!empty($selling_currencies)) {
                foreach ($selling_currencies as $selling_currency) {
                  if (empty($base_currency) || $selling_currency->id != $base_currency->id) {
                    $extra_currencies[] = $selling_currency;
                  }
                }
              }
            ?>
            <?php if (!empty($extra_currencies)) { ?>
            <div class="row">
              <div class="col-md-12">
                <div class="panel-body no-padding-left no-padding-right">
                  <label class="control-label"><?php echo _l('product_currency_prices'); ?></label>
                  <p class="text-muted no-margin"><small><?php echo _l('product_currency_prices_help'); ?></small></p>
                  <div class="table-responsive">
                    <table class="table table-bordered no-mtop mtop10 product-currency-prices-table">
                      <thead>
                        <tr>
                          <th><?php echo _l('product_currency'); ?></th>
                          <th><?php echo _l('product_currency_price'); ?></th>
                          <th><?php echo _l('product_currency_sale_price'); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($extra_currencies as $extra_currency) { ?>
                          <?php $existing_price = $product_currency_prices[(int) $extra_currency->id] ?? null; ?>
                          <tr>
                            <td class="valign-middle">
                              <strong><?php echo htmlspecialchars($extra_currency->name); ?></strong>
                              <?php if (!empty($extra_currency->symbol)) { ?>
                                <span class="text-muted">(<?php echo htmlspecialchars($extra_currency->symbol); ?>)</span>
                              <?php } ?>
                            </td>
                            <td>
                              <input type="number" step="0.01" min="0" class="form-control currency-price-rate"
                                data-currency-id="<?php echo (int) $extra_currency->id; ?>"
                                name="currency_prices[<?php echo (int) $extra_currency->id; ?>][rate]"
                                value="<?php echo $existing_price ? htmlspecialchars($existing_price->rate) : ''; ?>"
                                placeholder="<?php echo _l('product_currency_price_placeholder'); ?>">
                            </td>
                            <td>
                              <input type="number" step="0.01" min="0" class="form-control"
                                name="currency_prices[<?php echo (int) $extra_currency->id; ?>][sale_price]"
                                value="<?php echo ($existing_price && $existing_price->sale_price !== null) ? htmlspecialchars($existing_price->sale_price) : ''; ?>"
                                placeholder="<?php echo _l('product_currency_price_placeholder'); ?>">
                            </td>
                          </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <?php } ?>
            <?php if (!empty($has_digital_file)) { ?>
            <div id="digital_file_wrapper" class="row <?php if (!isset($product) || (empty($product->is_digital) && ($product->product_type ?? '') !== 'digital')) { echo 'hide'; } ?>">
              <div class="col-md-12">
                <label><?php echo _l('digital_product_file'); ?></label>
                <?php if (!empty($product->digital_file_path)) { ?>
                <p class="text-muted"><?php echo _l('current_file'); ?>: <?php echo htmlspecialchars(basename($product->digital_file_path)); ?></p>
                <?php } ?>
                <input type="file" name="digital_file" id="digital_file" class="form-control" accept=".pdf,.zip,.mp3,.mp4,.epub,.doc,.docx">
                <small class="text-muted"><?php echo _l('digital_file_allowed'); ?></small>
              </div>
            </div>
            <?php } ?>
            <?php
              $existing_image_class = 'col-md-4';
              $input_file_class     = 'col-md-8';
              if (empty($product->product_image)) {
                $existing_image_class = 'col-md-12';
                $input_file_class     = 'col-md-12';
              }
            ?>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group select-placeholder"<?php if (isset($product) && !empty($product->is_recurring_from)) { ?> data-toggle="tooltip" data-title="<?php echo _l('create_recurring_from_child_error_message', [_l('invoice_lowercase'), _l('invoice_lowercase'), _l('invoice_lowercase')]); ?>"<?php } ?>>
                  <label for="recurring" class="control-label">
                    <?php echo _l('invoice_add_edit_recurring'); ?>
                  </label>
                  <select class="selectpicker"
                    data-width="100%"
                    name="recurring"
                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>"
                    <?php
                      if (isset($product) && !empty($product->is_recurring_from)) {
                        echo 'disabled';
                      } ?>
                    >
                    <?php for ($i = 0; $i <= 12; ++$i) { ?>
                      <?php
                        $selected = '';
                        if (isset($product)) {
                          if (0 == $product->custom_recurring) {
                            if ($product->recurring == $i) {
                              $selected = 'selected';
                            }
                          }
                        }
                        if (0 == $i) {
                          $reccuring_string =  _l('invoice_add_edit_recurring_no');
                        } elseif (1 == $i) {
                          $reccuring_string = _l('invoice_add_edit_recurring_month', $i);
                        } else {
                          $reccuring_string = _l('invoice_add_edit_recurring_months', $i);
                        }
                      ?>
                      <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $reccuring_string; ?></option>
                    <?php } ?>
                    <option value="custom" <?php if (isset($product) && 0 != $product->recurring && 1 == $product->custom_recurring) { echo 'selected'; } ?>><?php echo _l('recurring_custom'); ?></option>
                  </select>
                </div>
              </div>
              <div class="recurring_custom <?php if ((isset($product) && 1 != $product->custom_recurring) || (!isset($product))) { echo 'hide'; } ?>">
                <div class="col-md-2">
                  <?php $value = (isset($product) && 1 == $product->custom_recurring ? $product->recurring : 1); ?>
                  <?php echo render_input('repeat_every_custom', 'Number', $value, 'number', ['min'=>1]); ?>
                </div>
                <div class="col-md-5">
                  <label>Select</label>
                  <select name="repeat_type_custom" id="repeat_type_custom" class="selectpicker" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                    <option value="day" <?php if (isset($product) && 1 == $product->custom_recurring && 'day' == $product->recurring_type) { echo 'selected'; } ?>><?php echo _l('invoice_recurring_days'); ?></option>
                    <option value="week" <?php if (isset($product) && 1 == $product->custom_recurring && 'week' == $product->recurring_type) { echo 'selected'; } ?>><?php echo _l('invoice_recurring_weeks'); ?></option>
                    <option value="month" <?php if (isset($product) && 1 == $product->custom_recurring && 'month' == $product->recurring_type) { echo 'selected'; } ?>><?php echo _l('invoice_recurring_months'); ?></option>
                    <option value="year" <?php if (isset($product) && 1 == $product->custom_recurring && 'year' == $product->recurring_type) { echo 'selected'; } ?>><?php echo _l('invoice_recurring_years'); ?></option>
                  </select>
                </div>
              </div>
              <div id="cycles_wrapper" class="<?php if (!isset($product) || (isset($product) && 0 == $product->recurring)) { echo ' hide'; }?>">
                <div class="col-md-12">
                  <?php $value = (isset($product) ? $product->cycles : 0); ?>
                  <div class="form-group recurring-cycles">
                    <label for="cycles"><?php echo _l('recurring_total_cycles'); ?></label>
                    <div class="input-group">
                      <input type="number" class="form-control"<?php if (0 == $value) { echo ' disabled'; } ?> name="cycles" id="cycles" value="<?php echo $value; ?>" <?php if (isset($product) && $product->cycles > 0) { echo 'min="'.($product->cycles).'"'; } ?>>
                      <div class="input-group-addon">
                        <div class="checkbox">
                          <input type="checkbox"<?php if (0 == $value) { echo ' checked'; } ?> id="unlimited_cycles">
                          <label for="unlimited_cycles"><?php echo _l('cycles_infinity'); ?></label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <?php if (!empty($product->product_image)) { ?>
                <div class="<?php echo htmlspecialchars($existing_image_class); ?>">
                  <div class="existing_image">
                    <label class="control-label">Existing Image</label>
                    <img src="<?php echo htmlspecialchars(products_get_product_image_url($product->product_image)); ?>" class="img img-responsive img-thubnail zoom" onerror="this.src='<?php echo htmlspecialchars(products_get_no_image_url()); ?>'"/>
                  </div>
                </div>
              <?php } ?>
              <div class="<?php echo htmlspecialchars($input_file_class); ?>">
                <div class="attachment">
                  <div class="form-group">
                    <label for="attachment" class="control-label"><small class="req text-danger">* </small><?php echo _l('product_image'); ?></label>
                    <input type="file" extension="png,jpg,jpeg,gif" filesize="<?php echo file_upload_max_size(); ?>" class="form-control" name="product" id="product" required>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group">
                  <label for="is_variation" class="control-label">
                    <?php echo _l('product_add_edit_variation'); ?>
                  </label>
                  <select class="selectpicker"
                    data-width="100%"
                    name="is_variation"
                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>"
                    >
                    <option value="0" <?php if (isset($product) && !empty($product->is_variation == 0)) echo 'selected'; ?>><?php echo _l('product_add_edit_variation_no'); ?></option>
                    <option value="1" <?php if (isset($product) && !empty($product->is_variation == 1)) echo 'selected'; ?>><?php echo _l('product_add_edit_variation_yes'); ?></option>
                  </select>
                </div>
              </div>
              <div id="variations_wrapper" class="<?php if (!isset($product) || (isset($product) && 0 == $product->is_variation)) { echo ' hide'; }?>">
                <div class="col-md-12">
                  <div class="table-responsive s_table">
                    <table class="table product-variations-table items table-main-product-variation-edit has-calculations no-mtop">
                      <thead>
                        <tr>
                          <th>
                            <?php echo _l('product_variation_table_heading'); ?>
                          </th>
                          <th><?php echo _l('product_variation_table_value'); ?></th>
                          <th>
                            <?php echo _l('product_variation_table_price'); ?>
                            <?php if (!empty($base_currency)) { ?>
                              <small class="text-muted">(<?php echo htmlspecialchars($base_currency->name); ?>)</small>
                            <?php } ?>
                          </th>
                          <?php foreach ($extra_currencies as $extra_currency) { ?>
                            <th>
                              <?php echo _l('product_variation_table_price'); ?>
                              <small class="text-muted">(<?php echo htmlspecialchars($extra_currency->name); ?>)</small>
                            </th>
                          <?php } ?>
                          <th><?php echo _l('product_variation_table_quantity'); ?></th>
                          <th align="center"><i class="fa fa-cog"></i></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr class="main">
                          <td>
                            <select class="selectpicker variation"
                              data-width="100%"
                              data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>"
                              >
                              <?php foreach ($variations as $variation_index => $variation) { ?>
                                <option value="<?php echo $variation['id']; ?>"><?php echo $variation['name']; ?></option>
                              <?php } ?>
                            </select>
                          </td>
                          <td>
                            <select class="selectpicker variation_value"
                              data-width="100%"
                              data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>"
                              <?php
                                if (isset($product) && !empty($product->is_recurring_from)) {
                                  echo 'disabled';
                                } ?>
                              >
                            </select>
                          </td>
                          <td></td>
                          <?php foreach ($extra_currencies as $extra_currency) { ?>
                            <td></td>
                          <?php } ?>
                          <td>
                            <button type="button" onclick="add_variation_value_to_table(); return false;" class="btn pull-right btn-primary"><i class="fa fa-check"></i></button>
                          </td>
                        </tr>
                        <?php if (isset($product) && !empty($product->variations)) { ?>
                          <?php
                            $product_variation_id = '';
                            foreach ($product->variations as $product_variation) {
                              if ($product_variation->variation_id != $product_variation_id) {
                                $product_variation_id = $product_variation->variation_id; ?>
                                <tr class="variation">
                                  <td><input class="form-control" value="<?php echo $product_variation->variation_name ?>" data-id="<?php echo $product_variation->variation_id ?>" readonly /></td>
                                  <td></td>
                                  <td></td>
                                  <?php foreach ($extra_currencies as $extra_currency) { ?>
                                    <td></td>
                                  <?php } ?>
                                  <td></td>
                                  <td><a href="#" class="btn btn-danger pull-right" onclick="delete_variation(this); return false;"><i class="fa fa-times"></i></a></td>
                                </tr>
                              <?php } ?>
                              <?php $variation_prices = $variation_currency_prices[(int) $product_variation->id] ?? []; ?>
                              <tr class="variation_value">
                                <td><input name="variations[variation][]" class="form-control variation" value="<?php echo $product_variation->variation_name ?>" data-id="<?php echo $product_variation->variation_id ?>" readonly /></td>
                                <td><input name="variations[variation_value][]" class="form-control variation_value" value="<?php echo $product_variation->variation_value ?>" data-id="<?php echo $product_variation->variation_value_id ?>" readonly /></td>
                                <td><input name="variations[rate][]" class="form-control rate" value="<?php echo $product_variation->rate ?>" /></td>
                                <?php foreach ($extra_currencies as $extra_currency) { ?>
                                  <?php $variation_price = $variation_prices[(int) $extra_currency->id] ?? null; ?>
                                  <td>
                                    <input name="variations[currency_rate][<?php echo (int) $extra_currency->id; ?>][]"
                                      class="form-control currency_rate"
                                      data-currency-id="<?php echo (int) $extra_currency->id; ?>"
                                      value="<?php echo $variation_price ? htmlspecialchars($variation_price->rate) : ''; ?>"
                                      placeholder="<?php echo _l('product_currency_price_placeholder'); ?>" />
                                  </td>
                                <?php } ?>
                                <td><input name="variations[quantity_number][]" class="form-control quantity_number" value="<?php echo $product_variation->quantity_number ?>" /></td>
                                <td><a href="#" class="btn btn-danger pull-right" onclick="delete_variation_value(this); return false;"><i class="fa fa-times"></i></a></td>
                              </tr>
                            <?php }
                          ?>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-info pull-right"><?php echo _l('submit'); ?></button>
            <?php echo form_close(); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php if (!empty($products_debug_enabled) && isset($products_debug_time)) { ?>
<div class="products-debug-timing alert alert-info mtop15" style="margin:15px 0;padding:10px 15px;font-size:12px;">
  <i class="fa fa-clock-o"></i> Products: page loaded in <strong><?php echo htmlspecialchars($products_debug_time); ?>s</strong>
</div>
<?php } ?>
<?php init_tail(); ?>
<script type="text/javascript">
  var mode = '<?php echo $this->uri->segment(3, 0); ?>';
  (mode == 'add_product') ? $('#product').prop('required',true) : $('#product').prop('required',false);

  // Additional selling currencies, so dynamically added variation rows get the
  // same per-currency price cells as the server-rendered ones.
  var PRODUCT_EXTRA_CURRENCIES = <?php echo json_encode(array_map(function ($currency) {
    return ['id' => (int) $currency->id, 'name' => $currency->name];
  }, $extra_currencies)); ?>;
  var PRODUCT_CURRENCY_PRICE_PLACEHOLDER = <?php echo json_encode(_l('product_currency_price_placeholder')); ?>;

  function product_currency_empty_cells() {
    return PRODUCT_EXTRA_CURRENCIES.map(function () { return '<td></td>'; }).join('');
  }

  function product_currency_price_cells() {
    return PRODUCT_EXTRA_CURRENCIES.map(function (currency) {
      // Seeded from the product-level price for that currency when one is set,
      // so a new variation does not silently fall back to the base rate.
      var seed = $('.currency-price-rate[data-currency-id="' + currency.id + '"]').val() || '';
      return '<td><input name="variations[currency_rate][' + currency.id + '][]" class="form-control currency_rate"'
        + ' data-currency-id="' + currency.id + '" value="' + seed + '"'
        + ' placeholder="' + PRODUCT_CURRENCY_PRICE_PLACEHOLDER + '" /></td>';
    }).join('');
  }
  $(function () {
    // Services and digital products carry no stock, so the quantity field is
    // locked and never validated for them.
    function productTracksStock() {
      if ($('#is_digital').length && $('#is_digital').is(':checked')) { return false; }
      if ($('#product_type').length) {
        return ['digital', 'service'].indexOf($('#product_type').val()) === -1;
      }
      return true;
    }
    function applyStockState() {
      var tracks = productTracksStock();
      var $qty   = $('#quantity_number');
      if (!$qty.length) { return; }
      if (tracks) {
        $qty.prop('readonly', false).closest('.form-group').removeClass('hide');
        if ($qty.rules) { $qty.rules('add', {required: true}); }
        return;
      }
      // Leave the stored value alone: overwriting it is what made the field
      // appear to reset every time a product was edited.
      if (!$qty.val()) { $qty.val(1); }
      $qty.prop('readonly', true).closest('.form-group').addClass('hide');
      if ($qty.rules) { $qty.rules('remove', 'required'); }
    }

    appValidateForm($('form'), {
      product_name        : "required",
      product_description : "required",
      product_category_id : "required",
      rate                : "required"
    });
    applyStockState();
    if ($('#is_digital').length) {
      $('#is_digital').click(function(event) {
        if($('#is_digital').is(':checked')){
          $(this).attr({value:1});
          $('#digital_file_wrapper').removeClass('hide');
          if ($('#product_type').length) $('#product_type').val('digital').selectpicker('refresh');
        }else{
          $(this).attr({value:0});
          $('#digital_file_wrapper').addClass('hide');
        }
        applyStockState();
      });
    }
    if ($('#product_type').length) {
      $('#product_type').on('change', function(){
        if ($(this).val() === 'digital') {
          $('#digital_file_wrapper').removeClass('hide');
        } else {
          $('#digital_file_wrapper').addClass('hide');
        }
        applyStockState();
      });
    }
    change_variation_values();
    change_variation_quantity_event();
  });
  function get_variation_value_preview_values() {
    var response = {};
    response.variation_id = parseInt($('.selectpicker.variation').val());
    response.variation_name = '';
    for (var variation_index = 0; variation_index < $('.selectpicker.variation option').length; variation_index++) {
      var variation_item = $($('.selectpicker.variation option')[variation_index]);
      if (variation_item.val() == response.variation_id) {
        response.variation_name = variation_item.text();
      }
    }
    response.variation_value_id = parseInt($('.selectpicker.variation_value').val());
    response.variation_value_value = '';
    response.variation_value_values = [];
    for (var variation_index = 0; variation_index < $('select.variation_value option').length; variation_index++) {
      var variation_value_item = $($('.selectpicker.variation_value option')[variation_index]);
      if (variation_value_item.val() == response.variation_value_id) {
        response.variation_value_value = variation_value_item.text();
      }
      response.variation_value_values.push({id: parseInt(variation_value_item.val()), value: variation_value_item.text()});
    }
    return response;
  }
  $("body").on(
    "change",
    '[name="recurring"]',
    function () {
      var val = $(this).val();
      val == "custom" ? $(".recurring_custom").removeClass("hide") : $(".recurring_custom").addClass("hide");
    }
  );
  $("body").on(
    "change",
    '[name="is_variation"]',
    function () {
      var val = $(this).val();
      if (val !== "" && val != 0) {
        $("body").find("#variations_wrapper").removeClass("hide");
      } else {
        $("body").find("#variations_wrapper").addClass("hide");
      }
    }
  );
  function change_variation_values() {
    $.ajax({
      url: site_url+'products/variations/values',
      type: 'POST',
      dataType: 'json',
      data : {'variation_id':$('.selectpicker.variation').val()},
      success : function (data) {
        var variation_values_html = '<option value="">' + $('.selectpicker.variation_value').data('none-selected-text') + '</option>';
        for (var variation_index = 0; variation_index < data.length; variation_index++) {
          variation_values_html += '<option value="' + data[variation_index]['id'] + '">' + data[variation_index]['value'] + '</option>';
        }
        $('.selectpicker.variation_value').html(variation_values_html);
        $('.selectpicker.variation_value').selectpicker("refresh");
      }
    });
  }
  function change_variation_quantity_event() {
    change_variation_quantity();
    $("body").on(
      "change",
      'input.quantity_number',
      function () {
        change_variation_quantity();
      }
    );
  }
  function change_variation_quantity() {
    var total_quantities = 0;
    var quantity_numbers = $('input.quantity_number');
    for (var quantiry_index = 0; quantiry_index < quantity_numbers.length; quantiry_index++) {
      total_quantities += parseInt($(quantity_numbers[quantiry_index]).val());
    }
    $('#quantity_number').val(total_quantities);
  }
  function add_variation_value_to_table() {
    var data = get_variation_value_preview_values();

    if (data.variation_id === "") {
      return;
    }
    
    var variation_row = null;
    var row_variation_id = '';
    var row_variation_value_id = '';
    var rows = $(".table.product-variations-table tbody tr:not(.main)");
    for (var row_index = 0; row_index < rows.length; row_index++) {
      if ($(rows[row_index]).hasClass('variation')) {
        row_variation_id = $(rows[row_index]).find("input").data('id');
        if (row_variation_id == data.variation_id) {
          variation_row = $(rows[row_index]);
        }
      } else {
        row_variation_id = $(rows[row_index]).find("input.variation").data('id');
        row_variation_value_id = $(rows[row_index]).find("input.variation_value").data('id');
        if (row_variation_id == data.variation_id) {
          variation_row = $(rows[row_index]);
        }
        if (!data.variation_value_id) {
          if (row_variation_id == data.variation_id) {
            return;
          }
        } else {
          if (row_variation_value_id == data.variation_value_id) {
            return;
          }
        }
      }
    }

    var table_row = "";
    if (!data.variation_value_id) {
      table_row += '<tr class="variation">';
      table_row += '<td><input class="form-control" value="' + data.variation_name + '" data-id="' + data.variation_id + '" readonly /></td>';
      table_row += '<td></td>';
      table_row += '<td></td>';
      table_row += product_currency_empty_cells();
      table_row += '<td></td>';
      table_row += '<td><a href="#" class="btn btn-danger pull-right" onclick="delete_variation(this); return false;"><i class="fa fa-times"></i></a></td>';
      table_row += '</tr>';
      for (var variation_value_index = 0; variation_value_index < data.variation_value_values.length; variation_value_index++) {
        if (data.variation_value_values[variation_value_index].id) {
          table_row += '<tr class="variation_value">';
          table_row += '<td><input name="variations[variation][]" class="form-control variation" value="' + data.variation_name + '" data-id="' + data.variation_id + '" readonly /></td>';
          table_row += '<td><input name="variations[variation_value][]" class="form-control variation_value" value="' + data.variation_value_values[variation_value_index].value + '" data-id="' + data.variation_value_values[variation_value_index].id + '" readonly /></td>';
          table_row += '<td><input name="variations[rate][]" class="form-control rate" value="' + $('input[name="rate"]').val() + '" /></td>';
          table_row += product_currency_price_cells();
          table_row += '<td><input name="variations[quantity_number][]" class="form-control quantity_number" value="1" /></td>';
          table_row += '<td><a href="#" class="btn btn-danger pull-right" onclick="delete_variation_value(this); return false;"><i class="fa fa-times"></i></a></td>';
          table_row += '</tr>';
        }
      }
      $("table.product-variations-table tbody").append(table_row);
    } else {
      if (!variation_row) {
        table_row += '<tr class="variation">';
        table_row += '<td><input class="form-control" value="' + data.variation_name + '" data-id="' + data.variation_id + '" readonly /></td>';
        table_row += '<td></td>';
        table_row += '<td></td>';
        table_row += product_currency_empty_cells();
        table_row += '<td></td>';
        table_row += '<td><a href="#" class="btn btn-danger pull-right" onclick="delete_variation(this); return false;"><i class="fa fa-times"></i></a></td>';
        table_row += '</tr>';
      }
      table_row += '<tr class="variation_value">';
      table_row += '<td><input name="variations[variation][]" class="form-control variation" value="' + data.variation_name + '" data-id="' + data.variation_id + '" readonly /></td>';
      table_row += '<td><input name="variations[variation_value][]" class="form-control variation_value" value="' + data.variation_value_value + '" data-id="' + data.variation_value_id + '" readonly /></td>';
      table_row += '<td><input name="variations[rate][]" class="form-control rate" value="' + $('input[name="rate"]').val() + '" /></td>';
      table_row += product_currency_price_cells();
      table_row += '<td><input name="variations[quantity_number][]" class="form-control quantity_number" value="1" /></td>';
      table_row += '<td><a href="#" class="btn btn-danger pull-right" onclick="delete_variation_value(this); return false;"><i class="fa fa-times"></i></a></td>';
      table_row += '</tr>';
      if (!variation_row) {
        $("table.product-variations-table tbody").append(table_row);
      } else {
        variation_row.after(table_row);
      }
    }

    change_variation_quantity_event();
  }
  $("body").on(
    "change",
    '.selectpicker.variation',
    function () {
      change_variation_values();
    }
  );
  function delete_variation_values(row) {
    if (row.hasClass('variation_value')) {
      delete_variation_values(row.next());
      row.remove();
    }
  }
  function delete_variation(row) {
    $(row)
      .parents("tr")
      .addClass("animated fadeOut", function () {
        setTimeout(function () {
          delete_variation_values($(row).parents("tr").next());
          $(row).parents("tr").remove();
        }, 50);
      });
  }
  function delete_variation_value(row) {
    $(row)
      .parents("tr")
      .addClass("animated fadeOut", function () {
        setTimeout(function () {
          $(row).parents("tr").remove();
        }, 50);
      });
  }
</script>