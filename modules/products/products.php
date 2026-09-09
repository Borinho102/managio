<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Perfex Shop
Module URI: https://codecanyon.net/item/perfex-shop-ecommerce-module-for-perfex-crm-products-services/27169285
Description: Ecommerce module for Perfex CRM (Sell Products & Services)
Version: 1.5.4
Requires at least: 2.3.*
Author: Themesic Interactive
Author URI: https://codecanyon.net/user/themesic/portfolio
*/

//Module name
define('PRODUCTS_MODULE', 'products');
require_once __DIR__.'/vendor/autoload.php';
modules\products\core\Apiinit::the_da_vinci_code(PRODUCTS_MODULE);
modules\products\core\Apiinit::ease_of_mind(PRODUCTS_MODULE);

// Define upload folder location (module - for digital, exit popups, gift cards)
define('PRODUCT_MODULE_UPLOAD_FOLDER', module_dir_path(PRODUCTS_MODULE, 'uploads/'));
// Product images stored in Perfex uploads (survives module updates/deploys)
define('PRODUCT_IMAGES_UPLOAD_PATH', FCPATH . 'uploads/products/');

// Get codeigniter instance
$CI = &get_instance();

// Register activation module hook
register_activation_hook(PRODUCTS_MODULE, 'products_module_activation_hook');
function products_module_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__.'/install.php';
}

// Register language files, must be registered if the module is using languages
register_language_files(PRODUCTS_MODULE, [PRODUCTS_MODULE]);

// Load module helper file
$CI->load->helper(PRODUCTS_MODULE.'/products');

// Load module Library file
 $CI->load->library(PRODUCTS_MODULE.'/'.'products_lib');

// Inject css file for products module
hooks()->add_action('app_admin_head', 'products_add_head_components');
function products_add_head_components()
{
    if ('1' != get_option('products_enabled')) {
        return;
    }
    $CI = &get_instance();
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    echo '<link href="'.module_dir_url('products', 'assets/css/products.css').'?v='.$CI->app_scripts->core_version().'"  rel="stylesheet" type="text/css" />';
    if (strpos($uri, '/admin/settings') === false || strpos($uri, 'group=products') === false) {
        echo '<script src="'.module_dir_url('products', 'assets/js/products.js').'?v='.$CI->app_scripts->core_version().'"></script>';
    }
}

// Inject Javascript file for products module
hooks()->add_action('app_admin_footer', 'products_load_js');
function products_load_js()
{
    if ('1' == get_option('products_enabled')) {
        $CI = &get_instance();

        echo '<script src="'.module_dir_url('products', 'assets/highcharts/highcharts.js').'?v='.$CI->app_scripts->core_version().'"></script>';
        echo '<script src="'.module_dir_url('products', 'assets/highcharts/variable-pie.js').'?v='.$CI->app_scripts->core_version().'"></script>';
        echo '<script src="'.module_dir_url('products', 'assets/highcharts/export-data.js').'?v='.$CI->app_scripts->core_version().'"></script>';
        echo '<script src="'.module_dir_url('products', 'assets/highcharts/accessibility.js').'?v='.$CI->app_scripts->core_version().'"></script>';
        echo '<script src="'.module_dir_url('products', 'assets/highcharts/exporting.js').'?v='.$CI->app_scripts->core_version().'"></script>';
        echo '<script src="'.module_dir_url('products', 'assets/highcharts/highcharts-3d.js').'?v='.$CI->app_scripts->core_version().'"></script>';
    }
}

// Redirect to cart/checkout after login when guest was sent to login from place_order
hooks()->add_action('after_contact_login', 'products_redirect_after_login');
hooks()->add_action('after_client_register_logged_in', 'products_redirect_after_login');
function products_redirect_after_login()
{
    $CI = &get_instance();
    $url = $CI->session->userdata('products_redirect_after_login');
    if (!empty($url)) {
        $CI->session->unset_userdata('products_redirect_after_login');
        redirect($url);
    }
}

