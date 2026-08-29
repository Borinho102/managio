<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if (staff_can('create', 'fournisseurs')) { ?>
                <div class="tw-mb-2">
                    <a href="<?php echo admin_url('fournisseurs/fournisseur'); ?>" class="btn btn-primary">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php echo _l('new_fournisseur'); ?>
                    </a>
                </div>
                <?php } ?>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('fournisseur_company'),
                            _l('fournisseur_contact_fullname'),
                            _l('fournisseur_phonenumber'),
                            _l('fournisseur_email'),
                            _l('fournisseur_category'),
                            _l('fournisseur_active'),
                        ], 'fournisseurs'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function() {
    initDataTable('.table-fournisseurs', window.location.href, [5], [5]);
});
</script>
</body>
</html>
