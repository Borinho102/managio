<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css"/>
<?php
$mUrl = 'clients/';
if (!empty(subdomain())) {
    $mUrl = 'admin/';
}
$module_name = $this->app_modules->get($module->module_name);
$back_url = base_url('clients/get_modules');
if (!empty(subdomain())) {
    $back_url = base_url('admin/get_modules');
} else if (!empty(is_super_admin())) {
    $back_url = base_url('saas/packages/modules');
}
$module_title = (!empty($module->module_title)) ? $module->module_title : $module_name['headers']['module_name'];
echo form_open($mUrl . 'proceedPayment');
?>
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="panel_s">
            <div class="panel-body panel-table-full">
                <div class="">
                    <a href="<?= $back_url ?>" class="btn btn-info ">
                        <i class="fa fa-arrow-left"></i>
                        <?= _l('back') ?>
                    </a>
                    <?php
                    if (!empty(is_super_admin())) {
                        ?>
                        <a href="<?= base_url('saas/packages/set_module_price/' . $module->package_module_id) ?>"
                           class="btn btn-success ">
                            <i class="fa fa-pencil"></i>
                            <?= _l('edit') ?>
                        </a>
                        <?php
                    }
                    ?>
                    <button type="button"
                            onclick="togglePaymentGateway('<?= $module->package_module_id ?>')"
                            class="pull-right  btn btn-sm btn-primary">
                        <?= _l('buy_now') ?>
                    </button>
                </div>

                <h2 class="mbot20"><?= $module_title ?>
                    <span class="pull-right">
                        <?= display_money($module->price) ?>
                    </span>
                </h2>
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
                <div class="creative-photo tw-mb-6 ">
                    <div>
                        <?php

                        echo form_hidden('companies_id', $companyInfo->companies_id);
                        echo form_hidden('company_history_id', $companyInfo->company_history_id);
                        echo form_hidden('package_module_id', $module->package_module_id);
                        echo form_hidden('module_name', $module->module_name);
                        echo form_hidden('price', $module->price);
                        echo form_hidden('name', $module_title);

                        $image_url = module_dir_url('saas/uploads');
                        if (!empty($module->preview_image)) {
                            $preview_image = unserialize($module->preview_image);
                            $image_url = base_url('uploads/modules/' . $module->package_module_id) . '/';

                            if (empty($preview_image)) {
                                $preview_image = [];
                                $preview_image[] = ['file_name' => 'Image_not_available.png'];
                            }
                        } else {
                            $preview_image = [];
                            $preview_image[] = ['file_name' => 'Image_not_available.png'];
                        }

                        foreach ($preview_image as $image) {
                            ?>
                            <a data-fancybox="gallery"
                               style="display: <?= $image['file_name'] == $preview_image[0]['file_name'] ? 'block' : 'none' ?>"
                               href="<?= $image_url . $image['file_name'] ?>">
                                <img class="tw-w-full"
                                     src="<?= $image_url . $image['file_name'] ?>"
                                     width="100%" height="auto"
                                     alt=""/>
                            </a>
                            <?php
                        }

                        ?>
                    </div>
                </div>
                <div class="p-description">
                    <h3><?= _l('description') ?></h3>
                    <p><?= $description = ($module->descriptions);
                        check_for_links($description); ?></p>
                    <?php
                    if (!empty($module->preview_video_url)) {
                        $preview_video_url = $module->preview_video_url;
                        // check is youtube video or not
                        $is_youtube = strpos($module->preview_video_url, 'youtube');
                        if (empty($is_youtube)) {
                            $is_youtube = strpos($module->preview_video_url, 'youtu.be');
                        }
                        if ($is_youtube) {
                            // check is embed url or not
                            $is_embed = strpos($module->preview_video_url, 'embed');
                            if (!$is_embed) {
                                $video_id = explode('=', $module->preview_video_url);
                                $video_id = end($video_id);
                                $module->preview_video_url = 'https://www.youtube.com/embed/' . $video_id;
                            }
                        }

                        ?>
                        <h3><?= _l('video') ?></h3>
                        <div class="creative-photo tw-mb-6 ">
                            <p>
                                <a href="<?= $preview_video_url ?>"
                                   target="_blank">
                                    <?= _l('video_preview_description', $module->preview_video_url) ?>
                                </a>

                            </p>
                            <div>
                                <iframe class="" width="100%" height="315"
                                        src="<?= $module->preview_video_url ?>"
                                        title="YouTube video player" frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen>
                                </iframe>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo form_close(); ?>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    Fancybox.bind('[data-fancybox="gallery"]', {
        //
    });
</script>
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