<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: PerfexGo
Description: Seamlessly connect your Perfex CRM to the dedicated mobile app for real-time access to your CRM features, push notifications, and on-the-go productivity.  
Version: 2.1.0
Author: My Perfex CRM
Author URI: https://myperfexcrm.com
*/

require(__DIR__ . '/vendor/autoload.php');

define('PERFEX_MOBILE_COMPANION', 'perfex_mobile_companion');

hooks()->add_action('notification_created', 'perfex_mobile_companion__notification_created');
function perfex_mobile_companion__notification_created($notification_id)
{
    $CI = &get_instance();

    $notification = $CI->db->where('id', $notification_id)->get(db_prefix() . 'notifications')->row();
    if (!empty($notification)) {
        $devices = $CI->db->where('user_id', $notification->touserid)->get(db_prefix() . 'push_notification_devices')->result_array();
        if (!empty($devices)) {
            $additional_data = '';
            if (!empty($notification->additional_data)) {
                $additional_data = unserialize($notification->additional_data);

                $i = 0;
                foreach ($additional_data as $data) {
                    if (strpos($data, '<lang>') !== false) {
                        $lang = get_string_between($data, '<lang>', '</lang>');
                        $temp = _l($lang);
                        if (strpos($temp, 'project_status_') !== FALSE) {
                            $status = get_project_status_by_id(strafter($temp, 'project_status_'));
                            $temp = $status['name'];
                        }
                        $additional_data[$i] = $temp;
                    }
                    $i++;
                }
            }

            $description = _l($notification->description, $additional_data);
            $icon = 'icon.png';
            if (($notification->fromcompany == NULL && $notification->fromuserid != 0) || ($notification->fromcompany == NULL && $notification->fromclientid != 0)) {
                if ($notification->fromuserid != 0) {
                    $icon = staff_profile_image_url($notification->fromuserid);
                } else {
                    $icon = contact_profile_image_url($notification->fromclientid);
                }
            }

            $perfex_crm_url = parse_url(base_url());

            $data = [
                "registrationToken" => array_column($devices, 'token'),
                "title" => $notification->from_fullname,
                "message" => $description,
                "image_url" => $icon,
                "click_action" => $notification->link,
                'data' => [
                    'type' => perfex_mobile_companion_notification_type($notification->link),
                    'registered_devices' => $devices,
                    'domain' => $_SERVER['SERVER_NAME'],
                    'identifier' => str_replace('/', '_', $perfex_crm_url['host'] . $perfex_crm_url['path'])
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://t29zrfey40.execute-api.us-east-1.amazonaws.com/trigger-notification');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            $result = curl_exec($ch);
            curl_close($ch);
        }
    }
}


function perfex_mobile_companion_notification_type($link)
{
    if (strpos($link, '#taskid=') !== false) {
        return 'task';
    }

    if (strpos($link, '#leadid=') !== false) {
        return 'lead';
    }

    if (strpos($link, 'invoices/') !== false) {
        return 'invoice';
    }

    if (strpos($link, 'proposals/') !== false) {
        return 'proposal';
    }

    if (strpos($link, 'projects/') !== false) {
        return 'project';
    }

    return '';
}

hooks()->add_action('pr_chat_message_notification', 'mpc_mobile_app_connector__push_notification');
function mpc_mobile_app_connector__push_notification($data)
{
    $CI = &get_instance();
    if (isset($data['type'])) {

        if($data['type'] == 'send-event') {
            $CI->db->where('user_id', $data['to']);
        } else {
            $CI->db->from(db_prefix() . 'chatgroupmembers');
            $CI->db->where('group_id', $data['to_group']);
            $CI->db->where('member_id !=', $data['from']);
            $result = $CI->db->get()->result_array();
    
            $CI->db->where_in('user_id', array_column($result, 'member_id'));
        }

        $devices = $CI->db->get(db_prefix() . 'push_notification_devices')->result_array();
        if (!empty($devices)) {
            $perfex_crm_url = parse_url(base_url());

            $data = [
                "registrationToken" => array_column($devices, 'token'),
                "title" => $data['from_name'],
                "message" => $data['message'],
                "image_url" => 'icon.png',
                "click_action" => $data['type'],
                'data' => [
                    'type' => $data['type'],
                    'registered_devices' => $devices,
                    'domain' => $_SERVER['SERVER_NAME'],
                    'identifier' => str_replace('/', '_', $perfex_crm_url['host'] . $perfex_crm_url['path'])
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://t29zrfey40.execute-api.us-east-1.amazonaws.com/trigger-notification');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            $result = curl_exec($ch);
            curl_close($ch);
        }
    }
}
/**
 * Load the module helper
 */
$CI = &get_instance();
$CI->load->helper(PERFEX_MOBILE_COMPANION . '/perfex_mobile_companion');

register_activation_hook(PERFEX_MOBILE_COMPANION, 'perfex_mobile_companion_activation_hook');
register_deactivation_hook(PERFEX_MOBILE_COMPANION, 'perfex_mobile_companion_deactivation_hook');

function perfex_mobile_companion_activation_hook()
{
    require_once(__DIR__ . '/install.php');

    $sourceDirectory = APP_MODULES_PATH . PERFEX_MOBILE_COMPANION . '/languages';
    $destinationDirectory = APPPATH . 'language'; 
    perfex_mobile_companion_copy_directory($sourceDirectory, $destinationDirectory);

    perfex_mobile_companion_update_htaccess();
}

function perfex_mobile_companion_deactivation_hook()
{
    $CI = &get_instance();

    $destinationDirectory = APPPATH . 'language/arabic';
    perfex_mobile_companion_remove_directory($destinationDirectory);
}

/**
 * Modify the .htaccess file safely.
 */
function perfex_mobile_companion_update_htaccess()
{
    $htaccessPath = FCPATH . '.htaccess';

    // Authorization header rules
    $authHeader = <<<HTACCESS

# Ensure Authorization headers are passed to PHP
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
HTACCESS;

    // If .htaccess exists, modify it
    if (file_exists($htaccessPath)) {
        $currentContent = file_get_contents($htaccessPath);

        // Prevent duplicate entries
        if (strpos($currentContent, 'E=HTTP_AUTHORIZATION') === false) {
            // Backup .htaccess before modifying
            copy($htaccessPath, $htaccessPath . '.bak');

            // Insert authorization rules inside <IfModule mod_rewrite.c>
            $updatedContent = preg_replace('/(<IfModule mod_rewrite\.c>)/', "$1\n" . $authHeader, $currentContent, 1);

            // Ensure safe file writing
            if (is_writable($htaccessPath)) {
                file_put_contents($htaccessPath, $updatedContent);
            }
        }
    } else {
        $defaultContent = <<<HTACCESS
AddDefaultCharset utf-8
<IfModule mod_rewrite.c>
RewriteEngine on
RewriteCond \$1 !^(index\.php|resources|robots\.txt)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?/\$1 [L,QSA]
</IfModule>
$authHeader
HTACCESS;

        // Ensure safe file creation
        if (is_writable(FCPATH)) {
            file_put_contents($htaccessPath, $defaultContent);
        }
    }
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(PERFEX_MOBILE_COMPANION, [PERFEX_MOBILE_COMPANION]);

hooks()->add_action('app_init', 'perfex_mobile_companion_actLib');
function perfex_mobile_companion_actLib()
{
    $CI = &get_instance();
    $CI->load->library(PERFEX_MOBILE_COMPANION . '/Envatoapi');
    $envato_res = $CI->envatoapi->validatePurchase(PERFEX_MOBILE_COMPANION);
    if (!$envato_res) {
        set_alert('danger', "One of your modules failed its verification and got deactivated. Please reactivate or contact support.");
        redirect(admin_url('modules'));
    }
}

hooks()->add_action('pre_activate_module', 'perfex_mobile_companion_sidecheck');
function perfex_mobile_companion_sidecheck($module_name)
{
    if ($module_name['system_name'] == PERFEX_MOBILE_COMPANION) {
        if (!option_exists(PERFEX_MOBILE_COMPANION . '_verified') && empty(get_option(PERFEX_MOBILE_COMPANION . '_verified')) && !option_exists(PERFEX_MOBILE_COMPANION . '_verification_id') && empty(get_option(PERFEX_MOBILE_COMPANION . '_verification_id'))) {
            $CI = &get_instance();
            $data['submit_url'] = $module_name['system_name'] . '/env_ver/activate';
            $data['original_url'] = admin_url('modules/activate/' . PERFEX_MOBILE_COMPANION);
            $data['module_name'] = PERFEX_MOBILE_COMPANION;
            $data['title']       = $module_name['headers']['module_name'] . " module activation";
            echo $CI->load->view($module_name['system_name'] . '/activate', $data, true);
            exit();
        }
    }
}

hooks()->add_action('pre_deactivate_module', PERFEX_MOBILE_COMPANION . '_deregister');
function perfex_mobile_companion_deregister($module_name)
{
    if ($module_name['system_name'] == PERFEX_MOBILE_COMPANION) {
        $CI = &get_instance();
        $CI->load->library(PERFEX_MOBILE_COMPANION . '/Envatoapi');
        $CI->envatoapi->deactivateLicense(PERFEX_MOBILE_COMPANION);
        delete_option(PERFEX_MOBILE_COMPANION . "_verified");
        delete_option(PERFEX_MOBILE_COMPANION . "_verification_id");
        delete_option(PERFEX_MOBILE_COMPANION . "_last_verification");
        delete_option(PERFEX_MOBILE_COMPANION . "_expire_verification");
        if (file_exists(__DIR__ . "/config/token.php")) {
            unlink(__DIR__ . "/config/token.php");
        }
    }
}

function perfex_mobile_companion_copy_directory($source, $destination)
{
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $files = scandir($source);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $sourcePath = $source . '/' . $file;
            $destinationPath = $destination . '/' . $file;

            if (is_dir($sourcePath)) {
                // If it's a directory, copy its contents using recursion
                if (!perfex_mobile_companion_copy_directory($sourcePath, $destinationPath)) {
                    return false; // Copy failed
                }
            } else {
                // If it's a file, copy it to the destination directory
                if (!copy($sourcePath, $destinationPath)) {
                    return false; // Copy failed
                }
            }
        }
    }

    return true; // Copy successful
}

function perfex_mobile_companion_remove_directory($destination)
{
    if (is_dir($destination)) {
        $items = array_diff(scandir($destination), array('.', '..'));

        foreach ($items as $item) {
            $itemPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                // If it's a directory, recursively remove it
                perfex_mobile_companion_remove_directory($itemPath);
            } else {
                // If it's a file, remove it
                unlink($itemPath);
            }
        }

        rmdir($destination);
    }
}

hooks()->add_action('app_admin_assets_added', 'perfex_mobile_companion_app_admin_assets_added');

function perfex_mobile_companion_app_admin_assets_added()
{
    $CI = &get_instance();
    $CI->app_scripts->add(PERFEX_MOBILE_COMPANION . '-main-js', base_url('modules/' . PERFEX_MOBILE_COMPANION . '/assets/main.js'));
    $CI->app_css->add(PERFEX_MOBILE_COMPANION . '-main-css', base_url('modules/' . PERFEX_MOBILE_COMPANION . '/assets/main.css'));
}

hooks()->add_action('admin_navbar_start', 'perfex_mobile_companion_admin_navbar_start');
function perfex_mobile_companion_admin_navbar_start()
{
    $CI = &get_instance();
    $CI->load->view(PERFEX_MOBILE_COMPANION . '/navbar', ['module_name' => PERFEX_MOBILE_COMPANION]);
}

hooks()->add_action('app_admin_footer', 'perfex_mobile_companion_app_admin_footer');
function perfex_mobile_companion_app_admin_footer()
{
    echo '<div id="mobileapp" class="animated fadeIn hide"></div>';
}
