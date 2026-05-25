<?php
if (!empty($invoices_to_merge)) {
    ?>
    <div class="alert alert-info">
        <div class="row">
            <div id="merge" class="col-md-6">
                <?php
                $this->load->view('packages/merge_invoice', ['invoices_to_merge' => $invoices_to_merge]);
                ?>
            </div>
        </div>
    </div>

    <?php
}
if (!empty($companyInfo)) {
    $allowed_modules = $companyInfo->modules ? unserialize($companyInfo->modules) : [];
    ?>
    <style type="text/css">
        .fs-16 {
            font-size: 2rem !important;
        }

        .fw-600 {
            font-weight: 600 !important;
        }

        .text-reset {
            color: inherit !important;
        }

        .rating i.hover,
        .rating i.active,
        .text-rating {
            color: #ffa707;
        }

        .rating i {
            color: #c3c3c5;
            font-size: 1rem;
            letter-spacing: -1px;
            -webkit-transition: all 0.3s;
            transition: all 0.3s;
        }

        .text-truncate-3 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .pt-4,
        .py-4 {
            padding-top: 1.5rem !important;
        }

        .card {
            -webkit-box-shadow: 0 0 13px 0 rgb(82 63 105 / 5%);
            box-shadow: 0 0 13px 0 rgb(82 63 105 / 5%);
            background-color: #fff;
            margin-bottom: 20px;
            border-color: #ebedf2;
        }

        .card {
            position: relative;
            display: -ms-flexbox;
            display: flex;
            -ms-flex-direction: column;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0, 0, 0, .125);
            border-radius: .25rem;
        }

        .card-body {
            -ms-flex: 1 1 auto;
            flex: 1 1 auto;
            min-height: 1px;
            padding: 1.25rem;
        }

        .card .card-footer {
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
            -webkit-box-pack: justify;
            -ms-flex-pack: justify;
            justify-content: space-between;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 4px;
            border-top: 1px solid #ebedf2;
            background-color: transparent;
            padding: 12px 25px;
        }

        .card-footer:last-child {
            border-radius: 0 0 calc(.25rem - 1px) calc(.25rem - 1px);
        }

        .fs-22 {
            font-size: 1.075rem !important;
        }

        .fw-600 {
            font-weight: 600 !important;
        }

        .img-fluid {
            max-width: 100%;
            height: auto;
        }
    </style>
    <div class="panel panel-custom">
        <!-- Table -->
        <div class="panel-body ">
            <div class="col-lg-12 col-md-12 row ">
                <?php
                $mUrl = 'admin/';
                foreach ($moduleInfo as $module) {

                    echo form_open($mUrl . 'proceedPayment');

                    $description = $this->app_modules->get($module->module_name);
                    if (in_array($module->module_name, $allowed_modules)) {
                        continue;
                    }
                    $module_title = (!empty($module->module_title)) ? $module->module_title : $module_name['headers']['module_name'];

//                    $module_title = moduleTitle($description);

                    $length = 350;
                    $description = strlen($module->descriptions) > $length ? substr($module->descriptions, 0, $length) . '...' : $module->descriptions;
                    $description = strip_tags($description);

                    $preview_image = '';
                    if (!empty($module->preview_image)) {
                        $preview_image = unserialize($module->preview_image);
                        $preview_image = base_url('uploads/modules/' . $module->package_module_id . '/' . $preview_image[0]['file_name']);
                    } else {
                        $preview_image = module_dir_url('saas/uploads/Image_not_available.png');
                        // remove last slash from url if exist
                        $preview_image = rtrim($preview_image, '/');
                    }
                    $url = base_url($mUrl . 'module_details/' . $module->module_name);

                    echo form_hidden('companies_id', $companyInfo->companies_id);
                    echo form_hidden('company_history_id', $companyInfo->company_history_id);
                    echo form_hidden('package_module_id', $module->package_module_id);
                    echo form_hidden('module_name', $module->module_name);
                    echo form_hidden('price', $module->price);
                    echo form_hidden('name', $module_title);

                    ?>

                    <div class="col-lg-4 col-md-6 row tw-ml-1">
                        <div class="card addon-card">
                            <div class="card-body">
                                <a href="<?= $url ?>" target="_blank">
                                    <img class="img-fluid"
                                         src="<?= $preview_image ?>"
                                         alt="<?= $module_title ?>"/></a>
                                <div class="pt-4">
                                    <a class="fs-16 fw-600 text-reset" href="<?= $url ?>"
                                       target="_blank"><?php echo $module_title ?></a>
                                    <div class="rating mb-2">
                                    </div>
                                    <div class="mar-no text-truncate-3">
                                        <p class="mar-no text-truncate-3"><?= $description ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="text-danger fs-22 fw-600"><?= display_money($module->price) ?>
                                    / <?= _l('month') ?>
                                </div>
                                <div class=""><a href="<?= $url ?>" target="_blank"
                                                 class="btn btn-sm btn-secondary tw-mr-2"><?= _l('preview') ?></a>
                                    <button type="button"
                                            onclick="togglePaymentGateway('<?= $module->package_module_id ?>')"
                                            class="btn btn-sm btn-primary">
                                        <?= _l('buy_now') ?>
                                    </button>
                                </div>
                            </div>

                            <div class="card-body payment_gateway hidden"
                                 id="add_module_<?php echo $module->package_module_id; ?>">

                                <?php foreach ($payment_modes as $mode) {
                                    $icon = $mode['icon'];
                                    ?>

                                    <button type="submit"
                                            class="btn btn-block mtop15 mbot15 tw-flex tw-items-center tw-justify-center"
                                            value="<?php echo $mode['id']; ?>"
                                            required
                                            id="pm_<?php echo $mode['id']; ?>" name="paymentmode">
                                        <?php
                                        if (strpos($icon, '.svg') !== false) {
                                            echo '<img src="' . base_url('uploads/gateways/' . $icon) . '" class="mright5" alt="' . _l($mode['gateway_name']) . '" style="max-height: 20px;">';
                                        } else {
                                            echo $icon;
                                        }
                                        ?>
                                        <span class="mleft4 "
                                        ><?php echo _l($mode['gateway_name']); ?></span>
                                    </button>

                                    <?php if (!empty($mode['description'])) { ?>
                                        <div class="mbot15">
                                            <?php echo $mode['description']; ?>
                                        </div>
                                    <?php }
                                } ?>

                            </div>
                        </div>
                    </div>

                    <?php echo form_close(); ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        function togglePaymentGateway(id) {
            // hide all other payment gateways
            const elements = document.querySelectorAll('.payment_gateway');
            // toggle the current payment gateway
            const element = document.getElementById('add_module_' + id);
            element.classList.toggle('hidden');
            elements.forEach(function (element) {
                if (element.id !== 'add_module_' + id) {
                    element.classList.add('hidden');
                }
            });
        }

    </script>

<?php } ?>
