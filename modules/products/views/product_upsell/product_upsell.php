<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo _l('product_upsell_rules'); ?></h4>
            <p class="text-muted"><?php echo _l('product_upsell_rules_desc'); ?></p>
            <div class="table-responsive mtop15">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th><?php echo _l('name'); ?></th>
                    <th><?php echo _l('product_upsell_trigger'); ?></th>
                    <th><?php echo _l('product_upsell_products'); ?></th>
                    <th><?php echo _l('active'); ?></th>
                    <th><?php echo _l('options'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $this->load->model('products/products_model');
                  foreach ($rules as $r) {
                    $triggers = !empty($r['trigger_product_ids']) ? explode(',', $r['trigger_product_ids']) : [];
                    $upsells = !empty($r['upsell_product_ids']) ? explode(',', $r['upsell_product_ids']) : [];
                    $trigger_names = [];
                    foreach ($triggers as $tid) {
                        $p = $this->products_model->get($tid);
                        if ($p) $trigger_names[] = is_object($p) ? $p->product_name : ($p['product_name'] ?? '');
                    }
                    $upsell_names = [];
                    foreach (array_slice($upsells, 0, 3) as $uid) {
                        $p = $this->products_model->get($uid);
                        if ($p) $upsell_names[] = is_object($p) ? $p->product_name : ($p['product_name'] ?? '');
                    }
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                    <td><?php echo !empty($trigger_names) ? implode(', ', $trigger_names) : _l('product_upsell_any'); ?></td>
                    <td><?php echo implode(', ', $upsell_names) . (count($upsells) > 3 ? '...' : ''); ?></td>
                    <td><?php echo $r['active'] ? _l('active') : _l('inactive'); ?></td>
                    <td>
                      <a href="#" class="btn btn-default btn-sm edit-upsell" data-id="<?php echo $r['id']; ?>" data-name="<?php echo htmlspecialchars($r['name']); ?>" data-trigger="<?php echo htmlspecialchars($r['trigger_product_ids'] ?? ''); ?>" data-upsell="<?php echo htmlspecialchars($r['upsell_product_ids'] ?? ''); ?>" data-active="<?php echo $r['active']; ?>" data-sort="<?php echo $r['sort_order']; ?>"><?php echo _l('edit'); ?></a>
                      <a href="<?php echo admin_url('products/product_upsell/delete/' . $r['id']); ?>" class="btn btn-danger btn-sm _delete"><?php echo _l('delete'); ?></a>
                    </td>
                  </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-success mtop15" data-toggle="modal" data-target="#upsellRuleModal"><?php echo _l('product_upsell_add'); ?></button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="upsellRuleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <?php echo form_open(admin_url('products/product_upsell/save')); ?>
      <input type="hidden" name="id" id="upsell_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 id="upsell_modal_title"><?php echo _l('product_upsell_add'); ?></h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label><?php echo _l('name'); ?></label>
          <input type="text" name="name" id="upsell_name" class="form-control">
        </div>
        <div class="form-group">
          <label><?php echo _l('product_upsell_trigger'); ?></label>
          <?php echo render_select('trigger_product_ids[]', $products, ['id', 'product_name'], '', [], ['multiple' => true, 'data-none-selected-text' => _l('product_upsell_any')]); ?>
          <p class="text-muted"><?php echo _l('product_upsell_trigger_help'); ?></p>
        </div>
        <div class="form-group">
          <label><?php echo _l('product_upsell_products'); ?> *</label>
          <?php echo render_select('upsell_product_ids[]', $products, ['id', 'product_name'], '', [], ['multiple' => true]); ?>
        </div>
        <div class="form-group">
          <label><?php echo _l('product_upsell_sort_order'); ?></label>
          <input type="number" name="sort_order" id="upsell_sort" class="form-control" value="0">
        </div>
        <div class="checkbox">
          <label><input type="checkbox" name="active" value="1" id="upsell_active" checked> <?php echo _l('active'); ?></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
        <button type="submit" class="btn btn-success"><?php echo _l('save'); ?></button>
      </div>
      <?php echo form_close(); ?>
    </div>
  </div>
</div>
<script>
$(function(){
  $('.edit-upsell').on('click', function(e){
    e.preventDefault();
    var id = $(this).data('id'), name = $(this).data('name'), trigger = $(this).data('trigger'), upsell = $(this).data('upsell'), active = $(this).data('active'), sort = $(this).data('sort');
    $('#upsell_id').val(id);
    $('#upsell_name').val(name);
    $('#upsell_sort').val(sort);
    $('#upsell_active').prop('checked', active == 1);
    if (trigger) {
      $('#upsellRuleModal select[name="trigger_product_ids[]"]').val(trigger.split(',').map(Number)).trigger('change');
    } else {
      $('#upsellRuleModal select[name="trigger_product_ids[]"]').val(null).trigger('change');
    }
    if (upsell) {
      $('#upsellRuleModal select[name="upsell_product_ids[]"]').val(upsell.split(',').map(Number)).trigger('change');
    }
    $('#upsell_modal_title').text('<?php echo _l('edit'); ?>');
    $('#upsellRuleModal').modal('show');
  });
  $('#upsellRuleModal').on('hidden.bs.modal', function(){
    $('#upsell_id').val('');
    $('#upsell_modal_title').text('<?php echo _l('product_upsell_add'); ?>');
  });
});
</script>
<?php init_tail(); ?>
