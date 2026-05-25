<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_124 extends App_module_migration
{
    /**
     * @throws Exception
     */
    public function up()
    {
        $CI = &get_instance();

        if (!empty(subdomain())) {
            set_alert('warning', 'Only super admin can update the system.');
            redirect('admin/dashboard');
        }

        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_gateway_products` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL DEFAULT 'package',
  `package_id` bigint UNSIGNED NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `gateway_name` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `price_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_gateway_subscriptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(50) DEFAULT 'package',
  `subscription_id` varchar(255) NOT NULL,
  `customer_id` varchar(255) NOT NULL,
  `company_id` varchar(255) NOT NULL,
  `module_id` int DEFAULT NULL,
  `gateway_name` varchar(255) NOT NULL,
  `temp` text NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_gateway_webhooks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL DEFAULT 'package',
  `webhook_id` varchar(255) NOT NULL,
  `webhook_secret` text NULL DEFAULT NULL,
  `gateway_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_payment_methods` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(20) NOT NULL,
  `icon` text NOT NULL,
  `field_1` varchar(100) DEFAULT NULL,
  `field_2` varchar(100) DEFAULT NULL,
  `field_3` varchar(100) DEFAULT NULL,
  `field_4` varchar(100) DEFAULT NULL,
  `field_5` varchar(100) DEFAULT NULL,
  `link` varchar(100) DEFAULT NULL,
  `fixed_fee` decimal(15,2) DEFAULT NULL,
  `percentage_fee` decimal(15,2) DEFAULT NULL,
  `modal` enum('Yes','No') DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3;");

        $CI->db->query("INSERT INTO `tbl_saas_payment_methods` (`id`, `gateway_name`, `icon`, `field_1`, `field_2`, `field_3`, `field_4`, `field_5`, `link`, `fixed_fee`, `percentage_fee`, `modal`, `status`, `created_at`, `updated_at`) VALUES
(1, 'paypal', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 48 48\" width=\"60px\" height=\"60px\">\n                                            <path fill=\"#1565C0\" d=\"M18.7,13.767l0.005,0.002C18.809,13.326,19.187,13,19.66,13h13.472c0.017,0,0.034-0.007,0.051-0.006C32.896,8.215,28.887,6,25.35,6H11.878c-0.474,0-0.852,0.335-0.955,0.777l-0.005-0.002L5.029,33.813l0.013,0.001c-0.014,0.064-0.039,0.125-0.039,0.194c0,0.553,0.447,0.991,1,0.991h8.071L18.7,13.767z\"></path>\n                                            <path fill=\"#039BE5\" d=\"M33.183,12.994c0.053,0.876-0.005,1.829-0.229,2.882c-1.281,5.995-5.912,9.115-11.635,9.115c0,0-3.47,0-4.313,0c-0.521,0-0.767,0.306-0.88,0.54l-1.74,8.049l-0.305,1.429h-0.006l-1.263,5.796l0.013,0.001c-0.014,0.064-0.039,0.125-0.039,0.194c0,0.553,0.447,1,1,1h7.333l0.013-0.01c0.472-0.007,0.847-0.344,0.945-0.788l0.018-0.015l1.812-8.416c0,0,0.126-0.803,0.97-0.803s4.178,0,4.178,0c5.723,0,10.401-3.106,11.683-9.102C42.18,16.106,37.358,13.019,33.183,12.994z\"></path>\n                                            <path fill=\"#283593\" d=\"M19.66,13c-0.474,0-0.852,0.326-0.955,0.769L18.7,13.767l-2.575,11.765c0.113-0.234,0.359-0.54,0.88-0.54c0.844,0,4.235,0,4.235,0c5.723,0,10.432-3.12,11.713-9.115c0.225-1.053,0.282-2.006,0.229-2.882C33.166,12.993,33.148,13,33.132,13H19.66z\"></path>\n                                        </svg>', 'paypal_client_id', 'paypal_secret_key', 'paypal_app_name', 'paypal_live|checkbox', '', 'payment/paypal', NULL, NULL, 'Yes', 'active', NULL, '2024-07-26 21:26:55'),
(2, 'stripe', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 48 48\" width=\"60px\" height=\"60px\">\n                                            <path fill=\"#03A9F4\" d=\"M41.765,38H6.235C4.439,38,3,36.63,3,34.848v-22.62C3,10.445,4.439,9,6.235,9h35.529C43.56,9,45,10.445,45,12.228v22.62C45,36.63,43.56,38,41.765,38z\"></path>\n                                            <path fill=\"#FFF\" d=\"M36.847,23.277c0.069-1.101,0.354-1.613,0.926-1.613c0.548,0,0.848,0.527,0.886,1.613H36.847z M40.953,23.935c0-1.23-0.27-2.203-0.781-2.885c-0.54-0.697-1.346-1.05-2.359-1.05c-2.088,0-3.396,1.546-3.396,4.022c0,1.384,0.345,2.427,1.038,3.085C36.072,27.702,36.958,28,38.085,28c1.047,0,2.017-0.251,2.632-0.655l-0.268-1.688c-0.607,0.331-1.31,0.511-2.095,0.511c-0.47,0-0.806-0.103-1.044-0.308c-0.262-0.219-0.408-0.572-0.46-1.076h4.068C40.944,24.669,40.953,24.106,40.953,23.935z M31.057,25.533c-0.221,0.377-0.531,0.58-0.89,0.58c-0.241,0-0.472-0.053-0.669-0.147v-3.718c0.428-0.441,0.814-0.491,0.942-0.491c0.631,0,0.941,0.681,0.941,2.017C31.384,24.534,31.269,25.126,31.057,25.533z M33.113,20.709c-0.438-0.571-1.059-0.853-1.845-0.853c-0.712,0-1.343,0.302-1.934,0.936l-0.142-0.784H27V31l2.481-0.416l0.017-2.799c0.387,0.121,0.779,0.185,1.131,0.185c0.627,0,1.53-0.157,2.235-0.926c0.667-0.73,0.996-1.862,0.996-3.361C33.86,22.358,33.615,21.353,33.113,20.709z M23.527,20.008H26V28h-2.473V20.008z M24.784,19.233c0.718,0,1.3-0.594,1.3-1.313c0-0.733-0.584-1.32-1.3-1.32c-0.738,0-1.323,0.587-1.323,1.32C23.461,18.64,24.046,19.233,24.784,19.233z M22.463,19.9c-0.705,0-1.279,0.372-1.491,1.031l-0.15-0.921h-2.17V28h2.482v-5.25c0.312-0.382,0.749-0.52,1.362-0.52c0.127,0,0.256,0,0.438,0.026v-2.294C22.751,19.921,22.6,19.9,22.463,19.9z M17.68,21.855l0.308-1.848h-1.601v-2.245l-2.129,0.354l-0.309,1.891L13.2,20.13l-0.277,1.726h1.024v3.622c0,0.941,0.238,1.599,0.72,1.998c0.421,0.335,1.011,0.493,1.843,0.493c0.654,0,1.043-0.112,1.297-0.184v-1.959c-0.133,0.041-0.48,0.115-0.716,0.115c-0.48,0-0.705-0.25-0.705-0.825v-3.265h1.294V21.855z M10.531,23.05c-0.707-0.265-1.118-0.473-1.118-0.803c0-0.275,0.229-0.434,0.646-0.434c0.737,0,1.509,0.281,2.023,0.544l0.3-1.829C11.964,20.326,11.113,20,9.94,20c-0.84,0-1.535,0.22-2.014,0.621c-0.532,0.429-0.802,1.043-0.802,1.786c0,1.347,0.824,1.918,2.166,2.402c0.857,0.308,1.154,0.527,1.154,0.868c0,0.322-0.274,0.514-0.795,0.514c-0.624,0-1.641-0.31-2.327-0.703l-0.282,1.853C7.616,27.663,8.676,28,9.788,28c0.887,0,1.622-0.21,2.102-0.606c0.568-0.432,0.844-1.077,0.844-1.905C12.736,24.106,11.891,23.531,10.531,23.05L10.531,23.05z\"></path>\n                                        </svg>', 'stripe_publishable_key', 'stripe_secret_key', NULL, NULL, NULL, 'payment/stripe', NULL, NULL, 'Yes', 'active', NULL, '2024-02-02 17:11:14');");

        $CI->db->query("ALTER TABLE `tbl_saas_companies_history` ADD `type` VARCHAR(50) NULL DEFAULT 'package' AFTER `companies_id`;");
    }


}
