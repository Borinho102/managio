<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: PayIn Gateway
Description: PayIn payment gateway for invoices, wallet top-up, and passwordless PayIn wallet access
Version: 1.0.0
Requires at least: 2.3.0
*/

const PAYIN_MODULE_NAME       = 'payin';
const PAYIN_MODULE_GATEWAY_ID = 'payin';

register_language_files(PAYIN_MODULE_NAME, [PAYIN_MODULE_NAME]);
register_payment_gateway('Payin_gateway', PAYIN_MODULE_NAME);
register_activation_hook(PAYIN_MODULE_NAME, 'payin_module_activation');

hooks()->add_action('admin_navbar_start', 'payin_admin_navbar_start');

function payin_module_activation()
{
    add_option('payin_module_initialized', 1);
}

function payin_admin_navbar_start()
{
    if (!is_staff_logged_in()) {
        return;
    }
    $onTenant = (function_exists('subdomain') && !empty(subdomain()))
        || (function_exists('is_subdomain') && !empty(is_subdomain()));
    if (!$onTenant) {
        return;
    }

    $CI = &get_instance();
    $wallet = ['provisioned' => false];

    try {
        if (!class_exists('Payin_client', false)) {
            require_once module_dir_path(PAYIN_MODULE_NAME) . 'libraries/Payin_client.php';
        }
        $company = function_exists('get_company_subscription') ? get_company_subscription() : null;
        if (!empty($company) && !empty($company->payin_user_id)) {
            $cached = $CI->session->userdata('payin_wallet_header');
            if (is_array($cached) && ($cached['at'] ?? 0) > time() - 60) {
                $wallet = $cached['data'];
            } else {
                $client = Payin_client::fromSsoConfig();
                $wallet = $client->walletBalance((int) $company->payin_user_id, (string) $company->domain);
                $CI->session->set_userdata('payin_wallet_header', ['at' => time(), 'data' => $wallet]);
            }
        } elseif (!empty($company)) {
            $wallet = ['provisioned' => false];
        }
    } catch (Throwable $e) {
        $wallet = [
            'provisioned' => true,
            'error'       => true,
            'formatted'   => _l('payin_wallet'),
        ];
    }

    $CI->load->view('payin/navbar', ['wallet' => $wallet]);
}
