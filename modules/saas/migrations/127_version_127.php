<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_127 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!empty(subdomain())) {
            set_alert('warning', 'Only super admin can run migrations.');
            redirect('admin/dashboard');
        }

        if (!$CI->db->field_exists('payin_user_id', 'tbl_saas_companies')) {
            $CI->db->query('ALTER TABLE `tbl_saas_companies` ADD `payin_user_id` INT NULL DEFAULT NULL');
        }
        if (!$CI->db->field_exists('payin_merchant_id', 'tbl_saas_companies')) {
            $CI->db->query('ALTER TABLE `tbl_saas_companies` ADD `payin_merchant_id` INT NULL DEFAULT NULL');
        }
        if (!$CI->db->field_exists('payin_provisioned_at', 'tbl_saas_companies')) {
            $CI->db->query('ALTER TABLE `tbl_saas_companies` ADD `payin_provisioned_at` DATETIME NULL DEFAULT NULL');
        }

        $exists = $CI->db->get_where('tbl_saas_payment_methods', ['gateway_name' => 'payin'])->row();
        if (empty($exists)) {
            $CI->db->query("INSERT INTO `tbl_saas_payment_methods`
                (`gateway_name`, `icon`, `field_1`, `field_2`, `field_3`, `field_4`, `field_5`,
                 `link`, `fixed_fee`, `percentage_fee`, `modal`, `status`, `created_at`, `updated_at`)
            VALUES (
                'payin',
                '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 48 48\" width=\"60px\" height=\"60px\">
                    <rect width=\"48\" height=\"48\" rx=\"8\" fill=\"#0F766E\"/>
                    <text x=\"50%\" y=\"55%\" dominant-baseline=\"middle\" text-anchor=\"middle\"
                          font-family=\"Arial,sans-serif\" font-weight=\"bold\" font-size=\"11\"
                          fill=\"#fff\">PayIn</text>
                </svg>',
                'payin_api_base_url',
                'payin_client_id',
                'payin_client_secret|password',
                'payin_enable_wallet|checkbox',
                'payin_enable_pawapay|checkbox',
                NULL,
                NULL,
                NULL,
                'No',
                'active',
                NOW(),
                NOW()
            );");
        }

        $options = [
            'payin_api_base_url'   => '',
            'payin_client_id'      => '',
            'payin_client_secret'  => '',
            'payin_enable_wallet'  => '1',
            'payin_enable_pawapay' => '1',
            'payin_sso_key'        => 'managio',
            'payin_sso_secret'     => '',
        ];

        foreach ($options as $name => $value) {
            $existing = $CI->db->get_where(db_prefix() . 'options', ['name' => $name])->row();
            if (empty($existing)) {
                $CI->db->insert(db_prefix() . 'options', [
                    'name'     => $name,
                    'value'    => $value,
                    'autoload' => 1,
                ]);
            }
        }
    }
}
