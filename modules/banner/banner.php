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

if (!defined('BN_CTL_PERFEX_VERSION')) {
    define('BN_CTL_PERFEX_VERSION', get_app_version() >= '3.2.0');
}
if (!defined('BANNER_MODULE')) {
    define('BANNER_MODULE', 'banner');
}
if (!defined('BANNER_MODULE_ATTACHMENTS_FOLDER')) {
    define('BANNER_MODULE_ATTACHMENTS_FOLDER', FCPATH . '/uploads/banner');
}

register_activation_hook(BANNER_MODULE, 'banner_module_activation_hook');
if (!function_exists('banner_module_activation_hook')) {
    function banner_module_activation_hook() {
        require_once __DIR__ . '/install.php';
    }
}

register_deactivation_hook(BANNER_MODULE, 'banner_module_deactivation_hook');
if (!function_exists('banner_module_deactivation_hook')) {
    function banner_module_deactivation_hook() {
        $themeHome = VIEWPATH . 'themes/perfex/views/my_home.php';
        if (file_exists($themeHome)) {
            @unlink($themeHome);
        }
    }
}

register_language_files(BANNER_MODULE, [BANNER_MODULE]);

try {
    get_instance()->load->helper(BANNER_MODULE . '/banner');
    require_once __DIR__ . '/includes/assets.php';
    require_once __DIR__ . '/includes/staff_permissions.php';
    require_once __DIR__ . '/includes/sidebar_menu_links.php';
} catch (Throwable $e) {
    log_message('error', 'Banner module boot failed: ' . $e->getMessage());
}

hooks()->add_filter('get_upload_path_by_type', function ($path, $type) {
    if ($type === 'banner') {
        return BANNER_MODULE_ATTACHMENTS_FOLDER;
    }

    return $path;
}, 0, 2);

if (!function_exists('bannerContent')) {
    function bannerContent($allowArea, $value = '') {
        if (!function_exists('getBannerDetails') || !function_exists('getNewsTicker')) {
            return '';
        }
        $details['banner'] = getBannerDetails($allowArea);
        $details['news'] = getNewsTicker($allowArea);
        if (!empty($details['banner']) || !empty($details['news'])) {
            return renderBanner($details);
        }

        return '';
    }
}

hooks()->add_action('app_init', 'banner_ensure_client_theme_override', 20);
hooks()->add_action('admin_init', 'banner_ensure_client_theme_override', 20);

if (!function_exists('banner_ensure_client_theme_override')) {
    /**
     * BannerCraft shows client banners via my_home.php theme override.
     * SaaS / incomplete installs often skip copy — recreate it when missing.
     */
    function banner_ensure_client_theme_override()
    {
        if (!defined('BANNER_MODULE') || !defined('VIEWPATH')) {
            return;
        }

        $themeHome = VIEWPATH . 'themes/perfex/views/my_home.php';
        $themeHomeSrc = module_dir_path(BANNER_MODULE, 'resources/application/views/themes/perfex/views/my_home.php');

        if (file_exists($themeHome) || !file_exists($themeHomeSrc)) {
            return;
        }

        $dir = dirname($themeHome);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @copy($themeHomeSrc, $themeHome);
    }
}

hooks()->add_action('before_start_render_dashboard_content', function () {
    try {
        echo bannerContent('admin_area');
    } catch (Throwable $e) {
        log_message('error', 'Banner dashboard render failed: ' . $e->getMessage());
    }
});

hooks()->add_action('display_banner_for_client_area', function () {
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    try {
        echo '<div class="row">' . bannerContent('clients_area') . '</div>';
    } catch (Throwable $e) {
        log_message('error', 'Banner client render failed: ' . $e->getMessage());
    }
});
