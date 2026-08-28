<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Wekonex Bridge
Description: Intégration Wekonex ↔ Managio (SSO, webhooks, journalisation).
Version: 1.0.4
Requires at least: 3.0.*
Author: Wekonex
*/

define('WEKONEX_BRIDGE_MODULE', 'wekonex_bridge');

$CI = &get_instance();
$CI->load->helper(WEKONEX_BRIDGE_MODULE . '/' . WEKONEX_BRIDGE_MODULE);

register_language_files(WEKONEX_BRIDGE_MODULE, [WEKONEX_BRIDGE_MODULE]);

register_activation_hook(WEKONEX_BRIDGE_MODULE, 'wekonex_bridge_activation');
register_deactivation_hook(WEKONEX_BRIDGE_MODULE, 'wekonex_bridge_deactivation');
register_uninstall_hook(WEKONEX_BRIDGE_MODULE, 'wekonex_bridge_uninstall');

hooks()->add_action('pre_admin_init', 'wekonex_bridge_sync_custom_fields', 1);
hooks()->add_filter('custom_fields_before_render', 'wekonex_bridge_filter_custom_fields_before_render', 10, 3);
hooks()->add_action('admin_init', 'wekonex_bridge_admin_init');
hooks()->add_action('admin_init', 'wekonex_bridge_ensure_options');
hooks()->add_action('admin_init', 'wekonex_bridge_run_upgrade');
hooks()->add_action('app_admin_footer', 'wekonex_bridge_custom_fields_ui_fix');

function wekonex_bridge_run_upgrade(): void
{
    if (is_admin()) {
        $table = db_prefix() . 'wekonex_entity_mappings';
        if (!get_instance()->db->table_exists($table)) {
            require_once __DIR__ . '/upgrade.php';
        }
    }

    wekonex_bridge_sync_custom_fields();
}

function wekonex_bridge_activation()
{
    require_once __DIR__ . '/install.php';
}

function wekonex_bridge_deactivation()
{
    update_option('wekonex_bridge_enabled', '0');
    wekonex_bridge_sync_custom_fields();
}

function wekonex_bridge_uninstall()
{
    require_once __DIR__ . '/uninstall.php';
}

function wekonex_bridge_admin_init()
{
    if (is_admin()) {
        $CI = &get_instance();
        $CI->app_menu->add_setup_children_item('modules', [
            'slug'     => 'wekonex-bridge-settings',
            'name'     => 'wekonex_bridge_settings',
            'href'     => admin_url('wekonex_bridge/settings'),
            'position' => 65,
        ]);
    }
}
