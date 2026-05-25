<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <?= form_open($this->uri->uri_string(), ['id' => 'mpg-form']); ?>
        <div class="tw-max-w-4xl tw-mx-auto">
            <div class="tw-flex tw-justify-between tw-mb-2">
                <div>
                    <h4 class="tw-my-0 tw-text-lg tw-font-bold tw-text-neutral-700">
                        <?= e($title); ?>
                    </h4>
                </div>
                <?php if (isset($manual_payment_gateway)) { ?>
                    <div class="tw-self-start tw-space-x-1">
                        <?php if (staff_can('create', 'knowledge_base')) { ?>
                            <a href="<?= admin_url('manual_payment_gateway/create'); ?>"
                               class="btn btn-primary"><?= _l('mpg_add_new'); ?></a>
                        <?php } ?>
                        <?php if (staff_can('delete', 'manual_payment_gateway')) { ?>
                            <a href="<?= admin_url('manual_payment_gateway/delete/' . $manual_payment_gateway->id); ?>"
                               class="btn btn-default _delete">
                                <i class="fa-regular fa-trash-can"></i>
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <div class="panel_s">
                <div class="panel-body">
                    <?php $value = (isset($manual_payment_gateway) ? $manual_payment_gateway->name : ''); ?>
                    <?php $attrs = (isset($manual_payment_gateway) ? [] : ['autofocus' => true]); ?>
                    <?= render_input('name', 'name', $value, 'text', $attrs); ?>
                    <p class="bold">
                        <?= _l('mpg_description'); ?>
                    </p>
                    <?php $contents = '';
                    if (isset($manual_payment_gateway)) {
                        $contents = $manual_payment_gateway->description;
                    } ?>
                    <?= render_textarea('description', '', $contents, [], [], '', 'tinymce tinymce-manual'); ?>
                    <div class="form-group">
                        <label class="control-label"><?= _l('mpg_form_fields'); ?></label>
                        <div class="table-responsive">
                            <table class="table table-bordered table-md mt-0">
                                <thead>
                                <tr>
                                    <th><?= _l('mpg_label'); ?></th>
                                    <th><?= _l('mpg_type'); ?></th>
                                    <th><?= _l('mpg_required'); ?></th>
                                    <th><?= _l('mpg_width'); ?></th>
                                    <th><?= _l('mpg_action'); ?></th>
                                </tr>
                                </thead>
                                <tbody id="commission-tbody">
                                    <?php
                                    if(count($form_fields) > 0)
                                    {
                                        $i = 0;
                                        foreach($form_fields as $fieldKey => $field) {
                                            $i++;
                                            ?>
                                            <tr>
                                                <td><input name="label[]" type="text" class="form-control" value="<?= $field['label'] ?>"></td>
                                                <td>
                                                    <select class="form-control" name="type[]">
                                                        <option value="text" <?= $field['type'] == 'text' ? 'selected' : '' ?>><?= _l('mpg_text'); ?></option>
                                                        <option value="email" <?= $field['type'] == 'email' ? 'selected' : '' ?>><?= _l('mpg_email'); ?></option>
                                                        <option value="file" <?= $field['type'] == 'file' ? 'selected' : '' ?>><?= _l('mpg_file'); ?></option>
                                                        <option value="number" <?= $field['type'] == 'number' ? 'selected' : '' ?>><?= _l('mpg_number'); ?></option>
                                                        <option value="date" <?= $field['type'] == 'date' ? 'selected' : '' ?>><?= _l('mpg_date'); ?></option>
                                                        <option value="textarea" <?= $field['type'] == 'textarea' ? 'selected' : '' ?>><?= _l('mpg_textarea'); ?></option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-control" name="require[]">
                                                        <option value="true" <?= $field['required'] == 'true' ? 'selected' : '' ?>><?= _l('mpg_true'); ?></option>
                                                        <option value="false" <?= $field['required'] == 'false' ? 'selected' : '' ?>><?= _l('mpg_false'); ?></option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-control" name="width[]">
                                                        <option value="25" <?= $field['width'] == '25' ? 'selected' : '' ?>><?= _l('mpg_25'); ?></option>
                                                        <option value="50" <?= $field['width'] == '50' ? 'selected' : '' ?>><?= _l('mpg_50'); ?></option>
                                                        <option value="75" <?= $field['width'] == '75' ? 'selected' : '' ?>><?= _l('mpg_75'); ?></option>
                                                        <option value="100" <?= $field['width'] == '100' ? 'selected' : '' ?>><?= _l('mpg_100'); ?></option>
                                                    </select>
                                                </td>
                                                <?php if($i == 1) { ?>
                                                    <td>
                                                        <button type="button" id="add" class="btn btn-success border-radius-sm">
                                                            <i class="fas fa-plus-circle"></i>
                                                        </button>
                                                    </td>
                                                <?php }else{ ?>
                                                    <td>
                                                        <button type="button" class="btn btn-danger border-radius-sm remove">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                    <?php }else{ ?>
                                        <tr>
                                            <td><input name="label[]" type="text" class="form-control"></td>
                                            <td>
                                                <select class="form-control" name="type[]">
                                                    <option value="text"><?= _l('mpg_text'); ?></option>
                                                    <option value="email"><?= _l('mpg_email'); ?></option>
                                                    <option value="file"><?= _l('mpg_file'); ?></option>
                                                    <option value="number"><?= _l('mpg_number'); ?></option>
                                                    <option value="date"><?= _l('mpg_date'); ?></option>
                                                    <option value="textarea"><?= _l('mpg_textarea'); ?></option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control" name="require[]">
                                                    <option value="true"><?= _l('mpg_true'); ?></option>
                                                    <option value="false"><?= _l('mpg_false'); ?></option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control" name="width[]">
                                                    <option value="25"><?= _l('mpg_25'); ?></option>
                                                    <option value="50"><?= _l('mpg_50'); ?></option>
                                                    <option value="75"><?= _l('mpg_75'); ?></option>
                                                    <option value="100"><?= _l('mpg_100'); ?></option>
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button" name="add" id="add" class="btn btn-success border-radius-sm">
                                                    <i class="fas fa-plus-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="panel-footer text-right">
                    <button type="submit" class="btn btn-primary">
                        <?= _l('submit'); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
    <?= form_close(); ?>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        init_editor('#description', {
            quickbars_selection_toolbar: `bold link ${app.options.is_ai_provider_enabled ? 'ai' : ''}`,
            append_plugins: 'quickbars',
            setup: function (editor) {
                if(app.options.is_ai_provider_enabled) {
                    configure_ai_editor(editor);
                }
            },
            toolbar_sticky: true,
        });

        appValidateForm($('#mpg-form'), {
            subject: 'required',
            articlegroup: 'required'
        });

        var count = 1;

        function dynamic_field(number)
        {
            html = '';
            html += '<tr>';
            html += '<td><input type="text" name="label[]" class="form-control required" /></td>';
            html += '<td><select name="type[]" class="form-control required">'+
                '<option value="text"><?= _l('mpg_text'); ?></option>'+
                '<option value="email"><?= _l('mpg_email'); ?></option>'+
                '<option value="file"><?= _l('mpg_file'); ?></option>'+
                '<option value="number"><?= _l('mpg_number'); ?></option>'+
                '<option value="date"><?= _l('mpg_date'); ?></option>'+
                '<option value="textarea"><?= _l('mpg_textarea'); ?></option>'+
                '</select></td>';
            html += '<td><select name="require[]" class="form-control required"><option value="true"><?= _l('mpg_true'); ?></option><option value="false"><?= _l('mpg_false'); ?></option></select></td>';
            html += '<td><select name="width[]" class="form-control required">'+
                '<option value="25"><?= _l('mpg_25'); ?></option>'+
                '<option value="50"><?= _l('mpg_50'); ?></option>'+
                '<option value="75"><?= _l('mpg_75'); ?></option>'+
                '<option value="100"><?= _l('mpg_100'); ?></option>'+
                '</select></td>';
            html += '<td><button type="button" class="btn btn-danger remove"><i class="fas fa-times-circle"></i></button></td></tr>';
            $('tbody').append(html);
        }

        $(document).on('click', '#add', function(){
            count++;
            dynamic_field(count);
        });

        $(document).on('click', '.remove', function(){
            count--;
            $(this).closest("tr").remove();
        });
    });
</script>
</body>

</html>