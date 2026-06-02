<?php
declare(strict_types=1);

// ─ Catch any stray output (PHP warnings/notices) before our JSON header
ob_start();

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Auth.php';

// Discard any whitespace/BOM that crept in before this point
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ─ Register a shutdown handler so a PHP fatal still returns valid JSON
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e['message']]);
    }
    ob_end_flush();
});

$auth = new NuAuth();
if (!$auth->checkAuth()) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db     = NuDatabase::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ─── READ BODY ─────────────────────────────────────────────────────────────
$body = [];
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    if (empty($body)) $body = $_POST;
}

try {
    switch ($action) {

        // ── list all reports ────────────────────────────────────────────────────
        case 'list':
            $rows = $db->fetchAll(
                "SELECT report_id, report_code, report_name, report_type,
                        report_view_mode, report_active,
                        COALESCE(report_updated_at, report_created_at) AS report_created_at
                 FROM nu_reports
                 ORDER BY report_name"
            );
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        // ── get single report ──────────────────────────────────────────────────
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing id');
            $row = $db->fetchOne(
                "SELECT * FROM nu_reports WHERE report_id = ?", [$id]
            );
            if (!$row) throw new Exception('Report not found');
            foreach (['report_columns','report_filters','report_settings'] as $col) {
                if (isset($row[$col]) && is_string($row[$col])) {
                    $row[$col] = json_decode($row[$col], true) ?? [];
                }
            }
            echo json_encode(['success' => true, 'data' => $row]);
            break;

        // ── preview — run raw SQL without saving (builder use only) ────────────
        case 'preview':
            $sql     = trim($body['report_sql'] ?? '');
            $columns = $body['report_columns'] ?? [];
            if (!$sql) throw new Exception('SQL is required');

            $firstWord = strtoupper(strtok(ltrim($sql), " \t\n\r"));
            if ($firstWord !== 'SELECT') throw new Exception('Only SELECT queries allowed');

            // Limit preview to 100 rows via subquery wrap
            $previewSql = "SELECT * FROM ({$sql}) AS _preview LIMIT 100";
            $rows       = $db->fetchAll($previewSql, []);

            if (empty($columns) && !empty($rows)) {
                foreach (array_keys($rows[0]) as $col) {
                    $columns[] = ['field' => $col, 'label' => ucwords(str_replace('_', ' ', $col))];
                }
            }
            echo json_encode(['success' => true, 'data' => $rows, 'columns' => $columns, 'total' => count($rows)]);
            break;

        // ── save ────────────────────────────────────────────────────────────────
        case 'save':
            $id       = (int)($body['report_id'] ?? 0);
            $name     = trim($body['report_name'] ?? '');
            $code     = trim($body['report_code'] ?? '');
            $type     = $body['report_type']      ?? 'table';
            $viewMode = $body['report_view_mode'] ?? 'table';
            $sql      = trim($body['report_sql']  ?? '');
            $columns  = $body['report_columns']   ?? [];
            $filters  = $body['report_filters']   ?? [];
            $settings = $body['report_settings']  ?? [];

            if (!$name) throw new Exception('Report name is required');
            if (!$sql)  throw new Exception('SQL query is required');

            // auto-generate code from name if blank
            if (!$code) {
                $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($name)));
                $code = trim($code, '_');
            }

            // SELECT-only guard
            $firstWord = strtoupper(strtok(ltrim($sql), " \t\n\r"));
            if ($firstWord !== 'SELECT') throw new Exception('Only SELECT queries are allowed');

            $colsJson     = json_encode(array_values($columns));
            $filtersJson  = json_encode(array_values($filters));
            $settingsJson = json_encode((object)$settings); // always object, never array
            $userId       = $_SESSION['user_id'] ?? null;

            if ($id) {
                $affected = $db->execute(
                    "UPDATE nu_reports SET
                        report_name=?, report_code=?, report_type=?, report_view_mode=?,
                        report_sql=?, report_columns=?, report_filters=?, report_settings=?,
                        report_updated_at=NOW()
                     WHERE report_id=?",
                    [$name, $code, $type, $viewMode, $sql, $colsJson, $filtersJson, $settingsJson, $id]
                );
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Report updated']);
            } else {
                $db->execute(
                    "INSERT INTO nu_reports
                        (report_name, report_code, report_type, report_view_mode,
                         report_sql, report_columns, report_filters, report_settings,
                         report_created_by, report_active)
                     VALUES (?,?,?,?,?,?,?,?,?,1)",
                    [$name, $code, $type, $viewMode, $sql, $colsJson, $filtersJson, $settingsJson, $userId]
                );
                $newId = $db->lastInsertId();
                echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Report created']);
            }
            break;

        // ── delete ─────────────────────────────────────────────────────────────
        case 'delete':
            $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing id');
            $db->execute("DELETE FROM nu_reports WHERE report_id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Report deleted']);
            break;

        // ── run saved report ────────────────────────────────────────────────────
        case 'run':
            $id   = (int)($_GET['id'] ?? 0);
            $code = trim($_GET['code'] ?? '');

            $filterParams = [];
            foreach ($_GET as $k => $v) {
                if (!in_array($k, ['action','id','code'], true)) {
                    $filterParams[$k] = $v;
                }
            }

            if ($id) {
                $report = $db->fetchOne("SELECT * FROM nu_reports WHERE report_id=?", [$id]);
            } elseif ($code) {
                $report = $db->fetchOne("SELECT * FROM nu_reports WHERE report_code=?", [$code]);
            } else {
                throw new Exception('id or code required');
            }
            if (!$report) throw new Exception('Report not found');

            $sql     = $report['report_sql'];
            $filters = json_decode($report['report_filters'] ?? '[]', true) ?: [];

            $whereParts = [];
            $bindings   = [];
            foreach ($filters as $f) {
                $field = $f['field'] ?? '';
                if ($field && isset($filterParams[$field]) && $filterParams[$field] !== '') {
                    $op = $f['operator'] ?? '=';
                    if ($op === 'LIKE') {
                        $whereParts[] = "`{$field}` LIKE ?";
                        $bindings[]   = '%' . $filterParams[$field] . '%';
                    } else {
                        $whereParts[] = "`{$field}` {$op} ?";
                        $bindings[]   = $filterParams[$field];
                    }
                }
            }

            if ($whereParts) {
                $sql = "SELECT * FROM ({$sql}) AS _rpt WHERE " . implode(' AND ', $whereParts);
            }

            $rows    = $db->fetchAll($sql, $bindings);
            $columns = json_decode($report['report_columns'] ?? '[]', true) ?: [];

            if (empty($columns) && !empty($rows)) {
                foreach (array_keys($rows[0]) as $col) {
                    $columns[] = ['field' => $col, 'label' => ucwords(str_replace('_', ' ', $col))];
                }
            }

            echo json_encode([
                'success'     => true,
                'data'        => $rows,
                'columns'     => $columns,
                'total'       => count($rows),
                'view_mode'   => $report['report_view_mode'] ?? 'table',
                'report_name' => $report['report_name'],
            ]);
            break;

        // ── list tables ────────────────────────────────────────────────────────────
        case 'tables':
            $tables = $db->fetchAll("SHOW TABLES");
            $flat   = array_map(fn($r) => array_values($r)[0], $tables);
            echo json_encode(['success' => true, 'data' => $flat]);
            break;

        // ── list columns of a table ───────────────────────────────────────────────
        case 'columns':
            $table = preg_replace('/[^a-z0-9_]/i', '', $_GET['table'] ?? '');
            if (!$table) throw new Exception('table required');
            $cols = $db->fetchAll("SHOW COLUMNS FROM `{$table}`");
            echo json_encode(['success' => true, 'data' => $cols]);
            break;

        default:
            throw new Exception('Unknown action: ' . htmlspecialchars($action));
    }

} catch (Throwable $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
