<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Accounting module (GTSSolution) expects tblclients.balance and tblclients.balance_as_of.
 * SaaS tenants may activate the module without running its DB migration — this ensures columns exist,
 * provisions acc_* tables, wraps the client profile hook, and applies PHP 8.2 compatibility patches.
 */
hooks()->add_action('app_init', 'managio_accounting_prepare_php82_compat', 0);
hooks()->add_action('app_init', 'managio_accounting_compat_bootstrap', 1);
hooks()->add_action('admin_init', 'managio_accounting_compat_bootstrap', 0);
hooks()->add_filter('before_client_added', 'managio_accounting_format_client_balance_fields');
hooks()->add_filter('before_client_updated', 'managio_accounting_format_client_balance_fields', 10, 2);
hooks()->add_filter('before_client_added', 'managio_accounting_merge_balance_from_request', 99);
hooks()->add_filter('before_client_updated', 'managio_accounting_merge_balance_from_request', 99, 2);
hooks()->add_filter('client_table_data', 'managio_accounting_preserve_balance_in_client_table_data', 99, 2);
hooks()->add_action('client_updated', 'managio_accounting_persist_balance_after_save', 5);
hooks()->add_action('after_client_created', 'managio_accounting_persist_balance_after_create', 5);
hooks()->add_action('admin_footer', 'managio_accounting_client_balance_form_footer');

function managio_accounting_module_active()
{
    return is_dir(FCPATH . 'modules/accounting');
}

function managio_accounting_prepare_php82_compat()
{
    if (!managio_accounting_module_active()) {
        return;
    }

    managio_accounting_apply_php82_patches();

    if (!managio_accounting_is_php82_patch_applied()) {
        managio_accounting_register_deprecation_shield();
    }
}

function managio_accounting_is_accounting_path($file)
{
    $normalized = str_replace('\\', '/', (string) $file);

    return strpos($normalized, '/modules/accounting/') !== false;
}

function managio_accounting_register_deprecation_shield()
{
    static $registered = false;

    if ($registered) {
        return;
    }

    $registered = true;

    static $previousHandler = null;
    $previousHandler = set_error_handler(
        static function ($severity, $message, $file, $line) use (&$previousHandler) {
            if ($severity === E_DEPRECATED && managio_accounting_is_accounting_path($file)) {
                return true;
            }

            if ($previousHandler) {
                return $previousHandler($severity, $message, $file, $line);
            }

            return false;
        },
        E_DEPRECATED
    );
}