// Inject Style file for products frontendview
hooks()->add_action('app_customers_footer', 'customers_load_css');

// SEO meta for product detail page
hooks()->add_action('app_customers_head', 'products_seo_meta');
function products_seo_meta()
{
    if (get_option('product_seo_meta_enabled') != '1') {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/products/client/product/') !== false && get_option('products_enabled') == '1') {
        $CI = &get_instance();
        if (isset($CI->data['meta_description']) && !empty($CI->data['meta_description'])) {
            echo '<meta name="description" content="' . htmlspecialchars($CI->data['meta_description']) . '">' . "\n";
        }
        if (isset($CI->data['canonical_url']) && !empty($CI->data['canonical_url'])) {
            echo '<link rel="canonical" href="' . htmlspecialchars($CI->data['canonical_url']) . '">' . "\n";
        }
    }
}

// Remarketing pixels
hooks()->add_action('app_customers_head', 'products_remarketing_head');
function products_remarketing_head()
{
    if (get_option('products_enabled') != '1') {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/products/client') === false) {
        return;
    }
    if (get_option('product_remarketing_facebook_enabled') != '1' && get_option('product_remarketing_google_enabled') != '1' && get_option('product_remarketing_custom_enabled') != '1') {
        return;
    }
    $fb = get_option('product_remarketing_facebook_enabled') == '1' ? get_option('product_remarketing_facebook_pixel_id') : '';
    $gid = get_option('product_remarketing_google_enabled') == '1' ? get_option('product_remarketing_google_id') : '';
    $CI = &get_instance();
    if (!isset($CI->currencies_model)) {
        $CI->load->model('currencies_model');
    }
    $base = $CI->currencies_model->get_base_currency();
    $currency = $base ? $base->name : 'USD';
    $config = ['facebook' => $fb, 'google_id' => $gid, 'google_label' => get_option('product_remarketing_google_label') ?: '', 'currency' => $currency];
    if ($fb || $gid) {
        echo '<script>var _productsRemarketing=' . json_encode($config) . ';</script>' . "\n";
    }
    if ($fb) {
        echo "<!-- Facebook Pixel -->\n<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . $fb . "');fbq('track','PageView');</script>\n";
        echo "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id=" . $fb . "&ev=PageView&noscript=1\"/></noscript>\n";
    }
    if ($gid) {
        echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . $gid . "\"></script>\n";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $gid . "');</script>\n";
    }
    if (get_option('product_remarketing_custom_enabled') == '1' && get_option('product_remarketing_custom_script')) {
        echo get_option('product_remarketing_custom_script') . "\n";
    }
}

hooks()->add_action('app_customers_footer', 'products_remarketing_footer');

hooks()->add_action('app_customers_footer', 'products_exit_popups_footer');
function products_exit_popups_footer()
{
    if (get_option('products_enabled') != '1') {
        return;
    }
    $test_mode = !empty($_GET['products_exit_popup_test']);
    if (!get_option('product_exit_popups_enabled') && !$test_mode) {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/products/client') === false) {
        return;
    }
    $CI = &get_instance();
    $CI->load->model('products/exit_popups_model');
    $has_cart = !empty($CI->session->cart_data);
    $page = 'product_listing';
    if (strpos($uri, '/products/client/place_order') !== false) {
        $page = 'cart_checkout';
    } elseif (strpos($uri, '/products/client/product/') !== false) {
        $page = 'product_detail';
    }
    $popups = $CI->exit_popups_model->get_active_for_page($page, $has_cart);
    if (empty($popups) && !$test_mode) {
        return;
    }
    if ($test_mode && empty($popups)) {
        $all = $CI->exit_popups_model->get();
        $popups = is_array($all) ? $all : ($all ? [$all] : []);
        $popups = array_values(array_filter($popups, function ($p) {
            $a = is_array($p) ? $p : (array) $p;
            return !empty($a['active']);
        }));
    }
    $base = module_dir_url('products', 'uploads/exit_popups/');
    foreach ($popups as &$p) {
        $p['image_url'] = !empty($p['image_path']) ? $base . $p['image_path'] : '';
    }
    $dismiss_days = (int) get_option('product_exit_popup_dismiss_days');
    $track_url = site_url('products/client/popup_track/');
    echo '<script>var _productsExitPopups=' . json_encode(array_values($popups)) . ';var _productsExitPopupDismissDays=' . $dismiss_days . ';var _productsExitPopupTrackUrl="' . addslashes($track_url) . '";var _productsExitPopupTest=' . ($test_mode ? '1' : '0') . ';</script>' . "\n";
    echo '<script src="' . module_dir_url('products', 'assets/js/exit_popup.js') . '"></script>' . "\n";
}

