<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
$route['saas/dashboard'] = 'saas/index';
$route['assignPackage'] = 'saas/gb_admin/assignPackage';
$route['updatePackage'] = 'saas/gb_admin/assignPackage';
$route['updatePackage/(:any)'] = 'saas/gb_admin/assignPackage/$1';
$route['checkoutPayment'] = 'saas/gb_admin/assignPackage';
$route['checkoutPayment/(:any)'] = 'saas/gb_admin/checkoutPayment/$1';
$route['privacy'] = 'saas/frontcms/home/privacy';
$route['tos'] = 'saas/frontcms/home/tos';
$route['find-my-company'] = "saas/frontcms/home/find_my_company";
$route['save_faq'] = 'saas/frontcms/home/save_faq/$1';
$route['front/contacts'] = 'settings/get_contacts';
$route['front/(:any)'] = 'saas/frontcms/home/page/$1';
$route['pricing'] = 'saas/frontcms/home/page/pricing';
$route['register'] = 'saas/frontcms/home/register';
$route['register/(:any)'] = 'saas/frontcms/home/register/$1';
$route['frontcms/(:any)'] = 'saas/frontcms/home/page/$1';
$route['front'] = 'saas/frontcms/home/index';
$route['affiliate-program'] = 'saas/frontcms/home/affiliate_program';
$route['become_affiliator'] = 'saas/frontcms/home/become_affiliator';
$route['affiliate'] = 'saas/frontcms/home/affiliate_program';
$route['affiliate/verify/(:any)'] = 'saas/affiliate/auth/verify/$1';
$route['affiliate/dashboard'] = 'saas/affiliate/dashboard';
$route['affiliate/commissions'] = 'saas/affiliate/dashboard/commissions';
$route['affiliate/payouts'] = 'saas/affiliate/dashboard/payouts';
$route['affiliate/delete_payouts/(:any)'] = 'saas/affiliate/dashboard/delete_payouts/$1';
$route['affiliate/payouts/(:any)/(:any)'] = 'saas/affiliate/dashboard/$1/$2';
$route['affiliate/referrals'] = 'saas/affiliate/dashboard/referrals';
$route['affiliate/settings'] = 'saas/affiliate/dashboard/settings';
$route['affiliate/(:any)'] = 'saas/affiliate/auth/$1';
$route['affiliate/auth/(:any)'] = 'saas/affiliate/auth/$1';
$route['home'] = 'saas/frontcms/home/index';
$route['signed_up'] = 'saas/gb/signed_up';
$route['setup'] = 'saas/setup';
$route['check_existing_activation_token_new'] = 'saas/setup/check_existing_activation_token_new';
$route['upgrade'] = 'saas/gb_admin/upgrade';
$route['get_package_info'] = 'saas/gb/get_package_info';
$route['get_package_info_s'] = 'saas/setup/get_package_info';
$route['check_coupon_code'] = 'saas/gb/check_coupon_code';
$route['package_details/(:any)'] = 'saas/gb/package_details/$1';
$route['subs_package_details/(:any)/(:any)'] = 'saas/gb/package_details/$1/$2';
$route['proceedPayment/(:any)'] = 'saas/gb/proceedPayment/$1';
$route['proceedPayment'] = 'saas/gb/proceedPayment';
$route['completePaypalPayment/(:any)'] = 'saas/gb/completePaypalPayment/$1';
$route['stipePaymentSuccess/(:any)'] = 'saas/gb/stipePaymentSuccess/$1';
$route['stripePayment/(:any)'] = 'saas/gb/stripePayment/$1';
$route['stripePayment'] = 'saas/gb/stripePayment';
$route['paymentCancel/(:any)'] = 'saas/gb/paymentCancel/$1';
$route['paymentCancel'] = 'saas/gb/paymentCancel';
$route['paymentSuccess/(:any)'] = 'saas/gb/paymentSuccess/$1';
$route['paymentSuccess'] = 'saas/gb/paymentSuccess';
$route['cancel_subscription/(:any)'] = 'saas/gb/cancel_subscription/$1';
$route['resume_subscription/(:any)'] = 'saas/gb/resume_subscription/$1';
$route['admin/delete_module/(:any)/(:any)'] = 'saas/gb_admin/delete_module/$1/$2';
$route['admin/paymentSuccess'] = 'saas/gb_admin/paymentSuccess';
$route['companyHistoryList/(:any)'] = 'saas/gb/companyHistoryList/$1';
$route['companyPaymentList/(:any)'] = 'saas/gb/companyPaymentList/$1';
$route['saas/payments'] = 'saas/companies/invoices';
// Netim super-admin explicit routes (saas/ prefix — bypasses HMVC auto-routing ambiguity)
$route['saas/netim_domains/settings']    = 'saas/netim_domains/settings';
$route['saas/netim_domains/requests']    = 'saas/netim_domains/requests';
$route['saas/netim_domains/domain_list'] = 'saas/netim_domains/domain_list';
$route['saas/netim_domains/register/(:any)']      = 'saas/netim_domains/register/$1';
$route['saas/netim_domains/reject/(:any)']        = 'saas/netim_domains/reject/$1';
$route['saas/netim_domains/configure_dns/(:any)'] = 'saas/netim_domains/configure_dns/$1';
$route['saas/netim_domains/test_connection']      = 'saas/netim_domains/test_connection';
$route['saas/netim_domains/check_availability']   = 'saas/netim_domains/check_availability';
$route['webhooks/(:any)'] = 'saas/webhooks/$1';
$route['checkout/(:any)'] = 'saas/gb/checkout/$1';
$route['checkout'] = 'saas/gb/checkout';

