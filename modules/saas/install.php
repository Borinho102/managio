<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (function_exists('is_subdomain') && function_exists('subdomain') && !empty(subdomain())) {
    redirect('admin/dashboard');
}


$error = false;
$errorList = array();
$path = APPPATH . 'config';
if (!is_writable($path)) {
    $error = true;
    $errorList[] = $path . ' is not writable. Make writable - Permissions 0755';
}

$app_config = $path . '/app-config.php';
if (!is_writable($app_config)) {
    $error = true;
    $errorList[] = $app_config . ' is not writable. Make writable - Permissions 0755';
}

$database_config = $path . '/database.php';
if (!is_writable($database_config)) {
    $error = true;
    $errorList[] = $database_config . ' is not writable. Make writable - Permissions 0755';
}
$confirmed = false;
$installed = false;

if (!$error) {
    if (!empty($_POST['purchase_key']) && !empty($_POST['buyer'])) {
        $env_data = remote_get_contents($_POST, true);
        $result = json_decode($env_data, true);
        if (!empty($result)) {
            if (!empty($result['success']) && $result['success'] == true) {
                $success = install_db($_POST, $result['all_table']);
                if (!empty($success)) {
                    installed();
                    $installed = true;
                    // redirect to module page
                    $confirmed = true;
                }
            } else {
                $error = true;
                $errorList[] = (!empty($result['message']) ? $result['message'] : 'Envato username or purchase code is invalid.');
                $confirmed = false;
            }
        }
    }
    require_once(__DIR__ . '/views/includes/purchase_verify.php');
    if (!$installed) {
        exit;
    }
} else {
    $confirmed = false;
}
// If confirmation screen is not yet done, require confirmation
if (!$confirmed) {
    require_once(__DIR__ . '/views/includes/install_requirements.php');
    exit;
}

