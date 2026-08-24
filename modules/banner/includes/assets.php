<?php

hooks()->add_action('app_admin_head', function () {
    if (!get_instance()->app_modules->is_active('banner')) {
        return;
    }

    $v = get_instance()->app_scripts->core_version();
    echo '<link href="' . module_dir_url('banner', 'assets/css/banner.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
    echo '<link href="' . module_dir_url('banner', 'assets/css/cropper.min.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
    echo '<link href="' . module_dir_url('banner', 'assets/css/style.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
});

hooks()->add_action('app_admin_footer', function () {
    if (!get_instance()->app_modules->is_active('banner')) {
        return;
    }

    $v = get_instance()->app_scripts->core_version();
    echo '<script src="' . module_dir_url('banner', 'assets/js/cropper.min.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/jquery-cropper.min.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/acmeticker.min.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/banner.bundle.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/custom_news_ticker.bundle.js') . '?v=' . $v . '"></script>';
});

hooks()->add_action('app_customers_head', function () {
    if (!get_instance()->app_modules->is_active('banner')) {
        return;
    }

    $v = get_instance()->app_scripts->core_version();
    echo '<link href="' . module_dir_url('banner', 'assets/css/banner.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
    echo '<link href="' . module_dir_url('banner', 'assets/css/style.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
});

if (!function_exists('banner_load_customer_js')) {
    function banner_load_customer_js() {
        if (!get_instance()->app_modules->is_active('banner')) {
            return;
        }

        $v = get_instance()->app_scripts->core_version();
        echo '<script src="' . module_dir_url('banner', 'assets/js/acmeticker.min.js') . '?v=' . $v . '"></script>';
        echo '<script src="' . module_dir_url('banner', 'assets/js/custom_news_ticker.bundle.js') . '?v=' . $v . '"></script>';
    }
}
hooks()->add_action('app_customers_footer', 'banner_load_customer_js');

if (!function_exists('banner_actLib')) {
    function banner_actLib() {
        return true;
    }
}
hooks()->add_action('app_init', 'banner_actLib');

if (!function_exists('banner_sidecheck')) {
    function banner_sidecheck($module_name) {
        return;
    }
}
hooks()->add_action('pre_activate_module', 'banner_sidecheck');

if (!function_exists('banner_deregister')) {
    function banner_deregister($module_name) {
        if (BANNER_MODULE == ($module_name['system_name'] ?? '')) {
            delete_option(BANNER_MODULE . '_verification_id');
            delete_option(BANNER_MODULE . '_last_verification');
            delete_option(BANNER_MODULE . '_product_token');
            delete_option(BANNER_MODULE . '_heartbeat');
        }
    }
}
hooks()->add_action('pre_deactivate_module', 'banner_deregister');
