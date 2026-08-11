<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/Workflow.php';

// Restricted to globeadmin only
$currentUser = $auth->getCurrentUser();
if (!$currentUser || strtolower((string)$currentUser['usr_role']) !== 'globeadmin') {
    echo '<div style="padding:24px;border:2px solid var(--color-error);background:var(--color-error-highlight);border-radius:8px;"><h3>Access Denied</h3><p>Only globeadmin developers can view system demo files.</p></div>';
    exit;
}

$db = NuDatabase::getInstance();

// Helper to generate UUIDs
function demo_generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Helper to resolve workflow, stage, and transition IDs dynamically
function demo_get_workflow_info($db) {
    $wf = $db->fetchOne("SELECT wf_id FROM nu_workflows WHERE wf_code = 'customer_request_wf' LIMIT 1");
    $wfId = $wf ? (int)$wf['wf_id'] : 100;

    $startStage = $db->fetchOne("SELECT wfs_id FROM nu_workflow_stages WHERE wfs_wf_id = ? AND wfs_is_start = 1 LIMIT 1", [$wfId]);
    $startStageId = $startStage ? (int)$startStage['wfs_id'] : 101;

    $t1 = $db->fetchOne("SELECT wft_id, wft_to_id FROM nu_workflow_transitions WHERE wft_wf_id = ? AND wft_from_id = ? LIMIT 1", [$wfId, $startStageId]);
    $trans1Id = $t1 ? (int)$t1['wft_id'] : 101;

    $trans2Id = 102;
    if ($t1) {
        $nextStageId = (int)$t1['wft_to_id'];
        $t2 = $db->fetchOne("SELECT wft_id FROM nu_workflow_transitions WHERE wft_wf_id = ? AND wft_from_id = ? LIMIT 1", [$wfId, $nextStageId]);
        if ($t2) {
            $trans2Id = (int)$t2['wft_id'];
        }
    }

    return [$wfId, $startStageId, $trans1Id, $trans2Id];
}

