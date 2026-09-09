<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s section-heading section-products">
    <div class="panel-body">
        <h4 class="no-margin"><?php echo _l('my_downloads'); ?></h4>
    </div>
</div>
<div class="panel_s">
    <div class="panel-body">
        <?php if (empty($downloads)) { ?>
        <p class="text-muted"><?php echo _l('no_downloads'); ?></p>
        <?php } else { ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?php echo _l('product_name'); ?></th>
                    <th><?php echo _l('Order Date'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($downloads as $d) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['product_name']); ?></td>
                    <td><?php echo _d($d['order_date']); ?></td>
                    <td>
                        <a href="<?php echo site_url('products/client/download/' . $d['order_item_id']); ?>" class="btn btn-success btn-sm">
                            <i class="fa fa-download"></i> <?php echo _l('download'); ?>
                        </a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } ?>
    </div>
</div>
