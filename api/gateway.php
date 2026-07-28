<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set up shutdown handler for fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal Server Error: ' . ($error['message'] ?? 'Unknown Error')
        ]);
        exit;
    }
});

$startTime = microtime(true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/_form_layout_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$db = NuDatabase::getInstance();

// ── 1. Authenticate Request ─────────────────────────────────────────────────
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

if (!$apiKey) {
    // Check Authorization Header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }
    if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
    }
}

if (!$apiKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Token required']);
    exit;
}

// Find token
$token = $db->fetchOne(
    "SELECT t.*, u.usr_username, u.usr_role FROM nu_api_tokens t
     JOIN nu_users u ON t.token_user_id = u.usr_id
     WHERE t.token_key = :key AND t.token_active = 1 AND (t.token_expires_at IS NULL OR t.token_expires_at > NOW())",
    [':key' => $apiKey]
);

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired API token']);
    exit;
}

// Establish User Context Session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['nu_user_id'] = $token['token_user_id'];
$_SESSION['usr_role']   = $token['usr_role'];
$_SESSION['username']   = $token['usr_username'];

// ── 2. Route Resolution ─────────────────────────────────────────────────────
$requestRoute = $_GET['route'] ?? null;
if (!$requestRoute && isset($_SERVER['PATH_INFO'])) {
    $requestRoute = $_SERVER['PATH_INFO'];
}
if (!$requestRoute && isset($_SERVER['REQUEST_URI'])) {
    $basePath = $_SERVER['SCRIPT_NAME']; // /api/gateway.php
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($requestUri, $basePath) === 0) {
        $requestRoute = substr($requestUri, strlen($basePath));
    }
}

$requestRoute = '/' . ltrim((string)$requestRoute, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Search for matching route
$endpoint = $db->fetchOne(
    "SELECT * FROM nu_api_endpoints WHERE endpoint_route = :r AND endpoint_active = 1 LIMIT 1",
    [':r' => $requestRoute]
);

$extractedId = $_GET['id'] ?? null;

// Supporting REST-style dynamic target routes e.g. /v1/customers/123
if (!$endpoint) {
    if (preg_match('/^(.+)\/([^\/]+)$/', $requestRoute, $matches)) {
        $potentialRoute = $matches[1];
        $potentialId = $matches[2];
        $endpoint = $db->fetchOne(
            "SELECT * FROM nu_api_endpoints WHERE endpoint_route = :r AND endpoint_active = 1 LIMIT 1",
            [':r' => $potentialRoute]
        );
        if ($endpoint) {
            $requestRoute = $potentialRoute;
            $extractedId = $potentialId;
        }
    }
}

if (!$endpoint) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'API Route not found']);
    exit;
}

// Verify HTTP Method
$allowedMethod = strtoupper($endpoint['endpoint_method'] ?? 'GET');
if ($allowedMethod !== 'ALL' && $allowedMethod !== $method) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => "Method {$method} not allowed on this endpoint. Expected: {$allowedMethod}"]);
    exit;
}

// Parse request payload
$payload = [];
if ($method === 'POST' || $method === 'PUT') {
    $rawPayload = file_get_contents('php://input');
    $payload = json_decode($rawPayload, true) ?: [];
    if (empty($payload)) {
        $payload = $_POST;
    }
} else {
    $payload = $_GET;
}

$responseCode = 200;
$responsePayload = [];

try {
    switch ($endpoint['endpoint_type']) {
        case 'form':
            $responsePayload = handleFormEndpoint($endpoint, $method, $extractedId, $payload, $token);
            break;

        case 'report':
            $responsePayload = handleReportEndpoint($endpoint, $payload);
            break;

        case 'dashboard':
            $responsePayload = handleDashboardEndpoint($endpoint);
            break;

        case 'custom':
            $responsePayload = handleCustomEndpoint($endpoint, $payload, $token);
            break;

        default:
            throw new Exception("Unknown endpoint type: " . $endpoint['endpoint_type']);
    }
} catch (Throwable $e) {
    $responseCode = 400;
    $responsePayload = ['success' => false, 'error' => $e->getMessage()];
}

