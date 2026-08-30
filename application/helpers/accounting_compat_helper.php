<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Accounting module (GTSSolution) expects tblclients.balance and tblclients.balance_as_of.
 * SaaS tenants may activate the module without running its DB migration — this ensures columns exist
 * and wraps the client profile hook so PHP 8 does not warn on missing properties.
 */
hooks()->add_action('app_init', 'managio_accounting_compat_bootstrap', 1);
hooks()->add_filter('before_client_added', 'managio_accounting_format_client_balance_fields');
hooks()->add_filter('before_client_updated', 'managio_accounting_format_client_balance_fields', 10, 2);

function managio_accounting_module_active()
{
    return is_dir(FCPATH . 'modules/accounting');
}

function managio_accounting_compat_bootstrap()
{
    if (!managio_accounting_module_active()) {
        return;
    }

    managio_accounting_ensure_client_columns();
    managio_accounting_wrap_client_profile_hook();
}

function managio_accounting_ensure_client_columns()
{
    $CI = &get_instance();

    if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'clients')) {
        return;
    }

    $table = db_prefix() . 'clients';

    if (!$CI->db->field_exists('balance', $table)) {
        $CI->db->query('ALTER TABLE `' . $table . '` ADD `balance` DECIMAL(15,2) NULL DEFAULT NULL');
    }

    if (!$CI->db->field_exists('balance_as_of', $table)) {
        $CI->db->query('ALTER TABLE `' . $table . '` ADD `balance_as_of` DATE NULL DEFAULT NULL');
    }
}

function managio_accounting_wrap_client_profile_hook()
{
    if (!function_exists('acc_init_client_profile')) {
        return;
    }

    hooks()->remove_action('after_customer_profile_company_field', 'acc_init_client_profile');
    hooks()->add_action('after_customer_profile_company_field', 'managio_accounting_acc_init_client_profile', 10);
}

function managio_accounting_acc_init_client_profile($client)
{
    if ($client === null) {
        $client = (object) [
            'balance'       => '',
            'balance_as_of' => '',
        ];
    } else {
        if (!property_exists($client, 'balance')) {
            $client->balance = '';
        }
        if (!property_exists($client, 'balance_as_of')) {
            $client->balance_as_of = '';
        }
    }

    acc_init_client_profile($client);
}

function managio_accounting_format_client_balance_fields($data, $id = null)
{
    if (!is_array($data)) {
        return $data;
    }

    if (array_key_exists('balance', $data)) {
        $data['balance'] = $data['balance'] === '' || $data['balance'] === null
            ? null
            : (float) str_replace(',', '.', (string) $data['balance']);
    }

    if (array_key_exists('balance_as_of', $data)) {
        if ($data['balance_as_of'] === '' || $data['balance_as_of'] === null) {
            $data['balance_as_of'] = null;
        } elseif (function_exists('to_sql_date')) {
            $data['balance_as_of'] = to_sql_date($data['balance_as_of']);
        }
    }

    return $data;
}
