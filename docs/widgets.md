# Dashboard Widgets Module

## Overview
The Dashboard Widgets module is a responsive, highly configurable portal dashboard rendering system. It lets administrators create, drag-and-drop, resize, and style dynamic data widgets (ranging from KPI statistics, line/bar/pie charts, data tables, quick-link lists, progress bars, and freeform HTML) scoped globally, to specific user roles, or to individual user accounts.

---

## Architecture & Key Files
- **`modules/widgets/widgets.php`**: Foundational template rendering and compiling dynamic widgets using structured PHP functions.
- **`modules/widgets/widgets.js`**: Core drag-and-drop layout manager, live preview parser, and Font Awesome icon filter.
- **`modules/dashboard/widget_api.php`**: REST endpoint backend executing safe queries, revoking widget objects, and writing serialized configs.

---

## Technical Details

### Widgets Database Schema (`nu_dashboard_widgets`)
Properties, dimensional span definitions, roles, and JSON-encoded configurations are indexed in `nu_dashboard_widgets`:

```sql
CREATE TABLE IF NOT EXISTS nu_dashboard_widgets (
    widget_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_user_id  INT UNSIGNED  DEFAULT NULL,      -- Scoped to specific user (Null = Global default)
    widget_role     VARCHAR(50)   DEFAULT NULL,      -- Default role placement (e.g. 'sales_clerk')
    widget_type     VARCHAR(32)   NOT NULL,          -- stat, chart_bar, chart_line, chart_pie, table, list, progress, custom
    widget_title    VARCHAR(100)  NOT NULL,
    widget_icon     VARCHAR(50)   DEFAULT NULL,      -- Font Awesome or direct Emoji glyphs
    widget_width    TINYINT(1)    NOT NULL DEFAULT 2, -- grid-column span (1 = 3 cols, 2 = 6, 3 = 9, 4 = 12 cols)
    widget_height   TINYINT(1)    NOT NULL DEFAULT 1, -- grid-row span height
    widget_position INT UNSIGNED  NOT NULL DEFAULT 0, -- Reorder sort values
    widget_config   TEXT          DEFAULT NULL,      -- JSON-encoded options (SQL strings, colors, HTML content)
    widget_active   TINYINT(1)    NOT NULL DEFAULT 1,
    widget_created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wgt_user (widget_user_id),
    INDEX idx_wgt_role (widget_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/modules/dashboard/widget_api.php?action=get` | `GET` | `get` | Resolves target widget configurations. |
| `/modules/dashboard/widget_api.php?action=save` | `POST` | `save` | Writes new or updates existing widget records. |
| `/modules/dashboard/widget_api.php?action=delete` | `POST` | `delete` | Disposes of a specific dashboard widget. |

---

## Usage Examples

### SQL Schema Structure of Custom Statistics Widget
```sql
-- Seed default statistic KPI widget for active manager role
INSERT INTO nu_dashboard_widgets (
    widget_role, widget_type, widget_title, widget_icon,
    widget_width, widget_height, widget_config
) VALUES (
    'manager',
    'stat',
    'Overdue Accounts',
    'fa-clock',
    2, -- Half-width grid
    1,
    '{"sql": "SELECT COUNT(*) as value FROM orders WHERE status = \'overdue\'", "subtitle": "Orders past delivery deadline", "color": "error", "link_module": "orders_checkout", "link_mode": "inline"}'
);
```

### Rendering a Chart Widget inside JavaScript using Chart.js
```javascript
// Target canvas mounted on widget_id: 'wc_42'
const chartCanvas = document.getElementById('wc_42');
const configData = JSON.parse(chartCanvas.dataset.chartjs);

// Render interactive Chart.js node directly
new Chart(chartCanvas, {
    type: configData.type,
    data: configData.data,
    options: configData.options
});
```
