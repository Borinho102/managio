<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="clearfix"></div>
            <div class="tw-mt-12 sm:tw-mt-0 col-md-10 col-md-offset-1">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700 tw-flex tw-items-center tw-space-x-2">
                    <span><?= $title; ?></span>
                </h4>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tab-content">
                            <div class="row">
                                <div class="col-md-12">
                                    <?php echo form_open(admin_url('banner/save_news_ticker'), ['id' => 'news-ticker-form'], ['id' => $news_ticker->id ?? '']); ?>
                                    <div class="row">
                                        <?= render_input('news_title', 'news_title', $news_ticker->news_title ?? '', '', [], [], 'col-md-4'); ?>
                                        <div class="col-md-4">
                                           <?= render_color_picker('title_bg_color', _l('title_bg_color'), $news_ticker->title_bg_color ?? ''); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <?= render_color_picker('title_text_color', _l('title_text_color'), $news_ticker->title_text_color ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="news_description row" id="news_description_0">
                                        <div class="col-md-8">
                                            <?= render_input('news[0][news_description]', '<small class="req text-danger">* </small>' . _l('news_description'), $news_ticker->news_details[0]['news_description'] ?? '', '', [], [], '', 'newsDescription'); ?>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="news[0][description_text_color]" class="control-label"><small class="req text-danger">* </small><?= _l('description_text_color'); ?></label>
                                                <div class="input-group mbot15 colorpicker-input colorpicker-element">
                                                    <input type="text" name="news[0][description_text_color]" id="news[0][description_text_color]" class="form-control descriptionTextColor" value="<?= $news_ticker->news_details[0]['description_text_color'] ?? ''; ?>">
                                                    <span class="input-group-addon"><i></i></span>
                                                </div>
                                            </div>                                        
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" id="addNewsDescription" class="btn btn-success pull-right <?= is_mobile() ? 'mbot10' : 'mtop20'; ?>"><i class="fa-regular fa-plus"></i></button>
                                        </div>
                                    </div>
                                    <div id="append_new_row">
                                        <?php if (isset($news_ticker) && count($news_ticker->news_details) > 1) {
                                            $counter = 0;
                                            foreach ($news_ticker->news_details as $key => $row) {
                                                if ($key > 0) { ?>
                                                    <div class="news_description row" id="news_description_<?= $counter; ?>">
                                                        <div class="col-md-8">
                                                            <?= render_input('news[' . $counter . '][news_description]', '<small class="req text-danger">* </small>' . _l('news_description'), $row['news_description'] ?? '', '', [], [], '', 'newsDescription'); ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="form-group">
                                                                <label for="news[<?= $counter; ?>][description_text_color]" class="control-label"><small class="req text-danger">* </small><?= _l('description_text_color'); ?></label>
                                                                <div class="input-group mbot15 colorpicker-input colorpicker-element">
                                                                    <input type="text" name="news[<?= $counter; ?>][description_text_color]" id="news[<?= $counter; ?>][description_text_color]" class="form-control descriptionTextColor" value="<?= $row['description_text_color'] ?? ''; ?>">
                                                                    <span class="input-group-addon"><i></i></span>
                                                                </div>
                                                            </div>                                        
                                                        </div>
                                                        <div class="col-md-1">
                                                            <button type="button" class="btn btn-danger pull-right removeNewsDescription <?= is_mobile() ? 'mbot10' : 'mtop20'; ?>"><i class="fa-regular fa-trash-can"></i></button>
                                                        </div>
                                                    </div>
                                            <?php }
                                                $counter++;
                                            } ?>
                                        <?php } ?>
                                    </div>
                                    <div class="row">
                                         <?php echo render_select('news_type', get_news_types(), ['id', 'name'], 'news_type', $news_ticker->news_type ?? '', [], [], 'col-md-6'); ?>
                                        <div class="col-md-6">
                                            <label class="control-label"><?= _l('news_title_icon'); ?><span class="text-danger text-sm"><?= _l('icon_example'); ?></span></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="title_icon" id="title_icon" value="<?= $news_ticker->title_icon ?? ''; ?>">
                                                <span class="input-group-btn">
                                                    <a href="https://fontawesome.com/icons" class="btn btn-default"
                                                        target="_blank"><?= _l('fontawesome'); ?></a>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <?php echo render_date_input('start_date', 'start_date', isset($news_ticker->start_date) ? _d($news_ticker->start_date) : '', ['data-date-min-date' => date('Y-m-d')], [], 'col-md-6'); ?>
                                        <?php echo render_date_input('end_date', 'end_date', isset($news_ticker->end_date) ? _d($news_ticker->end_date) : '', ['data-date-min-date' => date('Y-m-d')], [], 'col-md-6'); ?>
                                    </div>
                                    <hr />
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="panel_s">
                                                <div class="panel-body">
                                                    <p class="tw-text-lg tw-font-medium">
                                                        <?php echo _l('admin_area'); ?>
                                                    </p>
                                                    <?php echo render_select('staff_ids[]', $staff, ['staffid', ['firstname', 'lastname']], 'select_staff_members', (isset($news_ticker) && function_exists('banner_is_serialized') && banner_is_serialized($news_ticker->staff_ids)) ? unserialize($news_ticker->staff_ids) : '', ['data-actions-box' => true, 'multiple' => true], [], '', '', false); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="panel_s">
                                                <div class="panel-body">
                                                    <p class="tw-text-lg tw-font-medium">
                                                        <?php echo _l('clients_area'); ?>
                                                    </p>
                                                    <?php echo render_select('client_ids[]', $clients, ['userid', 'company'], 'select_clients', (isset($news_ticker) && function_exists('banner_is_serialized') && banner_is_serialized($news_ticker->client_ids)) ? unserialize($news_ticker->client_ids) : '', ['data-actions-box' => true, 'multiple' => true], [], '', '', false); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button type="submit" class="btn btn-primary" id="save-banner-image">
                            <?php echo _l('save'); ?>
                        </button>
                    </div>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>

    $(function () {
        appValidateForm($('#news-ticker-form'), {
            news_title: "required",
            news_type: "required",
            start_date: {
                required: true
            },
            end_date: {
                required: true,
                enddate: true
            },
            title_text_color: "required",
            title_bg_color: "required",
            description_text_color: "required"
        });

        add_news_ticker_validation();
     });

    function add_news_ticker_validation() {
        $('input.newsDescription').each(function () {
            $(this).rules('add', {
                required: true,
            })
        });

        $('input.descriptionTextColor').each(function () {
            $(this).rules('add', {
                required: true,
            })
        });
    }

    $(document).on('click', '#addNewsDescription', function(event) {
        var news_description_row = "";
        var total_element = $('.news_description').length;
        var last_id = $(".news_description:last").attr('id').split("_");
        var next_id = Number(last_id[2]) + 1;

        news_description_row = `<div class="news_description row" id="news_description_${next_id}">
                                    <div class="col-md-8">
                                        <?= render_input('news[0][news_description]', '<small class="req text-danger">* </small>' . _l('news_description'), '', '', [], [], '', 'newsDescription'); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="news[0][description_text_color]" class="control-label"><small class="req text-danger">* </small><?= _l('description_text_color'); ?></label>
                                            <div class="input-group mbot15 colorpicker-input colorpicker-element">
                                                <input type="text" name="news[0][description_text_color]" id="news[0][description_text_color]" class="form-control descriptionTextColor">
                                                <span class="input-group-addon"><i></i></span>
                                            </div>
                                        </div>                                        
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger pull-right removeNewsDescription <?= is_mobile() ? 'mbot10' : 'mtop20'; ?>"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </div>`;

        var newsDescriptionRow = $(news_description_row);
        newsDescriptionRow.find('input[name^="news[0][news_description]"]').attr(`name`, `news[${next_id}][news_description]`);
        newsDescriptionRow.find('input[name^="news[0][description_text_color]"]').attr(`name`, `news[${next_id}][description_text_color]`);
        newsDescriptionRow.find('input[id^="news[0][news_description]"]').attr(`id`, `news[${next_id}][news_description]`);
        newsDescriptionRow.find('input[id^="news[0][description_text_color]"]').attr(`id`, `news[${next_id}][description_text_color]`);
        $("#append_new_row").append(newsDescriptionRow);

        add_news_ticker_validation();
        init_color_pickers();
    });

    $(document).on("click", ".removeNewsDescription", function() {
        var rowId = $(this).closest('.news_description').attr('id');
        $("#" + rowId).remove();
    });
</script>