// Handle AJAX actions
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax_action'];

    try {
        if ($action === 'reset_demo') {
            list($wfId, $startStageId, $trans1Id, $trans2Id) = demo_get_workflow_info($db);
            // Delete and re-seed
            $db->query("DELETE FROM demo_customer_requests");
            $db->query("DELETE FROM demo_staff_services");
            $db->query("DELETE FROM demo_service_types");
            $db->query("DELETE FROM nu_workflow_instances WHERE wfi_wf_id = ?", [$wfId]);
            $db->query("DELETE FROM nu_workflow_history WHERE wfh_wfi_id NOT IN (SELECT wfi_id FROM nu_workflow_instances)");

            $db->exec("INSERT INTO `demo_service_types` (`service_type_id`, `name`, `description`, `price`) VALUES
            ('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'Plumbing Maintenance', 'General plumbing repairs, leak detection, and pipe maintenance.', 150.00),
            ('b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'Electrical Inspection', 'Safety audit of wiring, outlets, and panel inspection.', 120.00),
            ('c3d4e5f6-a7b8-9c0d-1e2f-3a4b5c6d7e8f', 'HVAC System Service', 'Heating, ventilation, and air conditioning diagnostic and filter change.', 200.00)");

            $db->exec("INSERT INTO `demo_customer_requests` (`request_id`, `customer_name`, `service_type_id`, `request_details`, `status`) VALUES
            ('d4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a', 'Alice Smith', 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'Main drain is running extremely slow and backing up.', 'Pending'),
            ('e5f6a7b8-c9d0-1e2f-3a4b-5c6d7e8f9a0b', 'Bob Johnson', 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'Living room outlets have no power. Breaker is not tripped.', 'Pending')");

            // Also re-start workflow instances for the two seeded customer requests
            if (class_exists('WorkflowEngine')) {
                $wfEngine = new WorkflowEngine();
                $wfEngine->start($wfId, (int)$currentUser['usr_id'], 'demo_customer_requests', 'd4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a');
                $wfEngine->start($wfId, (int)$currentUser['usr_id'], 'demo_customer_requests', 'e5f6a7b8-c9d0-1e2f-3a4b-5c6d7e8f9a0b');
            }

            echo json_encode(['success' => true, 'message' => 'Demo data reset successfully.']);
            exit;
        }

        if ($action === 'create_request') {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true);
            $customerName = trim($payload['customer_name'] ?? '');
            $serviceTypeId = trim($payload['service_type_id'] ?? '');
            $details = trim($payload['request_details'] ?? '');

            if (!$customerName || !$serviceTypeId) {
                echo json_encode(['success' => false, 'error' => 'Customer name and service type are required.']);
                exit;
            }

            $requestId = demo_generate_uuid();
            $db->insert('demo_customer_requests', [
                'request_id' => $requestId,
                'customer_name' => $customerName,
                'service_type_id' => $serviceTypeId,
                'request_details' => $details,
                'status' => 'Pending'
            ]);

            // Automatically start workflow
            if (class_exists('WorkflowEngine')) {
                $wfEngine = new WorkflowEngine();
                list($wfId, $startStageId, $trans1Id, $trans2Id) = demo_get_workflow_info($db);
                $wfEngine->start($wfId, (int)$currentUser['usr_id'], 'demo_customer_requests', $requestId);
            }

            echo json_encode(['success' => true, 'request_id' => $requestId]);
            exit;
        }

        if ($action === 'log_service') {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true);
            $requestId = trim($payload['request_id'] ?? '');
            $notes = trim($payload['staff_notes'] ?? '');
            $method = trim($payload['method'] ?? 'php_after_save');

            if (!$requestId || !$notes) {
                echo json_encode(['success' => false, 'error' => 'Customer request selection and notes are required.']);
                exit;
            }

            $logId = demo_generate_uuid();
            $db->insert('demo_staff_services', [
                'service_log_id' => $logId,
                'customer_request_id' => $requestId,
                'staff_notes' => $notes,
                'service_date' => date('Y-m-d H:i:s')
            ]);

            if ($method === 'php_after_save') {
                // Simulate PHP After Save script: update the customer requests status to Completed
                $db->update('demo_customer_requests', ['status' => 'Completed'], 'request_id = ?', [$requestId]);
                $msg = "Logged service action and updated Customer Request status to 'Completed' via simulated 'PHP After Save' script.";
            } else {
                // Simulate/trigger Workflow transition to Completed
                list($wfId, $startStageId, $trans1Id, $trans2Id) = demo_get_workflow_info($db);
                $instance = $db->fetchOne("SELECT wfi_id FROM nu_workflow_instances WHERE wfi_record_id = ? AND wfi_wf_id = ? ORDER BY wfi_id DESC LIMIT 1", [$requestId, $wfId]);

                if (class_exists('WorkflowEngine')) {
                    $wfEngine = new WorkflowEngine();

                    // Self-healing: if no workflow instance exists, automatically start one first
                    if (!$instance) {
                        try {
                            $wfEngine->start($wfId, (int)$currentUser['usr_id'], 'demo_customer_requests', $requestId);
                            $instance = $db->fetchOne("SELECT wfi_id FROM nu_workflow_instances WHERE wfi_record_id = ? AND wfi_wf_id = ? ORDER BY wfi_id DESC LIMIT 1", [$requestId, $wfId]);
                        } catch (Throwable $ignored) {}
                    }

                    if ($instance) {
                        $instObj = $wfEngine->getInstance((int)$instance['wfi_id']);

                        // Self-healing: if the instance is not active (completed/cancelled/rejected), reactivate it to start stage
                        if ($instObj && $instObj['wfi_status'] !== 'active') {
                            $db->update(
                                'nu_workflow_instances',
                                [
                                    'wfi_stage_id' => $startStageId,
                                    'wfi_status'   => 'active',
                                    'wfi_completed_at' => null
                                ],
                                'wfi_id = ?',
                                [$instance['wfi_id']]
                            );
                            $instObj = $wfEngine->getInstance((int)$instance['wfi_id']);
                        }

                        if ($instObj) {
                            if ((int)$instObj['wfi_stage_id'] === $startStageId) {
                                // Pending -> In Progress (Transition ID 101)
                                $wfEngine->advance((int)$instance['wfi_id'], $trans1Id, (int)$currentUser['usr_id'], 'Auto start service from staff service log');
                            }
                            // Now In Progress -> Completed (Transition ID 102)
                            $wfEngine->advance((int)$instance['wfi_id'], $trans2Id, (int)$currentUser['usr_id'], 'Completed service: ' . $notes);
                            $msg = "Logged service action and updated Customer Request status to 'Completed' via Workflow transitions and transition action hooks.";
                        } else {
                            $msg = "Logged service action. (No active workflow found to transition).";
                        }
                    } else {
                        $msg = "Logged service action. (No active workflow found to transition).";
                    }
                } else {
                    $msg = "Logged service action. (No active workflow found to transition).";
                }
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }

        if ($action === 'advance_wf') {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true);
            $instanceId = (int)($payload['instance_id'] ?? 0);
            $transitionId = (int)($payload['transition_id'] ?? 0);

            if (!$instanceId || !$transitionId) {
                echo json_encode(['success' => false, 'error' => 'Instance ID and Transition ID are required.']);
                exit;
            }

            if (class_exists('WorkflowEngine')) {
                $wfEngine = new WorkflowEngine();
                $wfEngine->advance($instanceId, $transitionId, (int)$currentUser['usr_id'], 'Advanced manually via Developer Demo console.');
                echo json_encode(['success' => true, 'message' => 'Workflow advanced successfully!']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Workflow engine class not found.']);
            }
            exit;
        }

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Fetch lists to render
$serviceTypes = $db->fetchAll("SELECT * FROM demo_service_types ORDER BY name ASC");
$customerRequests = $db->fetchAll("SELECT r.*, t.name as service_type_name, t.price as service_price FROM demo_customer_requests r JOIN demo_service_types t ON t.service_type_id = r.service_type_id ORDER BY r.created_at DESC");
$staffServices = $db->fetchAll("SELECT s.*, r.customer_name FROM demo_staff_services s JOIN demo_customer_requests r ON r.request_id = s.customer_request_id ORDER BY s.service_date DESC");

// Fetch active workflow instances
$instances = [];
try {
    list($wfId, $startStageId, $trans1Id, $trans2Id) = demo_get_workflow_info($db);
    $instances = $db->fetchAll("SELECT i.*, w.wf_name, s.wfs_name as stage_name, s.wfs_color as stage_color, r.customer_name FROM nu_workflow_instances i JOIN nu_workflows w ON w.wf_id = i.wfi_wf_id JOIN nu_workflow_stages s ON s.wfs_id = i.wfi_stage_id JOIN demo_customer_requests r ON r.request_id = i.wfi_record_id WHERE i.wfi_wf_id = ? ORDER BY i.wfi_id DESC", [$wfId]);
} catch (Throwable $ignored) {}
?>

<div class="nu-card nu-demo-module p-6 bg-white rounded-xl border border-gray-200">
    <div class="flex justify-between items-start border-b border-gray-200 pb-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                📁 System Demo Files <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full border border-blue-200">globeadmin only</span>
            </h2>
            <p class="text-sm text-gray-500 mt-1">This demo application showcases relational SQL `LEFT JOIN` configurations and server-side table updates via custom 'PHP After Save' scripts and 'Workflow transition hooks'.</p>
        </div>
        <button onclick="DemoModule.resetDemo()" class="nu-btn nu-btn-danger text-sm font-semibold flex items-center gap-1">
            🔄 Reset Demo Database &amp; Workflows
        </button>
    </div>

    <!-- TABS -->
    <div class="flex border-b border-gray-200 mb-6 gap-2">
        <button id="tabBtnSandbox" onclick="DemoModule.switchTab('sandbox')" class="py-2 px-4 font-semibold text-sm border-b-2 border-blue-600 text-blue-600">
            ⚡ Relational Sandbox &amp; Live Inspector
        </button>
        <button id="tabBtnCode" onclick="DemoModule.switchTab('code')" class="py-2 px-4 font-semibold text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
            📖 Developer Explanations &amp; Code Walkthroughs
        </button>
    </div>

    <!-- TAB 1: SANDBOX -->
    <div id="panelSandbox" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Input Sandbox Forms (col-span-5) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Step 1 Form -->
            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 shadow-sm">
                <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2 mb-3">
                    <span class="bg-blue-600 text-white text-xs w-6 h-6 rounded-full flex items-center justify-center font-bold">1</span>
                    Create Customer Service Request
                </h3>
                <p class="text-xs text-gray-500 mb-4">Inserts a record into `demo_customer_requests` with status 'Pending' and automatically triggers a new 'Workflow Instance' in the background.</p>

                <form id="frmCreateRequest" onsubmit="DemoModule.createRequest(event)" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Customer Name</label>
                        <input type="text" id="cust_name" name="customer_name" class="nu-input text-sm w-100" placeholder="e.g. Alice Smith" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Requested Service Type (Lookup/Join)</label>
                        <select id="service_type" name="service_type_id" class="nu-input text-sm w-100" required>
                            <option value="">-- select service type --</option>
                            <?php foreach ($serviceTypes as $t): ?>
                                <option value="<?= htmlspecialchars($t['service_type_id']) ?>"><?= htmlspecialchars($t['name']) ?> ($<?= number_format((float)$t['price'], 2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Request Details</label>
                        <textarea id="req_details" name="request_details" rows="2" class="nu-input text-sm w-100" placeholder="Describe the issue..."></textarea>
                    </div>

                    <button type="submit" class="nu-btn nu-btn-primary w-full text-sm font-semibold">
                        ➕ Save &amp; Start Request Life Cycle
                    </button>
                </form>
            </div>

            <!-- Step 2 Form -->
            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 shadow-sm">
                <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2 mb-3">
                    <span class="bg-blue-600 text-white text-xs w-6 h-6 rounded-full flex items-center justify-center font-bold">2</span>
                    Provide Staff Service &amp; Resolve Status
                </h3>
                <p class="text-xs text-gray-500 mb-4">Logs staff notes inside the `demo_staff_services` table. Submitting this form will automatically update the target Customer Request's status to 'Completed'.</p>

                <form id="frmLogService" onsubmit="DemoModule.logService(event)" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Select Customer Request</label>
                        <select id="log_request_id" name="request_id" class="nu-input text-sm w-100" required>
                            <option value="">-- select pending request --</option>
                            <?php foreach ($customerRequests as $r): ?>
                                <?php if ($r['status'] !== 'Completed'): ?>
                                    <option value="<?= htmlspecialchars($r['request_id']) ?>"><?= htmlspecialchars($r['customer_name']) ?> (<?= htmlspecialchars($r['service_type_name']) ?> - <?= htmlspecialchars($r['status']) ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Update Execution Method</label>
                        <div class="flex gap-4 pt-1">
                            <label class="inline-flex items-center text-xs text-gray-700 cursor-pointer">
                                <input type="radio" name="update_method" value="php_after_save" checked class="mr-2">
                                <b>PHP After Save Script</b>
                            </label>
                            <label class="inline-flex items-center text-xs text-gray-700 cursor-pointer">
                                <input type="radio" name="update_method" value="workflow" class="mr-2">
                                <b>Workflow Transitions</b>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Staff Resolution / Service Notes</label>
                        <textarea id="log_notes" name="staff_notes" rows="2" class="nu-input text-sm w-100" placeholder="Explain what repairs were completed..." required></textarea>
                    </div>

                    <button type="submit" class="nu-btn nu-btn-primary w-full text-sm font-semibold">
                        🛠️ Submit Service &amp; Trigger Status Update
                    </button>
                </form>
            </div>
        </div>

        <!-- Live Database Inspector & Workflows (col-span-7) -->
        <div class="lg:col-span-7 space-y-6">
            <!-- Table A: Customer Requests (LEFT JOIN Display) -->
            <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                <div class="bg-gray-100 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-sm text-gray-800 uppercase tracking-wider">
                        📋 Customer Requests (`demo_customer_requests` JOIN `demo_service_types`)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 uppercase font-semibold">
                                <th class="p-3">Customer</th>
                                <th class="p-3">Service Type (Joined)</th>
                                <th class="p-3">Details</th>
                                <th class="p-3 text-center">Price</th>
                                <th class="p-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php foreach ($customerRequests as $r): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-semibold"><?= htmlspecialchars($r['customer_name']) ?></td>
                                    <td class="p-3 text-blue-600"><?= htmlspecialchars($r['service_type_name']) ?></td>
                                    <td class="p-3 max-w-xs truncate" title="<?= htmlspecialchars($r['request_details'] ?? '') ?>"><?= htmlspecialchars($r['request_details'] ?: '(none)') ?></td>
                                    <td class="p-3 text-center font-medium">$<?= number_format((float)$r['service_price'], 2) ?></td>
                                    <td class="p-3 text-center">
                                        <?php if ($r['status'] === 'Completed'): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded-full font-bold">Completed</span>
                                        <?php elseif ($r['status'] === 'In Progress'): ?>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full font-bold">In Progress</span>
                                        <?php else: ?>
                                            <span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full font-bold">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($customerRequests)): ?>
                                <tr><td colspan="5" class="p-6 text-center text-gray-400 italic">No customer requests loaded yet. Use the form above to add a request.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table B: Active Workflow Instances -->
            <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                <div class="bg-gray-100 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-sm text-gray-800 uppercase tracking-wider">
                        🔄 Active Workflows (`nu_workflow_instances`)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 uppercase font-semibold">
                                <th class="p-3">Customer</th>
                                <th class="p-3">Current Stage</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Interactive Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php foreach ($instances as $inst): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-semibold"><?= htmlspecialchars($inst['customer_name']) ?></td>
                                    <td class="p-3">
                                        <span class="inline-block px-2.5 py-0.5 rounded text-white font-bold text-[11px]" style="background: <?= htmlspecialchars($inst['stage_color']) ?>;">
                                            <?= htmlspecialchars($inst['stage_name']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="capitalize font-semibold text-gray-600"><?= htmlspecialchars($inst['wfi_status']) ?></span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <?php
                                        if (class_exists('WorkflowEngine') && $inst['wfi_status'] === 'active') {
                                            $wfEngine = new WorkflowEngine();
                                            $trans = $wfEngine->getAvailableTransitions((int)$inst['wfi_id']);
                                            foreach ($trans as $tr) {
                                                echo '<button onclick="DemoModule.advanceWorkflow(' . $inst['wfi_id'] . ', ' . $tr['wft_id'] . ')" class="nu-btn nu-btn-ghost text-[11px] py-1 px-2.5 ml-1 border border-gray-300 font-semibold rounded hover:bg-gray-100 hover:text-blue-600 transition">👉 ' . htmlspecialchars($tr['wft_label']) . '</button>';
                                            }
                                        } else {
                                            echo '<span class="text-gray-400 italic">No actions available</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($instances)): ?>
                                <tr><td colspan="4" class="p-6 text-center text-gray-400 italic">No active workflows tracking customer requests.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table C: Staff Services Log -->
            <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                <div class="bg-gray-100 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-sm text-gray-800 uppercase tracking-wider">
                        🛠️ Staff Services Log (`demo_staff_services`)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 uppercase font-semibold">
                                <th class="p-3">Customer</th>
                                <th class="p-3">Staff Notes &amp; Activity</th>
                                <th class="p-3">Service Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php foreach ($staffServices as $s): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-semibold"><?= htmlspecialchars($s['customer_name']) ?></td>
                                    <td class="p-3 text-gray-600"><?= htmlspecialchars($s['staff_notes']) ?></td>
                                    <td class="p-3 text-gray-500 font-medium"><?= date('Y-m-d H:i:s', strtotime($s['service_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($staffServices)): ?>
                                <tr><td colspan="3" class="p-6 text-center text-gray-400 italic">No service entries logged yet. Complete Step 2 to populate.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: CODE EXPLANATIONS -->
    <div id="panelCode" class="hidden space-y-6">
        <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded mb-6 text-blue-900 text-sm">
            <b>How this coordinates in Nuvis:</b> Low-code builders configure layouts in JSON, but developers use code blocks to tie systems together. Below are the actual implementations used in this demo.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- SQL JOIN Explanation -->
            <div class="bg-gray-900 text-gray-100 p-5 rounded-lg border border-gray-800 shadow-md">
                <h3 class="text-white font-bold text-base border-b border-gray-800 pb-2 mb-3">1. SQL JOIN Configuration</h3>
                <p class="text-xs text-gray-400 mb-4">To resolve ID lookups into display values, Nuvis supports field-level joins within the dynamic layout, appending joined descriptors natively to the select list.</p>
                <pre class="bg-black p-3 rounded text-[11px] font-mono overflow-x-auto text-green-400"><code>SELECT
    r.*,
    t.name AS service_type_id_display
FROM `demo_customer_requests` r
LEFT JOIN `demo_service_types` t
  ON t.service_type_id = r.service_type_id
ORDER BY r.created_at DESC;</code></pre>
                <div class="text-[11.5px] text-gray-300 mt-4 leading-relaxed">
                    <b>Nuvis Layout Configuration:</b> Inside the `demo_customer_requests` form layout, the `service_type_id` select column is configured with two properties that trigger this join automatically:
                    <ul class="list-disc pl-5 mt-2 space-y-1 text-gray-400 text-xs">
                        <li><code class="text-gray-200">join_sql</code>: <code class="text-gray-300">LEFT JOIN demo_service_types ON demo_service_types.service_type_id = demo_customer_requests.service_type_id</code></li>
                        <li><code class="text-gray-200">join_display_field</code>: <code class="text-gray-300">demo_service_types.name</code></li>
                    </ul>
                </div>
            </div>

            <!-- Custom PHP After Save -->
            <div class="bg-gray-900 text-gray-100 p-5 rounded-lg border border-gray-800 shadow-md">
                <h3 class="text-white font-bold text-base border-b border-gray-800 pb-2 mb-3">2. PHP After Save Script Block</h3>
                <p class="text-xs text-gray-400 mb-4">When a Staff Service log is saved, we automatically update the Customer Request record status using the post-save execution block.</p>
                <pre class="bg-black p-3 rounded text-[11px] font-mono overflow-x-auto text-blue-400"><code>&lt;?php
// Placed inside the "PHP After Save" setting
// of form: demo_staff_services

$db = NuDatabase::getInstance();

// Individual layout field names are pre-injected
// as local PHP variables (e.g. $customer_request_id)
$reqId = $customer_request_id;

if ($reqId) {
    // Perform cross-table update query
    $db->update(
        'demo_customer_requests',
        ['status' => 'Completed'],
        'request_id = ?',
        [$reqId]
    );
}
</code></pre>
                <div class="text-[11.5px] text-gray-300 mt-4 leading-relaxed">
                    <b>NuBuilder Forte Token Substitution:</b> Nuvis also supports token-based substitutions in the PHP script, e.g., using <code class="text-gray-200">#customer_request_id#</code> directly in queries, which are replaced inline with escaped strings during form submission processing.
                </div>
            </div>

            <!-- Workflow Transition Hooks -->
            <div class="bg-gray-900 text-gray-100 p-5 rounded-lg border border-gray-800 shadow-md md:col-span-2">
                <h3 class="text-white font-bold text-base border-b border-gray-800 pb-2 mb-3">3. No-Code Workflows &amp; Transition Action Hooks</h3>
                <p class="text-xs text-gray-400 mb-4">Instead of hardcoding status updates, developers can use the **Workflow Engine**. Transition action hooks handle updating linked record columns natively, allowing stages to govern fields without code.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs text-gray-300">
                    <div class="bg-black/40 p-3 rounded">
                        <b class="text-white text-sm block mb-1">Workflow Stages</b>
                        <p class="text-gray-400 leading-relaxed">The workflow has three defined stages representing the status of the customer request:</p>
                        <ul class="list-disc pl-4 mt-2 space-y-1 text-gray-400 text-[11.5px]">
                            <li><b>Pending</b> (Start, code: <code class="text-yellow-400">Pending</code>)</li>
                            <li><b>In Progress</b> (Active, code: <code class="text-blue-400">In Progress</code>)</li>
                            <li><b>Completed</b> (End, code: <code class="text-green-400">Completed</code>)</li>
                        </ul>
                    </div>

                    <div class="bg-black/40 p-3 rounded">
                        <b class="text-white text-sm block mb-1">Transition Configuration</b>
                        <p class="text-gray-400 leading-relaxed">Transitions define actions moving records between stages:</p>
                        <ul class="list-disc pl-4 mt-2 space-y-1 text-gray-400 text-[11.5px]">
                            <li><b>Start Providing Service</b>: Pending ➔ In Progress</li>
                            <li><b>Mark Service Completed</b>: In Progress ➔ Completed</li>
                        </ul>
                    </div>

                    <div class="bg-black/40 p-3 rounded">
                        <b class="text-white text-sm block mb-1">`update_record` Hook</b>
                        <p class="text-gray-400 leading-relaxed">Setting <code class="text-gray-200">wft_hook = 'update_record'</code> on transitions instructs the WorkflowEngine to execute:</p>
                        <pre class="bg-black/60 p-1.5 rounded text-[10px] text-pink-400 font-mono mt-1">UPDATE [linked_table]
SET `status` = [to_stage_code]
WHERE id = [record_id];</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var DemoModule = (function() {
    var API = 'modules/system_demo_files/system_demo_files.php';

    function switchTab(tabId) {
        var tabSandbox = document.getElementById('tabBtnSandbox');
        var tabCode = document.getElementById('tabBtnCode');
        var panelSandbox = document.getElementById('panelSandbox');
        var panelCode = document.getElementById('panelCode');

        if (tabId === 'sandbox') {
            tabSandbox.className = 'py-2 px-4 font-semibold text-sm border-b-2 border-blue-600 text-blue-600';
            tabCode.className = 'py-2 px-4 font-semibold text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300';
            panelSandbox.classList.remove('hidden');
            panelCode.classList.add('hidden');
        } else {
            tabCode.className = 'py-2 px-4 font-semibold text-sm border-b-2 border-blue-600 text-blue-600';
            tabSandbox.className = 'py-2 px-4 font-semibold text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300';
            panelCode.classList.remove('hidden');
            panelSandbox.classList.add('hidden');
        }
    }

    async function resetDemo() {
        if (!confirm('Are you sure you want to reset the demo tables, delete all custom customer requests and service entries, and restore initial seed data?')) return;
        try {
            var res = await fetch(API + '?ajax_action=reset_demo', { credentials: 'same-origin' });
            var json = await res.json();
            if (json.success) {
                NuApp.toast(json.message, 'success');
                NuApp.loadModule('system_demo_files');
            } else {
                NuApp.toast(json.error || 'Failed to reset', 'error');
            }
        } catch (e) {
            console.error(e);
            NuApp.toast('Network error during reset', 'error');
        }
    }

    async function createRequest(e) {
        e.preventDefault();
        var payload = {
            customer_name: document.getElementById('cust_name').value,
            service_type_id: document.getElementById('service_type').value,
            request_details: document.getElementById('req_details').value
        };

        try {
            var res = await fetch(API + '?ajax_action=create_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            var json = await res.json();
            if (json.success) {
                NuApp.toast('Customer Request and associated Workflow Instance created!', 'success');
                NuApp.loadModule('system_demo_files');
            } else {
                NuApp.toast(json.error || 'Failed to save', 'error');
            }
        } catch (e) {
            console.error(e);
            NuApp.toast('Network error during save', 'error');
        }
    }

    async function logService(e) {
        e.preventDefault();
        var methodNode = document.querySelector('input[name="update_method"]:checked');
        var payload = {
            request_id: document.getElementById('log_request_id').value,
            method: methodNode ? methodNode.value : 'php_after_save',
            staff_notes: document.getElementById('log_notes').value
        };

        try {
            var res = await fetch(API + '?ajax_action=log_service', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            var json = await res.json();
            if (json.success) {
                NuApp.toast(json.message, 'success');
                NuApp.loadModule('system_demo_files');
            } else {
                NuApp.toast(json.error || 'Failed to log service', 'error');
            }
        } catch (e) {
            console.error(e);
            NuApp.toast('Network error logging service', 'error');
        }
    }

    async function advanceWorkflow(instanceId, transitionId) {
        try {
            var res = await fetch(API + '?ajax_action=advance_wf', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ instance_id: instanceId, transition_id: transitionId }),
                credentials: 'same-origin'
            });
            var json = await res.json();
            if (json.success) {
                NuApp.toast(json.message, 'success');
                NuApp.loadModule('system_demo_files');
            } else {
                NuApp.toast(json.error || 'Failed to advance workflow', 'error');
            }
        } catch (e) {
            console.error(e);
            NuApp.toast('Network error advancing workflow', 'error');
        }
    }

    return {
        switchTab: switchTab,
        resetDemo: resetDemo,
        createRequest: createRequest,
        logService: logService,
        advanceWorkflow: advanceWorkflow
    };
})();
</script>
