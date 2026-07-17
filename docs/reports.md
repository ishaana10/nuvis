# Reports Module

## Overview
The Reports module manages custom reporting models on the nuvis platform. It allows developers to build SQL-driven queries, design structured output views (with customized field labels and run-time filter criteria), and toggle between three visualization layouts: Standard Grid Tables, Interactive Pivot Charts (using WebDataRocks), or custom CSS Bar Chart overlays.

---

## Architecture & Key Files
- **`modules/reports/reports.php`**: Standard reporting wizard hosting SQL helper options, tab views, and result rendering panels.
- **`api/report.php`**: Secure administrative backend retrieving physical database statistics, evaluating custom report SQL bindings, and mapping query parameter filters.
- **`core/ReportRenderer.php`**: Server-side output compiler translating raw data strings into live, styled visual tables.

---

## Technical Details

### Reports Metadata (`nu_reports`)
Query configurations, column aliases, view parameters, and filter conditions are indexed in `nu_reports`:

```sql
CREATE TABLE IF NOT EXISTS nu_reports (
    report_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_code      VARCHAR(100)  NOT NULL UNIQUE, -- Unique code identifier
    report_name      VARCHAR(255)  NOT NULL,
    report_type      VARCHAR(32)   NOT NULL DEFAULT 'table', -- table | chart | summary
    report_view_mode VARCHAR(32)   NOT NULL DEFAULT 'table', -- table | webdatarocks | chart
    report_sql       TEXT          NOT NULL,                 -- Primary SELECT statement
    report_columns   TEXT          DEFAULT NULL,             -- JSON column field names and titles mapping
    report_filters   TEXT          DEFAULT NULL,             -- JSON parameter arrays specifying filters
    report_active    TINYINT(1)    NOT NULL DEFAULT 1,
    report_created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    report_updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rep_code (report_code),
    INDEX idx_rep_active (report_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/report.php?action=list` | `GET` | `list` | Lists all active reports. |
| `/api/report.php?action=get&id=X` | `GET` | `get` | Resolves report query and layouts specifications. |
| `/api/report.php?action=tables` | `GET` | `tables` | Returns list of database tables. |
| `/api/report.php?action=columns&table=X` | `GET` | `columns` | Pulls column details for a specific table. |
| `/api/report.php?action=preview` | `POST` | `preview` | Evaluates a raw SQL string and returns rows. |
| `/api/report.php?action=run&id=X` | `GET` | `run` | Safely evaluates and returns raw rows for a saved report, applying filter strings. |
| `/api/report.php?action=save` | `POST` | `save` | Writes new or updates existing report configurations. |
| `/api/report.php?action=delete` | `POST` | `delete` | Disposes of a specific report record. |

---

## Usage Examples

### SQL Schema Structure of SQL Reports
```sql
-- Seed default branch sales summary report
INSERT INTO nu_reports (report_name, report_code, report_type, report_view_mode, report_sql, report_columns, report_filters)
VALUES (
    'Branch Monthly Sales',
    'branch_monthly_sales',
    'table',
    'webdatarocks',
    'SELECT branch_name, category, SUM(amount) AS sales FROM sales GROUP BY branch_name, category',
    '[{"field": "branch_name", "label": "Branch"}, {"field": "category", "label": "Product Category"}, {"field": "sales", "label": "Sales Revenue"}]',
    '[{"field": "branch_name", "label": "Region Filter", "operator": "="}]'
);
```

### Running and Fetching Report Rows with Filters via fetch API
```javascript
const parameters = {
  branch_name: "Chicago"
};

const queryParams = new URLSearchParams({
  action: "run",
  id: 42, // Target report ID
  ...parameters
});

fetch('api/report.php?' + queryParams.toString(), {
  method: 'GET',
  credentials: 'same-origin'
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Filtered report records:", data.data);
      console.log("Defined column mappings:", data.columns);
  } else {
      console.error("Query syntax error:", data.error);
  }
});
```
