<div class="panel panel-custom">
    <div class="panel-heading tw-p-4">
        <h4 class="panel-title "><?= _l('payment') . '  ' . _l('summery') . ' ' . _l('module') . ' - ' ?>
            <span id="package_name"><?= $name ?></span>
            <span id="package_name">(<?= display_money($price) ?>)</span>
        </h4>
    </div>
    <div class="panel-body form-horizontal">
        <div class=" mb-lg ">
            <div class="col-lg-7 col-md-7 ">
                <div class="">
                    <?php
                    echo $paymentForm;
                    ?>
                </div>
            </div>
        </div>

    </div>
</div>


