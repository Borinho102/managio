<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Debug Mode
Description: A module to activate debug or development mode
Version: 1.0.0
Requires at least: 3.0.*
Author: ulutfa
Author URI: https://codecanyon.net/user/ulutfa
*/

defined('DEBUG_MODE_MODULE') or define('DEBUG_MODE_MODULE', 'debug_mode');

/**
 * Register activation module hook
 */
register_activation_hook(DEBUG_MODE_MODULE, function () {
    debug_mode_enable_environment('development');
});


register_deactivation_hook(DEBUG_MODE_MODULE, function () {
    debug_mode_enable_environment('production');
});

function debug_mode_enable_environment($desired_env)
{
    $env = ENVIRONMENT;
    $indexFile = FCPATH . 'index.php';
    $patterns = ['define(\'ENVIRONMENT\', \'' . $env . '\')', 'define("ENVIRONMENT", "' . $env . '")'];
    $replace = 'define(\'ENVIRONMENT\', \'' . $desired_env . '\')';
    file_put_contents($indexFile, str_replace($patterns, $replace, file_get_contents($indexFile)));
}