// Calculate Duration
$duration = (microtime(true) - $startTime) * 1000;

// Log API Execution
try {
    $db->insert('nu_api_logs', [
        'log_route'            => $requestRoute . ($extractedId ? '/' . $extractedId : ''),
        'log_method'           => $method,
        'log_token_name'       => $token['token_name'],
        'log_user_id'          => $token['token_user_id'],
        'log_request_payload'  => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'log_response_code'    => $responseCode,
        'log_response_payload' => json_encode($responsePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'log_duration'         => $duration
    ]);
} catch (Throwable $ignore) {
    error_log('[API Log Error] ' . $ignore->getMessage());
}

// Return output
http_response_code($responseCode);
echo json_encode($responsePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;

// ── FORM RESOLVER ───────────────────────────────────────────────────────────
function handleFormEndpoint($endpoint, $method, $id, $payload, $token) {
    global $db;
    $formCode = $endpoint['endpoint_target'];

    // Load form schema
    $form = $db->fetchOne("SELECT * FROM nu_forms WHERE form_code = ? LIMIT 1", [$formCode]);
    if (!$form) {
        throw new Exception("Form schema not found for code: {$formCode}");
    }

    $table  = $form['form_table'] ?? '';
    if ($table === '') {
        throw new Exception("Form has no target database table configured");
    }

    $layout = json_decode($form['form_layout'] ?? '[]', true) ?: [];
    $fields = nu_flatten_layout($layout);
    $pk = getTablePrimaryKey($table);

    switch ($method) {
        case 'GET':
            if ($id !== null && $id !== '') {
                $record = $db->fetchOne("SELECT * FROM `{$table}` WHERE `{$pk}` = :id LIMIT 1", [':id' => $id]);
                if (!$record) {
                    throw new Exception("Record with ID {$id} not found", 404);
                }
                // Add lookup labels for user friendliness
                foreach ($fields as $field) {
                    if (($field['type'] ?? '') !== 'lookup') continue;
                    $fname = $field['name'] ?? $field['fieldname'] ?? '';
                    if ($fname === '' || !isset($record[$fname])) continue;
                    $record[$fname . '_display'] = renderLookupDisplayLocal($field, $record[$fname]);
                }
                return ['success' => true, 'data' => $record];
            } else {
                // Paginated List
                $page = max(1, (int)($payload['page'] ?? 1));
                $perPage = max(1, min(100, (int)($payload['per_page'] ?? 20)));
                $offset = ($page - 1) * $perPage;

                $where = ['1=1'];
                $params = [];

                // Simple search query matching
                $q = trim((string)($payload['q'] ?? ''));
                if ($q !== '') {
                    $likes = [];
                    foreach ($fields as $field) {
                        $fname = $field['name'] ?? $field['fieldname'] ?? '';
                        if ($fname !== '') {
                            $likes[] = "`{$fname}` LIKE :q";
                        }
                    }
                    if ($likes) {
                        $where[] = '(' . implode(' OR ', $likes) . ')';
                        $params[':q'] = "%{$q}%";
                    }
                }

                $whereSql = implode(' AND ', $where);
                $records = $db->fetchAll(
                    "SELECT * FROM `{$table}` WHERE {$whereSql} ORDER BY `{$pk}` DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );

                $total = (int)$db->fetchOne("SELECT COUNT(*) as total FROM `{$table}` WHERE {$whereSql}", $params)['total'];

                // Add lookup labels
                foreach ($records as &$row) {
                    foreach ($fields as $field) {
                        if (($field['type'] ?? '') !== 'lookup') continue;
                        $fname = $field['name'] ?? $field['fieldname'] ?? '';
                        if ($fname === '' || !isset($row[$fname])) continue;
                        $row[$fname . '_display'] = renderLookupDisplayLocal($field, $row[$fname]);
                    }
                }
                unset($row);

                return [
                    'success'  => true,
                    'records'  => $records,
                    'total'    => $total,
                    'page'     => $page,
                    'per_page' => $perPage,
                    'pages'    => ceil($total / $perPage)
                ];
            }

        case 'POST':
        case 'PUT':
            $save = [];
            // Handle validation / mapping
            foreach ($fields as $field) {
                $fname = $field['name'] ?? $field['fieldname'] ?? '';
                if ($fname === '') continue;

                $type = $field['type'] ?? 'text';
                if (in_array($type, ['html', 'heading', 'divider', 'fieldset', 'subform', 'button'], true)) continue;

                // Check required validation
                if (!empty($field['required']) && !isset($payload[$fname]) && $method === 'POST') {
                    throw new Exception("Field '{$fname}' is required.");
                }

                if (isset($payload[$fname])) {
                    $save[$fname] = $payload[$fname];
                }
            }

            // Clean data according to table columns
            $cols = getTableColumnsLocal($table);
            $cleanSave = [];
            foreach ($save as $col => $val) {
                if (isset($cols[$col])) {
                    $cleanSave[$col] = $val;
                }
            }

            // System columns populate
            $now = date('Y-m-d H:i:s');
            $actor = $token['token_user_id'];

            if ($method === 'POST') {
                if (isset($cols['created_at'])) $cleanSave['created_at'] = $now;
                if (isset($cols['updated_at'])) $cleanSave['updated_at'] = $now;
                if (isset($cols['created_by'])) $cleanSave['created_by'] = $actor;
                if (isset($cols['updated_by'])) $cleanSave['updated_by'] = $actor;
                if (isset($cols['user_id']))    $cleanSave['user_id']    = $actor;

                $newId = $db->insert($table, $cleanSave);

                // Start workflow if configured
                try {
                    if (!class_exists('WorkflowEngine')) {
                        require_once __DIR__ . '/../core/Workflow.php';
                    }
                    $wfBound = $db->fetchOne('SELECT wf_id FROM nu_workflows WHERE LOWER(wf_form_code) = LOWER(:fcode) AND wf_active = 1 LIMIT 1', [':fcode' => $formCode]);
                    if ($wfBound) {
                        $wfEngine = new WorkflowEngine();
                        $wfEngine->start((int)$wfBound['wf_id'], (int)$actor, $table, (string)$newId);
                    }
                } catch (Throwable $ignore) {}

                // Trigger Outgoing Webhooks
                try {
                    require_once __DIR__ . '/../core/WebhookSender.php';
                    NuWebhookSender::trigger('form_insert', [
                        'table'     => $table,
                        'record_id' => $newId,
                        'data'      => array_merge($cleanSave, ['id' => $newId])
                    ]);
                } catch (Throwable $ignore) {}

                // Audit log
                logAuditLocal('api_create', $table, (string)$newId, null, $cleanSave);

                return ['success' => true, 'id' => $newId, 'message' => 'Record created successfully'];
            } else {
                if ($id === null || $id === '') {
                    throw new Exception("Record ID required for PUT update");
                }
                $old = $db->fetchOne("SELECT * FROM `{$table}` WHERE `{$pk}` = :id LIMIT 1", [':id' => $id]);
                if (!$old) {
                    throw new Exception("Record to update not found");
                }

                if (isset($cols['updated_at'])) $cleanSave['updated_at'] = $now;
                if (isset($cols['updated_by'])) $cleanSave['updated_by'] = $actor;

                $db->update($table, $cleanSave, "{$pk} = :id", [':id' => $id]);

                // Trigger Outgoing Webhooks
                try {
                    require_once __DIR__ . '/../core/WebhookSender.php';
                    NuWebhookSender::trigger('form_update', [
                        'table'     => $table,
                        'record_id' => $id,
                        'data'      => array_merge($cleanSave, ['id' => $id])
                    ]);
                } catch (Throwable $ignore) {}

                // Audit log
                logAuditLocal('api_update', $table, (string)$id, $old, $cleanSave);

                return ['success' => true, 'id' => $id, 'message' => 'Record updated successfully'];
            }

        case 'DELETE':
            if ($id === null || $id === '') {
                throw new Exception("Record ID required for deletion");
            }
            $old = $db->fetchOne("SELECT * FROM `{$table}` WHERE `{$pk}` = :id LIMIT 1", [':id' => $id]);
            if (!$old) {
                throw new Exception("Record to delete not found");
            }

            $db->delete($table, "{$pk} = :id", [':id' => $id]);

            // Trigger Outgoing Webhooks
            try {
                require_once __DIR__ . '/../core/WebhookSender.php';
                NuWebhookSender::trigger('form_delete', [
                    'table'     => $table,
                    'record_id' => $id,
                    'data'      => ['id' => $id]
                ]);
            } catch (Throwable $ignore) {}

            // Audit log
            logAuditLocal('api_delete', $table, (string)$id, $old, null);

            return ['success' => true, 'id' => $id, 'message' => 'Record deleted successfully'];
    }
}

// ── REPORT RESOLVER ─────────────────────────────────────────────────────────
function handleReportEndpoint($endpoint, $payload) {
    global $db;
    $reportCode = $endpoint['endpoint_target'];

    $report = $db->fetchOne("SELECT * FROM nu_reports WHERE report_code = :code OR report_id = :code LIMIT 1", [':code' => $reportCode]);
    if (!$report) {
        throw new Exception("Report code/id {$reportCode} not found");
    }

    $sql     = $report['report_sql'];
    $filters = json_decode($report['report_filters'] ?? '[]', true) ?: [];

    $whereParts = [];
    $bindings   = [];
    foreach ($filters as $f) {
        $field = $f['field'] ?? '';
        if ($field && isset($payload[$field]) && $payload[$field] !== '') {
            $op = $f['operator'] ?? '=';
            if ($op === 'LIKE') {
                $whereParts[] = "`{$field}` LIKE ?";
                $bindings[]   = '%' . $payload[$field] . '%';
            } else {
                $whereParts[] = "`{$field}` {$op} ?";
                $bindings[]   = $payload[$field];
            }
        }
    }

    if ($whereParts) {
        $sql = "SELECT * FROM ({$sql}) AS _rpt WHERE " . implode(' AND ', $whereParts);
    }

    $rows = $db->fetchAll($sql, $bindings);

    // If PDF export requested
    if (isset($payload['export']) && strtolower((string)$payload['export']) === 'pdf') {
        require_once __DIR__ . '/../core/PdfGenerator.php';
        $pdfSettings = json_decode($report['report_pdf_settings'] ?? '{}', true) ?: [];
        $pdfContent = NuPdfGenerator::generate($report, $rows, $pdfSettings);

        ob_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . ($report['report_code'] ?: 'report') . '.pdf"');
        echo $pdfContent;
        exit;
    }

    $columns = json_decode($report['report_columns'] ?? '[]', true) ?: [];
    if (empty($columns) && !empty($rows)) {
        foreach (array_keys($rows[0]) as $col) {
            $columns[] = ['field' => $col, 'label' => ucwords(str_replace('_', ' ', $col))];
        }
    }

    return [
        'success'     => true,
        'report_name' => $report['report_name'],
        'columns'     => $columns,
        'data'        => $rows,
        'total'       => count($rows)
    ];
}

// ── DASHBOARD RESOLVER ───────────────────────────────────────────────────────
function handleDashboardEndpoint($endpoint) {
    global $db;
    $target = $endpoint['endpoint_target']; // widget code/id or role

    // Check if targeting a specific widget first
    $widgets = $db->fetchAll(
        "SELECT * FROM nu_dashboard_widgets WHERE widget_id = :t OR widget_role = :t OR widget_title = :t",
        [':t' => $target]
    );

    if (empty($widgets)) {
        // Fallback: all active default widgets
        $widgets = $db->fetchAll("SELECT * FROM nu_dashboard_widgets WHERE widget_active = 1 AND widget_user_id IS NULL");
    }

    $data = [];
    foreach ($widgets as $w) {
        $cfg = json_decode($w['widget_config'] ?? '{}', true) ?: [];
        $sql = trim($cfg['sql'] ?? '');
        $widgetData = [];

        if ($sql !== '') {
            try {
                // Strip user_id template dynamic tags safely
                $sqlClean = str_replace('{{user_id}}', '1', $sql);
                if (preg_match('/^\s*SELECT\b/i', $sqlClean)) {
                    $widgetData = $db->fetchAll($sqlClean) ?: [];
                }
            } catch (Throwable $e) {
                $widgetData = ['error' => $e->getMessage()];
            }
        }

        $data[] = [
            'widget_id'    => $w['widget_id'],
            'widget_title' => $w['widget_title'],
            'widget_type'  => $w['widget_type'],
            'config'       => $cfg,
            'metrics'      => $widgetData
        ];
    }

    return [
        'success' => true,
        'widgets' => $data
    ];
}

// ── CUSTOM RESOLVER ─────────────────────────────────────────────────────────
function handleCustomEndpoint($endpoint, $payload, $token) {
    global $db;
    $config = json_decode($endpoint['endpoint_config'] ?? '{}', true) ?: [];
    $script = trim($config['php_script'] ?? '');

    if ($script === '') {
        // Default custom: raw custom SQL executor
        $sql = trim($config['sql_query'] ?? '');
        if ($sql === '') {
            return ['success' => true, 'message' => 'Endpoint has no PHP script or SQL query defined.'];
        }

        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            throw new Exception("Only SELECT queries are allowed for direct custom SQL endpoints");
        }

        // bind placeholders from payload
        $bindings = [];
        preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $placeholder) {
                $bindings[':' . $placeholder] = $payload[$placeholder] ?? null;
            }
        }

        $rows = $db->fetchAll($sql, $bindings);
        return [
            'success' => true,
            'data'    => $rows,
            'total'   => count($rows)
        ];
    }

    // Exec custom PHP sandbox
    $response = ['success' => true];
    try {
        $request = [
            'method'  => $_SERVER['REQUEST_METHOD'],
            'payload' => $payload,
            'headers' => function_exists('getallheaders') ? getallheaders() : []
        ];
        $user = $token;

        // Eval returns or populates $response
        eval($script);
    } catch (Throwable $e) {
        throw new Exception("Custom script execution error: " . $e->getMessage());
    }

    return $response;
}