function installed()
{
    // insert and line into application/config/app-config.php file
    $app_config_path = APPPATH . 'config/app-config.php';
    $app_config_file = file_get_contents($app_config_path);
    // check require_once(FCPATH . 'modules/saas/config/my_config.php'); is already added or not into the app-config.php file
    // if not added then add the line require_once(FCPATH . 'modules/saas/config/my_config.php'); into the app-config.php file last line
    if (strpos($app_config_file, "require_once(FCPATH . 'modules/saas/config/my_config.php');") !== false) {
        // already added
    } else {
        // not added
        $app_config_file = str_replace("define('APP_CSRF_PROTECTION', true);", "define('APP_CSRF_PROTECTION', true);\n\n\nrequire_once(FCPATH . 'modules/saas/config/my_config.php'); // added by saas", $app_config_file);
        if (!$fp = fopen($app_config_path, 'wb')) {
            die('Unable to write to config file');
        }

        flock($fp, LOCK_EX);
        fwrite($fp, $app_config_file, strlen($app_config_file));
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($app_config_path, 0644);
    }
// replace 'database' => config_item('default_database'), with 'database' => APP_DB_NAME, in application/config/database.php file
    $database_path = APPPATH . 'config/database.php';
    $database_file = file_get_contents($database_path);
    $database_file = str_replace("APP_DB_NAME", "config_item('default_database')", $database_file);

    if (!$fp = fopen($database_path, 'wb')) {
        die('Unable to write to config file');
    }

    flock($fp, LOCK_EX);
    fwrite($fp, $database_file, strlen($database_file));
    flock($fp, LOCK_UN);
    fclose($fp);
    @chmod($database_path, 0644);


// upload my_routes_samples.php to application/config and rename it to my_routes.php
    $sample_routes = module_dir_path(SaaS_MODULE) . 'config/routes.sample.php';
// upload the $sample_routes into application/config folder and rename it to my_routes.php
    $routes_path = APPPATH . 'config/my_routes.php';
    @chmod($routes_path, 0666);
    if (@copy($sample_routes, $routes_path) === false) {
        die('Unable to copy sample routes file to config folder . please make sure you have permission to copy routes.sample file');
    }

// upload my_autoload_samples.php to application/config and rename it to my_autoload.php
    $sample_autoload = module_dir_path(SaaS_MODULE) . 'config/autoload.sample.php';
// upload the $sample_autoload into application/config folder and rename it to my_autoload.php
    $autoload_path = APPPATH . 'config/my_autoload.php';
    @chmod($autoload_path, 0666);
    if (@copy($sample_autoload, $autoload_path) === false) {
        die('Unable to copy sample autoload file to config folder . please make sure you have permission to copy autoload.sample file');
    }

// add hook to application/models/Authentication_model.php file after if ((!empty($email)) and (!empty($password))) { line
// add the following line hooks()->do_action('before_login');
    $authentication_model_path = APPPATH . 'models/Authentication_model.php';
    $authentication_model_file = file_get_contents($authentication_model_path);
    $authentication_model_file = str_replace("if ((!empty(\$email)) and (!empty(\$password))) {", "if ((!empty(\$email)) and (!empty(\$password))) {\n\n\nhooks()->do_action('before_login');", $authentication_model_file);

    if (!$fp = fopen($authentication_model_path, 'wb')) {
        die('Unable to write to config file in ' . $authentication_model_path);
    }

    flock($fp, LOCK_EX);
    fwrite($fp, $authentication_model_file, strlen($authentication_model_file));
    flock($fp, LOCK_UN);
    fclose($fp);
    @chmod($authentication_model_path, 0644);

    $CI = &get_instance();
    $CI->db->query("ALTER TABLE `" . db_prefix() . "modules` CHANGE `active` `active` TINYINT(1) NOT NULL DEFAULT '0';");
    // check the column is already exist or not
    if (!$CI->db->field_exists('saas_company_id', db_prefix() . 'clients')) {
        $CI->db->query("ALTER TABLE " . db_prefix() . "clients ADD `saas_company_id` INT NULL DEFAULT NULL AFTER `company`;");
    }


    $CI->db->query("INSERT INTO " . db_prefix() . "options (`id`, `name`, `value`, `autoload`) VALUES
(NULL, 'saas_companyname', 'Perfect SaaS', 1),
(NULL, 'saas_allowed_files', '.png,.jpg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt', 1),
(NULL, 'saas_company_logo', 'd33bf9a5e1bb7471b7534ecfe8e12ff1.png', 1),
(NULL, 'saas_company_logo_dark', 'd85f63a4a464cba0dcc2e84805f209c4.png', 1),
(NULL, 'saas_favicon', 'favicon.png', 1),
(NULL, 'saas_dateformat', 'Y-m-d|%Y-%m-%d', 1),
(NULL, 'saas_time_format', '24', 1),
(NULL, 'saas_default_timezone', 'Asia/Dhaka', 1),
(NULL, 'saas_active_language', 'english', 1),
(NULL, 'saas_mail_engine', 'phpmailer', 1),
(NULL, 'saas_email_protocol', 'smtp', 1),
(NULL, 'saas_microsoft_mail_client_id', 'd', 1),
(NULL, 'saas_microsoft_mail_client_secret', '+XMWQ0rwL5X7zYHiyLQ=', 1),
(NULL, 'saas_microsoft_mail_azure_tenant_id', 'd', 1),
(NULL, 'saas_google_mail_client_id', 'e', 1),
(NULL, 'saas_google_mail_client_secret', '125455/=', 1),
(NULL, 'saas_smtp_encryption', 'tls', 1),
(NULL, 'saas_smtp_host', 'd', 1),
(NULL, 'saas_smtp_port', 'd', 1),
(NULL, 'saas_smtp_email', 'e', 1),
(NULL, 'saas_smtp_username', 'admin@gmail.com', 1),
(NULL, 'saas_smtp_password', '125455', 1),
(NULL, 'saas_smtp_email_charset', 'e', 1),
(NULL, 'saas_bcc_emails', 'e', 1),
(NULL, 'saas_email_signature', 'e', 1),
(NULL, 'saas_email_header', 'e', 1),
(NULL, 'saas_email_footer', 'e', 1),
(NULL, 'saas_server', 'local', 1),
(NULL, 'saas_cpanel_host', 'maluzts.sddsaddsa', 1),
(NULL, 'saas_cpanel_port', '2083', 1),
(NULL, 'saas_cpanel_username', 'maluztxf', 1),
(NULL, 'saas_cpanel_password', 'b7ee3ff4d0a87e46445ce387efb7e853e293a42139f77224683237b9e6d28a8a9209e06d3eb36dd654088aa2f47dc34aa8fc57151dadf1ce89a28950a9ba03d22EqSoFSJGPcSbPWpxGvenLQ5bAncHwOrMQpJJ7moaYA=', 1),
(NULL, 'saas_cpanel_output', 'json', 1),
(NULL, 'saas_plesk_host', '45.77.230.168', 1),
(NULL, 'saas_plesk_username', 'admin2', 1),
(NULL, 'saas_plesk_password', '0d5c9a888b93b38243ee37557238881851ed0627b83dfe33c135f6df01c0183fe79a823a77d2c243b734e8c2140e3bf20db727a01f85298f22daa3a36bcb76e0zx5yGhWV/vlsh/8vbFPwNqbA0ADJd4CJ3J1HqvlZ63M=', 1),
(NULL, 'saas_plesk_webspace_id', 'asdsdasda', 1),
(NULL, 'saas_paymentmethod_authorize_acceptjs_label', 'Authorize.net Accept.js', 1),
(NULL, 'saas_paymentmethod_authorize_acceptjs_public_key', 'asddsa', 1),
(NULL, 'saas_paymentmethod_authorize_acceptjs_api_login_id', 'sdadsa', 1),
(NULL, 'saas_paymentmethod_authorize_acceptjs_api_transaction_key', '', 1),
(NULL, 'saas_paymentmethod_authorize_acceptjs_description_dashboard', 'asddsa', 1),
(NULL, 'saas_paymentmethod_authorize_acceptjs_currencies', 'sdadsa', 1),
(NULL, 'saas_paymentmethod_instamojo_label', 'Instamojo', 1),
(NULL, 'saas_paymentmethod_instamojo_fee_fixed', '', 1),
(NULL, 'saas_paymentmethod_instamojo_fee_percent', '', 1),
(NULL, 'saas_paymentmethod_instamojo_api_key', '', 1),
(NULL, 'saas_paymentmethod_instamojo_auth_token', '', 1),
(NULL, 'saas_paymentmethod_instamojo_description_dashboard', '', 1),
(NULL, 'saas_paymentmethod_mollie_label', 'Mollie', 1),
(NULL, 'saas_paymentmethod_mollie_api_key', '', 1),
(NULL, 'saas_paymentmethod_mollie_description_dashboard', '', 1),
(NULL, 'saas_paymentmethod_mollie_currencies', '', 1),
(NULL, 'saas_paymentmethod_paypal_braintree_label', 'Braintree', 1),
(NULL, 'saas_paymentmethod_paypal_braintree_merchant_id', '', 1),
(NULL, 'saas_paymentmethod_paypal_braintree_api_public_key', '', 1),
(NULL, 'saas_paymentmethod_paypal_braintree_api_private_key', '', 1),
(NULL, 'saas_paymentmethod_paypal_braintree_currencies', '', 1),
(NULL, 'saas_paymentmethod_paypal_checkout_label', 'Paypal Smart Checkout', 1),
(NULL, 'saas_paymentmethod_paypal_checkout_fee_fixed', '', 1),
(NULL, 'saas_paymentmethod_paypal_checkout_fee_percent', '', 1),
(NULL, 'saas_paymentmethod_paypal_checkout_client_id', '', 1),
(NULL, 'saas_paymentmethod_paypal_checkout_secret', '', 1),
(NULL, 'saas_paymentmethod_paypal_checkout_payment_description', '', 1),
(NULL, 'saas_paymentmethod_paypal_checkout_currencies', '', 1),
(NULL, 'saas_paymentmethod_paypal_label', 'Paypal', 1),
(NULL, 'saas_paymentmethod_paypal_fee_fixed', '', 1),
(NULL, 'saas_paymentmethod_paypal_fee_percent', '', 1),
(NULL, 'saas_paymentmethod_paypal_username', '', 1),
(NULL, 'saas_paymentmethod_paypal_password', '', 1),
(NULL, 'saas_paymentmethod_paypal_signature', '', 1),
(NULL, 'saas_paymentmethod_paypal_description_dashboard', '', 1),
(NULL, 'saas_paymentmethod_paypal_currencies', '', 1),
(NULL, 'saas_paymentmethod_payu_money_label', 'PayU Money', 1),
(NULL, 'saas_paymentmethod_payu_money_fee_fixed', '', 1),
(NULL, 'saas_paymentmethod_payu_money_fee_percent', '', 1),
(NULL, 'saas_paymentmethod_payu_money_key', '', 1),
(NULL, 'saas_paymentmethod_payu_money_salt', '', 1),
(NULL, 'saas_paymentmethod_payu_money_description_dashboard', '', 1),
(NULL, 'saas_paymentmethod_payu_money_currencies', '', 1),
(NULL, 'saas_paymentmethod_stripe_label', 'Stripe Checkout', 1),
(NULL, 'saas_paymentmethod_stripe_fee_fixed', '', 1),
(NULL, 'saas_paymentmethod_stripe_fee_percent', '', 1),
(NULL, 'saas_paymentmethod_stripe_api_publishable_key', '', 1),
(NULL, 'saas_paymentmethod_stripe_api_secret_key', '', 1),
(NULL, 'saas_paymentmethod_stripe_description_dashboard', '', 1),
(NULL, 'saas_paymentmethod_stripe_currencies', '', 1),
(NULL, 'saas_paymentmethod_stripe_ideal_label', 'Stripe iDEAL', 1),
(NULL, 'saas_paymentmethod_stripe_ideal_api_secret_key', '', 1),
(NULL, 'saas_paymentmethod_stripe_ideal_api_publishable_key', '', 1),
(NULL, 'saas_paymentmethod_stripe_ideal_description_dashboard', '', 1),
(NULL, 'saas_paymentmethod_stripe_ideal_statement_descriptor', '', 1),
(NULL, 'saas_paymentmethod_two_checkout_label', '2Checkout', 1),
(NULL, 'saas_paymentmethod_two_checkout_fee_fixed', '', 1),
(NULL, 'saas_paymentmethod_two_checkout_fee_percent', '', 1),
(NULL, 'saas_paymentmethod_two_checkout_merchant_code', '', 1),
(NULL, 'saas_paymentmethod_two_checkout_secret_key', '', 1),
(NULL, 'saas_paymentmethod_two_checkout_description', '', 1),
(NULL, 'saas_paymentmethod_two_checkout_currencies', '', 1),
(NULL, 'saas_front_pricing_title', 'Our Pricing Rates', 1),
(NULL, 'saas_front_pricing_description', 'Start working with Perfect SaaS that can provide everything you need to generate awareness, drive traffic, connect.', 1),
(NULL, 'saas_front_slider', '1', 1),
(NULL, 'home_slider_speed', '10', 1),
(NULL, 'saas_server_wildcard', 'on', 1),
(NULL, 'enable_affiliate', 'TRUE', 1),
(NULL, 'affiliate_commission_amount', '203', 1),
(NULL, 'payment_rules_for_affiliates', 'no_payment_required', 1),
(NULL, 'withdrawal_payment_method', 'a:2:{i:0;s:1:\"1\";i:1;s:9:\"instamojo\";}', 1),
(NULL, 'affiliate_commission_type', 'percentage', 1),
(NULL, 'affiliate_rule', 'only_first_subscription', 1),
(NULL, 'saas_calculate_disk_space', 'both', 1),
(NULL, 'saas_reserved_tenant', 'admin,administrator,root,perfectsaas,acme,saaserp,hack,www', 1),
(NULL, 'custom_domain_title', 'Custom Domain Integration Guideline', 1),
(NULL, 'saas_default_theme', 'default', 1),
(NULL, 'custom_domain_details', '<div>Integrating a custom domain with DNS settings typically involves the following steps:</div>\n<div></div>\n<ol>\n<li><b>Purchase a domain name:</b><span> </span>You\'ll need to purchase a domain name from a domain registrar such as GoDaddy, Namecheap, or Google Domains.</li>\n<li><b>Obtain your DNS records:<span> </span></b>Once you have a domain provider, they will provide you with<span> </span><b>DNS records</b><span> </span>that you\'ll need to configure for your domain. These records will typically include an<span> </span><b>A record & CNAME record</b>.</li>\n<li><b>Configure DNS settings:</b><span> </span>Log in to your domain registrar\'s account and navigate to the DNS management section.You need to add 2 new DNS record, choose the record type (<b>A & CNAME</b>) & follow the settings below<span> </span><b>(<span>DNS Settings One </span><span>& </span><span>DNS Settings Two</span>)</b>, and enter the corresponding value.</li>\n<li><b>Wait for propagation:</b><span> </span>Once you\'ve made the changes to your DNS settings, it can take up to 48 hours for the changes to propagate throughout the internet. During this time, your website or application may be temporarily unavailable.</li>\n</ol>\n<div>That\'s it! Once your DNS records have propagated, your custom domain should be fully integrated with our application.</div>', 1),
(NULL, 'minimum_payout_amount', '20', 1);");

// SaaS Email
    $welcome_email = [
        'type' => 'saas',
        'slug' => 'saas-welcome-mail',
        'name' => 'SaaS Welcome Email',
        'subject' => 'Welcome aboard',
        'message' => 'Dear {name},<br/><br/>
    Thank you for registering on the  <b>{companyname}</b> platform. We are happy to have you on board.<br/><br/> 
    We just wanted to say welcome. We are thrilled to have you on board and look forward to working with you.<br/><br/>
    Please let us know if you have any questions or concerns. We are always happy to help.<br/><br/>
    
   
    We listed your company details below, make sure you keep them safe your account details
    <br/><br/>
    please follow this link:<big><strong><a href="{company_url}">View company url</a></strong></big><br/><br/>
    link does not work? copy and paste this link into your browser:<br/>
    <big><strong><a href="{company_url}">{company_url}</a></strong></big><br/><br/>
   
    Best regards,<br/>
    {email_signature}<br/>
    (This is an automated email, so please do not reply to this.)',
    ];

    $token_activate_account = [
        'type' => 'saas',
        'slug' => 'saas-token-activate-account',
        'name' => 'SaaS Token Activate Account',
        'subject' => 'Activate your account',
        'message' => 'Dear {name},<br/><br/>   
    Thank you for registering on the  <b>{companyname}</b> platform. We are happy to have you on board.<br/><br/>
    To verify your Your activation token please copy the activation code: {activation_token} and paste it into the activation form.<br/><br/>
    
    Please click on the link below to activate your account.<br/><br/>
    <big><strong><a href="{activation_url}">Start your registration</a></strong></big><br/><br/>
    link does not work? copy and paste this link into your browser:<br/>
    <big><strong><a href="{activation_url}">{activation_url}</a></strong></big><br/><br/>
    Please activate your account within 48 hours. Otherwise, your registration will be canceled.<br/><br/>
    Best regards,<br/>
    {email_signature}<br/>
    (This is an automated email, so please do not reply to this.)',
    ];

    $faq_request_email = [
        'type' => 'saas',
        'slug' => 'saas-faq-request-email',
        'name' => 'SaaS FAQ Request Email',
        'subject' => 'FAQ Request',
        'message' => 'Hi there,,<br/><br/>
    {name} has requested a FAQ.<br/><br/>
    <b>Question:</b><br/>
    {question}<br/><br/>
    
    you can answer this question by clicking on the link below.<br/><br/>
    <big><strong><a href="{faq_url}">Answer this question</a></strong></big><br/><br/>
    link does not work? copy and paste this link into your browser:<br/>
    <big><strong><a href="{faq_url}">{faq_url}</a></strong></big><br/><br/>
    
    Best regards,<br/>
    {email_signature}<br/>
    (This is an automated email, so please do not reply to it.)',
    ];

    $assign_new_package = [
        'type' => 'saas',
        'slug' => 'saas-assign-new-package',
        'name' => 'SaaS Assign New Package',
        'subject' => 'New Package',
        'message' => 'Dear {name},<br/><br/>
    We have assigned a new package to your account.<br/><br/>
    <b>Package:</b><br/>
    {package_name}<br/><br/>
    
    Best regards,<br/>
    {email_signature}<br/>
    (This is an automated email, so please do not reply to it.)',
    ];

    $company_expiration_email = [
        'type' => 'saas',
        'slug' => 'saas-company-expiration-email',
        'name' => 'Company Expiration Email',
        'subject' => '[Attention needed] - Company Expiration Reminder',
        'message' => 'Dear {name},<br/><br/>
As a valued user, we wanted to ensure you are aware of the upcoming expiration date for your company.<br/><br/>
As of {expiration_date}, your company will be expired.<br/><br/>
to avoid any interruption in service, please renew your company as soon as possible.<br/><br/>
by renewing your company, you will continue to enjoy all the benefits of your current plan.<br/><br/>
to renew your company, please follow this link:<br/><br/>
<big><strong><a href="{company_url}">Renew your company</a></strong></big><br/><br/>
link does not work? copy and paste this link into your browser:<br/>
<big><strong><a href="{company_url}">{company_url}</a></strong></big><br/><br/>

<strong>
    If you have any questions or concerns, please do not hesitate to contact us. We are always happy to help.   
</strong>
<br/><br/>

Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)',
    ];

    $inactive_company_email = [
        'type' => 'saas',
        'slug' => 'saas-inactive-company-email',
        'name' => 'Inactive Company Email',
        'subject' => '[Attention] - your company is inactive soon! Please take action',
        'message' => 'Dear {name},<br/><br/>
As a valued user, we wanted to ensure you are aware of the upcoming expiration date for your company.<br/><br/>
<strong>Despite our previous notifications,it seems that you have not renewed your company yet.</strong><br/><br/>
According to our records, your company already expired on {expiration_date}.<br/><br/>
Unfortunately, your company is inactive now.<br/><br/>
to avoid any interruption in service, please renew your company as soon as possible.<br/><br/>
by renewing your company, you will continue to enjoy all the benefits of your current plan.<br/><br/>
to renew your company, please follow this link:<br/><br/>
<big><strong><a href="{company_url}">Renew your company</a></strong></big><br/><br/>
link does not work? copy and paste this link into your browser:<br/>
<big><strong><a href="{company_url}">{company_url}</a></strong></big><br/><br/>

Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)',
    ];

    $company_url = [
        'type' => 'saas',
        'slug' => 'saas-company-url',
        'name' => 'Company URL',
        'subject' => 'Company URL',
        'message' => 'Dear {name},<br/><br/>
you had requested your company URL.<br/><br/>
so here is your company URL:<br/><br/>
<big><strong><a href="{company_url}">{company_url}</a></strong></big><br/><br/>
link does not work? copy and paste this link into your browser:<br/>
<big><strong><a href="{company_url}">{company_url}</a></strong></big><br/><br/>

Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)',
    ];

    $company_database_reset = [
        'type' => 'saas',
        'slug' => 'saas-company-database-reset',
        'name' => 'Company Database Reset',
        'subject' => 'Company Database Reset',
        'message' => 'Dear {name},<br/><br/>
your company database has been reset.<br/><br/>
you can login to your company by clicking on the link below.<br/><br/>
<big><strong><a href="{company_url}">Login to your company</a></strong></big><br/><br/>
link does not work? copy and paste this link into your browser:<br/>
<big><strong><a href="{company_url}">{company_url}</a></strong></big><br/><br/>

Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)',
    ];

    $affiliate_request = [
        'type' => 'affiliate',
        'slug' => 'affiliate-verification-email',
        'name' => 'Email Verification (Sent to Affiliate User After Registration)',
        'subject' => 'Verify Email Address',
        'message' => 'Dear {first_name},<br/><br/>
Thank you for registering with us.<br/><br/>
Please click on the link below to verify your email address and activate your account.<br/><br/>
<big><strong><a href="{verification_url}">Verify Email Address</a></strong></big><br/><br/>
link does not work? copy and paste this link into your browser:<br/>
<big><strong><a href="{verification_url}">{verification_url}</a></strong></big><br/><br/>

Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)'];

    $affiliate_withdrawal_request = [
        'type' => 'affiliate',
        'slug' => 'affiliate-withdrawal-request',
        'name' => 'Affiliate Withdrawal Request (Sent to Super Admin)',
        'subject' => 'Affiliate Withdrawal Request',
        'message' => 'Hello ,<br/><br/> 
an affiliate withdrawal request has been sent from {first_name} {last_name} <br/><br>
amount : {withdrawal_amount} <br/><br/>

check your affiliation to get Withdrawal Request and you can approve or reject the request .

Best regards,<br>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)
'];

    $affiliate_withdrawal_accepted = [
        'type' => 'affiliate',
        'slug' => 'affiliate-withdrawal-accepted',
        'name' => 'Affiliate Withdrawal Accepted (Sent to affiliate users)',
        'subject' => 'Affiliate Withdrawal Accepted',
        'message' => 'Dear {first_name} <br/><br/>
your affiliate withdraw request has been accepted.

you can view the request from your affiliation portal .


Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)'];


    $affiliate_withdrawal_declined = [
        'type' => 'affiliate',
        'slug' => 'affiliate-withdrawal-declined',
        'name' => 'Affiliate Withdrawal Declined (Sent to affiliate users)',
        'subject' => 'Affiliate Withdrawal Declined',
        'message' => 'Dear {first_name} <br/><br/>
your affiliate withdraw request has been declined.

you can view the request from your affiliation portal .


Best regards,<br/>
{email_signature}<br/>
(This is an automated email, so please do not reply to it.)'];


    $CI->load->model('emails_model');
    $templates = [$welcome_email, $token_activate_account, $faq_request_email, $company_database_reset, $assign_new_package, $company_expiration_email, $inactive_company_email, $company_url, $affiliate_request, $affiliate_withdrawal_request, $affiliate_withdrawal_accepted, $affiliate_withdrawal_declined];

    foreach ($templates as $t) {
        //this helper check buy slug and create if not exist by slug
        create_email_template($t['subject'], $t['message'], $t['type'], $t['name'], $t['slug']);
    }

    $CI->db->query("UPDATE " . db_prefix() . "staff SET role = '4' WHERE staffid = '" . get_staff_user_id() . "'");

}

