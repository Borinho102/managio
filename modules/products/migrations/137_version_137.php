<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_137 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $ex = $CI->db->where('type', 'products')->where('slug', 'product-abandoned-cart')->get(db_prefix() . 'emailtemplates')->row();
        if (empty($ex)) {
            $CI->db->insert(db_prefix() . 'emailtemplates', [
                'type' => 'products',
                'slug' => 'product-abandoned-cart',
                'language' => 'english',
                'name' => 'Abandoned Cart Reminder',
                'subject' => 'You left items in your cart',
                'message' => 'Hi {client_name},<br><br>You have items worth {cart_total} in your cart.<br><br><a href="{cart_link}">Complete your purchase</a><br><br>Best regards,<br>{companyname}',
                'fromname' => '{companyname}',
                'active' => '0',
            ]);
        }
    }
}
