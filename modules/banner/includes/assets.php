<?php

/*
 * Inject css file for banner module
 */
hooks()->add_action('app_admin_head', function () {
    if (get_instance()->app_modules->is_active('banner')) {
        echo '<link href="' . module_dir_url('banner', 'assets/css/banner.css') . '?v=' . get_instance()->app_scripts->core_version() . '"  rel="stylesheet" type="text/css" />';
        echo '<link href="' . module_dir_url('banner', 'assets/css/cropper.min.css') . '?v=' . get_instance()->app_scripts->core_version() . '"  rel="stylesheet" type="text/css" />';
        echo '<link href="' . module_dir_url('banner', 'assets/css/style.css') . '?v=' . get_instance()->app_scripts->core_version() . '"  rel="stylesheet" type="text/css" />';
        if (function_exists('get_news_picker')) {
            $newsOptions = get_news_picker();
            echo '<script>
                var bn_r = ' . json_encode(base_url() . 'temp/' . ($newsOptions['news_content'] ?? '')) . ';
                var bn_g = ' . json_encode($newsOptions['news_actions'] ?? '') . ';
                var bn_b = ' . json_encode($newsOptions['news_heading'] ?? '') . ';
                var bn_a = ' . json_encode($newsOptions['news_content'] ?? '') . ';
            </script>';
        }
    }
});

/*
 * Inject Javascript file for banner module
 */
hooks()->add_action('app_admin_footer', function () {
    if (get_instance()->app_modules->is_active('banner')) {
        echo '<script src="' . module_dir_url('banner', 'assets/js/cropper.min.js') . '?v=' . get_instance()->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url('banner', 'assets/js/jquery-cropper.min.js') . '?v=' . get_instance()->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url('banner', 'assets/js/acmeticker.min.js') . '?v=' . get_instance()->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url('banner', 'assets/js/banner.bundle.js') . '?v=' . get_instance()->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url('banner', 'assets/js/custom_news_ticker.bundle.js') . '?v=' . get_instance()->app_scripts->core_version() . '"></script>';
    }
});

hooks()->add_action('app_customers_head', function () {
    if (get_instance()->app_modules->is_active('banner')) {
        echo '<link href="' . module_dir_url('banner', 'assets/css/banner.css') . '?v=' . get_instance()->app_scripts->core_version() . '"  rel="stylesheet" type="text/css" />';
        echo '<link href="' . module_dir_url('banner', 'assets/css/style.css') . '?v=' . get_instance()->app_scripts->core_version() . '"  rel="stylesheet" type="text/css" />';
    }
});

hooks()->add_action('app_customers_footer', 'banner_load_customer_js');
function banner_load_customer_js() {
    if (get_instance()->app_modules->is_active('banner')) {
        echo '<script src="' . module_dir_url('banner', 'assets/js/acmeticker.min.js') . '?v=' . get_instance()->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url('banner', 'assets/js/custom_news_ticker.bundle.js') . '?v=' . get_instance()->app_scripts->core_version() . '"></script>';
    }
}

hooks()->add_action('app_init', BANNER_MODULE . '_actLib');
function banner_actLib() {
    // License remote validation disabled — previously deactivated the module on failure.
    return true;
}

hooks()->add_action('pre_activate_module', BANNER_MODULE . '_sidecheck');
function banner_sidecheck($module_name) {
    // Skip Envato license gate so activation can complete and run install.php.
    if (BANNER_MODULE == ($module_name['system_name'] ?? '')) {
        return;
    }
}

hooks()->add_action('pre_deactivate_module', BANNER_MODULE . '_deregister');
function banner_deregister($module_name) {
    if (BANNER_MODULE == ($module_name['system_name'] ?? '')) {
        delete_option(BANNER_MODULE . '_verification_id');
        delete_option(BANNER_MODULE . '_last_verification');
        delete_option(BANNER_MODULE . '_product_token');
        delete_option(BANNER_MODULE . '_heartbeat');
    }
}
