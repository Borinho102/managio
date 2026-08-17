<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_125 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!empty(subdomain())) {
            set_alert('warning', 'Only super admin can run migrations.');
            redirect('admin/dashboard');
        }

        // Register S3P as a payment gateway in tbl_saas_payment_methods
        $exists = $CI->db->get_where('tbl_saas_payment_methods', ['gateway_name' => 's3p'])->row();
        if (empty($exists)) {
            $CI->db->query("INSERT INTO `tbl_saas_payment_methods`
                (`gateway_name`, `icon`, `field_1`, `field_2`, `field_3`, `field_4`, `field_5`,
                 `link`, `fixed_fee`, `percentage_fee`, `modal`, `status`, `created_at`, `updated_at`)
            VALUES (
                's3p',
                '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 48 48\" width=\"60px\" height=\"60px\">
                    <rect width=\"48\" height=\"48\" rx=\"8\" fill=\"#F37021\"/>
                    <text x=\"50%\" y=\"55%\" dominant-baseline=\"middle\" text-anchor=\"middle\"
                          font-family=\"Arial,sans-serif\" font-weight=\"bold\" font-size=\"13\"
                          fill=\"#fff\">S3P</text>
                </svg>',
                's3p_api_key',
                's3p_secret_key',
                's3p_service_id',
                's3p_test_mode|checkbox',
                NULL,
                NULL,
                NULL,
                NULL,
                'No',
                'active',
                NOW(),
                NOW()
            );");
        }

        // Add S3P config keys to tbl_options (only if they don't exist yet)
        $options = [
            's3p_api_key'    => '',
            's3p_secret_key' => '',
            's3p_service_id' => '20052',
            's3p_test_mode'  => 'FALSE',
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