$route['clients/themebuilder'] = 'saas/builder';
$route['clients/themebuilder/(:any)'] = 'saas/builder/$1';
$route['clients/themebuilder/(:any)/(:any)'] = 'saas/builder/$1/$2';
$route['proceedPackage/(:any)/(:any)'] = 'saas/gb_client/proceedPackage/$1/$2';
$route['clients/subscribe/(:any)'] = 'saas/gb_client/subscribe/$1';
$route['clients/updatePackage'] = 'saas/gb_client/assignPackage';
$route['clients/updatePackage/(:any)'] = 'saas/gb_client/assignPackage/$1';
$route['clients/checkoutPayment'] = 'saas/gb_client/checkoutPayment';
$route['clients/checkoutPayment/(:any)'] = 'saas/gb_client/checkoutPayment/$1';
$route['clients/checkoutPayment/(:any)/(:any)'] = 'saas/gb_client/checkoutPayment/$1/$2';
$route['clients/billings'] = 'saas/gb_client/billings';
$route['clients/dashboard'] = 'saas/gb_client/billings';
$route['clients/custom_domain'] = 'saas/gb_client/custom_domain';
$route['clients/custom_domain/(:any)'] = 'saas/gb_client/custom_domain/$1';
$route['clients/custom_domain/(:any)'] = 'saas/gb_client/custom_domain/$1';
// Netim domain routes (client)
$route['clients/buy-domain'] = 'saas/gb_client/buy_domain';
$route['clients/buy-domain/(:any)'] = 'saas/gb_client/buy_domain/$1';
$route['clients/domain-search'] = 'saas/gb_client/domain_search';
$route['clients/my-domains'] = 'saas/gb_client/my_domains';
$route['clients/get_modules'] = 'saas/gb_client/get_modules';
$route['clients/get_modules/(:any)'] = 'saas/gb_client/get_modules/$1';
$route['clients/module_details/(:any)'] = 'saas/gb_client/module_details/$1';
$route['clients/module_price'] = 'saas/gb_client/module_price';
$route['clients/module_price/(:any)'] = 'saas/gb_client/module_price/$1';
$route['clients/custom_domain/(:any)/(:any)'] = 'saas/gb_client/custom_domain/$1/$2';
$route['clients/domainList/(:any)'] = 'saas/gb_client/domainList/$1';
$route['clients/companyHistoryList/(:any)'] = 'saas/gb_client/companyHistoryList/$1';
$route['clients/companyPaymentList/(:any)'] = 'saas/gb_client/companyPaymentList/$1';
$route['clients/customizePackages'] = 'saas/gb_client/customizePackages';
$route['clients/customizePackages/(:any)'] = 'saas/gb_client/customizePackages/$1';
$route['clients/referrals'] = 'saas/gb_client/referrals';
$route['clients/proceedPayment'] = 'saas/gb_client/proceedPayment';