function remote_get_contents($post, $getDB = null)
{
    if (function_exists('curl_init')) {
        return curl_get_contents($post, $getDB);
    } else {
        return 'Please enable the curl function';
    }
}

function curl_get_contents($post, $getDB = null)
{
    if (!empty($getDB)) {
        $url = SAAS_UPDATE_URL . 'api/getDB';
    } else {
        $url = SAAS_UPDATE_URL . 'api/check';
    }
    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_URL, $url);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl_handle, CURLOPT_POST, 1);
    $path = substr(realpath(dirname(__FILE__)), 0, -8);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, array(
        'envato_username' => $post['buyer'],
        'support_email' => get_option('smtp_email'),
        'purchase_code' => $post['purchase_key'],
        'item_id' => SAAS_ITEM_ID,
        'ip_address' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER["HTTP_HOST"],
        'url' => base_url(),
        'path' => $path,
    ));
    $output = curl_exec($curl_handle);
    curl_close($curl_handle);
    return $output;
}

function install_db($post, $sql_file)
{
    $CI = &get_instance();
    $h = trim($CI->db->hostname);
    $u = trim($CI->db->username);
    $p = trim($CI->db->password);
    $d = trim($CI->db->database);

    $mysqli = new mysqli($h, $u, $p, $d);
    if (mysqli_connect_errno())
        return false;
    $mysqli->multi_query($sql_file);

    do {
    } while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli));
    $mysqli->close();
    return true;

}
