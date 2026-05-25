<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s tw-mt-2">
                    <div class="panel-body">
                        <div class="table-responsive mt-4">
                            <table class="table table-bordered dt-table" data-order-col="0" data-order-type="desc">
                                <thead>
                                    <tr>
                                        <th><?= _l('#'); ?></th>
                                        <th><?= _l('invoice'); ?></th>
                                        <th><?= _l('customer'); ?></th>
                                        <th><?= _l('payment_mode'); ?></th>
                                        <th><?= _l('amount'); ?></th>
                                        <th><?= _l('date'); ?></th>
                                        <th><?= _l('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_data as $row): ?>
                                        <tr>
                                            <td><?= $row['sr_no']; ?></td>
                                            <td><?= $row['invoice_id']; ?></td>
                                            <td><?= $row['client_name']; ?></td>
                                            <td><?= $row['name']; ?></td>
                                            <td><?= $row['invoice_amount']; ?></td>
                                            <td><?= $row['created_at']; ?></td>
                                            <td><?= $row['action']; ?></td>
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