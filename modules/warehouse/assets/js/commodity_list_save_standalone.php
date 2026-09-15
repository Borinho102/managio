<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
/**
 * Standalone commodity save — must work even if commodity_list_js.php has a parse/runtime error.
 * Loaded after init_tail on commodity_list only.
 */
(function ($) {
  'use strict';

  function openCommodityModalFallback() {
    var $modal = $('#commodity_list-add-edit');
    if (!$modal.length) {
      return false;
    }
    try {
      $modal.modal('show');
    } catch (e) {
      $modal.addClass('in').css('display', 'block').attr('aria-hidden', 'false');
      if (!$('.modal-backdrop').length) {
        $('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
      }
    }
    try {
      $('.edit-commodity-title').addClass('hide');
      $('.add-commodity-title').removeClass('hide');
      $('#commodity_item_id').empty();
    } catch (e2) {}
    return false;
  }

  // Ajouter must work even if commodity_list_js.php failed to define new_commodity_item.
  if (typeof window.new_commodity_item !== 'function') {
    window.new_commodity_item = openCommodityModalFallback;
  }

  if (!window.__commodityStandaloneAddBound) {
    window.__commodityStandaloneAddBound = true;
    $(document).on('click', '#wh_btn_add_commodity', function (e) {
      e.preventDefault();
      e.stopPropagation();
      try {
        if (typeof window.new_commodity_item === 'function') {
          window.new_commodity_item();
        } else {
          openCommodityModalFallback();
        }
      } catch (err) {
        console.warn('[commodity add]', err);
        openCommodityModalFallback();
      }
      return false;
    });
  }

  if (window.__commodityStandaloneSaveBound) {
    return;
  }
  window.__commodityStandaloneSaveBound = true;

  var MSG_MISSING = <?php echo json_encode(_l('commodity_save_validation_failed')); ?>;
  var MSG_ERROR = <?php echo json_encode(_l('something_went_wrong')); ?>;
  var MSG_OK = <?php echo json_encode(_l('added_successfully')); ?>;
  var MSG_UPD = <?php echo json_encode(_l('updated_successfully')); ?>;
  var MSG_SKU = <?php echo json_encode(_l('sku_code_already_exists')); ?>;

  function $form() {
    return $('form.commodity_list-add-edit, #commodity_list_form').first();
  }

  function fieldVal($f, name, isSelect) {
    var $el = isSelect ? $f.find('select[name="' + name + '"]') : $f.find('input[name="' + name + '"]');
    return (($el.val() || '') + '').trim();
  }

  function setField($f, name, value, isSelect) {
    var $el = isSelect ? $f.find('select[name="' + name + '"]') : $f.find('input[name="' + name + '"]');
    if (!$el.length) {
      return;
    }
    $el.val(value);
    if (isSelect) {
      try { $el.selectpicker('refresh'); } catch (e) {}
      try { $el.trigger('change'); } catch (e) {}
    }
  }

  function labelFor($f, name) {
    var $el = $f.find('[name="' + name + '"]').first();
    var label = $.trim($el.closest('.form-group').find('label').first().clone().children().remove().end().text());
    return label || name;
  }

  function showMissing(labels) {
    var msg = MSG_MISSING + (labels && labels.length ? (' ' + labels.join(', ')) : '');
    var $alert = $('#commodity_save_validation_alert');
    if ($alert.length) {
      $alert.removeClass('hide').find('.commodity-save-validation-text').text(labels && labels.length ? labels.join(', ') : msg);
    }
    if (typeof alert_float === 'function') {
      alert_float('warning', msg, 7000);
    } else {
      alert(msg);
    }
    try { $('a[href="#interview_infor"]').tab('show'); } catch (e) {}
    var $body = $('#commodity_list-add-edit .modal-body');
    if ($body.length) {
      $body.animate({ scrollTop: 0 }, 250);
    }
  }

  function autofillRequired($f) {
    // Users often scroll past Code / Nom — prefill so Save is not a silent no-op.
    var code = fieldVal($f, 'commodity_code', false);
    var barcode = fieldVal($f, 'commodity_barcode', false);
    if (!code && barcode) {
      setField($f, 'commodity_code', barcode, false);
    }
    var desc = fieldVal($f, 'description', false);
    var skuName = fieldVal($f, 'sku_name', false);
    if (!desc && skuName) {
      setField($f, 'description', skuName, false);
      desc = skuName;
    }
    if (!desc) {
      var typeText = $.trim($f.find('select[name="commodity_type"] option:selected').text() || '');
      if (typeText && typeText.toLowerCase().indexOf('aucune') === -1 && typeText !== '') {
        setField($f, 'description', typeText, false);
      }
    }
  }

  function collectMissing($f) {
    autofillRequired($f);
    var missing = [];
    var req = [
      ['commodity_code', false],
      ['description', false],
      ['unit_id', true],
      ['rate', false],
    ];
    for (var i = 0; i < req.length; i++) {
      var name = req[i][0];
      var isSelect = req[i][1];
      var val = fieldVal($f, name, isSelect);
      var $el = isSelect ? $f.find('select[name="' + name + '"]') : $f.find('input[name="' + name + '"]');
      if (!val) {
        missing.push(labelFor($f, name));
        $el.closest('.form-group').addClass('has-error');
      } else {
        $el.closest('.form-group').removeClass('has-error');
      }
    }
    return missing;
  }

  function buildPayload($f) {
    var data = {};
    var keys = [
      'commodity_code', 'description', 'commodity_barcode', 'sku_code', 'sku_name',
      'long_description', 'commodity_type', 'unit_id', 'group_id', 'sub_group',
      'profif_ratio', 'tax', 'tax2', 'purchase_price', 'rate', 'origin',
      'style_id', 'model_id', 'size_id', 'color', 'guarantee', 'warehouse_id', 'parent_id'
    ];
    for (var i = 0; i < keys.length; i++) {
      var $el = $f.find('[name="' + keys[i] + '"]').first();
      data[keys[i]] = $el.length ? $el.val() : '';
    }
    data.long_descriptions = '';
    try {
      if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
        data.long_descriptions = tinymce.activeEditor.getContent();
      } else {
        data.long_descriptions = $f.find('textarea[name="long_descriptions"]').val() || '';
      }
    } catch (e) {}
    data.formdata = $f.serializeArray();
    data.without_checking_warehouse = $f.find('#without_checking_warehouse').is(':checked') ? 1 : 0;
    data.can_be_sold = $f.find('#can_be_sold').is(':checked') ? 'can_be_sold' : null;
    data.can_be_purchased = $f.find('#can_be_purchased').is(':checked') ? 'can_be_purchased' : null;
    data.can_be_manufacturing = $f.find('#can_be_manufacturing').is(':checked') ? 'can_be_manufacturing' : null;
    data.can_be_inventory = $f.find('#can_be_inventory').is(':checked') ? 'can_be_inventory' : null;

    var checkId = ($('#commodity_item_id').html() || '').trim();
    if (checkId) {
      data.id = $f.find('input[name="id"]').val();
    }
    return { data: data, checkId: checkId };
  }

  function finishSave(response, checkId) {
    $('.submit_btn').removeAttr('disabled');
    if (!response) {
      return;
    }
    if (response.commodityid && window.expenseDropzone && expenseDropzone.getQueuedFiles && expenseDropzone.getQueuedFiles().length > 0) {
      var add_variant = response.add_variant ? 'add_variant' : '';
      window._commoditySaveRedirectUrl = response.url || null;
      expenseDropzone.options.url = admin_url + 'warehouse/add_commodity_attachment/' + response.commodityid + '/' + add_variant;
      expenseDropzone.processQueue();
      return;
    }
    if (response.url) {
      window.location.assign(response.url);
      return;
    }
    if (typeof alert_float === 'function') {
      alert_float(checkId ? 'success' : 'warning', checkId ? MSG_UPD : MSG_OK);
    }
    $('#commodity_list-add-edit').modal('hide');
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('table.table-table_commodity_list')) {
      $('table.table-table_commodity_list').DataTable().ajax.reload(null, false);
    }
  }

  function doSave() {
    var $f = $form();
    if (!$f.length) {
      return;
    }
    $('#commodity_save_validation_alert').addClass('hide');
    var missing = collectMissing($f);
    if (missing.length) {
      showMissing(missing);
      return;
    }

    $('.submit_btn').attr('disabled', 'disabled');
    var built = buildPayload($f);
    var data = built.data;
    var checkId = built.checkId;

    var sku_data = {
      sku_code: data.sku_code || '',
      item_id: checkId ? data.id : ''
    };

    $.post(admin_url + 'warehouse/check_sku_duplicate', sku_data)
      .done(function (response) {
        try {
          response = typeof response === 'string' ? JSON.parse(response) : response;
        } catch (e) {
          $('.submit_btn').removeAttr('disabled');
          alert_float('warning', MSG_ERROR);
          return;
        }
        if (response.message == 'false' || response.message === false) {
          $('.submit_btn').removeAttr('disabled');
          alert_float('warning', MSG_SKU);
          return;
        }

        $.ajax({
          url: $f.attr('action'),
          type: 'POST',
          data: data,
          dataType: 'json',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (resp) {
          finishSave(resp, checkId);
        }).fail(function (xhr) {
          $('.submit_btn').removeAttr('disabled');
          try {
            var parsed = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (parsed && (parsed.url || parsed.commodityid)) {
              finishSave(parsed, checkId);
              return;
            }
          } catch (e) {}
          alert_float('danger', MSG_ERROR);
        });
      })
      .fail(function () {
        $('.submit_btn').removeAttr('disabled');
        alert_float('danger', MSG_ERROR);
      });
  }

  // Capture phase / delegated — wins even if other handlers fail or stopPropagation oddly.
  $(document).on('click', '#commodity_list_save_btn', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    try {
      doSave();
    } catch (err) {
      console.error('[commodity standalone save]', err);
      $('.submit_btn').removeAttr('disabled');
      if (typeof alert_float === 'function') {
        alert_float('danger', MSG_ERROR);
      }
    }
    return false;
  });

  // Dropzone init isolated from the big JS file.
  function initDz() {
    if (typeof Dropzone === 'undefined' || typeof appCreateDropzoneOptions !== 'function') {
      return;
    }
    var el = document.querySelector('#commodity_list-add-edit #dropzoneDragArea');
    if (!el || el.dropzone) {
      if (el && el.dropzone) {
        window.expenseDropzone = el.dropzone;
      }
      return;
    }
    try {
      Dropzone.autoDiscover = false;
      var preview = document.querySelector('#commodity_list-add-edit .dropzone-previews') || el;
      window.expenseDropzone = new Dropzone(el, appCreateDropzoneOptions({
        url: admin_url + 'warehouse/add_commodity_attachment/0',
        autoProcessQueue: false,
        clickable: true,
        previewsContainer: preview,
        addRemoveLinks: true,
        maxFiles: 10,
        acceptedFiles: 'image/*'
      }));
    } catch (e) {
      console.warn('[commodity dropzone]', e);
    }
  }

  $(document).on('shown.bs.modal', '#commodity_list-add-edit', function () {
    initDz();
  });
  $(function () {
    if ($('#commodity_list-add-edit').is(':visible')) {
      initDz();
    }
  });
})(jQuery);
</script>
