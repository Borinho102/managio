<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-justify-between tw-items-center tw-gap-x-6">
                    <div class="tw-flex tw-justify-between tw-items-center tw-gap-x-1">
                        <?php if (staff_can('create', MANUAL_PAYMENT_GATEWAY_MODULE_NAME)) { ?>
                            <a href="<?= admin_url('manual_payment_gateway/create'); ?>"
                               class="btn btn-primary">
                                <i class="fa-regular fa-plus tw-mr-1"></i>
                                <?= _l('mpg_add_new'); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
                <div class="panel_s tw-mt-2">
                    <div class="panel-body">
                        <div class="table-responsive mt-4">
                            <table class="table table-bordered dt-table" data-order-col="0" data-order-type="desc">
                                <thead>
                                    <tr>
                                        <th><?= _l('#'); ?></th>
                                        <th><?= _l('name'); ?></th>
                                        <th><?= _l('status'); ?></th>
                                        <?php if (staff_can('edit', MANUAL_PAYMENT_GATEWAY_MODULE_NAME) || staff_can('delete', MANUAL_PAYMENT_GATEWAY_MODULE_NAME)) { ?>
                                            <th><?= _l('mpg_action'); ?></th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_data as $row): ?>
                                        <tr>
                                            <td><?= $row['sr_no']; ?></td>
                                            <td><?= $row['name']; ?></td>
                                            <td><?= $row['status']; ?></td>
                                            <td><?= $row['actions']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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
</body>
</html>