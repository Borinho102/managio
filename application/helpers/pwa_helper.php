<?php

defined('BASEPATH') or exit('No direct script access allowed');

function pwa_head()
{
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;

    $name = function_exists('get_option') ? (get_option('companyname') ?: 'Managio') : 'Managio';
    $base = function_exists('site_url') ? rtrim(site_url('/'), '/') : '';
    $icon = $base . '/assets/pwa/icons';

    echo '<link rel="manifest" href="' . $base . '/manifest.webmanifest">' . PHP_EOL;
    echo '<meta name="theme-color" content="#2563eb">' . PHP_EOL;
    echo '<meta name="msapplication-TileColor" content="#2563eb">' . PHP_EOL;
    echo '<meta name="msapplication-config" content="' . $base . '/browserconfig.xml">' . PHP_EOL;
    echo '<meta name="mobile-web-app-capable" content="yes">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-title" content="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
    echo '<meta name="application-name" content="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
    echo '<meta name="format-detection" content="telephone=no">' . PHP_EOL;
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $icon . '/apple-touch-icon.png">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . $icon . '/icon-192.png">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="512x512" href="' . $icon . '/icon-512.png">' . PHP_EOL;
    echo '<script src="' . $base . '/assets/pwa/register.js" defer></script>' . PHP_EOL;
}

if (function_exists('hooks')) {
    hooks()->add_action('app_admin_head', 'pwa_head');
    hooks()->add_action('app_customers_head', 'pwa_head');
    hooks()->add_action('app_admin_authentication_head', 'pwa_head');
    hooks()->add_action('app_affiliates_head', 'pwa_head');
    hooks()->add_action('app_vendor_head', 'pwa_head');
}