hooks()->add_action('app_customers_head', 'products_heatmap_head');
function products_heatmap_head()
{
    if (get_option('products_enabled') != '1' || get_option('product_heatmap_script_enabled') != '1' || empty(get_option('product_heatmap_script'))) {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/products/client') === false) {
        return;
    }
    echo get_option('product_heatmap_script') . "\n";
}
function products_remarketing_footer()
{
    if (get_option('products_enabled') != '1') {
        return;
    }
    if (get_option('product_remarketing_facebook_enabled') != '1' && get_option('product_remarketing_google_enabled') != '1') {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/products/client') === false) {
        return;
    }
    echo '<script src="' . module_dir_url('products', 'assets/js/remarketing.js') . '"></script>' . "\n";
}
function customers_load_css()
{
    if ('1' == get_option('products_enabled')) {
        $CI      = &get_instance();
        $viewuri = $_SERVER['REQUEST_URI'];
        if (false !== strpos($viewuri, '/products/client')) {
            echo '<link href="'.module_dir_url('products', 'assets/css/products_frontend.css').'?v='.$CI->app_scripts->core_version().'"  rel="stylesheet" type="text/css" />';
        }
    }
}

//inject permissions Feature and Capabilities for products module
hooks()->add_filter('staff_permissions', 'products_module_permissions_for_staff');
function products_module_permissions_for_staff($permissions)
{
    $viewGlobalName      = _l('permission_view').'('._l('permission_global').')';
    $allPermissionsArray = [
        'view'     => $viewGlobalName,
        'create'   => _l('permission_create'),
    ];
    $permissions['products'] = [
        'name'         => _l('products'),
        'capabilities' => $allPermissionsArray,
    ];

    return $permissions;
}

