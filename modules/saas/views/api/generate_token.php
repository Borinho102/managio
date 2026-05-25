<div>

    <div class="_buttons tw-mb-2 sm:tw-mb-4">

        <a href="<?php echo saas_url('api/generate_token'); ?>"
           class="btn btn-primary pull-left display-block"
           class="btn btn-xs btn-info" data-toggle="modal" data-placement="top" data-target="#myModal"
        >
            <i class="fa-regular fa-plus tw-mr-1"></i>
            <?= _l('generate') . ' ' . _l('token') ?>
        </a>
        <div class="clearfix"></div>
    </div>
    <div class="panel_s">
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped DataTables " id="DataTables" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th><?= _l('title') ?></th>
                        <th><?= _l('name') ?></th>
                        <th><?= _l('status') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <script type="text/javascript">
                        list = base_url + "saas/api/tokenList";
                    </script>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        $(function () {
            'use strict';
            initDataTable('#DataTables', list, undefined, undefined, 'undefined');
        });
        // document onload
    </script>

</div>