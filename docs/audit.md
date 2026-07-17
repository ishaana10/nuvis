# Audit Trail Module

## Overview
The Audit Trail module monitors and logs all transactional activities within the application. This ensures accountability, debugging insights, and security compliance. It automatically logs operations such as user login, record creation, updates, and deletions, preserving both old and new states in JSON format.

---

## Architecture & Key Files
- **`modules/audit/audit.php`**: The backend-rendered control panel viewing recent logs, filtering by context, and supporting paginated tables.
- **`core/Audit.php`**: The `NuAudit` system class handling data formatting, IP and browser resolution, safety try-catch wrappers, and execution of SQL logs.

---

## Technical Details

### Schema (`nu_audit_log`)
The system queries and structures logs via the `nu_audit_log` table:

```sql
CREATE TABLE IF NOT EXISTS nu_audit_log (
    audit_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_action     VARCHAR(32)   NOT NULL,
    audit_table      VARCHAR(128)  NOT NULL DEFAULT '',
    audit_record_id  VARCHAR(64)   DEFAULT NULL,
    audit_old_data   LONGTEXT      DEFAULT NULL,
    audit_new_data   LONGTEXT      DEFAULT NULL,
    audit_user_id    VARCHAR(64)   DEFAULT NULL,
    audit_username   VARCHAR(128)  NOT NULL DEFAULT 'system',
    audit_ip         VARCHAR(45)   NOT NULL DEFAULT '',
    audit_user_agent VARCHAR(512)  NOT NULL DEFAULT '',
    audit_timestamp  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_ts     (audit_timestamp),
    INDEX idx_audit_user   (audit_user_id),
    INDEX idx_audit_action (audit_action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

*Note: `audit_record_id` and `audit_user_id` are defined as `VARCHAR(64)` to seamlessly support both traditional integer IDs and modern `VARCHAR(36)` UUID structures without truncating or failing to match.*

### Integer Casting Protections
Unlike traditional databases, `NuAudit::log` and filters must **never** cast primary keys or user IDs to `(int)` prior to database interaction. Casting hypothetical UUIDs (e.g. `'usr_a1b2c3d4...'`) to an integer yields `0`, silently breaking table relations.

---

## Usage Examples

### Triggering an Audit Event manually in PHP
```php
require_once 'core/Audit.php';

$audit = new NuAudit();

$oldRecord = ['status' => 'pending', 'total' => 120.00];
$newRecord = ['status' => 'approved', 'total' => 120.00];

// Logs update operation safely with state comparison
$audit->log(
    'update',
    'orders',
    'ord_987654321', // String record PK (UUID-safe)
    $oldRecord,
    $newRecord
);
```

### Retrieving filtered audit logs programmatically
```php
$audit = new NuAudit();

// Find all updates on the 'orders' table
$logs = $audit->getLogs([
    'action'  => 'update',
    'table'   => 'orders',
    'user_id' => 'usr_11112222-3333-4444-5555-666677778888' // Raw UUID string filter
], 10, 0);

foreach ($logs as $log) {
    echo "User " . $log['audit_username'] . " updated orders on " . $log['audit_timestamp'] . "\n";
}
```
