# Error Log Module

## Overview
The Error Log module manages structural logging and visual monitoring of application errors. It captures PHP parser errors, uncaught core exceptions, custom database SQL errors, runtime JavaScript console failures, and user-invoked app errors. It structures these reports with complete call trace stacks, query variables, and request contexts.

---

## Architecture & Key Files
- **`modules/errorlog/errorlog.php`**: The backend interactive UI dashboard displaying paginated errors, search bars, and sliding details panels.
- **`api/errorlog.php`**: Backend controller managing queries, purging logs, details extraction, and processing JS console errors.
- **`core/ErrorLogger.php`**: The foundational `NuErrorLogger` singleton class managing PHP global standard hooks (`set_error_handler`, `set_exception_handler`, and `register_shutdown_function`).

---

## Technical Details

### Logging Schema (`nu_error_log`)
Errors are captured and written to the `nu_error_log` database container:

```sql
CREATE TABLE IF NOT EXISTS nu_error_log (
    errlog_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    errlog_type           VARCHAR(32)   NOT NULL, -- PHP, SQL, JS, APP
    errlog_severity       VARCHAR(32)   NOT NULL, -- fatal, error, warning, info, debug
    errlog_message        TEXT          NOT NULL,
    errlog_context        LONGTEXT      DEFAULT NULL, -- JSON formatted array
    errlog_trace          LONGTEXT      DEFAULT NULL, -- Stack frames
    errlog_file           VARCHAR(500)  DEFAULT NULL,
    errlog_line           INT UNSIGNED  DEFAULT 0,
    errlog_request_uri    VARCHAR(500)  DEFAULT NULL,
    errlog_request_method VARCHAR(16)   DEFAULT NULL,
    errlog_user_id        VARCHAR(64)   DEFAULT NULL,
    errlog_user_name      VARCHAR(128)  DEFAULT NULL,
    errlog_created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_errlog_type (errlog_type),
    INDEX idx_errlog_sev  (errlog_severity),
    INDEX idx_errlog_time (errlog_created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/errorlog.php?action=list` | `GET` | `list` | Sells filtered, paginated rows of error items. |
| `/api/errorlog.php?action=stats` | `GET` | `stats` | Computes error counts broken down by types and severities. |
| `/api/errorlog.php?action=get&id=X` | `GET` | `get` | Resolves detailed row specifications including full backtrace stacks. |
| `/api/errorlog.php?action=delete&id=X` | `POST` | `delete` | Disposes of a specific log row item. |
| `/api/errorlog.php?action=clear` | `POST` | `clear` | Truncates the entire log table. |

---

## Usage Examples

### Catching and Logging Custom SQL Errors safely
```php
try {
    $db = NuDatabase::getInstance();
    $db->query("SELECT * FROM non_existent_table WHERE user_id = :id", [':id' => $userId]);
} catch (PDOException $e) {
    // Intercepts SQL error and writes full context safely
    NuErrorLogger::logSql(
        "SELECT * FROM non_existent_table WHERE user_id = :id",
        $e->getMessage(),
        ['id' => $userId],
        __FILE__,
        __LINE__
    );
}
```

### Logging Custom App Messages
```php
require_once 'core/ErrorLogger.php';

// Logs internal warnings with relevant metadata parameters
NuErrorLogger::logApp(
    "Credit limit exceeded during checkout",
    [
        'customer_id'  => 'cust_abc123',
        'limit'        => 5000.00,
        'requested'    => 5450.00
    ],
    NuErrorLogger::SEV_WARNING
);
```
