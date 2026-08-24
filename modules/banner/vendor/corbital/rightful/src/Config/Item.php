<?php

$module_name = 'banner';
$module      = get_instance()->app_modules->get($module_name);

$config      = [
    'product_id'          => basename($module['headers']['uri']),
    'api_url'             => 'https://api.corbitaltech.dev/api/v3',
    'current_version'     => $module['installed_version'],
    'verify_type'         => 'envato',
    'root_path'           => realpath(TEMP_FOLDER),
    'module_name'         => $module['system_name'],
    'support_url'         => 'aHR0cHM6Ly9zdXBwb3J0LmNvcmJpdGFsdGVjaC5kZXYvbG9naW4=',
    'renew_support_url'   => $module['headers']['uri'],
];
