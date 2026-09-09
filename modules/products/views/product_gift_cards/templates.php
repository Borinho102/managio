<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo _l('product_gift_card_templates'); ?></h4>
            <a href="<?php echo admin_url('products/product_gift_cards/template'); ?>" class="btn btn-success mtop15"><?php echo _l('product_gift_card_add_template'); ?></a>
            <a href="<?php echo admin_url('products/product_gift_cards'); ?>" class="btn btn-default mtop15"><?php echo _l('product_gift_cards'); ?></a>
            <div class="table-responsive mtop15">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th><?php echo _l('name'); ?></th>
                    <th><?php echo _l('product_gift_card_design'); ?></th>
                    <th><?php echo _l('active'); ?></th>
                    <th><?php echo _l('options'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($templates as $t) { ?>
                  <tr>
                    <td><?php echo htmlspecialchars($t['name']); ?></td>
                    <td>
                      <?php if (!empty($t['design_image'])) {
                        $img = get_upload_path_by_type('products') . $t['design_image'];
                        if (file_exists($img)) {
                          echo '<img src="' . module_dir_url('products', 'uploads') . '/' . htmlspecialchars($t['design_image']) . '" style="max-width:100px;max-height:60px;">';
                        } else {
                          echo _l('product_gift_card_html_design');
                        }
                      } else {
                        echo _l('product_gift_card_html_design');
                      } ?>
                    </td>
                    <td><?php echo $t['active'] ? _l('active') : _l('inactive'); ?></td>
                    <td>
                      <a href="<?php echo admin_url('products/product_gift_cards/template/' . $t['id']); ?>" class="btn btn-default btn-sm"><?php echo _l('edit'); ?></a>
                      <a href="<?php echo admin_url('products/product_gift_cards/delete_template/' . $t['id']); ?>" class="btn btn-danger btn-sm _delete"><?php echo _l('delete'); ?></a>
                    </td>
                  </tr>
                  <?php } ?>
                  <?php if (empty($templates)) { ?>
                  <tr><td colspan="4"><?php echo _l('no_data'); ?></td></tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
