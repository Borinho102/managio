<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s" id="TableData">
          <div class="panel-body">
            <?php if (has_permission('products', '', 'create')) { ?>
              <a href="<?php echo admin_url('products/exit_popups/add'); ?>" class="btn btn-info pull-left display-block">
                <?php echo _l('exit_popup_new'); ?>
              </a>
            <?php } ?>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <div class="panel_s">
              <div class="panel-body">
                <?php
                render_datatable([
                  _l('exit_popup_name'),
                  _l('exit_popup_title'),
                  _l('exit_popup_trigger'),
                  _l('exit_popup_target'),
                  _l('active'),
                  _l('exit_popup_impressions'),
                  _l('exit_popup_clicks'),
                ], 'exit_popup');
                ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Exit Popup Preview Modal -->
<div class="modal fade" id="exit-popup-preview-modal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title"><?php echo _l('preview'); ?> - <?php echo _l('exit_popups'); ?></h4>
      </div>
      <div class="modal-body" id="exit-popup-preview-body">
        <p class="text-muted text-center"><?php echo _l('loading'); ?>...</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>
$(function(){
  initDataTable('.table-exit_popup', '<?php echo admin_url('products/exit_popups'); ?>', undefined, undefined, 'undefined', [0, 'asc']);
  $(document).on('click', '.exit-popup-preview', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    if (!id) return;
    var $modal = $('#exit-popup-preview-modal');
    var $body = $('#exit-popup-preview-body');
    $body.html('<p class="text-muted text-center"><?php echo _l('loading'); ?>...</p>');
    $modal.modal('show');
    $.get('<?php echo admin_url('products/exit_popups/preview/'); ?>' + id, function(res){
      if (res && res.success) {
        function esc(s){ return (s==null||s==='') ? '' : $('<div>').text(s).html(); }
        var html = '';
        if (res.image_url) {
          html += '<p><img src="' + esc(res.image_url) + '" class="img-responsive" style="max-width:100%;border-radius:6px;margin-bottom:12px;" alt="" /></p>';
        }
        html += '<h4 style="margin-top:0;">' + esc(res.title) + '</h4>';
        html += '<div class="exit-popup-preview-body" style="margin-bottom:12px;line-height:1.5;">' + (res.body || '') + '</div>';
        if (res.coupon_code) {
          html += '<p><span class="label label-default" style="font-size:13px;padding:8px 12px;">' + esc(res.coupon_code) + '</span></p>';
        }
        if (res.cta_text && res.cta_url) {
          html += '<p><a href="' + esc(res.cta_url) + '" target="_blank" class="btn btn-success">' + esc(res.cta_text) + '</a></p>';
        }
        $body.html(html || '<p class="text-muted"><?php echo _l('no_data_found'); ?></p>');
      } else {
        $body.html('<p class="text-danger">' + (res && res.message ? res.message : '<?php echo _l('error_occurred'); ?>') + '</p>');
      }
    }, 'json').fail(function(){
      $body.html('<p class="text-danger"><?php echo _l('error_occurred'); ?></p>');
    });
  });
});
</script>
