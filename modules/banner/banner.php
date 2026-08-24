<?php

defined('BASEPATH') || exit('No direct script access allowed');

/*
    Module Name: BannerCraft
    Description: Robust tool to effortlessly organize your banners, enhancing the visual appeal and effectiveness of your CRM
    Version: 1.2.1
    Requires at least: 3.0.*
    Module URI: https://codecanyon.net/item/bannercraft-dynamic-banner-management-module-for-perfex-crm/51504146
    Author: <a href="https://codecanyon.net/user/corbitaltech" target="_blank">Corbital Technologies<a/>
*/

define('BN_CTL_PERFEX_VERSION', get_app_version() >= '3.2.0');
/*
 * Define module name
 * Module Name Must be in CAPITAL LETTERS
 */
define('BANNER_MODULE', 'banner');

define('BANNER_MODULE_ATTACHMENTS_FOLDER', FCPATH.'/uploads/banner');

require_once __DIR__.'/vendor/autoload.php';

/*
 * Register activation module hook
 */
register_activation_hook(BANNER_MODULE, 'banner_module_activation_hook');
function banner_module_activation_hook() {
    require_once __DIR__ . '/install.php';
}

/*
 * Register deactivation module hook
 */
register_deactivation_hook(BANNER_MODULE, 'banner_module_deactivation_hook');
function banner_module_deactivation_hook() {
    $my_files_list = [
        VIEWPATH.'themes/perfex/views/my_home.php',
    ];

    foreach ($my_files_list as $actual_path) {
        if (file_exists($actual_path)) {
            @unlink($actual_path);
        }
    }
}

/*
 * Register language files, must be registered if the module is using languages
 */
register_language_files(BANNER_MODULE, [BANNER_MODULE]);

/*
 * Load module helper file
 */
get_instance()->load->helper(BANNER_MODULE.'/banner');

// Do NOT require install.php here — it must run only via activation hook.
// Requiring it on every request caused HTTP 500 (undefined sprintsf / license die).

require_once __DIR__.'/includes/assets.php';
require_once __DIR__.'/includes/staff_permissions.php';
require_once __DIR__.'/includes/sidebar_menu_links.php';

// Soft license stubs — keep module usable without Envato heartbeat kill-switches.
if (class_exists('\modules\banner\core\Apiinit')) {
    // no-op on boot; activation form still available via pre_activate_module
}

hooks()->add_filter('module_banner_action_links', function ($actions) {
    try {
        get_instance()->load->library(BANNER_MODULE . '/banner_aeiou');
        $update = get_instance()->banner_aeiou->checkUpdateStatus(BANNER_MODULE);
        if ($update > 0) {
            $actions[] = '<a href="' . admin_url('banner/env_ver/check_update') . '" class="text-warning">' . _l('check_update') . '</a>';
        }
    } catch (Throwable $e) {
        // Ignore update-check failures (license API / network).
    }

    return $actions;
});

hooks()->add_filter('get_upload_path_by_type', function ($path, $type) {
    switch ($type) {
        case 'banner':
            $path = BANNER_MODULE_ATTACHMENTS_FOLDER;
            break;

        default:
            $path = $path;
            break;
    }

    return $path;
}, 0, 2);


function bannerContent($allowArea, $value = '') {
    $details['banner'] = getBannerDetails($allowArea);
    $details['news'] = getNewsTicker($allowArea);
    if (!empty($details['banner']) || !empty($details['news'])) {
        return renderBanner($details);
    }
}

hooks()->add_action('before_start_render_dashboard_content', function () {
    $content = bannerContent('admin_area');
    echo $content;
});

hooks()->add_action('display_banner_for_client_area', function () {
    $content = '<div class="row">';
    $content .= bannerContent('clients_area');
    $content .= '</div>';
    echo $content;
});