// Core client-area controllers must stay ahead of the frontend catch-all.
// Without these, /verification and /authentication are swallowed by $route['(:any)']
// and bounce with /clients until the browser crashes.
$route['verification'] = 'verification/index';
$route['verification/(:any)'] = 'verification/$1';
$route['verification/(:any)/(:any)'] = 'verification/$1/$2';
$route['verification/(:any)/(:any)/(:any)'] = 'verification/$1/$2/$3';
$route['authentication'] = 'authentication/index';
$route['authentication/(:any)'] = 'authentication/$1';
$route['authentication/(:any)/(:any)'] = 'authentication/$1/$2';
$route['authentication/(:any)/(:any)/(:any)'] = 'authentication/$1/$2/$3';
$route['authentication/(:any)/(:any)/(:any)/(:any)'] = 'authentication/$1/$2/$3/$4';
$route['authentication/(:any)/(:any)/(:any)/(:any)/(:any)'] = 'authentication/$1/$2/$3/$4/$5';

$route['admin/themebuilder'] = 'saas/builder';
$route['admin/themebuilder/(:any)'] = 'saas/builder/$1';
$route['admin/themebuilder/(:any)/(:any)'] = 'saas/builder/$1/$2';
$route['admin/updatePackage'] = 'saas/gb_admin/assignPackage';
$route['admin/billings'] = 'saas/gb_admin/billings';
$route['admin/custom_domain'] = 'saas/gb_admin/custom_domain';
$route['admin/custom_domain/(:any)'] = 'saas/gb_admin/custom_domain/$1';
// Netim domain routes (admin company context)
$route['admin/buy-domain'] = 'saas/gb_admin/buy_domain';
$route['admin/buy-domain/(:any)'] = 'saas/gb_admin/buy_domain/$1';
$route['admin/domain-search'] = 'saas/gb_admin/domain_search';
$route['admin/my-domains'] = 'saas/gb_admin/my_domains';
// Netim super-admin routes
$route['admin/netim-domains'] = 'saas/netim_domains/domain_list';
$route['admin/netim-requests'] = 'saas/netim_domains/requests';
$route['admin/netim-settings'] = 'saas/netim_domains/settings';
$route['admin/get_modules'] = 'saas/gb_admin/get_modules';
$route['admin/get_modules/(:any)'] = 'saas/gb_admin/get_modules/$1';
$route['admin/module_details/(:any)/(:num)'] = 'saas/gb_admin/module_details/$1/$2';
$route['admin/module_details/(:any)'] = 'saas/gb_admin/module_details/$1';
$route['admin/custom_domain/(:any)/(:any)'] = 'saas/gb_admin/custom_domain/$1/$2';
$route['admin/domainList/(:any)'] = 'saas/gb_admin/domainList/$1';
$route['admin/companyHistoryList/(:any)'] = 'saas/gb_admin/companyHistoryList/$1';
$route['admin/companyPaymentList/(:any)'] = 'saas/gb_admin/companyPaymentList/$1';
$route['admin/customizePackages'] = 'saas/gb_admin/customizePackages';
$route['admin/customizePackages/(:any)'] = 'saas/gb_admin/customizePackages/$1';
$route['admin/referrals'] = 'saas/gb_admin/referrals';
$route['admin/proceedPayment'] = 'saas/gb_admin/proceedPayment';
$route['saas/module_details/(:any)'] = 'saas/gb_admin/module_details/$1';
$route['domain-not-available'] = "saas/setup/domain_not_available";
$route['theme/(:any)'] = "saas/frontcms/home/theme/$1";
$route['theme/(:any)/(:any)'] = "saas/frontcms/home/theme/$1/$2";
$route['theme/(:any)/(:any)/(:any)'] = "saas/frontcms/home/theme/$1/$2/$3";
$route['theme/(:any)/(:any)/(:any)/(:any)'] = "saas/frontcms/home/theme/$1/$2/$3/$4";
$route['preview'] = "saas/frontcms/home/preview";
$route['preview/(:any)'] = "saas/frontcms/home/preview/$1";
$route['preview/(:any)/(:any)'] = "saas/frontcms/home/preview/$1/$2";
$route['preview/(:any)/(:any)/(:any)'] = "saas/frontcms/home/preview/$1/$2/$3";
$route['preview/(:any)/(:any)/(:any)/(:any)'] = "saas/frontcms/home/preview/$1/$2/$3/$4";
$route["login_as_companies/(:any)"] = 'saas/gb_admin/login_as_companies/$1';
$route["login_as_companies"] = 'saas/gb_admin/login_as_companies';
$route["saas-data/(:any)"] = 'saas/frontcms/home/saas_data/$1';

