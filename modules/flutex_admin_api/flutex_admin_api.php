<?php

defined('BASEPATH') || exit('No direct script access allowed');

/*
    Module Name: Flutex Admin/Staff API
    Description: REST API For Flutex Admin/Staff Flutter App
    Version: 2.1.1
    Requires at least: 3.0.*
    Author: Branditta
    Author URI: https://codecanyon.net/user/branditta/portfolio
*/

define('FLUTEX_ADMIN_API', 'flutex_admin_api');
require_once __DIR__.'/vendor/autoload.php';

register_activation_hook(FLUTEX_ADMIN_API, 'flutex_admin_api_activate_hook');
function flutex_admin_api_activate_hook()
{
    require_once __DIR__.'/install.php';
}

register_deactivation_hook(FLUTEX_ADMIN_API, 'flutex_admin_api_deactivate_hook');
function flutex_admin_api_deactivate_hook()
{
    update_option('flutex_admin_api_enabled', 0);
}

register_language_files(FLUTEX_ADMIN_API, [FLUTEX_ADMIN_API]);

get_instance()->load->helper(FLUTEX_ADMIN_API.'/flutex_admin_api');

hooks()->add_action('admin_init', 'add_flutex_admin_api_settings_tabs');
function add_flutex_admin_api_settings_tabs()
{
    $CI = &get_instance();

    $CI->app->add_settings_section('flutex_admin_api', [
        'title'    => _l('flutex_admin_api'),
        'position' => 1,
        'children' => [
            [
                'name'     => _l('flutex_admin_settings'),
                'view'     => 'flutex_admin_api/flutex_admin_settings',
                'position' => 1,
                'icon'     => 'fas fa-mobile-alt',
            ],
        ],
    ]);
}

hooks()->add_filter('module_flutex_admin_api_action_links', 'module_flutex_admin_api_action_links');
function module_flutex_admin_api_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('settings') . '?group=flutex_admin_settings">' . _l('settings') . '</a>';
    return $actions;
}

hooks()->add_action('notification_created', 'flutex_admin_api_push_notification');
function flutex_admin_api_push_notification($notification_id)
{
    $CI = &get_instance();
    $CI->load->library(FLUTEX_ADMIN_API.'/flutex_admin_api');
    $send_push_notification = $CI->flutex_admin_api->send_push_notification($notification_id);
}

hooks()->add_action('app_init', 'flutex_admin_api_init');
function flutex_admin_api_init()
{
    $CI = &get_instance();
    $CI->load->library(FLUTEX_ADMIN_API.'/flutex_admin_api');
    $verify = $CI->flutex_admin_api->verify(FLUTEX_ADMIN_API);
    if (!$verify['status']) {
        get_instance()->app_modules->deactivate(FLUTEX_ADMIN_API);
        set_alert('danger', $verify['message']);
    }
}

hooks()->add_action('pre_activate_module', 'flutex_admin_api_activation');
function flutex_admin_api_activation()
{
    $CI = &get_instance();
    $CI->load->library(FLUTEX_ADMIN_API.'/flutex_admin_api');
    $CI->flutex_admin_api->pre_activate(FLUTEX_ADMIN_API);
}

hooks()->add_action('pre_deactivate_module', 'flutex_admin_api_deregister');
function flutex_admin_api_deregister()
{
    delete_option(FLUTEX_ADMIN_API.'_verification_key');
    delete_option(FLUTEX_ADMIN_API.'_last_verification');
    delete_option(FLUTEX_ADMIN_API.'_log');
}