<?php
declare(strict_types=1);

/**
 * api/agent.php - Agent Management, Builder, and Runtime REST Endpoints
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/AgentRuntime.php';

$db = NuDatabase::getInstance();
$auth = new NuAuth();

if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user = $auth->getCurrentUser();
$role = strtolower((string)($user['usr_role'] ?? ''));

if ($role !== 'globeadmin' && $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Access restricted to administrative roles']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $agents = $db->fetchAll("SELECT * FROM nu_agents ORDER BY agent_id DESC");
            foreach ($agents as &$a) {
                $a['agent_tools'] = !empty($a['agent_tools']) ? (is_array($a['agent_tools']) ? $a['agent_tools'] : (json_decode($a['agent_tools'], true) ?: [])) : [];
                $a['agent_tool_config'] = !empty($a['agent_tool_config']) ? (json_decode($a['agent_tool_config'], true) ?: []) : [];
            }
            echo json_encode(['success' => true, 'data' => $agents]);
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $agent = $db->fetchOne("SELECT * FROM nu_agents WHERE agent_id = ?", [(int)$id]);
            if ($agent) {
                $agent['agent_tools'] = !empty($agent['agent_tools']) ? (is_array($agent['agent_tools']) ? $agent['agent_tools'] : (json_decode($agent['agent_tools'], true) ?: [])) : [];
                $agent['agent_tool_config'] = !empty($agent['agent_tool_config']) ? (json_decode($agent['agent_tool_config'], true) ?: []) : [];
            }
            echo json_encode(['success' => true, 'data' => $agent]);
            break;

        case 'save':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = (int)($input['agent_id'] ?? 0);
            $code = trim($input['agent_code'] ?? 'agent_' . time());
            $name = trim($input['agent_name'] ?? 'New Agent');
            $prompt = $input['agent_system_prompt'] ?? '';
            $model = $input['agent_model'] ?? 'gemini-1.5-flash';
            $tools = is_array($input['agent_tools'] ?? null) ? json_encode($input['agent_tools']) : ($input['agent_tools'] ?? '[]');
            $memoryType = $input['agent_memory_type'] ?? 'conversation';
            $maxTokens = (int)($input['agent_max_tokens'] ?? 2000);
            $active = isset($input['agent_active']) ? (int)$input['agent_active'] : 1;

            $saveData = [
                'agent_code' => $code,
                'agent_name' => $name,
                'agent_system_prompt' => $prompt,
                'agent_model' => $model,
                'agent_tools' => $tools,
                'agent_memory_type' => $memoryType,
                'agent_max_tokens' => $maxTokens,
                'agent_active' => $active,
                'agent_updated_at' => date('Y-m-d H:i:s')
            ];

            if ($id > 0) {
                $db->update('nu_agents', $saveData, 'agent_id = ?', [$id]);
            } else {
                $saveData['agent_created_at'] = date('Y-m-d H:i:s');
                $id = $db->insert('nu_agents', $saveData);
            }

            // Check if API keys were provided in settings save
            if (isset($input['gemini_api_key'])) {
                $apiKey = trim($input['gemini_api_key']);
                $hasSetting = $db->fetchOne("SELECT setting_key FROM nu_system_settings WHERE setting_key = 'gemini_api_key'");
                if ($hasSetting) {
                    $db->update('nu_system_settings', ['setting_value' => $apiKey], "setting_key = 'gemini_api_key'");
                } else {
                    $db->insert('nu_system_settings', ['setting_key' => 'gemini_api_key', 'setting_value' => $apiKey]);
                }
            }

            if (isset($input['mem0_api_key'])) {
                $mem0Key = trim($input['mem0_api_key']);
                $hasMem0 = $db->fetchOne("SELECT setting_key FROM nu_system_settings WHERE setting_key = 'mem0_api_key'");
                if ($hasMem0) {
                    $db->update('nu_system_settings', ['setting_value' => $mem0Key], "setting_key = 'mem0_api_key'");
                } else {
                    $db->insert('nu_system_settings', ['setting_key' => 'mem0_api_key', 'setting_value' => $mem0Key]);
                }
            }

            echo json_encode(['success' => true, 'agent_id' => $id]);
            break;

        case 'delete':
            $id = (int)($_POST['agent_id'] ?? $_GET['id'] ?? 0);
            if ($id > 0) {
                $db->query("DELETE FROM nu_agents WHERE agent_id = ?", [$id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'run':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $agentId = $input['agent_id'] ?? $input['agent_code'] ?? 1;
            $userMessage = trim($input['message'] ?? $input['prompt'] ?? '');
            $context = $input['context'] ?? [];

            if (empty($userMessage)) {
                echo json_encode(['success' => false, 'error' => 'Message is required']);
                exit;
            }

            $runtime = new AgentRuntime($db, $auth);
            $result = $runtime->run($agentId, $userMessage, $context);

            echo json_encode($result);
            break;

        case 'runs':
            $agentId = (int)($_GET['agent_id'] ?? 0);
            if ($agentId > 0) {
                $runs = $db->fetchAll("SELECT * FROM nu_agent_runs WHERE agent_id = ? ORDER BY run_id DESC LIMIT 50", [$agentId]);
            } else {
                $runs = $db->fetchAll("SELECT r.*, a.agent_name FROM nu_agent_runs r LEFT JOIN nu_agents a ON a.agent_id = r.agent_id ORDER BY r.run_id DESC LIMIT 50");
            }
            echo json_encode(['success' => true, 'data' => $runs]);
            break;

        case 'run_details':
            $runId = (int)($_GET['run_id'] ?? 0);
            $run = $db->fetchOne("SELECT * FROM nu_agent_runs WHERE run_id = ?", [$runId]);
            $messages = $db->fetchAll("SELECT * FROM nu_agent_messages WHERE run_id = ? ORDER BY msg_id ASC", [$runId]);
            $toolCalls = $db->fetchAll("SELECT * FROM nu_agent_tool_calls WHERE run_id = ? ORDER BY tool_id ASC", [$runId]);

            echo json_encode([
                'success' => true,
                'run' => $run,
                'messages' => $messages,
                'tool_calls' => $toolCalls
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
