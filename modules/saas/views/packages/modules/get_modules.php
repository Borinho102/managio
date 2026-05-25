<div class="panel_s">
    <div class="panel-body panel-table-full">
        <div class="row">
            <div class="col-lg-12">
                <?php
                $mUrl = 'clients/';
                if (!empty(subdomain())) {
                    $mUrl = 'admin/';
                }
                $allowed_modules = $companyInfo->modules ? unserialize($companyInfo->modules) : [];

                if (!empty($all_modules)) {
                    foreach ($all_modules as $module) {
                        if (in_array($module->module_name, $allowed_modules)) {
                            continue;
                        }
                        echo form_open($mUrl . 'proceedPayment');

                        $module_name = $this->app_modules->get($module->module_name);
                        $module_title = (!empty($module->module_title)) ? $module->module_title : $module_name['headers']['module_name'];
                        // after 100 characters add ... to the description
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
                        <div class="col-lg-4 col-lg-4">
                            <div class="product-item">
                                <div class="thumbnil-price">
                                    <img class="tw-w-full"
                                         src="<?= $preview_image ?>"
                                         alt="<?= $module_title ?>"/>
                                    <div class="label label-primary product-price">
                                        <?= display_money($module->price) ?>
                                    </div>
                                </div>
                                <div class="product-content">
                                    <a href="<?= $url ?>" class=" product-title fz-18-b-black">
                                        <?= $module_title ?>
                                    </a>
                                    <div class="info fz-15-m-black-2 tw-py-5">
                                        <?= $description ?>
                                    </div>

                                    <div class="tw-flex tw-justify-between tw-items-center">
                                        <button type="button"
                                                onclick="togglePaymentGateway('<?= $module->package_module_id ?>')"
                                                class="btn btn-sm btn-primary">
                                            <?= _l('buy_now') ?>
                                        </button>
                                        <a href="<?= $url ?>"
                                           class="btn btn-sm tw-text-base tw-p-5 font-weight-normal label label-primary
                                    badge-pill"><?= _l('preview') ?>
                                        </a>

                                    </div>
                                </div>
                            </div>

                            <div class="card-body payment_gateway hidden"
                                 id="add_module_<?php echo $module->package_module_id; ?>">

                                <?php foreach ($payment_modes as $mode) {
                                    $icon = $mode['icon'];
                                    ?>

                                    <button type="submit"
                                            class="btn btn-block  mbot5 tw-flex tw-items-center tw-justify-center"
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

                        <?php echo form_close(); ?>
                    <?php }
                }

                ?>
            </div>
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
