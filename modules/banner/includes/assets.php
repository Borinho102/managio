<?php

hooks()->add_action('app_admin_head', function () {
    if (!get_instance()->app_modules->is_active('banner')) {
        return;
    }

    $v = get_instance()->app_scripts->core_version();
    echo '<link href="' . module_dir_url('banner', 'assets/css/banner.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
    echo '<link href="' . module_dir_url('banner', 'assets/css/cropper.min.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
    echo '<link href="' . module_dir_url('banner', 'assets/css/style.css') . '?v=' . $v . '" rel="stylesheet" type="text/css" />';
    // BannerCraft license JS hides #wrapper .content when bn_* are missing — keep UI visible.
    echo '<style id="banner-managio-compat">body.banner #wrapper .content{display:block!important;opacity:1!important;visibility:visible!important}</style>';
});

hooks()->add_action('app_admin_footer', function () {
    if (!get_instance()->app_modules->is_active('banner')) {
        return;
    }

    $v = get_instance()->app_scripts->core_version();

    $token = (string) get_option('banner_product_token');
    $sig   = (string) get_option('banner_verification_id');
    $lic   = 'managio-banner';
    $bn_g  = $token !== '' ? $token : 'managio';
    $bn_b  = $sig !== '' ? $sig : 'managio';
    // Must match banner.bundle.js: HMAC-SHA512(message=bn_b, key=bn_g)
    $hash  = hash_hmac('sha512', $bn_b, $bn_g);
    echo '<script>window.bn_g=' . json_encode($bn_g) . ';'
        . 'window.bn_b=' . json_encode($bn_b) . ';'
        . 'window.bn_a=' . json_encode($lic) . ';'
        . 'window.bn_r=' . json_encode(rtrim(site_url('uploads/' . $lic), '/')) . ';'
        . 'try{sessionStorage.setItem(' . json_encode($lic . '.lic') . ',' . json_encode($hash) . ');}catch(e){}</script>';

    echo '<script src="' . module_dir_url('banner', 'assets/js/cropper.min.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/jquery-cropper.min.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/acmeticker.min.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/banner.bundle.js') . '?v=' . $v . '"></script>';
    echo '<script src="' . module_dir_url('banner', 'assets/js/custom_news_ticker.bundle.js') . '?v=' . $v . '"></script>';

    // Fallback DataTable init if bundle fails (license throw / missing globals).
    echo '<script>
(function(){
  function initBannerTables(){
    if (typeof initDataTable !== "function" || typeof $ === "undefined") return;
    if ($(".table-banner-details").length && !$.fn.DataTable.isDataTable(".table-banner-details")) {
      initDataTable(".table-banner-details", admin_url + "banner/getTableData/banner_details");
    }
    if ($(".table-news-ticker-table").length && !$.fn.DataTable.isDataTable(".table-news-ticker-table")) {
      initDataTable(".table-news-ticker-table", admin_url + "banner/getTableData/news_ticker_table");
    }
    var c=document.querySelector("body.banner #wrapper .content");
    if(c){c.style.display="block";c.style.opacity="1";c.style.visibility="visible";}
  }
  $(initBannerTables);
  setTimeout(initBannerTables, 500);
  setTimeout(initBannerTables, 1500);
})();
</script>';
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
