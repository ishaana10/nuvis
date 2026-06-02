<?php
/**
 * api/query.php
 * Execute / export / validate a saved query by code.
 * Actions: execute (default), export, validate
 */
require_once '../config.php';
require_once '../core/Database.php';
require_once '../core/Auth.php';
require_once '../core/QueryExecutor.php';

$auth = new NuAuth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'execute';
$code   = $_GET['code']   ?? '';

$params = [];
foreach ($_GET as $k => $v) {
    if (!in_array($k, ['action', 'code'], true)) {
        $params[$k] = $v;
    }
}

$executor = new NuQueryExecutor();

// ── EXPORT ────────────────────────────────────────────────────────────────
if ($action === 'export') {
    $result = $executor->exportCsv($code, $params);
    if (isset($result['error'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit;
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
    echo $result['csv'];
    exit;
}

// ── VALIDATE (EXPLAIN dry-run) ────────────────────────────────────────────
if ($action === 'validate') {
    header('Content-Type: application/json');
    if (!$code) { echo json_encode(['success' => false, 'error' => 'Missing code']); exit; }
    $db  = NuDatabase::getInstance();
    $row = $db->fetchOne('SELECT query_sql FROM nu_queries WHERE query_code = ?', [$code]);
    if (!$row) { echo json_encode(['success' => false, 'error' => 'Query not found']); exit; }
    try {
        $db->fetchAll('EXPLAIN ' . $row['query_sql']);
        echo json_encode(['success' => true, 'message' => 'SQL is valid']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── EXECUTE (default) ─────────────────────────────────────────────────────
header('Content-Type: application/json');
$result = $executor->execute($code, $params);
if (isset($result['error'])) {
    echo json_encode(['success' => false, 'error' => $result['error']]);
} else {
    echo json_encode(['success' => true, 'data' => $result]);
}
