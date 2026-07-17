# Menu Builder Module

## Overview
The Menu Builder module manages navigation routing, permission access, parent-child hierarchies, and icon styling across the sidebar menu structure. It allows administrators to map navigation items directly to forms, custom reports, raw SQL queries, or absolute external HTTP URLs, and restrict visibility depending on active user roles.

---

## Architecture & Key Files
- **`modules/menus/menus.php`**: The backend-rendered control panel tree interface enabling item configurations, nested tree mapping, and drag-and-drop hierarchy sorting.
- **`modules/menus/api/menus.php`**: REST endpoint handling database CRUD operations, parent-child relationships, role visibility list updates, and sorting states.
- **`core/MenuRenderer.php`**: System template renderer drawing raw SVG layouts and compiling localized sidebars recursively based on active credentials.

---

## Technical Details

### Database Schema (`nu_menus`)
Menu hierarchy, open modes, and role-based permissions are managed via `nu_menus`:

```sql
CREATE TABLE IF NOT EXISTS nu_menus (
    menu_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_parent_id    INT UNSIGNED  NOT NULL DEFAULT 0, -- Parent container id (0 = top-level)
    menu_label        VARCHAR(100)  NOT NULL,
    menu_type         VARCHAR(24)   NOT NULL DEFAULT 'form', -- form | report | query | url | group | divider
    menu_target       VARCHAR(255)  DEFAULT NULL,            -- Identification code target (form_code, query_code)
    menu_icon         VARCHAR(255)  NOT NULL DEFAULT 'default',
    menu_order        INT UNSIGNED  NOT NULL DEFAULT 0,
    menu_browse_mode  VARCHAR(32)   NOT NULL DEFAULT 'inline', -- inline | popup
    menu_preview_mode VARCHAR(32)   NOT NULL DEFAULT 'inline', -- inline | popup
    menu_default_view VARCHAR(32)   NOT NULL DEFAULT 'browse', -- browse | preview
    menu_active       TINYINT(1)    NOT NULL DEFAULT 1,
    menu_roles        VARCHAR(500)  DEFAULT NULL, -- comma-separated allowed roles (e.g. 'admin,manager')
    INDEX idx_menu_parent (menu_parent_id),
    INDEX idx_menu_order  (menu_order),
    INDEX idx_menu_active (menu_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/modules/menus/api/menus.php?action=get&id=X` | `GET` | `get` | Resolves item properties including parents and visibility. |
| `/modules/menus/api/menus.php?action=roles` | `GET` | `roles` | Lists available role visibility filters. |
| `/modules/menus/api/menus.php?action=create` | `POST` | `create` | Spawns a new menu record. |
| `/modules/menus/api/menus.php?action=update` | `POST` | `update` | Overwrites active node properties. |
| `/modules/menus/api/menus.php?action=reorder` | `POST` | `reorder` | Sets relative `menu_order` ranks across sorted arrays. |

---

## Usage Examples

### SQL Schema Structure and Relational Hierarchies
```sql
-- Retrieve nested sidebar configurations matching authorized roles
SELECT m.*
FROM nu_menus m
WHERE m.menu_active = 1
  AND (m.menu_roles IS NULL OR FIND_IN_SET('admin', m.menu_roles) > 0)
ORDER BY m.menu_parent_id ASC, m.menu_order ASC;
```

### Saving a New Menu Item via JavaScript payload
```javascript
const savePayload = {
  type: "form",
  label: "Customer Orders",
  target: "customer_orders", // Maps to a form code
  parent: 0,                 // Root menu item
  order: 10,
  roles: ["admin", "manager"], // Select2 array mapped to CSV in api
  active: 1,
  icon: "shopping-cart",
  browse_mode: "inline",
  preview_mode: "popup",
  default_view: "browse"
};

fetch('modules/menus/api/menus.php?action=create', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(savePayload)
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Navigation node added successfully.");
  }
});
```
