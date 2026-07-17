# Roles & Permissions Module

## Overview
The Roles & Permissions module implements granular Role-Based Access Control (RBAC) across nuvis form actions. It allows administrators to register custom user roles and configure permission matrices governing rights (View, Add, Edit, Delete, Export) per-form, using a global default fallback (`*`) layer for rapid initialization.

---

## Architecture & Key Files
- **`modules/roles/roles.php`**: Standard control panel displaying active user roles list, custom role addition modals, and interactive permission grids.
- **`api/roles.php`**: REST gate compiling active roles, saving matrix updates, resolving form catalogs, and preventing deletions of protected system roles.

---

## Technical Details

### Security Fallback Row (`*`)
The permission matrix structures access via form-level rows. The top-most row is designated as `*` (All Forms). When evaluating an action permission (e.g. `can_edit`) for a specific form code (e.g. `customer_registration`), if no specific rule entry is declared for that form, the system defaults to the values specified in the `*` record.

### System Protected Roles
Certain pre-seeded roles (e.g., `'globeadmin'`, `'admin'`) are flagged with `role_is_system = 1` inside the schema, protecting them against deletion or configuration overrides.

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/roles.php?action=list` | `GET` | `list` | Lists registered roles and descriptions. |
| `/api/roles.php?action=get&role_code=X` | `GET` | `get` | Resolves target role permission matrix rows. |
| `/api/roles.php?action=forms` | `GET` | `forms` | Lists active forms currently registered inside the builder. |
| `/api/roles.php?action=create` | `POST` | `create` | Spawns a custom role code. |
| `/api/roles.php?action=update&role_code=X` | `POST` | `update` | Overwrites role names and metadata. |
| `/api/roles.php?action=delete&role_code=X` | `POST` | `delete` | Disposes of custom roles. |
| `/api/roles.php?action=save_perms&role_code=X` | `POST` | `save_perms` | Overwrites detailed permission matrices. |

---

## Usage Examples

### SQL Schema Structure of Roles and Access Matrix
```sql
-- Role details
CREATE TABLE IF NOT EXISTS nu_roles (
    role_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_code        VARCHAR(50)   NOT NULL UNIQUE, --lowercase alphanumeric code
    role_name        VARCHAR(100)  NOT NULL,
    role_description VARCHAR(255)  DEFAULT NULL,
    role_is_system   TINYINT(1)    NOT NULL DEFAULT 0,
    role_created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_code (role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Form-level permissions matrix mapping
CREATE TABLE IF NOT EXISTS nu_role_permissions (
    rfp_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rfp_role_code   VARCHAR(50)   NOT NULL,
    rfp_form_code   VARCHAR(100)  NOT NULL, -- Target form code or '*' fallback
    rfp_can_view    TINYINT(1)    NOT NULL DEFAULT 0,
    rfp_can_add     TINYINT(1)    NOT NULL DEFAULT 0,
    rfp_can_edit    TINYINT(1)    NOT NULL DEFAULT 0,
    rfp_can_delete  TINYINT(1)    NOT NULL DEFAULT 0,
    rfp_can_export  TINYINT(1)    NOT NULL DEFAULT 0,
    UNIQUE KEY uq_role_form (rfp_role_code, rfp_form_code),
    INDEX idx_rfp_role (rfp_role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Saving Permissions via fetch API
```javascript
const permissionRows = [
  {
    form_code: "*", // Global fallback
    can_view: 1,
    can_add: 0,
    can_edit: 0,
    can_delete: 0,
    can_export: 0
  },
  {
    form_code: "orders_checkout", // Form overrides
    can_view: 1,
    can_add: 1,
    can_edit: 1,
    can_delete: 0,
    can_export: 1
  }
];

fetch('api/roles.php?action=save_perms&role_code=sales_clerk', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ permissions: permissionRows })
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Permissions updated successfully.");
  }
});
```