function managio_accounting_compat_bootstrap()
{
    if (!managio_accounting_module_active()) {
        return;
    }

    // Must run before install.php loads Accounting_model (PHP 8.2 dynamic properties).
    managio_accounting_apply_php82_patches();
    managio_accounting_ensure_client_columns();
    managio_accounting_ensure_database();
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

function managio_accounting_ensure_database()
{
    $CI = &get_instance();

    if (!isset($CI->db)) {
        return;
    }

    if ($CI->db->table_exists(db_prefix() . 'acc_class')) {
        return;
    }

    managio_accounting_apply_php82_patches();

    $provision = function () use ($CI) {
        if (function_exists('saas_provision_module_database')) {
            saas_provision_module_database('accounting');

            return;
        }

        $installFile = FCPATH . 'modules/accounting/install.php';
        if (is_file($installFile)) {
            require_once $installFile;
            log_message('info', '[accounting_compat] ran install.php');
        }

        if ($CI->app_modules->is_installed('accounting')
            && $CI->app_modules->is_database_upgrade_required('accounting')) {
            $migration = new App_module_migration('accounting');
            $migration->to_latest();
            log_message('info', '[accounting_compat] ran accounting migrations');
        }
    };

    if (managio_accounting_is_php82_patch_applied()) {
        $provision();
    } else {
        managio_accounting_run_with_deprecation_suppressed($provision);
    }
}

/**
 * PHP 8.2+: Accounting_model assigns $this->db without declaring the property.
 */
function managio_accounting_apply_php82_patches()
{
    $modelsDir = FCPATH . 'modules/accounting/models/';
    $patched   = false;

    if (is_dir($modelsDir)) {
        foreach (glob($modelsDir . '*.php') ?: [] as $modelFile) {
            $baseName  = basename($modelFile, '.php');
            $className = ucfirst($baseName);
            if (managio_accounting_patch_model_file($modelFile, $className)) {
                $patched = true;
            }
        }
    }

    $modelFile = FCPATH . 'modules/accounting/models/Accounting_model.php';
    if (is_file($modelFile) && managio_accounting_patch_model_file($modelFile, 'Accounting_model')) {
        $patched = true;
    }

    return $patched;
}

function managio_accounting_is_php82_patch_applied()
{
    $modelFile = FCPATH . 'modules/accounting/models/Accounting_model.php';

    if (!is_readable($modelFile)) {
        return false;
    }

    $content = file_get_contents($modelFile);

    return $content !== false
        && (strpos($content, 'AllowDynamicProperties') !== false || strpos($content, 'public $db') !== false);
}

function managio_accounting_patch_model_file($modelFile, $className)
{
    if (!is_readable($modelFile)) {
        return false;
    }

    $content = file_get_contents($modelFile);
    if ($content === false || strpos($content, 'class ' . $className) === false) {
        return false;
    }

    if (strpos($content, 'AllowDynamicProperties') !== false || strpos($content, 'public $db') !== false) {
        return true;
    }

    $patched = preg_replace(
        '/class\s+' . preg_quote($className, '/') . '\b/',
        "#[\\AllowDynamicProperties]\nclass {$className}",
        $content,
        1
    );

    if (!is_string($patched) || $patched === $content) {
        $patched = preg_replace(
            '/(class\s+' . preg_quote($className, '/') . '[^\{]*\{)/',
            "$1\n    /** @var CI_DB_query_builder */\n    public \$db;",
            $content,
            1
        );
    }

    if (!is_string($patched) || $patched === $content) {
        log_message('error', '[accounting_compat] could not build PHP 8.2 patch for ' . basename($modelFile));

        return false;
    }

    if (!is_writable($modelFile)) {
        log_message('error', '[accounting_compat] cannot write PHP 8.2 patch (check permissions): ' . $modelFile);

        return false;
    }

    if (file_put_contents($modelFile, $patched) === false) {
        log_message('error', '[accounting_compat] failed writing PHP 8.2 patch: ' . $modelFile);

        return false;
    }

    log_message('info', '[accounting_compat] applied PHP 8.2 patch to ' . basename($modelFile));

    return true;
}

function managio_accounting_run_with_deprecation_suppressed(callable $callback)
{
    managio_accounting_register_deprecation_shield();

    return $callback();
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

function managio_accounting_client_has_balance_columns()
{
    $CI = &get_instance();

    if (!isset($CI->db)) {
        return false;
    }

    $table = db_prefix() . 'clients';

    return $CI->db->field_exists('balance', $table)
        && $CI->db->field_exists('balance_as_of', $table);
}

function managio_accounting_collect_balance_from_request()
{
    $CI  = &get_instance();
    $out = [
        'balance'       => null,
        'balance_as_of' => null,
        'has_balance'   => false,
        'has_date'      => false,
    ];

    if (!$CI->input->post()) {
        return $out;
    }

    $balanceKeys = ['balance', 'opening_balance', 'client_balance', 'acc_balance', 'opening_balance_amount'];
    foreach ($balanceKeys as $key) {
        $value = $CI->input->post($key);
        if ($value !== null && $value !== '') {
            $out['balance']     = $value;
            $out['has_balance'] = true;
            break;
        }
    }

    if (!$out['has_balance']) {
        foreach ($_POST as $key => $value) {
            if (!is_string($key) || $value === '' || $value === null) {
                continue;
            }

            $lowerKey = strtolower($key);
            if (strpos($lowerKey, 'balance') === false
                || strpos($lowerKey, 'as_of') !== false
                || strpos($lowerKey, 'date') !== false) {
                continue;
            }

            $out['balance']     = $value;
            $out['has_balance'] = true;
            break;
        }
    }

    $dateKeys = ['balance_as_of', 'opening_balance_as_of', 'opening_balance_date', 'balance_as_of_date'];
    foreach ($dateKeys as $key) {
        if ($CI->input->post($key) !== null) {
            $out['balance_as_of'] = $CI->input->post($key);
            $out['has_date']      = true;
            break;
        }
    }

    if (!$out['has_date']) {
        foreach ($_POST as $key => $value) {
            if (!is_string($key) || $value === null) {
                continue;
            }

            $lowerKey = strtolower($key);
            if (strpos($lowerKey, 'balance_as_of') !== false
                || (strpos($lowerKey, 'balance') !== false && strpos($lowerKey, 'date') !== false)) {
                $out['balance_as_of'] = $value;
                $out['has_date']      = true;
                break;
            }
        }
    }

    return $out;
}

function managio_accounting_merge_balance_from_request($data, $id = null)
{
    if (!is_array($data) || !managio_accounting_client_has_balance_columns()) {
        return $data;
    }

    $post = managio_accounting_collect_balance_from_request();

    if ($post['has_balance']) {
        $data['balance'] = $post['balance'];
    }

    if ($post['has_date']) {
        $data['balance_as_of'] = $post['balance_as_of'];
    }

    return managio_accounting_format_client_balance_fields($data, $id);
}

function managio_accounting_preserve_balance_in_client_table_data($filtered, $original)
{
    if (!managio_accounting_client_has_balance_columns() || !is_array($original)) {
        return $filtered;
    }

    foreach (['balance', 'balance_as_of'] as $key) {
        if (array_key_exists($key, $original)) {
            $filtered[$key] = $original[$key];
        }
    }

    return $filtered;
}

function managio_accounting_persist_balance_after_save($hook_data)
{
    if (!managio_accounting_client_has_balance_columns()) {
        return;
    }

    $client_id = is_array($hook_data) ? (int) ($hook_data['id'] ?? 0) : 0;
    if ($client_id <= 0) {
        return;
    }

    $post = managio_accounting_collect_balance_from_request();
    if (!$post['has_balance'] && !$post['has_date']) {
        return;
    }

    $update = [];

    if ($post['has_balance']) {
        $formatted         = managio_accounting_format_client_balance_fields(['balance' => $post['balance']]);
        $update['balance'] = $formatted['balance'];
    }

    if ($post['has_date']) {
        $formatted                = managio_accounting_format_client_balance_fields(['balance_as_of' => $post['balance_as_of']]);
        $update['balance_as_of']  = $formatted['balance_as_of'];
    }

    if ($update === []) {
        return;
    }

    $CI = &get_instance();
    $CI->db->where('userid', $client_id);
    $CI->db->update(db_prefix() . 'clients', $update);

    log_message('info', '[accounting_compat] persisted balance for client ' . $client_id);
}

function managio_accounting_persist_balance_after_create($hook_data)
{
    $client_id = is_array($hook_data) ? (int) ($hook_data['id'] ?? 0) : 0;

    if ($client_id > 0) {
        managio_accounting_persist_balance_after_save(['id' => $client_id]);
    }
}

function managio_accounting_client_balance_form_footer()
{
    $CI = &get_instance();

    if ($CI->router->fetch_class() !== 'clients') {
        return;
    }
    ?>
    <script>
    (function($) {
        var $form = $('#client-profile-form');
        if (!$form.length) {
            return;
        }

        function ensureBalanceFieldNames() {
            if (!$form.find('input[name="balance"], input[name="opening_balance"]').length) {
                $form.find('label').filter(function() {
                    var label = $(this).text().toLowerCase();
                    return label.indexOf('équilibre') !== -1 || label.indexOf('balance') !== -1;
                }).each(function() {
                    var $input = $(this).closest('.form-group').find('input[type="text"], input[type="number"]');
                    if ($input.length && !$input.attr('name')) {
                        $input.attr('name', 'balance');
                    }
                });
            }

            if (!$form.find('input[name="balance_as_of"], input[name="opening_balance_as_of"]').length) {
                $form.find('label').filter(function() {
                    var label = $(this).text().toLowerCase();
                    return label.indexOf('à partir') !== -1 || label.indexOf('as of') !== -1;
                }).each(function() {
                    var $input = $(this).closest('.form-group').find('input');
                    if ($input.length && !$input.attr('name')) {
                        $input.attr('name', 'balance_as_of');
                    }
                });
            }
        }

        ensureBalanceFieldNames();
        $form.on('submit', function() {
            ensureBalanceFieldNames();

            if (window.location.search.indexOf('managio_debug=1') !== -1) {
                console.log('[managio:balance]', {
                    balance: $form.find('[name="balance"], [name="opening_balance"]').first().val(),
                    balance_as_of: $form.find('[name="balance_as_of"], [name="opening_balance_as_of"]').first().val()
                });
            }
        });
    })(jQuery);
    </script>
    <?php
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

if (managio_accounting_module_active()) {
    managio_accounting_prepare_php82_compat();
}
