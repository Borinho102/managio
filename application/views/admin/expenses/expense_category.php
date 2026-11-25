<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$budget_categories = $budget_categories ?? [];
$acc_accounts = $acc_accounts ?? [];
$has_accounting = is_array($budget_categories) || is_array($acc_accounts);
if (empty($budget_categories) && empty($acc_accounts) && function_exists('module_dir_path') && is_dir(module_dir_path('accounting'))) {
    $CI = &get_instance();
    if ($CI->db->table_exists(db_prefix() . 'acc_project_budget_categories')) {
        $budget_categories = $CI->db->order_by('name', 'asc')->get(db_prefix() . 'acc_project_budget_categories')->result_array();
    }
    if ($CI->db->table_exists(db_prefix() . 'acc_accounts')) {
        $CI->load->model('accounting/accounting_model');
        $acc_accounts = $CI->accounting_model->get_accounts('', db_prefix() . 'acc_accounts.active = 1');
    }
}
?>
<div class="modal fade" id="expense-category-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <?= form_open(admin_url('expenses/category'), ['id' => 'expense-category-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span
                        class="edit-title"><?= _l('edit_expense_category'); ?></span>
                    <span
                        class="add-title"><?= _l('new_expense_category'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div id="additional"></div>
                        <?= render_input('name', 'expense_add_edit_name'); ?>
                        <?= render_textarea('description', 'expense_add_edit_description'); ?>

                        <?php if (!empty($budget_categories)) { ?>
                            <?= render_select(
                                'budget_category_id',
                                $budget_categories,
                                ['id', 'name'],
                                'acc_budget_category',
                                '',
                                ['data-none-selected-text' => _l('dropdown_non_selected_tex')]
                            ); ?>
                            <p class="text-muted tw-text-sm">
                                <?= _l('expense_category_budget_help'); ?>
                            </p>
                        <?php } ?>

                        <?php if (!empty($acc_accounts)) { ?>
                            <hr />
                            <p class="bold"><?= _l('expense_category_accounting_mapping'); ?></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <?= render_select(
                                        'payment_account',
                                        $acc_accounts,
                                        ['id', 'name', 'account_type_name'],
                                        'payment_account',
                                        '',
                                        [],
                                        [],
                                        '',
                                        '',
                                        false
                                    ); ?>
                                </div>
                                <div class="col-md-6">
                                    <?= render_select(
                                        'deposit_to',
                                        $acc_accounts,
                                        ['id', 'name', 'account_type_name'],
                                        'deposit_to',
                                        '',
                                        [],
                                        [],
                                        '',
                                        '',
                                        false
                                    ); ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit"
                    class="btn btn-primary"><?= _l('submit'); ?></button>
            </div>
        </div><!-- /.modal-content -->
        <?= form_close(); ?>
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<script>
    window.addEventListener('load', function() {
        appValidateForm($('#expense-category-form'), {
            name: 'required'
        }, manage_categories);
        $('#expense-category-modal').on('hidden.bs.modal', function(event) {
            $('#additional').html('');
            $('#expense-category-modal input[name="name"]').val('');
            $('#expense-category-modal textarea').val('');
            if ($('#expense-category-modal select[name="budget_category_id"]').length) {
                $('#expense-category-modal select[name="budget_category_id"]').selectpicker('val', '');
            }
            if ($('#expense-category-modal select[name="payment_account"]').length) {
                $('#expense-category-modal select[name="payment_account"]').selectpicker('val', '');
            }
            if ($('#expense-category-modal select[name="deposit_to"]').length) {
                $('#expense-category-modal select[name="deposit_to"]').selectpicker('val', '');
            }
            $('.add-title').removeClass('hide');
            $('.edit-title').removeClass('hide');
        });
    });

    function manage_categories(form) {
        var data = $(form).serialize();
        var url = form.action;
        $.post(url, data).done(function(response) {
            response = JSON.parse(response);

            if (response.success == true) {
                alert_float('success', response.message);
                if ($('body').hasClass('expense') && typeof(response.id) != 'undefined') {
                    var category = $('#category');
                    category.find('option:first').after('<option value="' + response.id + '">' + response.name +
                        '</option>');
                    category.selectpicker('val', response.id);
                    category.selectpicker('refresh');
                }
            }

            if ($.fn.DataTable.isDataTable('.table-expenses-categories')) {
                $('.table-expenses-categories').DataTable().ajax.reload();
            }

            $('#expense-category-modal').modal('hide');
        });
        return false;
    }

    function new_category() {
        $('#expense-category-modal').modal('show');
        $('.edit-title').addClass('hide');
    }

    function edit_category(invoker, id) {
        var name = $(invoker).data('name');
        var description = $(invoker).data('description');
        var budgetCategoryId = $(invoker).data('budget-category-id') || '';
        var paymentAccount = $(invoker).data('payment-account') || '';
        var depositTo = $(invoker).data('deposit-to') || '';
        $('#additional').append(hidden_input('id', id));
        $('#expense-category-modal input[name="name"]').val(name);
        $('#expense-category-modal textarea').val(description);
        if ($('#expense-category-modal select[name="budget_category_id"]').length) {
            $('#expense-category-modal select[name="budget_category_id"]').selectpicker('val', String(budgetCategoryId));
        }
        if ($('#expense-category-modal select[name="payment_account"]').length) {
            $('#expense-category-modal select[name="payment_account"]').selectpicker('val', String(paymentAccount));
        }
        if ($('#expense-category-modal select[name="deposit_to"]').length) {
            $('#expense-category-modal select[name="deposit_to"]').selectpicker('val', String(depositTo));
        }
        $('#expense-category-modal').modal('show');
        $('.add-title').addClass('hide');
    }
</script>
