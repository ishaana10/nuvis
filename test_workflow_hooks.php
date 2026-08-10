<?php
declare(strict_types=1);

// Ensure we run in command-line or server environment
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Workflow.php';

echo "=== STARTING WORKFLOW HOOK VERIFICATION TEST ===\n";

$db = NuDatabase::getInstance();

// 1. Setup/Recreate a mock table for testing updates
try {
    $db->exec("DROP TABLE IF EXISTS test_workflow_records");
    $db->exec("CREATE TABLE test_workflow_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(150),
        status VARCHAR(50),
        assigned_user VARCHAR(150),
        additional_notes TEXT
    )");
    echo "test_workflow_records table setup successfully.\n";
} catch (Throwable $e) {
    echo "Error setting up test table: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Insert mock record
$recordId = $db->insert('test_workflow_records', [
    'name' => 'John Doe Request',
    'status' => 'Draft',
    'assigned_user' => 'Pending Assignment',
    'additional_notes' => 'Initial request details'
]);
echo "Inserted mock record ID: {$recordId}\n";

// 3. Create mock workflows, stages and transitions in SQLite test DB
try {
    // Delete any existing mock rows
    $db->query("DELETE FROM nu_workflows WHERE wf_id = 999");
    $db->query("DELETE FROM nu_workflow_stages WHERE wfs_wf_id = 999");
    $db->query("DELETE FROM nu_workflow_transitions WHERE wft_wf_id = 999");
    $db->query("DELETE FROM nu_workflow_instances WHERE wfi_wf_id = 999");

    // Insert Workflow
    $db->insert('nu_workflows', [
        'wf_id' => 999,
        'wf_code' => 'test_hook_wf',
        'wf_name' => 'Test Hook Workflow',
        'wf_description' => 'A workflow to test configurable JSON hooks',
        'wf_form_code' => 'test_form',
        'wf_active' => 1
    ]);

    // Insert Stages
    $db->insert('nu_workflow_stages', [
        'wfs_id' => 9991,
        'wfs_wf_id' => 999,
        'wfs_code' => 'Draft',
        'wfs_name' => 'Draft Stage',
        'wfs_description' => 'Draft description',
        'wfs_color' => '#888888',
        'wfs_is_start' => 1,
        'wfs_is_end' => 0,
        'wfs_order' => 1
    ]);

    $db->insert('nu_workflow_stages', [
        'wfs_id' => 9992,
        'wfs_wf_id' => 999,
        'wfs_code' => 'Approved',
        'wfs_name' => 'Approved Stage',
        'wfs_description' => 'Approved description',
        'wfs_color' => '#22c55e',
        'wfs_is_start' => 0,
        'wfs_is_end' => 1,
        'wfs_order' => 2
    ]);

    // Insert Transitions with custom JSON hook configuration
    $customHookConfig = [
        [
            'action' => 'update_record',
            'table' => 'test_workflow_records',
            'field' => 'assigned_user',
            'value' => 'Assigned to {{actor_name}}'
        ],
        [
            'action' => 'update_record',
            'table' => 'test_workflow_records',
            'field' => 'additional_notes',
            'value' => 'Workflow advanced from Draft to Approved. Comment: {{comment}}. Instance ID: {{wfi_id}}'
        ],
        [
            'action' => 'update_record',
            'table' => 'test_workflow_records',
            'field' => 'status',
            'value' => 'Approved'
        ],
        [
            'action' => 'send_email',
            'to' => 'manager@example.com',
            'subject' => 'Approved: {{name}}',
            'body' => 'Hello, request for {{name}} was approved by {{actor_name}} with comments: {{comment}}.'
        ]
    ];

    $db->insert('nu_workflow_transitions', [
        'wft_id' => 9991,
        'wft_wf_id' => 999,
        'wft_from_id' => 9991,
        'wft_to_id' => 9992,
        'wft_action' => 'advance',
        'wft_label' => 'Approve Request',
        'wft_hook' => json_encode($customHookConfig)
    ]);

    echo "Workflows, stages, and transitions setup successfully.\n";
} catch (Throwable $e) {
    echo "Error setting up workflow configurations: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Start workflow instance
$engine = new WorkflowEngine();
$instanceId = $engine->start(999, 1, 'test_workflow_records', (string)$recordId);
echo "Started workflow instance ID: {$instanceId}\n";

// 5. Advance workflow instance to trigger hooks
echo "Advancing workflow...\n";
$advanceResult = $engine->advance($instanceId, 9991, 1, 'Looks excellent, approved immediately!');

if ($advanceResult) {
    echo "Workflow advanced successfully.\n";
} else {
    echo "Failed to advance workflow.\n";
    exit(1);
}

// 6. Verify record updates
$updatedRecord = $db->fetchOne("SELECT * FROM test_workflow_records WHERE id = ?", [$recordId]);
echo "=== VERIFYING UPDATED RECORD VALUES ===\n";
echo "Record Name: " . $updatedRecord['name'] . "\n";
echo "Record Status (Expected: Approved): " . $updatedRecord['status'] . "\n";
echo "Assigned User (Expected: Assigned to Globe Admin): " . $updatedRecord['assigned_user'] . "\n";
echo "Additional Notes (Expected: contains comments and Instance ID): " . $updatedRecord['additional_notes'] . "\n";

$statusOk = $updatedRecord['status'] === 'Approved';
$assignedOk = strpos($updatedRecord['assigned_user'], 'Globe Admin') !== false;
$notesOk = strpos($updatedRecord['additional_notes'], 'Looks excellent, approved immediately!') !== false && strpos($updatedRecord['additional_notes'], "Instance ID: {$instanceId}") !== false;

if ($statusOk && $assignedOk && $notesOk) {
    echo "=== ALL CHECKS PASSED SUCCESSFULLY! ===\n";
    exit(0);
} else {
    echo "=== CHECKS FAILED! ===\n";
    exit(1);
}
