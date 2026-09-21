<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Required app theme head hook
 */
if (function_exists('app_theme_affiliate_head_hook')) {
    hooks()->add_action('app_affiliates_head', 'app_theme_affiliate_head_hook');
}

/**
 * Default theme menu items
 * In most cases you will want to add this hook because of all the features
 */
if (function_exists('add_default_theme_menu_items')) {
    hooks()->add_action('clients_init', 'add_default_theme_menu_items');
}

if (function_exists('register_theme_affiliate_assets_hook')) {
    register_theme_affiliate_assets_hook('theme_affiliate');
}

if (!function_exists('theme_affiliate')) {
function theme_affiliate()
{
    $CI = &get_instance();

    $groupName = $CI->app_scripts->default_theme_group();
    $themePath = function_exists('theme_assets_path') ? theme_assets_path() : 'assets/themes/perfex';
    $ver = $CI->app_css->core_version();

    // CSS first so a JS helper failure cannot leave the portal unstyled.
    if (function_exists('add_favicon_link_asset')) {
        try {
            add_favicon_link_asset($groupName);
        } catch (Throwable $e) {
            // ignore favicon failures
        }
    }

    $CI->app_css->theme('reset-css', 'assets/css/reset.css');
    $CI->app_css->theme('bootstrap-css', 'assets/plugins/bootstrap/css/bootstrap.min.css');
    $CI->app_css->theme('inter-font', 'https://rsms.me/inter/inter.css');

    if (function_exists('is_rtl') && is_rtl()) {
        $CI->app_css->theme('bootstrap-rtl-css', 'assets/plugins/bootstrap-arabic/css/bootstrap-arabic.min.css');
    }

    $CI->app_css->theme('datatables-css', 'assets/plugins/datatables/datatables.min.css');
    $CI->app_css->theme('fontawesome-css', 'assets/plugins/font-awesome/css/fontawesome.min.css');
    $CI->app_css->theme('fontawesome-brands', 'assets/plugins/font-awesome/css/brands.min.css');
    $CI->app_css->theme('fontawesome-solid', 'assets/plugins/font-awesome/css/solid.min.css');
    $CI->app_css->theme('fontawesome-regular', 'assets/plugins/font-awesome/css/regular.min.css');
    $CI->app_css->theme('datetimepicker-css', 'assets/plugins/datetimepicker/jquery.datetimepicker.min.css');
    $CI->app_css->theme('bootstrap-select-css', 'assets/plugins/bootstrap-select/css/bootstrap-select.min.css');
    $CI->app_css->theme('lightbox-css', 'assets/plugins/lightbox/css/lightbox.min.css');
    $CI->app_css->theme('colorpicker-css', 'assets/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css');
    $CI->app_css->theme('tailwind-css', 'assets/builds/tailwind.css', ['bootstrap-css']);
    $CI->app_css->theme('theme-css', rtrim($themePath, '/') . '/css/style.css');
    $CI->app_css->theme('affiliate-custom-css', 'modules/affiliate/assets/css/affiliate_portal.css?aff=20260921c');

    try {
        $CI->app_scripts->theme('bootstrap-js', 'assets/plugins/bootstrap/js/bootstrap.min.js');
        add_datatables_js_assets($groupName);
        add_jquery_validation_js_assets($groupName);
        add_bootstrap_select_js_assets($groupName);

        $CI->app_scripts->theme('datetimepicker-js', 'assets/plugins/datetimepicker/jquery.datetimepicker.full.min.js');
        $CI->app_scripts->theme('chart-js', 'assets/plugins/Chart.js/Chart.min.js');
        $CI->app_scripts->theme('colorpicker-js', 'assets/plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js');
        $CI->app_scripts->theme('lightbox-js', 'assets/plugins/lightbox/js/lightbox.min.js');
        $CI->app_scripts->theme('common-js', 'assets/builds/common.js');
        $CI->app_scripts->theme(
            'theme-global-js',
            base_url($CI->app_scripts->core_file($themePath . '/js', 'global.js')) . '?v=' . $ver,
            ['common-js']
        );

        if (function_exists('is_affiliate_logged_in') && is_affiliate_logged_in()) {
            $CI->app_scripts->theme('dropzone-js', 'assets/plugins/dropzone/min/dropzone.min.js');
            $CI->app_scripts->theme('circle-progress-js', 'assets/plugins/jquery-circle-progress/circle-progress.min.js');
            $CI->app_scripts->theme('jquery-comments-js', 'assets/plugins/jquery-comments/js/jquery-comments.min.js');
            $CI->app_scripts->theme('frape-gantt-js', 'assets/plugins/frappe/frappe-gantt-es2015.js');
            add_moment_js_assets($groupName);
            add_dropbox_js_assets($groupName);
            $CI->app_css->theme('jquery-comments-css', 'assets/plugins/jquery-comments/css/jquery-comments.css');
            $CI->app_css->theme('frappe-gantt-css', 'assets/plugins/frappe/frappe-gantt.css');
            $CI->app_css->theme('dropzone-basic-css', 'assets/plugins/dropzone/min/basic.min.css');
            $CI->app_css->theme('dropzone-css', 'assets/plugins/dropzone/min/dropzone.min.css');
            add_calendar_assets($groupName);
            if (get_option('enable_google_picker') == '1') {
                add_google_api_js_assets($groupName);
            }
            $CI->app_scripts->theme(
                'theme-js',
                base_url($CI->app_scripts->core_file($themePath . '/js', 'clients.js')) . '?v=' . $ver,
                ['common-js']
            );
        }
    } catch (Throwable $e) {
        log_message('error', 'theme_affiliate scripts: ' . $e->getMessage());
    }
}
}
