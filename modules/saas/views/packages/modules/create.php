<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <div class="panel_s">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo _l('set_module_price'); ?></h4>
            </div>

            <div class="panel-body">
                <?php
                if (!empty($module_info)) {
                    $id = $module_info->package_module_id;
                } else {
                    $id = null;
                }
                echo form_open(base_url('saas/packages/update_modules/' . $id), array('enctype' => 'multipart/form-data', 'data-parsley-validate' => '', 'id' => 'module_form', 'role' => 'form')); ?>


                <div class="form-group mbot15<?= count($modules) > 0 ? ' select-placeholder' : ''; ?>">
                    <label for="allowed_modules"
                           class="control-label"><?php echo _l('select') . ' ' . _l('module'); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <br/>
                    <?php if (count($modules) > 0) { ?>
                        <select class="selectpicker"
                                data-toggle="<?php echo $this->input->get('module_name'); ?>"
                                name="module_name" data-actions-box="true"
                                data-width="100%"
                                required
                                data-title="<?php echo _l('dropdown_non_selected_tex'); ?>">
                            <?php foreach ($modules as $module) {
                                if ($module['system_name'] == 'saas') {
                                    continue;
                                }
                                $selected = '';
                                if (isset($module_info)) {
                                    if ($module_info->module_name == $module['system_name']) {
                                        $selected = ' selected';
                                    }
                                } ?>
                                <option value="<?php echo $module['system_name']; ?>" <?php echo $selected; ?>>
                                    <?php echo $module['headers']['module_name']; ?></option>
                                <?php
                            } ?>
                        </select>
                    <?php } else { ?>
                        <p class="tw-text-neutral-500">
                            <?php echo _l('modules'); ?>
                        </p>
                    <?php } ?>
                </div>
                <div class="form-group">
                    <label for="field-1" class="control-label"><?= _l('title') ?>
                        <span class="text-danger">*</span></label>
                    <div class="">
                        <?php // Locale switcher for module title/description editing ?>
                        <div class="mbot10">
                            <label class="control-label">Locale</label>
                            <select id="module-locale-switcher" class="form-control input-sm" style="width:150px; display:inline-block; margin-left:8px;">
                                <?php foreach (saas_available_locales() as $loc => $label) { ?>
                                    <option value="<?= $loc ?>" <?= ($loc === 'en' ? 'selected' : '') ?>><?= $label ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <input required type="text" name="module_title" id="module_title_plain"
                               placeholder="<?= _l('enter') . ' ' . _l('module') . ' ' . _l('title') ?>"
                               class="form-control" value="<?php
                        // Populate plain field for backwards compatibility and quick edits
                        if (!empty($module_info->module_title)) {
                            // If stored as localized JSON, prefer English in the plain field
                            $plain = saas_pick_localized($module_info->module_title, 'en');
                            echo $plain;
                        }
                        ?>"/>

                        <?php // Localized title inputs (hidden by switcher) ?>
                        <?php foreach (saas_available_locales() as $loc => $label) {
                            $val = '';
                            if (!empty($module_info->module_title)) {
                                $maybe = saas_pick_localized($module_info->module_title, $loc);
                                if (!empty($maybe)) {
                                    $val = $maybe;
                                }
                            }
                            ?>
                            <input type="text" class="form-control localized-module-title localized-title-<?= $loc ?>" data-locale="<?= $loc ?>" name="module_title_locales[<?= $loc ?>]" value="<?= html_escape($val) ?>" style="margin-top:8px; display:none;" placeholder="<?= $label ?> <?= _l('title') ?>">
                        <?php } ?>

                    </div>
                </div>
                <div class="form-group">
                    <label for="field-1" class="control-label"><?= _l('price') ?>
                        <span class="text-danger">*</span></label>
                    <div class="">
                        <input required type="number" name="price"
                               placeholder="<?= _l('enter') . ' ' . _l('module') . ' ' . _l('price') ?>"
                               class="form-control" value="<?php
                        if (!empty($module_info->price)) {
                            echo $module_info->price;
                        }
                        ?>"/>
                    </div>
                </div>

                <?php // Per-currency pricing: one amount per selected currency, without monthly/yearly/lifetime splits.
                if (!empty($currencies) && is_array($currencies)) { ?>
                    <div class="form-group">
                        <label class="control-label"><?= _l('per_currency_prices') ?></label>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th><?= _l('currency') ?></th>
                                    <th><?= _l('price') ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($currencies as $cur) {
                                    $code = is_array($cur) ? strtoupper($cur['name']) : strtoupper($cur->name);
                                    $symbol = is_array($cur) ? $cur['symbol'] : $cur->symbol;
                                    $value = '';
                                    if (!empty($module_prices[strtoupper($code)])) {
                                        $currency_prices = $module_prices[strtoupper($code)];
                                        if (is_array($currency_prices)) {
                                            $value = $currency_prices['default'] ?? $currency_prices['monthly'] ?? $currency_prices['yearly'] ?? $currency_prices['lifetime'] ?? '';
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <select class="form-control" name="prices[<?= $code ?>][currency]">
                                                <?php foreach ($currencies as $currency_option) {
                                                    $option_code = is_array($currency_option) ? strtoupper($currency_option['name']) : strtoupper($currency_option->name);
                                                    $option_symbol = is_array($currency_option) ? $currency_option['symbol'] : $currency_option->symbol;
                                                    $selected = ($option_code === $code) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?= $option_code ?>" <?= $selected ?>><?= $option_code ?> (<?= $option_symbol ?>)</option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control" name="prices[<?= $code ?>][amount]" value="<?= $value ?>" placeholder="<?= _l('price') ?>"/>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>

                <div class="form-group">
                    <label for="field-1" class="control-label"><?= _l('preview_video_url') ?></label>
                    <div class="">
                        <input type="text" name="preview_video_url"
                               placeholder="<?= _l('enter') . ' ' . _l('preview_video_url') ?>"
                               class="form-control" value="<?php
                        if (!empty($module_info->preview_video_url)) {
                            echo $module_info->preview_video_url;
                        }
                        ?>"/>
                    </div>
                </div>

                <div class="row mtop20">
                    <div class="col-md-6">
                        <?php echo render_input('module_order', 'leads_status_add_edit_order', total_rows('tbl_saas_package_module') + 1, 'number'); ?>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="field-1" class="control-label"><?= _l('status') ?></label>
                            <div class="checkbox checkbox-success">
                                <input
                                    <?= (!empty($module_info->status) && $module_info->status == 'published' || empty($module_info) ? 'checked' : ''); ?>
                                        class="select_one" type="checkbox" name="status" value="published">
                                <label>
                                    <?= _l('published') ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="attachments_area">
                    <div class="attachments">
                        <div class="attachment">
                            <div class="form-group ">
                                <label for="attachment"
                                       class="control-label"><?php echo _l('screenshots'); ?></label>
                                <div class="input-group">
                                    <input type="file"
                                           extension="<?php echo 'jpg,png,jpeg,gif'; ?>"
                                           filesize="<?php echo file_upload_max_size(); ?>"
                                           class="form-control" name="attachments[0]"
                                           accept=".jpg,.png,.jpeg,.gif">
                                    <span class="input-group-btn">
                                                            <button class="btn btn-default add_more_attachments"
                                                                    data-max="<?php echo 7; ?>"
                                                                    type="button"><i class="fa fa-plus"></i></button>
                                                        </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                if (!empty($module_info->preview_image)) {
                    $all_preview_image = unserialize($module_info->preview_image);
                    foreach ($all_preview_image as $preview_image) {
                        $img = base_url('uploads/modules/' . $module_info->package_module_id . '/' . $preview_image['file_name']);
                        ?>
                        <div class="form-group mtop20 mbot20">
                            <div class="preview_image">
                                <a href="<?php echo $img; ?>" target="_blank">
                                    <img src="<?php echo $img; ?>"
                                         class="img-thumbnail" width="100px">
                                </a>
                                <a href="javascript:void(0);"
                                   data-file="<?php echo $preview_image['file_name']; ?>"
                                   class="remove_preview_image"><?php echo _l('remove'); ?></a>
                            </div>
                        </div>

                    <?php }
                }
                ?>
                <div class="remove_input">

                </div>


                <div class="form-group mtop20">
                    <?php
                    // Prepare localized descriptions
                    $descriptions_plain = '';
                    $descriptions_locales = [];
                    if (!empty($module_info->descriptions)) {
                        $descriptions_plain = saas_pick_localized($module_info->descriptions, 'en');
                        foreach (saas_available_locales() as $loc => $label) {
                            $descriptions_locales[$loc] = saas_pick_localized($module_info->descriptions, $loc);
                        }
                    }
                    ?>

                    <?php echo render_textarea('descriptions', _l('descriptions'), $descriptions_plain, [], [], '', 'tinymce'); ?>

                    <?php // Localized description editors (hidden by switcher) ?>
                    <?php foreach (saas_available_locales() as $loc => $label) { ?>
                        <label class="localized-description-label localized-desc-<?= $loc ?>" style="display:none; margin-top:10px;"><?= $label ?> <?= _l('descriptions') ?></label>
                        <textarea id="descriptions_<?= $loc ?>" style="display:none;" class="localized-description localized-desc-<?= $loc ?>" name="descriptions_locales[<?= $loc ?>]"><?= html_escape($descriptions_locales[$loc] ?? '') ?></textarea>
                    <?php } ?>
                </div>

                <script>
                    (function ($) {
                        $(function () {
                            function destroyTinymceFor(selector) {
                                if (typeof tinyMCE !== 'undefined') {
                                    try {
                                        tinyMCE.remove(selector);
                                    } catch (e) {}
                                }
                                if (typeof tinymce !== 'undefined') {
                                    try {
                                        tinymce.remove(selector);
                                    } catch (e) {}
                                }
                            }

                            function initTinymceFor(selector) {
                                var $el = $(selector);
                                if (!$el.length) {
                                    return;
                                }
                                var id = $el.attr('id');
                                if (!id) {
                                    return;
                                }
                                if (typeof tinymce !== 'undefined') {
                                    if (tinymce.get(id)) {
                                        return;
                                    }
                                    try {
                                        tinymce.init({ selector: '#' + id });
                                    } catch (e) {}
                                }
                                if (typeof tinyMCE !== 'undefined' && !tinyMCE.get(id)) {
                                    try {
                                        tinyMCE.init({ selector: '#' + id });
                                    } catch (e) {}
                                }
                            }

                            function syncLocalizedContentBeforeSubmit() {
                                var $enField = $('textarea[name="descriptions"]');
                                var $enLocaleField = $('textarea[name="descriptions_locales[en]"]');

                                if ($enLocaleField.length && !$enLocaleField.val()) {
                                    $enLocaleField.val($enField.val());
                                }

                                $('.localized-description').each(function () {
                                    var $field = $(this);
                                    var id = $field.attr('id');
                                    if (!id) {
                                        return;
                                    }
                                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get(id)) {
                                        $field.val(tinyMCE.get(id).getContent());
                                    }
                                    if (typeof tinymce !== 'undefined' && tinymce.get(id)) {
                                        $field.val(tinymce.get(id).getContent());
                                    }
                                });
                            }

                            function showLocale(locale) {
                                var isEnglish = (locale === 'en');

                                $('#module_title_plain').toggle(isEnglish);
                                $('.localized-module-title').hide();
                                $('.localized-description, .localized-description-label').hide();

                                if (isEnglish) {
                                    $('textarea[name="descriptions"]').closest('.form-group').show();
                                    $('textarea[name="descriptions"]').show();
                                    destroyTinymceFor('.localized-description');
                                    initTinymceFor('textarea[name="descriptions"]');
                                    return;
                                }

                                $('textarea[name="descriptions"]').closest('.form-group').hide();
                                $('textarea[name="descriptions"]').hide();
                                destroyTinymceFor('textarea[name="descriptions"]');

                                $('.localized-module-title[data-locale="' + locale + '"]').show();
                                $('.localized-desc-' + locale).show();
                                initTinymceFor('#descriptions_' + locale);
                            }

                            var $switcher = $('#module-locale-switcher');
                            $switcher.on('change', function () {
                                showLocale($(this).val());
                            });

                            showLocale($switcher.val());

                            $('#module_form').on('submit', function () {
                                syncLocalizedContentBeforeSubmit();
                            });
                        });
                    })(jQuery);
                </script>

                <div class="btn-bottom-toolbar text-right">
                    <button type="submit"
                            class="btn-tr btn btn-primary mright5 text-right invoice-form-submit save-as-draft transaction-submit">
                        <?php echo _l('update'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo form_close(); ?>
<script>
    $(function () {
        $('#module_form').appFormValidator();

        $('.remove_preview_image').on('click', function () {
            var file = $(this).data('file');
            var input = '<input type="hidden" name="remove_preview_image[]" value="' + file + '">';
            $('.remove_input').append(input);
            $(this).parent().parent().remove();
        });
    });
</script>