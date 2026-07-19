<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../sessions/webhook_demo_logs.json';

// Handle incoming log viewing and clearing (authenticated admins only)
$action = $_GET['action'] ?? '';
if ($action !== '') {
    $auth = new NuAuth();
    if (!$auth->checkAuth()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    if ($action === 'list_logs') {
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
            echo json_encode(['success' => true, 'logs' => $logs]);
        } else {
            echo json_encode(['success' => true, 'logs' => []]);
        }
        exit;
    }

    if ($action === 'clear_logs') {
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        echo json_encode(['success' => true, 'message' => 'Logs cleared successfully']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown receiver action']);
    exit;
}

// ─── Capture incoming Webhook (unauthenticated target endpoint) ────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
$headers = getallheaders();
$rawBody = file_get_contents('php://input');

// Parse headers
$event = $headers['X-Nuvis-Event'] ?? $headers['x-nuvis-event'] ?? 'test_webhook';
$delivery = $headers['X-Nuvis-Delivery'] ?? $headers['x-nuvis-delivery'] ?? '';
$signature = $headers['X-Nuvis-Signature'] ?? $headers['x-nuvis-signature'] ?? '';

// Try to parse payload
$payload = null;
if (trim($rawBody) !== '') {
    $payload = json_decode($rawBody, true) ?: ['raw_text' => $rawBody];
} else {
    $payload = ['empty_payload' => true];
}

// Construct log item
$newLog = [
    'delivery_id' => $delivery ?: uniqid('dlv_', true),
    'event'       => $event,
    'method'      => $method,
    'headers'     => $headers,
    'payload'     => $payload,
    'received_at' => date('Y-m-d H:i:s')
];

// Read existing logs
$logs = [];
if (file_exists($logFile)) {
    $logs = json_decode(file_get_contents($logFile), true) ?: [];
}

// Prepend to list
array_unshift($logs, $newLog);

// Keep max 25 entries
if (count($logs) > 25) {
    $logs = array_slice($logs, 0, 25);
}

// Save back
file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));

// Return success response to the dispatching curl call
echo json_encode([
    'success' => true,
    'message' => 'Webhook received and logged by demo listener',
    'delivery_id' => $newLog['delivery_id']
]);
?>
