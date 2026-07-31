<?php
/**
 * api/barcode.php
 * REST API endpoint for Barcode and Product/Good/Service operations:
 *   GET   action=list            - List all items (with optional type filtering)
 *   GET   action=get             - Retrieve details of a specific item by database id
 *   GET   action=lookup          - Retrieve details of a specific item by scanned barcode
 *   POST  action=save            - Create or update an item (including generating barcode if blank)
 *   POST  action=delete          - Delete an item by database id
 *   GET   action=generate_code   - Generate a valid, unique random barcode number
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new NuAuth();
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

$db     = NuDatabase::getInstance();

// Self-healing: Ensure nu_products table exists and has seed data
try {
    $db->query("SELECT 1 FROM `nu_products` LIMIT 1");
} catch (\Throwable $t) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `nu_products` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `type` VARCHAR(50) NOT NULL DEFAULT 'product',
                `barcode` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT DEFAULT NULL,
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_product_barcode` (`barcode`),
                INDEX `idx_product_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Seed initial products, goods, and services
        $db->exec("
            INSERT IGNORE INTO `nu_products` (`name`, `type`, `barcode`, `description`, `price`) VALUES
            ('Organic Coffee Beans (Dark Roast)', 'product', '7501031311309', 'Premium single-origin organic arabica coffee beans, slow-roasted to a rich dark finish. 500g bag.', 18.99),
            ('Ergonomic Wireless Mouse', 'product', '074182285197', 'Rechargeable wireless ergonomic mouse with silent clicking, side-scroll wheel, and adjustable DPI settings up to 4000.', 45.50),
            ('Eco-Friendly Bamboo Water Bottle', 'product', '8886367301031', 'Double-walled vacuum insulated water bottle made from stainless steel and covered with natural, sustainable bamboo. 750ml.', 24.99),
            ('Heavy Duty Industrial Pallet', 'good', 'GD-WRH-PAL-02', 'High-density polyethylene structural foam plastic pallet. Ideal for warehouse storage and forklift transport. Rated up to 1500kg.', 79.95),
            ('Ultra-Soft Microfiber Towel Set', 'good', 'GD-TOWEL-S4', 'Pack of 4 quick-drying, highly absorbent premium microfiber towels. Perfect for home, gym, or automotive detailing.', 15.49),
            ('Web Application Development Consultation', 'service', 'SVC-WEB-DEV-01', 'One-hour professional architectural and consulting session with a Senior Software Engineer regarding web app stacks and cloud hosting.', 120.00),
            ('Enterprise IT Security Audit', 'service', 'SVC-IT-SEC-05', 'Comprehensive end-to-end network penetration testing, software vulnerability scan, and infrastructure compliance audit report.', 1500.00);
        ");
    } catch (\Throwable $t2) {
        // Fail silently
    }
}

$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    if (empty($input)) $input = $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';

// Helper function to generate a unique barcode
function generateUniqueBarcode($db, $type) {
    $prefix = '750'; // standard prefix
    if ($type === 'good') {
        $prefix = '400';
    } elseif ($type === 'service') {
        $prefix = '900';
    }

    $attempts = 0;
    while ($attempts < 100) {
        $attempts++;
        // Generate a 12-digit numeric code
        $code = $prefix . sprintf('%09d', mt_rand(100000000, 999999999));

        // Compute EAN check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$code[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        $fullCode = $code . $checkDigit;

        // Check if barcode already exists
        $exists = $db->fetchOne("SELECT id FROM nu_products WHERE barcode = ?", [$fullCode]);
        if (!$exists) {
            return $fullCode;
        }
    }
    // Alphanumeric fallback if we somehow cannot generate a numeric EAN-13
    return strtoupper(uniqid($type === 'service' ? 'SVC-' : ($type === 'good' ? 'GD-' : 'PRD-')));
}

try {
    switch ($action) {

        // ------------------------------------------------------------------
        case 'list':
            $type = $_GET['type'] ?? '';
            $sql  = "SELECT * FROM nu_products";
            $params = [];
            if (in_array($type, ['product', 'good', 'service'], true)) {
                $sql .= " WHERE type = ?";
                $params[] = $type;
            }
            $sql .= " ORDER BY id DESC";
            $items = $db->fetchAll($sql, $params);
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        // ------------------------------------------------------------------
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                throw new \InvalidArgumentException('Item ID is required.');
            }
            $item = $db->fetchOne("SELECT * FROM nu_products WHERE id = ?", [$id]);
            if (!$item) {
                echo json_encode(['success' => false, 'message' => 'Item not found.']);
            } else {
                echo json_encode(['success' => true, 'data' => $item]);
            }
            break;

        // ------------------------------------------------------------------
        case 'lookup':
            $barcode = trim((string)($_GET['barcode'] ?? ''));
            if ($barcode === '') {
                throw new \InvalidArgumentException('Barcode is required for lookup.');
            }
            $item = $db->fetchOne("SELECT * FROM nu_products WHERE barcode = ?", [$barcode]);
            if (!$item) {
                echo json_encode(['success' => false, 'message' => 'No item found with barcode: ' . htmlspecialchars($barcode)]);
            } else {
                echo json_encode(['success' => true, 'data' => $item]);
            }
            break;

        // ------------------------------------------------------------------
        case 'save':
            $id          = (int)($input['id'] ?? 0);
            $name        = trim((string)($input['name'] ?? ''));
            $type        = trim((string)($input['type'] ?? 'product'));
            $barcode     = trim((string)($input['barcode'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $price       = (float)($input['price'] ?? 0.00);

            if ($name === '') {
                throw new \InvalidArgumentException('Item name is required.');
            }
            if (!in_array($type, ['product', 'good', 'service'], true)) {
                throw new \InvalidArgumentException('Invalid item type.');
            }

            // Auto-generate barcode if left blank
            if ($barcode === '') {
                $barcode = generateUniqueBarcode($db, $type);
            } else {
                // Ensure barcode is unique (except when updating the same item)
                $dupCheckSql = "SELECT id FROM nu_products WHERE barcode = ?";
                $dupCheckParams = [$barcode];
                if ($id > 0) {
                    $dupCheckSql .= " AND id != ?";
                    $dupCheckParams[] = $id;
                }
                $dup = $db->fetchOne($dupCheckSql, $dupCheckParams);
                if ($dup) {
                    throw new \RuntimeException('The barcode "' . htmlspecialchars($barcode) . '" is already assigned to another item.');
                }
            }

            if ($id > 0) {
                $db->query(
                    "UPDATE nu_products SET name = ?, type = ?, barcode = ?, description = ?, price = ?, updated_at = NOW() WHERE id = ?",
                    [$name, $type, $barcode, $description, $price, $id]
                );
                echo json_encode([
                    'success' => true,
                    'message' => 'Item updated successfully.',
                    'data' => ['id' => $id, 'barcode' => $barcode]
                ]);
            } else {
                $db->query(
                    "INSERT INTO nu_products (name, type, barcode, description, price, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$name, $type, $barcode, $description, $price]
                );
                $newId = $db->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Item created successfully.',
                    'data' => ['id' => $newId, 'barcode' => $barcode]
                ]);
            }
            break;

        // ------------------------------------------------------------------
        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                throw new \InvalidArgumentException('Item ID is required for deletion.');
            }
            $db->query("DELETE FROM nu_products WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Item deleted successfully.']);
            break;

        // ------------------------------------------------------------------
        case 'generate_code':
            $type = $_GET['type'] ?? 'product';
            $code = generateUniqueBarcode($db, $type);
            echo json_encode(['success' => true, 'barcode' => $code]);
            break;

        // ------------------------------------------------------------------
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
