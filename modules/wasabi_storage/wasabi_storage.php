<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Wasabi Storage
Description: Store CRM attachments and backups on Wasabi S3-compatible object storage
Version: 1.0.0
Requires at least: 2.3.*
*/

define('WASABI_STORAGE_MODULE_NAME', 'wasabi_storage');
define('WASABI_STORAGE_EXTERNAL', 'wasabi');

$CI = &get_instance();

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$CI->load->helper(WASABI_STORAGE_MODULE_NAME . '/wasabi_storage');

register_activation_hook(WASABI_STORAGE_MODULE_NAME, 'wasabi_storage_activation_hook');

function wasabi_storage_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

// Ensure mapping table exists after upgrades without re-activating the module.
if (!function_exists('wasabi_storage_ensure_table')) {
    function wasabi_storage_ensure_table()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'wasabi_files';
        if ($CI->db->table_exists($table)) {
            return;
        }

        $CI->db->query('CREATE TABLE `' . $table . '` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rel_type` VARCHAR(64) NOT NULL,
            `rel_id` INT(11) NOT NULL DEFAULT 0,
            `file_name` VARCHAR(255) NOT NULL,
            `object_key` TEXT NOT NULL,
            `filetype` VARCHAR(120) NULL,
            `dateadded` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `rel_lookup` (`rel_type`, `rel_id`),
            KEY `file_name` (`file_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
    }
}
wasabi_storage_ensure_table();

register_language_files(WASABI_STORAGE_MODULE_NAME, [WASABI_STORAGE_MODULE_NAME]);

hooks()->add_filter('module_wasabi_storage_action_links', 'module_wasabi_storage_action_links');
hooks()->add_action('admin_init', 'wasabi_storage_init_menu_items');

hooks()->add_filter('before_handle_task_attachments_array', 'wasabi_storage_filter_task_attachments');
hooks()->add_filter('before_handle_ticket_attachment', 'wasabi_storage_filter_ticket_attachments');
hooks()->add_filter('before_handle_project_file_uploads', 'wasabi_storage_filter_project_files');
hooks()->add_filter('before_handle_sales_attachments', 'wasabi_storage_filter_sales_attachments');
hooks()->add_filter('before_handle_client_attachment', 'wasabi_storage_filter_client_attachments');
hooks()->add_filter('before_handle_expense_attachment', 'wasabi_storage_filter_expense_attachment');
hooks()->add_filter('before_handle_contract_attachment', 'wasabi_storage_filter_contract_attachment');
hooks()->add_filter('before_handle_lead_attachment', 'wasabi_storage_filter_lead_attachment');
hooks()->add_filter('before_handle_newsfeed_post_attachments', 'wasabi_storage_filter_newsfeed_attachments');
hooks()->add_filter('before_handle_estimate_request_attachment', 'wasabi_storage_filter_estimate_request_attachment');
hooks()->add_filter('before_handle_project_discussion_comment_attachment', 'wasabi_storage_filter_discussion_attachment');
hooks()->add_filter('before_handle_company_logo_upload', 'wasabi_storage_filter_company_logo');
hooks()->add_filter('before_handle_company_signature_upload', 'wasabi_storage_filter_company_signature');
hooks()->add_filter('before_handle_favicon_upload', 'wasabi_storage_filter_favicon');
hooks()->add_filter('before_handle_staff_profile_image_upload', 'wasabi_storage_filter_staff_profile');
hooks()->add_filter('before_handle_contact_profile_image_upload', 'wasabi_storage_filter_contact_profile');

hooks()->add_filter('download_file_path', 'wasabi_storage_download_file_path', 10, 2);
hooks()->add_filter('project_file_url', 'wasabi_storage_project_file_url', 10, 2);
hooks()->add_filter('preview_image_missing', 'wasabi_storage_filter_preview_image_missing', 10, 2);

hooks()->add_action('before_remove_project_file', 'wasabi_storage_on_remove_project_file');
hooks()->add_action('before_make_backup', 'wasabi_storage_remember_backup_snapshot');
hooks()->add_action('after_make_backup', 'wasabi_storage_maybe_push_new_backups');
hooks()->add_action('after_cron_run', 'wasabi_storage_maybe_push_new_backups', 20);

function wasabi_storage_on_remove_project_file($id)
{
    $CI = &get_instance();
    $CI->db->where('id', $id);
    $file = $CI->db->get(db_prefix() . 'project_files')->row();
    if ($file && !empty($file->external) && $file->external === WASABI_STORAGE_EXTERNAL) {
        wasabi_storage_delete_mapping_and_object('project', $file->project_id, $file->file_name);
    }
}

/**
 * @param array $actions
 * @return array
 */
function module_wasabi_storage_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('wasabi_storage') . '">' . _l('settings') . '</a>';

    return $actions;
}

function wasabi_storage_init_menu_items()
{
    if (!is_admin()) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_setup_menu_item('wasabi-storage', [
        'href'     => admin_url('wasabi_storage'),
        'name'     => _l('wasabi_storage'),
        'position' => 66,
    ]);
}