// Inject sidebar menu and links for products module
hooks()->add_action('admin_init', 'products_module_init_menu_items');
function products_module_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('products', [
            'slug'     => 'Products',
            'name'     => _l('products'),
            'icon'     => 'fa fa-cart-plus',
            'href'     => '#',
            'position' => 30,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'Products',
            'name'     => _l('products'),
            'href'     => admin_url('products'),
            'position' => 1,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'products_categories',
            'name'     => _l('products_categories'),
            'href'     => admin_url('products/products_categories'),
            'position' => 5,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'order_history',
            'name'     => _l('order_history'),
            'href'     => admin_url('products/order_history'),
            'position' => 5,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'add_new_order',
            'name'     => _l('add_new_order'),
            'href'     => admin_url('products/staff_order'),
            'position' => 8,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'variations',
            'name'     => _l('variations'),
            'href'     => admin_url('products/variations'),
            'position' => 9,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'exit_popups',
            'name'     => _l('exit_popups'),
            'href'     => admin_url('products/exit_popups'),
            'position' => 9,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'product_notifications',
            'name'     => _l('product_notifications'),
            'href'     => admin_url('products/product_notifications'),
            'position' => 9,
        ]);
    }

    if (has_permission('products', '', 'view') && get_option('product_reviews_enabled') == '1') {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'product_reviews',
            'name'     => _l('product_reviews'),
            'href'     => admin_url('products/product_reviews'),
            'position' => 10,
        ]);
    }

    if (has_permission('products', '', 'view') && get_option('product_referral_enabled') == '1') {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'product_referrals',
            'name'     => _l('product_referral_program'),
            'href'     => admin_url('products/product_referrals'),
            'position' => 10,
        ]);
    }

    if (has_permission('products', '', 'view') && get_option('product_gift_cards_enabled') == '1') {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'product_gift_cards',
            'name'     => _l('product_gift_cards'),
            'href'     => admin_url('products/product_gift_cards'),
            'position' => 10,
        ]);
    }

    if (has_permission('products', '', 'view') && get_option('product_upsell_enabled') == '1') {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'product_upsell',
            'name'     => _l('product_upsell_rules'),
            'href'     => admin_url('products/product_upsell'),
            'position' => 10,
        ]);
    }

    if (0 == get_option('coupons_disabled')) {
        if (has_permission('products', '', 'view')) {
            $CI->app_menu->add_sidebar_children_item('products', [
                'slug'     => 'coupons',
                'name'     => _l('coupons'),
                'href'     => admin_url('products/coupons'),
                'position' => 10,
            ]);
        }
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('reports', [
            'slug'     => 'order_report',
            'name'     => _l('order_report'),
            'href'     => admin_url('products/order_report'),
            'position' => 7,
        ]);
    }
    if (has_permission('products', '', 'view') && get_option('product_analytics_enabled') != '0') {
        $CI->app_menu->add_sidebar_children_item('products', [
            'slug'     => 'analytics',
            'name'     => _l('analytics'),
            'href'     => admin_url('products/analytics'),
            'position' => 6,
        ]);
    }

    if (has_permission('products', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('reports', [
            'slug'     => 'quantities_report',
            'name'     => _l('quantities_report'),
            'href'     => admin_url('products/quantities_report'),
            'position' => 8,
        ]);
    }
    
    // Add settings menu(tab menu) In Admin Side
    $CI->app->add_settings_section_child('other', 'products', [
        'name'     => 'Products',
        'view'     => 'products/settings',
        'position' => 60,
        'icon'     => 'fa fa-shopping-cart',
    ]);
}

// Inject email template for products module
hooks()->add_action('after_email_templates', 'add_email_template_products');
function add_email_template_products()
{
    $CI                        = &get_instance();
    $data['hasPermissionEdit'] = has_permission('email_templates', '', 'edit');
    $data['orders']            = $CI->emails_model->get([
        'type'     => 'order',
        'language' => 'english',
    ]);
    $CI->load->view('products/mail_lists/email_templates_list', $data, false);
}

hooks()->add_action('after_email_templates', 'add_email_template_products_marketing');
function add_email_template_products_marketing()
{
    $CI = &get_instance();
    $data['hasPermissionEdit'] = has_permission('email_templates', '', 'edit');
    $data['products_templates'] = $CI->emails_model->get([
        'type'     => 'products',
        'language' => 'english',
    ]);
    $CI->load->view('products/mail_lists/email_templates_marketing_list', $data, false);
}

// Inject merge fields that will be used email templates for products module
register_merge_fields('products/order_merge_fields');

hooks()->add_filter('available_merge_fields', 'products_fields_merge');
function products_fields_merge($fields)
{
    $products_types = ['order', 'products'];
    $final_fields = [];
    foreach ($fields as $key => $value) {
        if (isset($value['other'])) {
            foreach ($value['other'] as $s_key => $s_value) {
                if (!empty($value['other'][$s_key]['available'])) {
                    foreach ($products_types as $pt) {
                        if (!in_array($pt, $value['other'][$s_key]['available'])) {
                            array_push($value['other'][$s_key]['available'], $pt);
                        }
                    }
                }
            }
        }
        if (isset($value['client'])) {
            foreach ($value['client'] as $s_key => $s_value) {
                if (!empty($value['client'][$s_key]['available'])) {
                    foreach ($products_types as $pt) {
                        if (!in_array($pt, $value['client'][$s_key]['available'])) {
                            array_push($value['client'][$s_key]['available'], $pt);
                        }
                    }
                }
            }
        }
        if (isset($value['invoice'])) {
            foreach ($value['invoice'] as $s_key => $s_value) {
                if (!empty($value['invoice'][$s_key]['available'])) {
                    foreach ($products_types as $pt) {
                        if (!in_array($pt, $value['invoice'][$s_key]['available'])) {
                            array_push($value['invoice'][$s_key]['available'], $pt);
                        }
                    }
                }
            }
        }
        $final_fields[$key] = $value;
    }

    return $final_fields;
}

