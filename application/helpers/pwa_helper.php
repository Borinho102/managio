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
    $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $description = 'CRM, invoices, clients and operations';
    $base = function_exists('site_url') ? rtrim(site_url('/'), '/') : '';
    $icon = $base . '/assets/pwa/icons';
    $splash = $base . '/assets/pwa/splashes';
    $color = '#2563eb';
    $start = $base . '/?utm_source=pwa';

    echo '<link rel="manifest" href="' . $base . '/manifest.webmanifest" crossorigin="use-credentials">' . PHP_EOL;
    echo '<meta name="theme-color" content="' . $color . '">' . PHP_EOL;
    echo '<meta name="theme-color" content="' . $color . '" media="(prefers-color-scheme: light)">' . PHP_EOL;
    echo '<meta name="theme-color" content="' . $color . '" media="(prefers-color-scheme: dark)">' . PHP_EOL;
    echo '<meta name="color-scheme" content="light dark">' . PHP_EOL;
    echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
    echo '<meta name="application-name" content="' . $safe . '">' . PHP_EOL;
    echo '<meta name="mobile-web-app-capable" content="yes">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-title" content="' . $safe . '">' . PHP_EOL;
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $icon . '/apple-touch-icon.png">' . PHP_EOL;

    foreach (pwa_splash_media() as $file => $media) {
        echo '<link rel="apple-touch-startup-image" media="' . $media . '" href="' . $splash . '/' . $file . '">' . PHP_EOL;
    }

    echo '<meta name="msapplication-TileColor" content="' . $color . '">' . PHP_EOL;
    echo '<meta name="msapplication-TileImage" content="' . $icon . '/mstile-150.png">' . PHP_EOL;
    echo '<meta name="msapplication-config" content="' . $base . '/browserconfig.xml">' . PHP_EOL;
    echo '<meta name="msapplication-navbutton-color" content="' . $color . '">' . PHP_EOL;
    echo '<meta name="msapplication-starturl" content="' . $start . '">' . PHP_EOL;
    echo '<meta name="msapplication-tap-highlight" content="no">' . PHP_EOL;
    echo '<meta name="format-detection" content="telephone=no">' . PHP_EOL;
    echo '<meta property="og:type" content="website">' . PHP_EOL;
    echo '<meta property="og:title" content="' . $safe . '">' . PHP_EOL;
    echo '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
    echo '<meta property="og:image" content="' . $icon . '/icon-512.png">' . PHP_EOL;
    echo '<meta name="twitter:card" content="summary">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $icon . '/icon-32.png">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . $icon . '/icon-192.png">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="512x512" href="' . $icon . '/icon-512.png">' . PHP_EOL;
    echo '<script src="' . $base . '/assets/pwa/register.js" defer></script>' . PHP_EOL;
}

function pwa_splash_media()
{
    return [
        '640x1136.png'  => '(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
        '750x1334.png'  => '(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
        '828x1792.png'  => '(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
        '1125x2436.png' => '(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
        '1170x2532.png' => '(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
        '1179x2556.png' => '(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
        '1242x2208.png' => '(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
        '1242x2688.png' => '(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
        '1284x2778.png' => '(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
        '1290x2796.png' => '(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
        '1536x2048.png' => '(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
        '1668x2388.png' => '(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
        '2048x2732.png' => '(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
    ];
}

if (function_exists('hooks')) {
    hooks()->add_action('app_admin_head', 'pwa_head');
    hooks()->add_action('app_customers_head', 'pwa_head');
    hooks()->add_action('app_admin_authentication_head', 'pwa_head');
    hooks()->add_action('app_affiliates_head', 'pwa_head');
    hooks()->add_action('app_vendor_head', 'pwa_head');
}
