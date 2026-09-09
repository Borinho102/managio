<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_133 extends App_module_migration
{
    public function up()
    {
        add_option('product_gift_card_min_amount', 1);
    }
}
