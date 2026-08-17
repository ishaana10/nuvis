<?php
declare(strict_types=1);

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/AgentRuntime.php';
require_once __DIR__ . '/core/Workflow.php';

echo "=== Nuvis AI Agent & Workflow Automated Test Suite ===\n\n";

$db = NuDatabase::getInstance();

// Test 1: Verify DB Tables
echo "[Test 1] Verifying AI Agent and Scheduled Trigger Database Tables...\n";
$tables = ['nu_agents', 'nu_agent_runs', 'nu_agent_messages', 'nu_agent_tool_calls', 'nu_agent_memory', 'nu_scheduled_triggers'];
foreach ($tables as $t) {
    $row = $db->fetchOne("SELECT count(*) as c FROM `{$t}`");
    if ($row !== null) {
        echo " -> Table '{$t}' verified successfully.\n";
    } else {
        throw new Exception("Table '{$t}' not found!");
    }
}

// Test 2: Verify Agent Runtime Execution & Tool Routing
echo "\n[Test 2] Testing Agent Runtime execution and tool routing...\n";
$runtime = new AgentRuntime($db, null);
$runResult = $runtime->run(1, "Get details for customer request record d4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a from table demo_customer_requests");

if ($runResult['success']) {
    echo " -> Agent execution completed successfully. Run ID: {$runResult['run_id']}\n";
    echo " -> Agent Answer: {$runResult['answer']}\n";
} else {
    throw new Exception("Agent execution failed: " . ($runResult['error'] ?? 'Unknown'));
}

// Test 3: Verify Tool Call Logging & Traces
echo "\n[Test 3] Verifying Execution Run Traces & Tool Call Logs...\n";
$messages = $db->fetchAll("SELECT * FROM nu_agent_messages WHERE run_id = ?", [$runResult['run_id']]);
$toolCalls = $db->fetchAll("SELECT * FROM nu_agent_tool_calls WHERE run_id = ?", [$runResult['run_id']]);

echo " -> Recorded " . count($messages) . " message(s) and " . count($toolCalls) . " tool call(s).\n";

// Test 4: Verify Call Agent Workflow Hook Action
echo "\n[Test 4] Testing Workflow call_agent action hook...\n";
$wfEngine = new WorkflowEngine();
$trans = [
    'wft_id' => 999,
    'wft_wf_id' => 100,
    'wft_from_id' => 101,
    'wft_to_id' => 102,
    'wft_label' => 'AI Review Transition',
    'wft_hook' => json_encode([
        'action' => 'call_agent',
        'agent_id' => 1,
        'prompt' => 'Review customer request {{record.customer_name}}'
    ])
];
$instance = [
    'wfi_id' => 1,
    'wf_name' => 'Customer Request Workflow',
    'wfi_record_table' => 'demo_customer_requests',
    'wfi_record_id' => 'd4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a',
    'wfi_started_by' => 1
];

// Execute hook directly via reflection
$ref = new ReflectionClass('WorkflowEngine');
$method = $ref->getMethod('executeHook');
$method->setAccessible(true);
$method->invoke($wfEngine, $trans, $instance, 1, 'Testing call_agent hook');

echo " -> Workflow call_agent hook executed without exceptions.\n";

echo "\n=== All AI Agent Tests Passed Successfully! ===\n";