hooks()->add_filter('other_merge_fields_available_for', 'products_merge_fields_available_for');
function products_merge_fields_available_for($available)
{
    if (!in_array('products', $available)) {
        $available[] = 'products';
    }
    return $available;
}

// Add Menu In Customer Side
hooks()->add_action('customers_navigation_start', 'add_product_menu');
function add_product_menu()
{
	// Show products only at logged in users for clients area
    if (1 == get_option('nlu_product_menu_disabled') && 0 == get_option('product_menu_disabled')) {
		if (is_client_logged_in()) {
			echo '<li class="customers-nav-item-contracts">
				<a href="'.site_url('products/client').'">'._l('products').'</a>
			</li>';
		}
	}
	
	// Allow products view for everyone in clients area
    if (0 == get_option('nlu_product_menu_disabled') && 0 == get_option('product_menu_disabled')) {
        echo '<li class="customers-nav-item-contracts">
            <a href="'.site_url('products/client').'">'._l('products').'</a>
        </li>';
        if (is_client_logged_in()) {
            if (get_option('product_wishlist_enabled') != '0') {
                echo '<li class="customers-nav-item-contracts">
                    <a href="'.site_url('products/client/wishlist').'">'._l('wishlist').'</a>
                </li>';
            }
            if (get_option('product_digital_downloads_enabled') != '0') {
                echo '<li class="customers-nav-item-contracts">
                    <a href="'.site_url('products/client/downloads').'">'._l('my_downloads').'</a>
                </li>';
            }
            if (get_option('product_referral_enabled') == '1') {
                echo '<li class="customers-nav-item-contracts">
                    <a href="'.site_url('products/client/referral').'">'._l('product_referral_program').'</a>
                </li>';
            }
            if (get_option('product_gift_cards_enabled') == '1') {
                echo '<li class="customers-nav-item-contracts">
                    <a href="'.site_url('products/client/buy_gift_card').'">'._l('product_buy_gift_card').'</a>
                </li>';
            }
        }
	}
}

hooks()->add_action('settings_tab_footer', 'add_product_setting_footer');
function add_product_setting_footer($tab)
{
    if (isset($tab['slug']) && $tab['slug'] == 'products') {
        echo '<script type="text/javascript">
                $(function() {
                    $("select.selectpicker.tax").removeAttr("multiple");
                    $("select.selectpicker.tax").parent().removeClass("show-tick");
                    if (typeof init_selectpicker === "function") init_selectpicker();
                    $("[data-toggle=tooltip]").tooltip({container: "body"});
                });
            </script>';
    }
}

hooks()->add_filter('before_settings_updated', 'update_setting_data');
function update_setting_data($data)
{
    if (!empty($data['settings']['product_tax_for_shipping_cost'])) {
        $data['settings']['product_tax_for_shipping_cost'] = serialize($data['settings']['product_tax_for_shipping_cost']);
    }
    // The enabled-currencies multi-select posts an array; store it as the comma
    // separated id list the pricing helpers read. An empty selection must still
    // be saved, so this cannot sit behind an !empty() check.
    foreach (['product_currencies_enabled', 'product_currencies_no_tax'] as $currency_list_option) {
        if (!array_key_exists($currency_list_option, $data['settings'] ?? [])) {
            continue;
        }
        $selected_currencies = $data['settings'][$currency_list_option];
        if (!is_array($selected_currencies)) {
            $selected_currencies = array_filter(explode(',', (string) $selected_currencies));
        }
        $selected_currencies = array_unique(array_filter(array_map('intval', $selected_currencies)));
        $data['settings'][$currency_list_option] = implode(',', $selected_currencies);
    }

    return $data;
}