if (!function_exists('saas_uri_first_segment')) {
    function saas_uri_first_segment()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $path ?: '/';
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $script_dir = rtrim($script_dir, '/');
        if ($script_dir !== '' && $script_dir !== '/' && strpos($path, $script_dir) === 0) {
            $path = substr($path, strlen($script_dir));
        }
        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }
        $segments = explode('/', $path);
        if (($segments[0] ?? '') === 'index.php') {
            array_shift($segments);
        }
        return $segments[0] ?? '';
    }
}

if (!function_exists('saas_is_reserved_first_segment')) {
    function saas_is_reserved_first_segment($segment, $routes)
    {
        if ($segment === '') {
            return false;
        }

        $reserved = [
            'admin', 'authentication', 'clients', 'verification', 'login', 'logout',
            'register', 'forgot_password', 'reset_password', 'invoice', 'estimate',
            'proposal', 'contract', 'subscription', 'knowledge-base', 'knowledge_base',
            'forms', 'download', 'consent', 'cron', 'contacts', 'privacy-policy',
            'terms-and-conditions', 'check_emails', 'migration', 'gateways',
        ];
        if (in_array($segment, $reserved, true)) {
            return true;
        }
        if (isset($routes[$segment])) {
            return true;
        }
        foreach ([ucfirst($segment), $segment] as $name) {
            if (file_exists(APPPATH . 'controllers/' . $name . '.php')) {
                return true;
            }
        }
        return false;
    }
}

$saas_first_segment = saas_uri_first_segment();
$saas_keep_app_routes = saas_is_reserved_first_segment($saas_first_segment, $route);

if (function_exists('is_subdomain') && empty(is_subdomain())) {
    if ($saas_keep_app_routes) {
        $route['default_controller'] = 'clients';
    } else {
        $route['default_controller'] = 'saas/frontcms/home/index';
        $route['(:any)'] = 'saas/frontcms/home/index/$1';
    }
} else if (function_exists('is_subdomain') && !empty(is_subdomain())) {
    if ($saas_keep_app_routes) {
        $route['default_controller'] = 'clients';
    } else {
        $route['default_controller'] = 'saas/frontcms/home/client';
        $route['(:any)'] = 'saas/frontcms/home/client/$1';
    }
    $route['admin/dashboard'] = 'saas/gb_admin/billings';
}

if (function_exists('check_subdomain')) {
    $subdomain = check_subdomain();
    if (!empty($subdomain)) {
        $company_route = $subdomain['slug'];

        // Clone existing static routes with company route
        foreach ($route as $key => $value) {
            $new_key = $company_route . "/" . ($key == '/' ? '' : $key);
            $route[$new_key] = $value;
        }
        // Make catch-all static route for all the controllers method and modules using max of 7 levels.
        // Based on latest research perfex v3.4 7 level is more than sufficient (can increase with needs)
        $route["$company_route/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)"] = '$1/$2/$3/$4/$5/$6/$7/$8';
        $route["$company_route/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)"] = '$1/$2/$3/$4/$5/$6/$7';
        $route["$company_route/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)"] = '$1/$2/$3/$4/$5/$6';
        $route["$company_route/(:any)/(:any)/(:any)/(:any)/(:any)"] = '$1/$2/$3/$4/$5';
        $route["$company_route/(:any)/(:any)/(:any)/(:any)"] = '$1/$2/$3/$4';
        $route["$company_route/(:any)/(:any)/(:any)"] = '$1/$2/$3';
        $route["$company_route/(:any)/(:any)"] = '$1/$2';
        $route["$company_route/(:any)"] = '$1';
        $route["$company_route"] = 'clients';
    }
}

// check is_subdomain function is exist or not
if (!function_exists('guess_base_url')) {
    function guess_base_url()
    {
        $base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https' : 'http';
        $base_url .= '://' . $_SERVER['HTTP_HOST'];
        $base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        $base_url = preg_replace('/install.*/', '', $base_url);
        return $base_url;
    }
}