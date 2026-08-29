<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700"><?php echo e($title); ?></h4>
                <?php echo form_open($this->uri->uri_string()); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <?php $attrs = (isset($fournisseur) ? [] : ['autofocus' => true]); ?>
                        <?php $value = (isset($fournisseur) ? $fournisseur->company : ''); ?>
                        <?php echo render_input('company', 'fournisseur_company', $value, 'text', $attrs); ?>

                        <div class="row">
                            <div class="col-md-6">
                                <?php $value = (isset($fournisseur) ? $fournisseur->vat : ''); ?>
                                <?php echo render_input('vat', 'fournisseur_vat', $value); ?>
                            </div>
                            <div class="col-md-6">
                                <?php
                                $categoryOptions = [];
                                foreach ($categories as $key => $label) {
                                    $categoryOptions[] = ['id' => $key, 'name' => $label];
                                }
                                $selected = (isset($fournisseur) ? $fournisseur->category : '');
                                echo render_select('category', $categoryOptions, ['id', 'name'], 'fournisseur_category', $selected);
                                ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <?php $value = (isset($fournisseur) ? $fournisseur->phonenumber : ''); ?>
                                <?php echo render_input('phonenumber', 'fournisseur_phonenumber', $value); ?>
                            </div>
                            <div class="col-md-6">
                                <?php $value = (isset($fournisseur) ? $fournisseur->email : ''); ?>
                                <?php echo render_input('email', 'fournisseur_email', $value, 'email'); ?>
                            </div>
                        </div>

                        <?php $value = (isset($fournisseur) ? $fournisseur->website : ''); ?>
                        <?php echo render_input('website', 'fournisseur_website', $value); ?>

                        <hr />
                        <h5 class="tw-font-semibold tw-mb-3"><?php echo _l('fournisseur_contact_fullname'); ?></h5>

                        <div class="row">
                            <div class="col-md-4">
                                <?php $value = (isset($fournisseur) ? $fournisseur->contact_fullname : ''); ?>
                                <?php echo render_input('contact_fullname', 'fournisseur_contact_fullname', $value); ?>
                            </div>
                            <div class="col-md-4">
                                <?php $value = (isset($fournisseur) ? $fournisseur->contact_phonenumber : ''); ?>
                                <?php echo render_input('contact_phonenumber', 'fournisseur_contact_phonenumber', $value); ?>
                            </div>
                            <div class="col-md-4">
                                <?php $value = (isset($fournisseur) ? $fournisseur->contact_email : ''); ?>
                                <?php echo render_input('contact_email', 'fournisseur_contact_email', $value, 'email'); ?>
                            </div>
                        </div>

                        <hr />

                        <?php $value = (isset($fournisseur) ? $fournisseur->address : ''); ?>
                        <?php echo render_textarea('address', 'fournisseur_address', $value); ?>

                        <div class="row">
                            <div class="col-md-4">
                                <?php $value = (isset($fournisseur) ? $fournisseur->city : ''); ?>
                                <?php echo render_input('city', 'fournisseur_city', $value); ?>
                            </div>
                            <div class="col-md-4">
                                <?php $value = (isset($fournisseur) ? $fournisseur->state : ''); ?>
                                <?php echo render_input('state', 'fournisseur_state', $value); ?>
                            </div>
                            <div class="col-md-4">
                                <?php $value = (isset($fournisseur) ? $fournisseur->zip : ''); ?>
                                <?php echo render_input('zip', 'fournisseur_zip', $value); ?>
                            </div>
                        </div>

                        <?php
                        $selected = (isset($fournisseur) ? $fournisseur->country : '');
                        echo render_select('country', $countries, ['country_id', ['short_name']], 'fournisseur_country', $selected, ['data-none-selected-text' => _l('dropdown_non_selected_tex')]);
                        ?>

                        <?php $value = (isset($fournisseur) ? $fournisseur->notes : ''); ?>
                        <?php echo render_textarea('notes', 'fournisseur_notes', $value); ?>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="active" id="active" value="1" <?php
                            if (!isset($fournisseur) || (isset($fournisseur) && $fournisseur->active == 1)) {
                                echo 'checked';
                            }
                            ?>>
                            <label for="active"><?php echo _l('fournisseur_active'); ?></label>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <a href="<?php echo admin_url('fournisseurs'); ?>" class="btn btn-default"><?php echo _l('cancel'); ?></a>
                        <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
