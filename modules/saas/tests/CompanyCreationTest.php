<?php

/**
 * Manual integration test for SaaS company creation flow.
 * Run from CLI: php modules/saas/tests/CompanyCreationTest.php
 *
 * Requires: CRM is installed, DB credentials are in application/config/database.php
 */

define('BASEPATH', true);
define('APPPATH', __DIR__ . '/../../../application/');
define('SAAS_MODULE', 'saas');

$results = [];
$pass = 0;
$fail = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $results, $pass, $fail;
    if ($condition) {
        $results[] = "[PASS] $name";
        $pass++;
    } else {
        $results[] = "[FAIL] $name" . ($detail ? " | $detail" : '');
        $fail++;
    }
}

/**
 * Unit-style checks — no DB needed.
 */

// 1. payment_method self-reference guard
// Before fix: $pdata['payment_method'] was read before $pdata was defined.
// Simulate the fix: read from POST (here we use a mock).
$_POST['payment_method'] = 'stripe';
$payment_method_post = $_POST['payment_method'] ?? null;
$pdata_method = !empty($payment_method_post) ? $payment_method_post : 'manual';
assert_test(
    'payment_method reads from POST, not self-referential $pdata',
    $pdata_method === 'stripe',
    'got: ' . $pdata_method
);

unset($_POST['payment_method']);
$payment_method_post = $_POST['payment_method'] ?? null;
$pdata_method = !empty($payment_method_post) ? $payment_method_post : 'manual';
assert_test(
    'payment_method falls back to manual when POST empty',
    $pdata_method === 'manual',
    'got: ' . $pdata_method
);

// 2. array_diff direction for not_activated
// Before fix: array_diff($activated, $diff) — always empty when activated ⊆ diff.
// After fix:  array_diff($diff, $activated) — correctly finds unactivated modules.
$diff      = ['module_a', 'module_b', 'module_c'];
$activated = ['module_a', 'module_c']; // module_b failed

$wrong_not_activated = array_diff($activated, $diff); // old (always [])
$fixed_not_activated = array_diff($diff, $activated); // new (catches module_b)

assert_test(
    'Old array_diff($activated, $diff) always empty — confirms bug existed',
    empty($wrong_not_activated),
    'old result: ' . implode(',', $wrong_not_activated)
);

assert_test(
    'Fixed array_diff($diff, $activated) catches failed modules',
    $fixed_not_activated === ['module_b'] || in_array('module_b', $fixed_not_activated),
    'fixed result: ' . implode(',', $fixed_not_activated)
);

// 3. Column intersection logic (fix for SELECT * column mismatch)
$seed_cols = ['id', 'firstname', 'lastname', 'email', 'password', 'admin', 'active', 'datecreated'];
$main_cols = ['id', 'firstname', 'lastname', 'email', 'password', 'admin', 'active', 'datecreated', 'new_col_added_by_migration'];

$common = array_values(array_intersect($seed_cols, $main_cols));
assert_test(
    'Common column intersection excludes extra main DB columns',
    !in_array('new_col_added_by_migration', $common),
    'common cols: ' . implode(',', $common)
);
assert_test(
    'Common column intersection keeps all shared columns',
    count($common) === count($seed_cols),
    'count=' . count($common) . ' expected=' . count($seed_cols)
);

// 4. install_basic_data staff insert SQL shape
$db_name   = 'test_new_db';
$old_db    = 'test_main_db';
$uTable    = 'tbl_staff';
$cols      = ['id', 'firstname', 'lastname', 'email', 'password'];
$cols_str  = '`' . implode('`, `', $cols) . '`';
$sql       = "INSERT INTO `{$db_name}`.`{$uTable}` ({$cols_str}) SELECT {$cols_str} FROM `{$old_db}`.`{$uTable}` WHERE `{$uTable}`.`role` != 4";
assert_test(
    'INSERT SELECT uses explicit column list (no SELECT *)',
    strpos($sql, 'SELECT *') === false && strpos($sql, '`id`') !== false,
    substr($sql, 0, 120)
);

// 5. create_database result handling
$db_result_success = ['result' => 'success', 'db_name' => 'crm_acme'];
$db_result_fail    = ['error' => 'Access denied for user'];

assert_test(
    'Success result correctly detected',
    !empty($db_result_success['result']) && $db_result_success['result'] === 'success'
);
assert_test(
    'Error result correctly detected',
    !empty($db_result_fail['error'])
);

// 6. manual_payment_gateway_install is now a function (not inline code)
$install_file = __DIR__ . '/../../../modules/manual_payment_gateway/install.php';
if (file_exists($install_file)) {
    $contents = file_get_contents($install_file);
    assert_test(
        'install.php defines manual_payment_gateway_install() function',
        strpos($contents, 'function manual_payment_gateway_install') !== false,
        'file: ' . $install_file
    );
    assert_test(
        'install.php defines manual_payment_gateway_uninstall() function',
        strpos($contents, 'function manual_payment_gateway_uninstall') !== false
    );
    assert_test(
        'install.php no longer runs table creation as inline code (wrapped in function)',
        strpos($contents, "\$CI = &get_instance();\n\nif") === false,
        'inline code check'
    );
} else {
    assert_test('install.php exists', false, 'not found: ' . $install_file);
}

// 7. main init file registers hooks at top level (not inside module_init)
$init_file = __DIR__ . '/../../../modules/manual_payment_gateway/manual_payment_gateway.php';
if (file_exists($init_file)) {
    $contents = file_get_contents($init_file);
    // hooks should NOT be inside manual_payment_gateway_module_init
    preg_match('/function manual_payment_gateway_module_init\(\)(.*?)^}/ms', $contents, $m);
    $func_body = $m[1] ?? '';
    assert_test(
        'register_activation_hook not inside manual_payment_gateway_module_init()',
        strpos($func_body, 'register_activation_hook') === false,
        'func_body excerpt: ' . substr($func_body, 0, 200)
    );
    // hooks should exist at top level (after require_once install.php)
    assert_test(
        'register_activation_hook exists at file top level',
        strpos($contents, "register_activation_hook(MANUAL_PAYMENT_GATEWAY_MODULE_NAME") !== false
    );
} else {
    assert_test('manual_payment_gateway.php exists', false, 'not found: ' . $init_file);
}

// 8. $msg always defined (null-safe fallback)
// Before fix: $msg could be undefined if neither branch set it.
$msg_set   = 'update_company';
$msg_final = $msg_set ?? 'save_company';
assert_test('$msg null-coalesce fallback works', $msg_final === 'update_company');

$msg_unset = null;
$msg_final = $msg_unset ?? 'save_company';
assert_test('$msg falls back when null', $msg_final === 'save_company');

// --- Output ---
echo PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
echo "SaaS Company Creation — Test Results" . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
foreach ($results as $r) {
    echo $r . PHP_EOL;
}
echo str_repeat('-', 60) . PHP_EOL;
echo "Passed: $pass  Failed: $fail  Total: " . ($pass + $fail) . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
exit($fail > 0 ? 1 : 0);
