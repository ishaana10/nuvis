<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new NuAuth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currUser = $auth->getCurrentUser();
$role = strtolower($currUser['usr_role'] ?? '');
if ($role !== 'globeadmin' && $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: Admin only']);
    exit;
}

$db = NuDatabase::getInstance();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        try {
            $endpoints = $db->fetchAll("SELECT * FROM nu_api_endpoints ORDER BY endpoint_created_at DESC");
            echo json_encode(['success' => true, 'endpoints' => $endpoints]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save':
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = $input['endpoint_id'] ?? null;

            $route = trim((string)($input['endpoint_route'] ?? ''));
            $route = '/' . ltrim($route, '/'); // normalize leading slash

            $data = [
                'endpoint_name'   => trim((string)($input['endpoint_name'] ?? '')),
                'endpoint_route'  => $route,
                'endpoint_method' => strtoupper(trim((string)($input['endpoint_method'] ?? 'GET'))),
                'endpoint_type'   => trim((string)($input['endpoint_type'] ?? 'form')),
                'endpoint_target' => trim((string)($input['endpoint_target'] ?? '')),
                'endpoint_config' => $input['endpoint_config'] ?? null,
                'endpoint_active' => (int)($input['endpoint_active'] ?? 1)
            ];

            if ($data['endpoint_name'] === '' || $data['endpoint_route'] === '' || $data['endpoint_target'] === '') {
                echo json_encode(['success' => false, 'error' => 'Name, Route, and Target are required fields']);
                exit;
            }

            // Check duplicate route
            $dupSql = "SELECT endpoint_id FROM nu_api_endpoints WHERE endpoint_route = :route";
            $dupParams = [':route' => $data['endpoint_route']];
            if ($id) {
                $dupSql .= " AND endpoint_id != :id";
                $dupParams[':id'] = (int)$id;
            }
            $duplicate = $db->fetchOne($dupSql, $dupParams);
            if ($duplicate) {
                echo json_encode(['success' => false, 'error' => "An API route '{$data['endpoint_route']}' is already defined."]);
                exit;
            }

            if ($id) {
                $db->update('nu_api_endpoints', $data, 'endpoint_id = :id', [':id' => (int)$id]);
                echo json_encode(['success' => true, 'message' => 'API endpoint updated successfully']);
            } else {
                $newId = $db->insert('nu_api_endpoints', $data);
                echo json_encode(['success' => true, 'message' => 'API endpoint created successfully', 'endpoint_id' => $newId]);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete':
        try {
            $id = $_GET['id'] ?? 0;
            $db->delete('nu_api_endpoints', 'endpoint_id = :id', [':id' => (int)$id]);
            echo json_encode(['success' => true, 'message' => 'API endpoint deleted successfully']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to delete: ' . $e->getMessage()]);
        }
        break;

    case 'logs':
        try {
            $logs = $db->fetchAll("SELECT * FROM nu_api_logs ORDER BY log_created_at DESC LIMIT 100");
            echo json_encode(['success' => true, 'logs' => $logs]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'clear_logs':
        try {
            $db->exec("TRUNCATE TABLE nu_api_logs");
            echo json_encode(['success' => true, 'message' => 'API execution logs cleared successfully']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'targets':
        try {
            // Fetch Forms
            $forms = $db->fetchAll("SELECT form_code as code, form_name as name FROM nu_forms WHERE form_active = 1 ORDER BY form_name");

            // Fetch Reports
            $reports = $db->fetchAll("SELECT report_code as code, report_name as name FROM nu_reports WHERE report_active = 1 ORDER BY report_name");

            // Fetch Widgets (both specific widgets and role defaults)
            $widgets = $db->fetchAll(
                "SELECT DISTINCT widget_role as code, CONCAT('Role default: ', widget_role) as name
                 FROM nu_dashboard_widgets
                 WHERE widget_role IS NOT NULL AND widget_role != ''
                 UNION
                 SELECT CAST(widget_id as CHAR) as code, widget_title as name
                 FROM nu_dashboard_widgets
                 WHERE widget_active = 1"
            );

            echo json_encode([
                'success' => true,
                'forms'   => $forms,
                'reports' => $reports,
                'widgets' => $widgets
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown API action']);
}
?>