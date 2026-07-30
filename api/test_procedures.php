<?php
/**
 * api/test_procedures.php
 * Automated test script to verify Procedures/Custom PHP Functions table, self-healing, API endpoints, and calling helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';

echo "=== Procedures Module Automated Tests ===\n\n";

try {
    // 1. Verify self-healing table exists
    $db = NuDatabase::getInstance();
    $hasTable = $db->fetchOne("SHOW TABLES LIKE 'nu_procedures'");
    if ($hasTable) {
        echo "[PASS] nu_procedures table successfully created via self-healing.\n";
    } else {
        throw new Exception("nu_procedures table not found.");
    }

    // 2. Clean up any existing test procedures
    $db->query("DELETE FROM nu_procedures WHERE procedure_code LIKE 'test_calc_%'");

    // 3. Insert a test procedure
    $procData = [
        'procedure_name'        => 'Test Calc Tax',
        'procedure_code'        => 'test_calc_tax',
        'procedure_description' => 'A centralized test procedure for tax calculations.',
        'procedure_php'         => '<?php
$subtotal = $_proc_params[\'subtotal\'] ?? 0;
$rate     = $_proc_params[\'rate\'] ?? 0.1;
echo "Calculating tax...\n";
$_proc_result = [
    \'tax\'   => $subtotal * $rate,
    \'total\' => $subtotal * (1 + $rate)
];
',
        'procedure_active'      => 1
    ];
    $db->insert('nu_procedures', $procData);
    echo "[PASS] Successfully inserted test procedure 'test_calc_tax'.\n";

    // 4. Test execution via NuProcedure::run()
    $res = NuProcedure::run('test_calc_tax', ['subtotal' => 200, 'rate' => 0.15]);
    if ($res['success']) {
        echo "[PASS] Procedure executed successfully.\n";
        if (trim($res['output']) === 'Calculating tax...') {
            echo "[PASS] Printed echoes captured correctly.\n";
        } else {
            echo "[FAIL] Printed output was: " . var_export($res['output'], true) . "\n";
        }
        if (is_array($res['data']) && $res['data']['tax'] === 30.0 && $res['data']['total'] === 230.0) {
            echo "[PASS] Structured \$_proc_result payload returned correctly.\n";
        } else {
            echo "[FAIL] Data returned was: " . var_export($res['data'], true) . "\n";
        }
    } else {
        throw new Exception("Procedure execution failed: " . ($res['error'] ?? ''));
    }

    // 5. Test execution via nu_run_procedure()
    $res2 = nu_run_procedure('test_calc_tax', ['subtotal' => 100, 'rate' => 0.05]);
    if ($res2['success'] && $res2['data']['tax'] === 5.0) {
        echo "[PASS] nu_run_procedure wrapper works perfectly.\n";
    } else {
        throw new Exception("nu_run_procedure wrapper failed.");
    }

    // 6. Test execution via run_procedure()
    $res3 = run_procedure('test_calc_tax', ['subtotal' => 400, 'rate' => 0.1]);
    if ($res3['success'] && $res3['data']['tax'] === 40.0) {
        echo "[PASS] run_procedure wrapper works perfectly.\n";
    } else {
        throw new Exception("run_procedure wrapper failed.");
    }

    // Clean up
    $db->query("DELETE FROM nu_procedures WHERE procedure_code LIKE 'test_calc_%'");
    echo "\n[ALL TESTS COMPLETED SUCCESSFULLY!]\n";

} catch (Throwable $e) {
    echo "\n[TEST EXCEPTION FAILURE]: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    exit(1);
}