// ── DATABASE HELPERS ────────────────────────────────────────────────────────
function getTablePrimaryKey($table) {
    global $db;
    try {
        $row = $db->fetchOne("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        return ($row && !empty($row['Column_name'])) ? $row['Column_name'] : 'id';
    } catch (Throwable $e) {
        return 'id';
    }
}

function getTableColumnsLocal($table) {
    global $db;
    try {
        $rows = $db->fetchAll("DESCRIBE `{$table}`");
        $cols = [];
        foreach ($rows as $row) {
            $cols[$row['Field']] = true;
        }
        return $cols;
    } catch (Throwable $e) {
        return [];
    }
}

function renderLookupDisplayLocal($field, $value) {
    global $db;
    $lookup     = $field['lookup'] ?? [];
    $table      = $lookup['table'] ?? '';
    $idCol      = $lookup['id_column'] ?? $lookup['idcolumn'] ?? 'id';
    $displayCol = $lookup['display_column'] ?? $lookup['displaycolumn'] ?? '';

    if ($table === '' || $idCol === '' || $displayCol === '' || $value === '' || $value === null) {
        return (string)($value ?? '');
    }
    try {
        $row = $db->fetchOne("SELECT `{$displayCol}` FROM `{$table}` WHERE `{$idCol}` = :id LIMIT 1", [':id' => $value]);
        return (string)($row[$displayCol] ?? $value);
    } catch (Throwable $e) {
        return (string)$value;
    }
}

function logAuditLocal($action, $table, $recordId, $old = null, $new = null) {
    global $nuConfig;
    if (empty($nuConfig['enableAuditTrail'])) return;
    try {
        require_once __DIR__ . '/../core/Audit.php';
        $audit = new NuAudit();
        $audit->log($action, $table, $recordId, $old, $new);
    } catch (Throwable $ignore) {}
}
?>