// Inject upload folder location for products module
hooks()->add_filter('get_upload_path_by_type', 'product_upload_folder', 10, 2);
function product_upload_folder($path, $type)
{
    if ('products' == $type) {
        return PRODUCT_MODULE_UPLOAD_FOLDER;
    }

    return $path;
}

// Change Order Status On change Invoice status
hooks()->add_action('invoice_status_changed', 'change_order_status');
function change_order_status($data)
{
    if (!class_exists('Invoices_model', false)) {
        get_instance()->load->model('invoices_model');
    }
    $CI = &get_instance();
    $CI->load->model('products/order_model');
        if (Invoices_model::STATUS_PAID == $data['status']) {
            $CI->order_model->update_quantity_on_invoice($data['invoice_id']);
            $order = $CI->db->where('invoice_id', $data['invoice_id'])->get(db_prefix() . 'order_master')->row();
            if ($order) {
                products_send_notification('order_paid', $order);
                if (get_option('product_social_proof_enabled') == '1' && $CI->db->table_exists(db_prefix() . 'product_social_proof')) {
                    products_record_recent_purchase($order);
                }
            }
        if (get_option('product_gift_cards_enabled') == '1' && $CI->db->table_exists(db_prefix() . 'product_gift_card_purchases')) {
            $purchases = $CI->db->where('invoice_id', $data['invoice_id'])->where('gift_card_id', null)->get(db_prefix() . 'product_gift_card_purchases')->result_array();
            foreach ($purchases as $p) {
                products_create_gift_card_from_purchase($p);
            }
        }
    }
    $CI->order_model->update_status($data['invoice_id'], $data['status']);
}

hooks()->add_action('after_invoice_added', 'products_after_invoice_added');
function products_after_invoice_added($invoice_id)
{
    $CI = &get_instance();
    $order = $CI->db->where('invoice_id', $invoice_id)->get(db_prefix() . 'order_master')->row();
    if ($order) {
        products_send_notification('order_placed', $order);
        if (get_option('product_referral_enabled') == '1' && !empty($order->referral_code_id)) {
            $CI->load->model('products/product_referrals_model');
            $CI->product_referrals_model->record_order_referral($order->id, $order->referral_code_id, $order->total);
        }
    }
}

hooks()->add_action('invoice_marked_as_cancelled', 'change_cancel_order');

function change_cancel_order($invoice_id)
{
    if (!class_exists('Invoices_model', false)) {
        get_instance()->load->model('invoices_model');
    }

    $CI = &get_instance();
    $CI->load->model('products/order_model');
    $CI->order_model->update_status($invoice_id, Invoices_model::STATUS_CANCELLED);
}

hooks()->add_action('app_init', PRODUCTS_MODULE.'_actLib');
function products_actLib()
{
    $CI = &get_instance();
    $CI->load->library(PRODUCTS_MODULE.'/Products_aeiou');
    $license_valid = $CI->products_aeiou->validatePurchase(PRODUCTS_MODULE);
    if (!$license_valid) {
        set_alert('danger', 'One of your modules failed its verification and got deactivated. Please reactivate or contact support.');
    }
}

hooks()->add_action('pre_activate_module', PRODUCTS_MODULE.'_sidecheck');
function products_sidecheck($module_name)
{
    if (PRODUCTS_MODULE == $module_name['system_name']) {
        modules\products\core\Apiinit::activate($module_name);
    }
}

hooks()->add_action('pre_deactivate_module', PRODUCTS_MODULE.'_deregister');
function products_deregister($module_name)
{
    if (PRODUCTS_MODULE == $module_name['system_name']) {
        delete_option(PRODUCTS_MODULE.'_verification_id');
        delete_option(PRODUCTS_MODULE.'_last_verification');
        delete_option(PRODUCTS_MODULE.'_product_token');
        delete_option(PRODUCTS_MODULE.'_heartbeat');
    }
}
