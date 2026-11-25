<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_146 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'expenses_categories')
            && !$CI->db->field_exists('budget_category_id', db_prefix() . 'expenses_categories')
        ) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'expenses_categories`
                ADD COLUMN `budget_category_id` INT(11) NULL DEFAULT NULL,
                ADD KEY `budget_category_id` (`budget_category_id`)');
        }

        add_option('acc_cf_expense_automatic_conversion', 1);
        add_option('acc_active_cf_expense_category_mapping', 1);
    }
}
