<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade" id="product_category_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button group="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">
                    <span class="edit-title"><?php echo _l('edit_category_heading'); ?></span>
                    <span class="add-title"><?php echo _l('new_category_heading'); ?></span>
                </h4>
            </div>
            <?php echo form_open('products/products_categories/category', ['id'=>'product-category-modal']); ?>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php
                        $parent_options = [['p_category_id' => 0, 'p_category_name' => _l('dropdown_non_selected_tex')]];
                        $all_cats = $this->product_category_model->get();
                        foreach ($all_cats as $c) {
                            $parent_options[] = ['p_category_id' => $c['p_category_id'], 'p_category_name' => $c['p_category_name']];
                        }
                        echo render_select('parent_id', $parent_options, ['p_category_id', 'p_category_name'], 'parent_category', 0, ['data-none-selected-text' => _l('dropdown_non_selected_tex')]);
                        ?>
                        <?php echo render_input('p_category_name', 'category_name'); ?>
                        <?php echo render_textarea('p_category_description', 'category_description'); ?>
                        <?php echo form_hidden('p_category_id'); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button group="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button group="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="<?php echo module_dir_url('products', 'assets/js/category_modal.js'); ?>"></script>
