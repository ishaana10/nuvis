# Report Dashboards Module

## Overview
The Report Dashboards module provides a visual administrative builder used to organize multiple reports under unified dashboard portlets. It features role-based restrictions (assigning dynamic visibilities to distinct nested reports) and provides an interactive filter panel bar allowing end-users to apply unified inputs (such as Month, Quarter, Year, or Lookups) directly across selected data tables.

---

## Architecture & Key Files
- **`modules/report_dashboards/report_dashboards.php`**: Frontend workspace displaying active dashboard grids, running interactive filters, and hosting config parameters.
- **`api/report_dashboards.php`**: Secure administrative backend retrieving physical database statistics, evaluating custom report SQL bindings, and mapping multi-dimensional dashboard arrays.

---

## Technical Details

### Dashboard Config Metadata (`nu_report_dashboards`)
Dashboard profiles, included reporting arrays, permissions, and unified filters are archived in `nu_report_dashboards`:

```sql
CREATE TABLE IF NOT EXISTS nu_report_dashboards (
    dashboard_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dashboard_code        VARCHAR(100)  NOT NULL UNIQUE, -- System identification slug
    dashboard_name        VARCHAR(255)  NOT NULL,
    dashboard_description VARCHAR(500)  DEFAULT NULL,
    dashboard_role_access LONGTEXT      DEFAULT NULL,    -- JSON list of restricted roles (None = All Roles)
    dashboard_filters     LONGTEXT      DEFAULT NULL,    -- JSON parameters describing unified filters
    dashboard_reports     LONGTEXT      DEFAULT NULL,    -- JSON configuration listing nested reports and sub-permissions
    dashboard_active      TINYINT(1)    NOT NULL DEFAULT 1,
    dashboard_created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dashboard_updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dash_code (dashboard_code),
    INDEX idx_dash_active (dashboard_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/report_dashboards.php?action=list` | `GET` | `list` | Lists active dashboards available for the current user's role. |
| `/api/report_dashboards.php?action=get&id=X` | `GET` | `get` | Resolves unified filters, included reports, and custom roles configurations. |
| `/api/report_dashboards.php?action=lookup_options` | `GET` | `lookup_options` | Dynamic options provider fetching relational parameters for lookup filters. |
| `/api/report_dashboards.php?action=run_report` | `GET` | `run_report` | Executes a specific report under the dashboard, binding unified filter parameters. |
| `/api/report_dashboards.php?action=save` | `POST` | `save` | Writes new or updates existing dashboard records. |
| `/api/report_dashboards.php?action=delete` | `POST` | `delete` | Disposes of a specific report dashboard. |

---

## Usage Examples

### SQL Schema Structure of Report Dashboards
```sql
-- Retrieve all active report dashboards allowed for a specific user role
SELECT dashboard_id, dashboard_name, dashboard_code, dashboard_description
FROM nu_report_dashboards
WHERE dashboard_active = 1
  AND (dashboard_role_access IS NULL
       OR dashboard_role_access = '[]'
       OR JSON_CONTAINS(dashboard_role_access, '"manager"'));
```

### Complete Dashboard Configurations Payload JSON
An example JSON structure saved in the database representing unified configurations:

```json
{
  "dashboard_name": "Executive Revenue Summary",
  "dashboard_code": "executive_revenue_summary",
  "dashboard_description": "Global corporate revenue reports filtered by month and sales location.",
  "dashboard_active": 1,
  "dashboard_role_access": ["admin", "executive"],
  "dashboard_filters": [
    {
      "field": "month",
      "label": "Select Month",
      "type": "month",
      "operator": "="
    },
    {
      "field": "loc_id",
      "label": "Sales Office",
      "type": "lookup",
      "operator": "=",
      "lookup_table": "locations",
      "lookup_val_col": "loc_id",
      "lookup_lbl_col": "loc_name"
    }
  ],
  "dashboard_reports": [
    {
      "report_id": 12,
      "report_name": "Monthly Region Profit",
      "allowed_roles": ["admin"]
    },
    {
      "report_id": 15,
      "report_name": "Branch Sales Breakdown",
      "allowed_roles": ["admin", "executive"]
    }
  ]
}
```
