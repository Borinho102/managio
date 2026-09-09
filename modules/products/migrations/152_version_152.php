<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_152 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Currencies that are sold tax free. A merchant selling cross border
        // often charges tax only in their home currency, so tax has to be a
        // per-currency decision rather than a per-product one.
        add_option('product_currencies_no_tax', '');

        // Services and digital products are not stock items, but they were
        // being decremented like physical goods. That drove quantity to 0 after
        // the first order and then made the product look out of stock. Repair
        // the rows that were already zeroed.
        if ($CI->db->field_exists('product_type', db_prefix() . 'product_master')) {
            $CI->db->query('UPDATE `' . db_prefix() . 'product_master`
                SET `quantity_number` = 1
                WHERE `product_type` IN ("service", "digital") AND `quantity_number` < 1');
        }
        $CI->db->query('UPDATE `' . db_prefix() . 'product_master`
            SET `quantity_number` = 1
            WHERE `is_digital` = 1 AND `quantity_number` < 1');
    }
}
