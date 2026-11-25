<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Fournisseurs
Description: Gestion des fournisseurs — unifié avec Acheter → Fournisseurs (pur_vendor)
Version: 1.1.0
Requires at least: 2.3.*
Author: Managio
*/

define('FOURNISSEURS_MODULE_NAME', 'fournisseurs');

hooks()->add_action('admin_init', 'fournisseurs_module_init_menu_items');
hooks()->add_action('admin_init', 'fournisseurs_permissions');
hooks()->add_action('admin_init', 'fournisseurs_ensure_database');
hooks()->add_action('admin_init', 'fournisseurs_migrate_to_pur_vendor');
hooks()->add_filter('staff_can', 'fournisseurs_map_purchase_vendors_permission', 10, 4);

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

function fournisseurs_ensure_database()
{
    $CI = &get_instance();

    if (!$CI->db->table_exists(db_prefix() . 'fournisseurs')) {
        require_once __DIR__ . '/install.php';
    }

    // Link column on purchase vendors for migration / de-dupe
    if ($CI->db->table_exists(db_prefix() . 'pur_vendor')
        && !$CI->db->field_exists('fournisseur_id', db_prefix() . 'pur_vendor')
    ) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pur_vendor`
            ADD COLUMN `fournisseur_id` INT(11) NULL DEFAULT NULL,
            ADD KEY `fournisseur_id` (`fournisseur_id`)');
    }
}

/**
 * One-time sync of legacy fournisseurs rows into purchase pur_vendor (single source of truth).
 */
function fournisseurs_migrate_to_pur_vendor()
{
    if (get_option('fournisseurs_migrated_to_pur_vendor') == '1') {
        return;
    }

    $CI = &get_instance();

    if (!$CI->db->table_exists(db_prefix() . 'fournisseurs')
        || !$CI->db->table_exists(db_prefix() . 'pur_vendor')
    ) {
        return;
    }

    if (!$CI->db->field_exists('fournisseur_id', db_prefix() . 'pur_vendor')) {
        return;
    }

    $rows = $CI->db->get(db_prefix() . 'fournisseurs')->result_array();

    foreach ($rows as $row) {
        $fournisseurId = (int) $row['id'];

        $CI->db->where('fournisseur_id', $fournisseurId);
        $existingByLink = $CI->db->get(db_prefix() . 'pur_vendor')->row();
        if ($existingByLink) {
            continue;
        }

        // Skip if same company already exists as a purchase vendor
        $company = trim((string) ($row['company'] ?? ''));
        if ($company !== '') {
            $CI->db->where('company', $company);
            $existingByName = $CI->db->get(db_prefix() . 'pur_vendor')->row();
            if ($existingByName) {
                $CI->db->where('userid', $existingByName->userid);
                $CI->db->update(db_prefix() . 'pur_vendor', ['fournisseur_id' => $fournisseurId]);
                continue;
            }
        }

        $vendor = [
            'company'      => $company !== '' ? $company : ('Fournisseur #' . $fournisseurId),
            'vat'          => $row['vat'] ?? null,
            'phonenumber'  => $row['phonenumber'] ?? null,
            'website'      => $row['website'] ?? null,
            'address'      => $row['address'] ?? null,
            'city'         => $row['city'] ?? null,
            'state'        => $row['state'] ?? null,
            'zip'          => $row['zip'] ?? null,
            'country'      => isset($row['country']) ? (int) $row['country'] : 0,
            'active'       => isset($row['active']) ? (int) $row['active'] : 1,
            'datecreated'  => !empty($row['datecreated']) ? $row['datecreated'] : date('Y-m-d H:i:s'),
            'addedfrom'    => isset($row['addedfrom']) ? (int) $row['addedfrom'] : 0,
            'category'     => $row['category'] ?? null,
            'fournisseur_id' => $fournisseurId,
            'billing_street' => $row['address'] ?? null,
            'billing_city'   => $row['city'] ?? null,
            'billing_state'  => $row['state'] ?? null,
            'billing_zip'    => $row['zip'] ?? null,
            'billing_country'=> isset($row['country']) ? (int) $row['country'] : 0,
        ];

        $CI->db->insert(db_prefix() . 'pur_vendor', $vendor);
        $vendorId = $CI->db->insert_id();

        if ($vendorId && (!empty($row['contact_fullname']) || !empty($row['contact_email']) || !empty($row['email']))) {
            $nameParts = preg_split('/\s+/', trim((string) ($row['contact_fullname'] ?? '')), 2);
            $firstname = $nameParts[0] ?? 'Contact';
            $lastname  = $nameParts[1] ?? '';
            $email     = !empty($row['contact_email']) ? $row['contact_email'] : ($row['email'] ?? '');

            if ($email !== '' || $firstname !== '') {
                $CI->db->insert(db_prefix() . 'pur_contacts', [
                    'userid'      => $vendorId,
                    'is_primary'  => 1,
                    'firstname'   => $firstname !== '' ? $firstname : 'Contact',
                    'lastname'    => $lastname,
                    'email'       => $email !== '' ? $email : ('vendor' . $vendorId . '@local.invalid'),
                    'phonenumber' => $row['contact_phonenumber'] ?? ($row['phonenumber'] ?? ''),
                    'datecreated' => date('Y-m-d H:i:s'),
                    'active'      => 1,
                ]);
            }
        }
    }

    update_option('fournisseurs_migrated_to_pur_vendor', '1');
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

/**
 * Staff with fournisseurs capability can use Acheter → Fournisseurs (purchase_vendors).
 */
function fournisseurs_map_purchase_vendors_permission($can, $capability, $feature, $staff_id)
{
    if ($can || $feature !== 'purchase_vendors') {
        return $can;
    }

    // Avoid recursion: check raw fournisseurs permission via permissions array
    static $checking = false;
    if ($checking) {
        return $can;
    }

    $checking = true;
    $mapped = staff_can($capability, 'fournisseurs', $staff_id);
    // view_own on purchase_vendors ← view on fournisseurs
    if (!$mapped && $capability === 'view_own') {
        $mapped = staff_can('view', 'fournisseurs', $staff_id);
    }
    $checking = false;

    return $mapped ? true : $can;
}

/**
 * Menu: no top-level Fournisseurs — use Acheter → Fournisseurs (purchase/vendors).
 * Quick action points to the purchase vendor form.
 */
function fournisseurs_module_init_menu_items()
{
    $CI = &get_instance();

    $canPurchaseVendors = has_permission('purchase_vendors', '', 'view')
        || has_permission('purchase_vendors', '', 'view_own')
        || has_permission('purchase_vendors', '', 'create');
    $canFournisseurs = staff_can('view', 'fournisseurs') || staff_can('create', 'fournisseurs');

    if ($canPurchaseVendors || $canFournisseurs) {
        $CI->app->add_quick_actions_link([
            'name'       => _l('fournisseur'),
            'url'        => 'purchase/vendor',
            'permission' => $canPurchaseVendors ? 'purchase_vendors' : 'fournisseurs',
            'position'   => 6,
            'icon'       => 'fa-solid fa-truck-field',
        ]);
    }
}

/**
 * Categories available for suppliers (legacy helper kept for any remaining views).
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
