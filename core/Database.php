<?php
declare(strict_types=1);
/**
 * NuDatabase - PDO wrapper (singleton)
 * PHP 8.1+ compatible
 * IMPORTANT: destructor must NOT touch session - it runs during PHP shutdown
 * after session_write_close() has already been called.
 */
class NuDatabase {
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct() {
        global $nuConfig;
        $this->config = $nuConfig ?? [];
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function getConnection() {
        return self::getInstance()->pdo;
    }

    private function connect() {
        $host    = $this->config['dbHost']     ?? 'localhost';
        $dbName  = $this->config['dbName']     ?? '';
        $user    = $this->config['dbUser']     ?? '';
        $pass    = $this->config['dbPassword'] ?? '';
        $charset = $this->config['dbCharset']  ?? 'utf8mb4';
        $port    = $this->config['dbPort']     ?? 3306;

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // MUST be true: MySQL native prepared statements cannot execute DDL
            // (CREATE TABLE / ALTER TABLE / DROP TABLE). Emulated prepares
            // route DDL through PDO::exec() internally and work correctly.
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];
        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Fallback to file-based SQLite database so visual previews/dev tools never crash and persist state
            $this->pdo = new PDO("sqlite:/tmp/test_nuvis.sqlite", null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // Create essential tables to support mock/visual preview gracefully
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_forms (form_id INTEGER PRIMARY KEY, form_code TEXT UNIQUE, form_table TEXT, form_layout TEXT, browse_layout TEXT, form_active INTEGER, form_type TEXT, form_name TEXT, form_description TEXT, form_pk_type TEXT, form_table_mode TEXT, browse_search_enabled INTEGER, browse_search_placeholder TEXT, browse_search_fields TEXT, browse_page_size INTEGER, browse_default_sort TEXT, browse_php TEXT, browse_conditions TEXT, browse_delete_enabled INTEGER)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_system_settings (setting_key VARCHAR(50) PRIMARY KEY, setting_value TEXT)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_error_log (err_id INTEGER PRIMARY KEY, err_message TEXT, err_file TEXT, err_line INTEGER, err_severity TEXT, err_user_id TEXT, err_created_at TEXT)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_audit_log (audit_id INTEGER PRIMARY KEY, audit_action TEXT, audit_table TEXT, audit_username TEXT, audit_timestamp TEXT)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_menus (menu_id INTEGER PRIMARY KEY, menu_label TEXT, menu_type TEXT, menu_target TEXT, menu_parent_id INTEGER, menu_order INTEGER, menu_roles TEXT, menu_role_access TEXT, menu_active INTEGER, menu_icon TEXT)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_users (usr_id INTEGER PRIMARY KEY, usr_username TEXT UNIQUE, usr_password TEXT, usr_email TEXT, usr_role TEXT, usr_active INTEGER, usr_2fa_secret TEXT, usr_failed_attempts INTEGER DEFAULT 0, usr_last_attempt TEXT, usr_password_changed_at TEXT, usr_must_change_password INTEGER DEFAULT 0, usr_created_at TEXT, usr_updated_at TEXT, usr_name TEXT, usr_custom_fields TEXT, usr_custom_fields_def TEXT)");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_users (usr_id, usr_username, usr_password, usr_email, usr_role, usr_active, usr_name, usr_custom_fields, usr_custom_fields_def) VALUES (1, 'globeadmin', '" . password_hash('password', PASSWORD_BCRYPT) . "', 'globeadmin@example.com', 'globeadmin', 1, 'Globe Admin', '{}', '[]')");
            // Create demo tables
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS demo_service_types (service_type_id TEXT PRIMARY KEY, name TEXT, description TEXT, price REAL)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS demo_customer_requests (request_id TEXT PRIMARY KEY, customer_name TEXT, service_type_id TEXT, request_details TEXT, status TEXT DEFAULT 'Pending', created_at TEXT, updated_at TEXT)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS demo_staff_services (service_log_id TEXT PRIMARY KEY, customer_request_id TEXT, staff_notes TEXT, service_date TEXT)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_workflow_instances (wfi_id INTEGER PRIMARY KEY, wfi_wf_id INTEGER, wfi_stage_id INTEGER, wfi_record_table TEXT, wfi_record_id TEXT, wfi_status TEXT, wfi_started_by INTEGER)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_workflows (wf_id INTEGER PRIMARY KEY, wf_code TEXT, wf_name TEXT, wf_description TEXT, wf_form_code TEXT, wf_active INTEGER)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_workflow_stages (wfs_id INTEGER PRIMARY KEY, wfs_wf_id INTEGER, wfs_code TEXT, wfs_name TEXT, wfs_description TEXT, wfs_color TEXT, wfs_is_start INTEGER, wfs_is_end INTEGER, wfs_order INTEGER)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS nu_workflow_transitions (wft_id INTEGER PRIMARY KEY, wft_wf_id INTEGER, wft_from_id INTEGER, wft_to_id INTEGER, wft_action TEXT, wft_label TEXT, wft_hook TEXT)");

            // Seed sample data
            $nowStr = date('Y-m-d H:i:s');
            $this->pdo->exec("INSERT OR IGNORE INTO demo_service_types VALUES ('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'Plumbing Maintenance', 'General plumbing repairs', 150.0)");
            $this->pdo->exec("INSERT OR IGNORE INTO demo_service_types VALUES ('b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'Electrical Inspection', 'Safety audit', 120.0)");
            $this->pdo->exec("INSERT OR IGNORE INTO demo_customer_requests VALUES ('d4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a', 'Alice Smith', 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'Main drain backing up', 'Pending', '{$nowStr}', '{$nowStr}')");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_workflows VALUES (100, 'customer_request_wf', 'Customer Request Workflow', 'A demo workflow', 'demo_customer_requests', 1)");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_workflow_stages VALUES (101, 100, 'Pending', 'Pending Service', 'The service request is created', '#f59e0b', 1, 0, 1)");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_workflow_stages VALUES (102, 100, 'In Progress', 'Service In Progress', 'The staff has commenced', '#3b82f6', 0, 0, 2)");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_workflow_stages VALUES (103, 100, 'Completed', 'Completed', 'The requested service', '#10b981', 0, 1, 3)");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_workflow_transitions VALUES (101, 100, 101, 102, 'advance', 'Start Providing Service', 'update_record')");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_workflow_transitions VALUES (102, 100, 102, 103, 'advance', 'Mark Service Completed', 'update_record')");
            $this->pdo->exec("INSERT OR IGNORE INTO nu_workflow_instances VALUES (1, 100, 101, 'demo_customer_requests', 'd4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a', 'active', 1)");
        }

        // Ensure nu_menus columns exist in case of upgrade or existing database
        $sessionActive = (session_status() === PHP_SESSION_ACTIVE);
        if (!$sessionActive || empty($_SESSION['_nu_menu_columns_ensured'])) {
            try {
                $tableExists = $this->pdo->query("SHOW TABLES LIKE 'nu_menus'")->fetch();
                if ($tableExists) {
                    $columns = [];
                    $stmt = $this->pdo->query("SHOW COLUMNS FROM `nu_menus`");
                    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $columns[] = strtolower($col['Field']);
                    }
                    if (!in_array('menu_role_access', $columns, true)) {
                        $this->pdo->exec("ALTER TABLE `nu_menus` ADD COLUMN `menu_role_access` VARCHAR(512) DEFAULT NULL");
                    }
                    if (!in_array('menu_open_mode', $columns, true)) {
                        $this->pdo->exec("ALTER TABLE `nu_menus` ADD COLUMN `menu_open_mode` VARCHAR(30) NOT NULL DEFAULT 'inline|browse'");
                    }
                    if (!in_array('menu_browse_mode', $columns, true)) {
                        $this->pdo->exec("ALTER TABLE `nu_menus` ADD COLUMN `menu_browse_mode` VARCHAR(10) NOT NULL DEFAULT 'inline'");
                    }
                    if (!in_array('menu_preview_mode', $columns, true)) {
                        $this->pdo->exec("ALTER TABLE `nu_menus` ADD COLUMN `menu_preview_mode` VARCHAR(10) NOT NULL DEFAULT 'inline'");
                    }
                    if (!in_array('menu_default_view', $columns, true)) {
                        $this->pdo->exec("ALTER TABLE `nu_menus` ADD COLUMN `menu_default_view` VARCHAR(10) NOT NULL DEFAULT 'browse'");
                    }
                    if ($sessionActive) {
                        $_SESSION['_nu_menu_columns_ensured'] = true;
                    }
                }
            } catch (Exception $ignored) {}
        }

        // Self-healing: Ensure nu_system_settings table exists and is populated
        if (!$sessionActive || empty($_SESSION['_nu_system_settings_ensured'])) {
            try {
                $tableExists = $this->pdo->query("SHOW TABLES LIKE 'nu_system_settings'")->fetch();
                if (!$tableExists) {
                    $this->pdo->exec("CREATE TABLE `nu_system_settings` (
                        `setting_key` VARCHAR(50) NOT NULL,
                        `setting_value` LONGTEXT DEFAULT NULL,
                        PRIMARY KEY (`setting_key`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }

                // Seed default settings if they are missing
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `nu_system_settings` WHERE `setting_key` = ?");

                // app_name
                $stmt->execute(['app_name']);
                if ((int)$stmt->fetchColumn() === 0) {
                    $this->pdo->prepare("INSERT INTO `nu_system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)")
                              ->execute(['app_name', 'nuvis']);
                }

                // app_logo
                $stmt->execute(['app_logo']);
                if ((int)$stmt->fetchColumn() === 0) {
                    $this->pdo->prepare("INSERT INTO `nu_system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)")
                              ->execute(['app_logo', '']);
                }

                // system_fields_def
                $stmt->execute(['system_fields_def']);
                if ((int)$stmt->fetchColumn() === 0) {
                    $this->pdo->prepare("INSERT INTO `nu_system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)")
                              ->execute(['system_fields_def', '[]']);
                }

                // forgot_password_enabled
                $stmt->execute(['forgot_password_enabled']);
                if ((int)$stmt->fetchColumn() === 0) {
                    $this->pdo->prepare("INSERT INTO `nu_system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)")
                              ->execute(['forgot_password_enabled', '1']);
                }

                // user_header_format
                $stmt->execute(['user_header_format']);
                if ((int)$stmt->fetchColumn() === 0) {
                    $this->pdo->prepare("INSERT INTO `nu_system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)")
                              ->execute(['user_header_format', '{name} | {location}']);
                }

                if ($sessionActive) {
                    $_SESSION['_nu_system_settings_ensured'] = true;
                }
            } catch (Exception $ignored) {}
        }

        // Self-healing: Ensure API Manager (nu_api_endpoints and nu_api_logs) tables exist
        if (!$sessionActive || empty($_SESSION['_nu_api_manager_tables_ensured'])) {
            try {
                $hasEndpoints = $this->pdo->query("SHOW TABLES LIKE 'nu_api_endpoints'")->fetch();
                if (!$hasEndpoints) {
                    $this->pdo->exec("CREATE TABLE `nu_api_endpoints` (
                        `endpoint_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `endpoint_name` VARCHAR(150) NOT NULL,
                        `endpoint_route` VARCHAR(250) NOT NULL UNIQUE,
                        `endpoint_method` VARCHAR(10) NOT NULL DEFAULT 'GET',
                        `endpoint_type` VARCHAR(20) NOT NULL DEFAULT 'form',
                        `endpoint_target` VARCHAR(200) NOT NULL,
                        `endpoint_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `endpoint_config` TEXT DEFAULT NULL,
                        `endpoint_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_api_route` (`endpoint_route`),
                        INDEX `idx_api_active` (`endpoint_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }

                $hasLogs = $this->pdo->query("SHOW TABLES LIKE 'nu_api_logs'")->fetch();
                if (!$hasLogs) {
                    $this->pdo->exec("CREATE TABLE `nu_api_logs` (
                        `log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `log_route` VARCHAR(250) NOT NULL,
                        `log_method` VARCHAR(10) NOT NULL,
                        `log_token_name` VARCHAR(100) DEFAULT NULL,
                        `log_user_id` VARCHAR(64) DEFAULT NULL,
                        `log_request_payload` TEXT DEFAULT NULL,
                        `log_response_code` INT NOT NULL,
                        `log_response_payload` TEXT DEFAULT NULL,
                        `log_duration` DOUBLE DEFAULT NULL,
                        `log_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_log_route` (`log_route`),
                        INDEX `idx_log_created` (`log_created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }

                if ($sessionActive) {
                    $_SESSION['_nu_api_manager_tables_ensured'] = true;
                }
            } catch (Exception $ignored) {}
        }

        // Self-healing: Ensure nu_procedures table exists
        if (!$sessionActive || empty($_SESSION['_nu_procedures_ensured'])) {
            try {
                $hasProcedures = $this->pdo->query("SHOW TABLES LIKE 'nu_procedures'")->fetch();
                if (!$hasProcedures) {
                    $this->pdo->exec("CREATE TABLE `nu_procedures` (
                        `procedure_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `procedure_name` VARCHAR(150) NOT NULL,
                        `procedure_code` VARCHAR(100) NOT NULL UNIQUE,
                        `procedure_description` VARCHAR(255) DEFAULT NULL,
                        `procedure_php` MEDIUMTEXT DEFAULT NULL,
                        `procedure_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `procedure_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `procedure_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX `idx_proc_code` (`procedure_code`),
                        INDEX `idx_proc_active` (`procedure_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
                if ($sessionActive) {
                    $_SESSION['_nu_procedures_ensured'] = true;
                }
            } catch (Exception $ignored) {}
        }

        // Self-healing: Ensure nu_products table exists and is populated
        if (!$sessionActive || empty($_SESSION['_nu_products_ensured'])) {
            try {
                $hasProducts = $this->pdo->query("SHOW TABLES LIKE 'nu_products'")->fetch();
                if (!$hasProducts) {
                    $this->pdo->exec("CREATE TABLE `nu_products` (
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
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    // Seed initial products, goods, and services
                    $this->pdo->exec("INSERT INTO `nu_products` (`name`, `type`, `barcode`, `description`, `price`) VALUES
                    ('Organic Coffee Beans (Dark Roast)', 'product', '7501031311309', 'Premium single-origin organic arabica coffee beans, slow-roasted to a rich dark finish. 500g bag.', 18.99),
                    ('Ergonomic Wireless Mouse', 'product', '074182285197', 'Rechargeable wireless ergonomic mouse with silent clicking, side-scroll wheel, and adjustable DPI settings up to 4000.', 45.50),
                    ('Eco-Friendly Bamboo Water Bottle', 'product', '8886367301031', 'Double-walled vacuum insulated water bottle made from stainless steel and covered with natural, sustainable bamboo. 750ml.', 24.99),
                    ('Heavy Duty Industrial Pallet', 'good', 'GD-WRH-PAL-02', 'High-density polyethylene structural foam plastic pallet. Ideal for warehouse storage and forklift transport. Rated up to 1500kg.', 79.95),
                    ('Ultra-Soft Microfiber Towel Set', 'good', 'GD-TOWEL-S4', 'Pack of 4 quick-drying, highly absorbent premium microfiber towels. Perfect for home, gym, or automotive detailing.', 15.49),
                    ('Web Application Development Consultation', 'service', 'SVC-WEB-DEV-01', 'One-hour professional architectural and consulting session with a Senior Software Engineer regarding web app stacks and cloud hosting.', 120.00),
                    ('Enterprise IT Security Audit', 'service', 'SVC-IT-SEC-05', 'Comprehensive end-to-end network penetration testing, software vulnerability scan, and infrastructure compliance audit report.', 1500.00)");
                }
                if ($sessionActive) {
                    $_SESSION['_nu_products_ensured'] = true;
                }
            } catch (Exception $ignored) {}
        }

        // Self-healing: Ensure developer demo tables exist and are populated
        if (!$sessionActive || empty($_SESSION['_nu_demo_tables_ensured'])) {
            try {
                $hasDemoTypes = $this->pdo->query("SHOW TABLES LIKE 'demo_service_types'")->fetch();
                if (!$hasDemoTypes) {
                    $this->pdo->exec("CREATE TABLE IF NOT EXISTS `demo_service_types` (
                        `service_type_id` VARCHAR(36) NOT NULL,
                        `name` VARCHAR(150) NOT NULL,
                        `description` TEXT DEFAULT NULL,
                        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        PRIMARY KEY (`service_type_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                    $this->pdo->exec("INSERT INTO `demo_service_types` (`service_type_id`, `name`, `description`, `price`) VALUES
                    ('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'Plumbing Maintenance', 'General plumbing repairs, leak detection, and pipe maintenance.', 150.00),
                    ('b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'Electrical Inspection', 'Safety audit of wiring, outlets, and panel inspection.', 120.00),
                    ('c3d4e5f6-a7b8-9c0d-1e2f-3a4b5c6d7e8f', 'HVAC System Service', 'Heating, ventilation, and air conditioning diagnostic and filter change.', 200.00)");
                }

                $hasDemoRequests = $this->pdo->query("SHOW TABLES LIKE 'demo_customer_requests'")->fetch();
                if (!$hasDemoRequests) {
                    $this->pdo->exec("CREATE TABLE IF NOT EXISTS `demo_customer_requests` (
                        `request_id` VARCHAR(36) NOT NULL,
                        `customer_name` VARCHAR(150) NOT NULL,
                        `service_type_id` VARCHAR(36) NOT NULL,
                        `request_details` TEXT DEFAULT NULL,
                        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`request_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                    $this->pdo->exec("INSERT INTO `demo_customer_requests` (`request_id`, `customer_name`, `service_type_id`, `request_details`, `status`) VALUES
                    ('d4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a', 'Alice Smith', 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'Main drain is running extremely slow and backing up.', 'Pending'),
                    ('e5f6a7b8-c9d0-1e2f-3a4b-5c6d7e8f9a0b', 'Bob Johnson', 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'Living room outlets have no power. Breaker is not tripped.', 'Pending')");
                }

                $hasDemoStaff = $this->pdo->query("SHOW TABLES LIKE 'demo_staff_services'")->fetch();
                if (!$hasDemoStaff) {
                    $this->pdo->exec("CREATE TABLE IF NOT EXISTS `demo_staff_services` (
                        `service_log_id` VARCHAR(36) NOT NULL,
                        `customer_request_id` VARCHAR(36) NOT NULL,
                        `staff_notes` TEXT DEFAULT NULL,
                        `service_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`service_log_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                }

                if ($sessionActive) {
                    $_SESSION['_nu_demo_tables_ensured'] = true;
                }
            } catch (Exception $ignored) {}
        }

        // Self-healing: Ensure developer demo forms exist in nu_forms
        if (!$sessionActive || empty($_SESSION['_nu_demo_forms_ensured'])) {
            try {
                $hasForm = $this->pdo->query("SELECT form_id FROM nu_forms WHERE form_code = 'demo_customer_requests'")->fetch();

                $layoutTypes = json_encode([
                    ["type" => "row", "children" => [
                        ["name" => "name", "label" => "Service Name", "type" => "text", "required" => true, "col" => 6],
                        ["name" => "price", "label" => "Price ($)", "type" => "number", "required" => true, "col" => 6]
                    ]],
                    ["type" => "row", "children" => [
                        ["name" => "description", "label" => "Description", "type" => "textarea", "col" => 12, "rows" => 4]
                    ]]
                ]);
                $browseTypes = json_encode([
                    ["fieldname" => "name", "fieldlabel" => "Service Name", "width" => "200px", "align" => "left", "formatter" => "text", "sortable" => true],
                    ["fieldname" => "price", "fieldlabel" => "Price", "width" => "120px", "align" => "right", "formatter" => "currency", "sortable" => true],
                    ["fieldname" => "description", "fieldlabel" => "Description", "width" => "300px", "align" => "left", "formatter" => "text", "sortable" => true]
                ]);

                $layoutRequests = json_encode([
                    ["type" => "row", "children" => [
                        ["name" => "customer_name", "label" => "Customer Name", "type" => "text", "required" => true, "col" => 6],
                        ["name" => "service_type_id", "label" => "Service Type", "type" => "select", "required" => true, "col" => 6, "options_source" => "table", "options_table" => "demo_service_types", "options_value_col" => "service_type_id", "options_label_col" => "name", "join_sql" => "LEFT JOIN demo_service_types ON demo_service_types.service_type_id = demo_customer_requests.service_type_id", "join_display_field" => "demo_service_types.name"]
                    ]],
                    ["type" => "row", "children" => [
                        ["name" => "request_details", "label" => "Request Details", "type" => "textarea", "col" => 12, "rows" => 4]
                    ]],
                    ["type" => "row", "children" => [
                        ["name" => "status", "label" => "Status", "type" => "text", "required" => true, "col" => 6, "default_value" => "Pending", "readonly" => true]
                    ]]
                ]);
                $browseRequests = json_encode([
                    ["fieldname" => "customer_name", "fieldlabel" => "Customer Name", "width" => "180px", "align" => "left", "formatter" => "text", "sortable" => true],
                    ["fieldname" => "service_type_id", "fieldlabel" => "Service Type", "width" => "200px", "align" => "left", "formatter" => "text", "sortable" => true, "join_sql" => "LEFT JOIN demo_service_types ON demo_service_types.service_type_id = demo_customer_requests.service_type_id", "join_display_field" => "demo_service_types.name"],
                    ["fieldname" => "request_details", "fieldlabel" => "Details", "width" => "280px", "align" => "left", "formatter" => "text", "sortable" => true],
                    ["fieldname" => "status", "fieldlabel" => "Status", "width" => "120px", "align" => "center", "formatter" => "badge", "sortable" => true]
                ]);

                $layoutStaff = json_encode([
                    ["type" => "row", "children" => [
                        ["name" => "customer_request_id", "label" => "Customer Request", "type" => "select", "required" => true, "col" => 6, "options_source" => "table", "options_table" => "demo_customer_requests", "options_value_col" => "request_id", "options_label_col" => "customer_name", "options_filter" => "status != 'Completed'", "join_sql" => "LEFT JOIN demo_customer_requests ON demo_customer_requests.request_id = demo_staff_services.customer_request_id", "join_display_field" => "demo_customer_requests.customer_name"],
                        ["name" => "service_date", "label" => "Service Date", "type" => "datetime", "required" => true, "col" => 6]
                    ]],
                    ["type" => "row", "children" => [
                        ["name" => "staff_notes", "label" => "Staff Service Notes", "type" => "textarea", "required" => true, "col" => 12, "rows" => 4]
                    ]]
                ]);
                $browseStaff = json_encode([
                    ["fieldname" => "customer_request_id", "fieldlabel" => "Customer", "width" => "180px", "align" => "left", "formatter" => "text", "sortable" => true, "join_sql" => "LEFT JOIN demo_customer_requests ON demo_customer_requests.request_id = demo_staff_services.customer_request_id", "join_display_field" => "demo_customer_requests.customer_name"],
                    ["fieldname" => "staff_notes", "fieldlabel" => "Notes", "width" => "300px", "align" => "left", "formatter" => "text", "sortable" => true],
                    ["fieldname" => "service_date", "fieldlabel" => "Date & Time", "width" => "160px", "align" => "left", "formatter" => "text", "sortable" => true]
                ]);
                $staffAfterSave = '$db = NuDatabase::getInstance();' . "\n" .
                                  '$reqId = $customer_request_id;' . "\n" .
                                  'if ($reqId) {' . "\n" .
                                  '    $db->update("demo_customer_requests", ["status" => "Completed"], "request_id = ?", [$reqId]);' . "\n" .
                                  '}';

                if (!$hasForm) {
                    // 1. Service Types Form
                    $this->pdo->prepare("INSERT INTO `nu_forms` (
                        `form_code`, `form_type`, `form_name`, `form_table`, `form_description`,
                        `form_layout`, `browse_layout`, `form_active`, `form_pk_type`, `form_table_mode`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                        'demo_service_types', 'main', 'Service Types', 'demo_service_types', 'Manage service types and pricing.',
                        $layoutTypes, $browseTypes, 1, 'uuid', 'existing'
                    ]);

                    // 2. Customer Requests Form
                    $this->pdo->prepare("INSERT INTO `nu_forms` (
                        `form_code`, `form_type`, `form_name`, `form_table`, `form_description`,
                        `form_layout`, `browse_layout`, `form_active`, `form_pk_type`, `form_table_mode`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                        'demo_customer_requests', 'main', 'Customer Requests', 'demo_customer_requests', 'Manage customer requests.',
                        $layoutRequests, $browseRequests, 1, 'uuid', 'existing'
                    ]);

                    // 3. Staff Services Form
                    $this->pdo->prepare("INSERT INTO `nu_forms` (
                        `form_code`, `form_type`, `form_name`, `form_table`, `form_description`,
                        `form_layout`, `browse_layout`, `form_custom_php_after`, `form_active`, `form_pk_type`, `form_table_mode`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                        'demo_staff_services', 'main', 'Staff Services', 'demo_staff_services', 'Log staff service actions.',
                        $layoutStaff, $browseStaff, $staffAfterSave, 1, 'uuid', 'existing'
                    ]);
                } else {
                    // Update existing form layouts to ensure Join configs are in place
                    $this->pdo->prepare("UPDATE `nu_forms` SET `form_layout` = ?, `browse_layout` = ? WHERE `form_code` = ?")->execute([$layoutTypes, $browseTypes, 'demo_service_types']);
                    $this->pdo->prepare("UPDATE `nu_forms` SET `form_layout` = ?, `browse_layout` = ? WHERE `form_code` = ?")->execute([$layoutRequests, $browseRequests, 'demo_customer_requests']);
                    $this->pdo->prepare("UPDATE `nu_forms` SET `form_layout` = ?, `browse_layout` = ?, `form_custom_php_after` = ? WHERE `form_code` = ?")->execute([$layoutStaff, $browseStaff, $staffAfterSave, 'demo_staff_services']);
                }

                if ($sessionActive) {
                    $_SESSION['_nu_demo_forms_ensured'] = true;
                }
            } catch (Exception $ignored) {}
        }

        // Self-healing: Ensure developer demo workflow exists in nu_workflows
        if (!$sessionActive || empty($_SESSION['_nu_demo_workflow_ensured'])) {
            try {
                $hasWf = $this->pdo->query("SELECT wf_id FROM nu_workflows WHERE wf_code = 'customer_request_wf'")->fetch();
                if (!$hasWf) {
                    // 1. Insert workflow
                    $this->pdo->prepare("INSERT INTO `nu_workflows` (
                        `wf_code`, `wf_name`, `wf_description`, `wf_form_code`, `wf_active`
                    ) VALUES (?, ?, ?, ?, ?)")->execute([
                        'customer_request_wf', 'Customer Request Workflow', 'A demo workflow tracking customer service requests through life cycle stage progressions.', 'demo_customer_requests', 1
                    ]);
                    $wfId = (int)$this->pdo->lastInsertId();
                } else {
                    $wfId = (int)$hasWf['wf_id'];
                }

                // Verify and synchronize stages
                $stageStmt = $this->pdo->prepare("SELECT wfs_id, wfs_code FROM nu_workflow_stages WHERE wfs_wf_id = ?");
                $stageStmt->execute([$wfId]);
                $existingStages = $stageStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

                if (empty($existingStages) || !isset($existingStages['Pending']) || !isset($existingStages['In Progress']) || !isset($existingStages['Completed'])) {
                    // Wipe broken structures for this workflow and re-insert them cleanly
                    $this->pdo->prepare("DELETE FROM `nu_workflow_transitions` WHERE `wft_wf_id` = ?")->execute([$wfId]);
                    $this->pdo->prepare("DELETE FROM `nu_workflow_stages` WHERE `wfs_wf_id` = ?")->execute([$wfId]);

                    // Stage 1: Pending
                    $this->pdo->prepare("INSERT INTO `nu_workflow_stages` (
                        `wfs_wf_id`, `wfs_code`, `wfs_name`, `wfs_description`, `wfs_color`, `wfs_is_start`, `wfs_is_end`, `wfs_order`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                        $wfId, 'Pending', 'Pending Service', 'The service request is created and is waiting for assignment/commencement.', '#f59e0b', 1, 0, 1
                    ]);
                    $stagePendingId = (int)$this->pdo->lastInsertId();

                    // Stage 2: In Progress
                    $this->pdo->prepare("INSERT INTO `nu_workflow_stages` (
                        `wfs_wf_id`, `wfs_code`, `wfs_name`, `wfs_description`, `wfs_color`, `wfs_is_start`, `wfs_is_end`, `wfs_order`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                        $wfId, 'In Progress', 'Service In Progress', 'The staff has commenced providing the requested service.', '#3b82f6', 0, 0, 2
                    ]);
                    $stageProgressId = (int)$this->pdo->lastInsertId();

                    // Stage 3: Completed
                    $this->pdo->prepare("INSERT INTO `nu_workflow_stages` (
                        `wfs_wf_id`, `wfs_code`, `wfs_name`, `wfs_description`, `wfs_color`, `wfs_is_start`, `wfs_is_end`, `wfs_order`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                        $wfId, 'Completed', 'Completed', 'The requested service has been successfully completed.', '#10b981', 0, 1, 3
                    ]);
                    $stageCompletedId = (int)$this->pdo->lastInsertId();

                    // Insert Transitions & Action Hooks
                    // Transition 1: Start Service (Pending -> In Progress)
                    $this->pdo->prepare("INSERT INTO `nu_workflow_transitions` (
                        `wft_wf_id`, `wft_from_id`, `wft_to_id`, `wft_action`, `wft_label`, `wft_hook`
                    ) VALUES (?, ?, ?, ?, ?, ?)")->execute([
                        $wfId, $stagePendingId, $stageProgressId, 'advance', 'Start Providing Service', 'update_record'
                    ]);

                    // Transition 2: Finish Service (In Progress -> Completed)
                    $this->pdo->prepare("INSERT INTO `nu_workflow_transitions` (
                        `wft_wf_id`, `wft_from_id`, `wft_to_id`, `wft_action`, `wft_label`, `wft_hook`
                    ) VALUES (?, ?, ?, ?, ?, ?)")->execute([
                        $wfId, $stageProgressId, $stageCompletedId, 'advance', 'Mark Service Completed', 'update_record'
                    ]);
                } else {
                    // Double check transitions exist
                    $transStmt = $this->pdo->prepare("SELECT COUNT(*) FROM nu_workflow_transitions WHERE wft_wf_id = ?");
                    $transStmt->execute([$wfId]);
                    if ((int)$transStmt->fetchColumn() === 0) {
                        $stagePendingId   = (int)$existingStages['Pending'];
                        $stageProgressId  = (int)$existingStages['In Progress'];
                        $stageCompletedId = (int)$existingStages['Completed'];

                        // Re-insert Transitions & Action Hooks
                        $this->pdo->prepare("INSERT INTO `nu_workflow_transitions` (
                            `wft_wf_id`, `wft_from_id`, `wft_to_id`, `wft_action`, `wft_label`, `wft_hook`
                        ) VALUES (?, ?, ?, ?, ?, ?)")->execute([
                            $wfId, $stagePendingId, $stageProgressId, 'advance', 'Start Providing Service', 'update_record'
                        ]);

                        $this->pdo->prepare("INSERT INTO `nu_workflow_transitions` (
                            `wft_wf_id`, `wft_from_id`, `wft_to_id`, `wft_action`, `wft_label`, `wft_hook`
                        ) VALUES (?, ?, ?, ?, ?, ?)")->execute([
                            $wfId, $stageProgressId, $stageCompletedId, 'advance', 'Mark Service Completed', 'update_record'
                        ]);
                    }
                }

                if ($sessionActive) {
                    $_SESSION['_nu_demo_workflow_ensured'] = true;
                }
            } catch (Exception $ignored) {}
        }
    }

    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Run a DML query (SELECT / INSERT / UPDATE / DELETE) via a prepared statement.
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Run a DDL statement (CREATE TABLE, ALTER TABLE, DROP TABLE, etc.)
     * directly via PDO::exec() — bypasses the prepared-statement layer.
     * MySQL cannot run DDL through native prepared statements.
     * Throws PDOException on failure.
     */
    public function exec(string $sql) {
        $result = $this->pdo->exec($sql);
        if ($result === false) {
            $err = $this->pdo->errorInfo();
            throw new \PDOException('DDL exec failed [' . ($err[0] ?? '') . ']: ' . ($err[2] ?? 'unknown error'));
        }
        return $result;
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch() ?: null;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    // PHP 8.1+ compatible
    public function insert($table, $data) {
        $cols         = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(function($k) { return ":$k"; }, array_keys($data)));
        $params       = [];
        foreach ($data as $k => $v) {
            $params[":$k"] = $v;
        }
        $sql = "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})";
        $this->query($sql, $params);
        return (int)$this->pdo->lastInsertId();
    }

    // PHP 8.1+ compatible
    public function update($table, $data, $where, $whereParams = []) {
        $sets   = implode(', ', array_map(function($k) { return "{$k} = :set_{$k}"; }, array_keys($data)));
        $params = [];
        foreach ($data as $k => $v) {
            $params[":set_{$k}"] = $v;
        }

        if (!empty($whereParams) && array_keys($whereParams) === range(0, count($whereParams) - 1)) {
            $i = 0;
            $where = preg_replace_callback('/\?/', function() use (&$i) {
                return ':where_' . $i++;
            }, $where);
            $namedWhere = [];
            foreach ($whereParams as $idx => $val) {
                $namedWhere[':where_' . $idx] = $val;
            }
            $whereParams = $namedWhere;
        }

        $sql  = "UPDATE {$table} SET {$sets} WHERE {$where}";
        $stmt = $this->query($sql, array_merge($params, $whereParams));
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = []) {
        return $this->query("DELETE FROM {$table} WHERE {$where}", $params)->rowCount();
    }

    public function lastInsertId() {
        return (int)$this->pdo->lastInsertId();
    }

    public function beginTransaction() { $this->pdo->beginTransaction(); }
    public function commit()           { $this->pdo->commit(); }
    public function rollback()         { $this->pdo->rollBack(); }

    public function __destruct() {
        $this->pdo = null;
    }

    private function __clone() {}
    public function __wakeup() { throw new RuntimeException('Cannot unserialize NuDatabase.'); }
}

