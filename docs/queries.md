# Query Builder Module

## Overview
The Query Builder module allows developers to construct, save, manage, and execute complex SQL SELECT queries safely. It provides two visual editing modalities: a GUI-driven builder (selecting tables, fields, adding visual WHERE conditionals, ordering, and imposing limits) and a raw, SELECT-validated freeform SQL editor panel. It supports runtime parameterized placeholders (e.g., `:status`, `:start_date`), visual input forms for parameter binding, and direct CSV exporting.

---

## Architecture & Key Files
- **`modules/queries/queries.php`**: Standard interface rendering saved query portfolios, dynamic condition blocks, and results tables.
- **`api/queries.php`**: Secure background executor validating all operations as SELECT-only, executing queries, processing dynamic parameters, and rendering results.
- **`core/QueryExecutor.php`**: Foundational abstraction layer validating, parsing, and executing safe read-only SQL commands.

---

## Technical Details

### Database Schema (`nu_queries`)
Saved custom reporting queries are archived within the `nu_queries` table:

```sql
CREATE TABLE IF NOT EXISTS nu_queries (
    query_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    query_code        VARCHAR(100)  NOT NULL UNIQUE, -- Unique code identifier
    query_name        VARCHAR(255)  NOT NULL,
    query_description VARCHAR(500)  DEFAULT NULL,
    query_sql         TEXT          NOT NULL,        -- Complete SELECT query string
    query_parameters  TEXT          DEFAULT NULL,    -- JSON configuration listing expected parameter types
    query_active      TINYINT(1)    NOT NULL DEFAULT 1,
    query_created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    query_updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_query_code (query_code),
    INDEX idx_query_active (query_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/queries.php?action=get_tables` | `GET` | `get_tables` | Lists non-system tables in the database. |
| `/api/queries.php?action=get_columns&table=X` | `GET` | `get_columns` | Pulls column details for a specific table. |
| `/api/queries.php?action=get&id=X` | `GET` | `get` | Resolves query record metadata. |
| `/api/queries.php?action=save` | `POST` | `save` | Writes new or updates existing query records. |
| `/api/queries.php?action=run&code=X` | `GET` | `run` | Safely evaluates and returns raw rows for a saved query code. |
| `/api/queries.php?action=export&code=X` | `GET` | `export` | Automatically compiles and streams query rows as a CSV file. |

---

## Usage Examples

### SQL Schema Structure of Parameterized Queries
```sql
-- Seed default parameterized customer log summary
INSERT INTO nu_queries (query_name, query_code, query_description, query_sql, query_parameters)
VALUES (
    'Orders by Location and Status',
    'orders_by_location_status',
    'Returns a list of customer orders matching location and status parameters.',
    'SELECT order_id, customer_name, total_amount, status, created_at FROM orders WHERE location = :location AND status = :status ORDER BY created_at DESC',
    '{"location": {"label": "Location", "type": "text", "default": "New York"}, "status": {"label": "Status", "type": "text", "default": "active"}}'
);
```

### Executing a Parameterized Query with JSON payloads
```javascript
// Executes parameterized query matching 'active' status and 'New York' location
const parameters = {
  location: "New York",
  status: "active"
};

const queryParams = new URLSearchParams({
  action: "run",
  code: "orders_by_location_status",
  ...parameters
});

fetch('api/queries.php?' + queryParams.toString(), {
  method: 'GET',
  credentials: 'same-origin'
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Records matching parameters:", data.data.records);
  } else {
      console.error("Query execution failed:", data.error);
  }
});
```
