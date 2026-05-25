<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_122 extends App_module_migration
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

        $CI->db->query("CREATE TABLE IF NOT EXISTS `tbl_saas_api_token` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `token` varchar(200) NOT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;");

        // get all companies and save them in clients table saas_company_id column
        $companies = $CI->db->query("SELECT * FROM tbl_saas_companies")->result();
        if (!empty($companies)) {
            foreach ($companies as $company) {
                if (!empty($company->db_name)) {
                    $CI->old_db = config_db($company->db_name);
                    if (!empty($CI->old_db->database)) {

                        $CI->old_db->query("USE " . $CI->old_db->database);

                        $CI->old_db->query('alter table `' . db_prefix() . 'templates` modify `content` longtext null;');

                        $CI->old_db->query(
                            'CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'filters` (
                `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
                `builder` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
                `staff_id` int UNSIGNED NOT NULL,
                `identifier` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
                `is_shared` tinyint UNSIGNED NOT NULL DEFAULT \'0\',
                PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
                        );

                        $CI->old_db->query('CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'filter_defaults` (
            `filter_id` int UNSIGNED NOT NULL,
            `staff_id` int NOT NULL,
            `identifier` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
            `view` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
            FOREIGN KEY (`filter_id`) REFERENCES `' . db_prefix() . 'filters`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`staff_id`) REFERENCES `' . db_prefix() . 'staff`(`staffid`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
                        );

                    }
                }
            }
        }

    }


}