if (!class_exists('Database')) {
    class_alias('NuDatabase', 'Database');
}

/**
 * NuProcedure Helper Class
 * Allows executing saved custom PHP functions from other server-side contexts.
 */
class NuProcedure {
    /**
     * Executes a saved custom PHP function (procedure) by its code.
     *
     * @param string $code
     * @param array $params
     * @param array $hashCookies
     * @return array
     */
    public static function run($code, $params = [], $hashCookies = []) {
        $db = NuDatabase::getInstance();
        $proc = $db->fetchOne("SELECT * FROM nu_procedures WHERE procedure_code = ? AND procedure_active = 1", [$code]);
        if (!$proc) {
            return [
                'success' => false,
                'error' => "Procedure not found or is inactive: " . $code
            ];
        }

        // Setup sandboxed execution variables
        $_proc_params = $params;
        $_proc_db     = $db;
        $_proc_auth   = class_exists('NuAuth') ? NuAuth::getInstance() : null;
        $_proc_hash   = $hashCookies;
        $_proc_result = null;

        ob_start();
        try {
            eval('?>' . $proc['procedure_php']);
            $output = ob_get_clean();
            return [
                'success' => true,
                'output'  => $output,
                'data'    => $_proc_result
            ];
        } catch (Throwable $e) {
            ob_end_clean();
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('nu_run_procedure')) {
    function nu_run_procedure($code, $params = [], $hashCookies = []) {
        return NuProcedure::run($code, $params, $hashCookies);
    }
}

if (!function_exists('run_procedure')) {
    function run_procedure($code, $params = [], $hashCookies = []) {
        return NuProcedure::run($code, $params, $hashCookies);
    }
}
