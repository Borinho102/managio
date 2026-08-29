<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Fournisseurs
Description: Gestion des fournisseurs (suppliers directory)
Version: 1.0.0
Requires at least: 2.3.*
Author: Managio
*/

define('FOURNISSEURS_MODULE_NAME', 'fournisseurs');

hooks()->add_action('admin_init', 'fournisseurs_module_init_menu_items');
hooks()->add_action('admin_init', 'fournisseurs_permissions');

register_activation_hook(FOURNISSEURS_MODULE_NAME, 'fournisseurs_module_activation_hook');
register_uninstall_hook(FOURNISSEURS_MODULE_NAME, 'fournisseurs_module_uninstall_hook');

register_language_files(FOURNISSEURS_MODULE_NAME, [FOURNISSEURS_MODULE_NAME]);

function fournisseurs_module_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
}

function fournisseurs_module_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

function fournisseurs_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities('fournisseurs', $capabilities, _l('fournisseurs'));
}

function fournisseurs_module_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
        'name'       => _l('fournisseur'),
        'url'        => 'fournisseurs/fournisseur',
        'permission' => 'fournisseurs',
        'position'   => 6,
        'icon'       => 'fa-solid fa-truck-field',
    ]);

    if (staff_can('view', 'fournisseurs') || staff_can('create', 'fournisseurs')) {
        $CI->app_menu->add_sidebar_menu_item('fournisseurs', [
            'name'     => _l('fournisseurs'),
            'href'     => admin_url('fournisseurs'),
            'position' => 6,
            'icon'     => 'fa-solid fa-truck-field',
            'badge'    => [],
        ]);
    }
}

/**
 * Categories available for suppliers.
 *
 * @return array<string, string>
 */
function fournisseurs_get_categories()
{
    return [
        'transport'  => _l('fournisseur_category_transport'),
        'fuel'       => _l('fournisseur_category_fuel'),
        'equipment'  => _l('fournisseur_category_equipment'),
        'services'   => _l('fournisseur_category_services'),
        'other'      => _l('fournisseur_category_other'),
    ];
}

/**
 * @param string $key
 * @return string
 */
function format_fournisseur_category($key)
{
    $categories = fournisseurs_get_categories();

    return $categories[$key] ?? ($key !== '' ? $key : '-');
}
