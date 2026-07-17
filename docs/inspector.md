# DB & Server Inspector Module

## Overview
The DB & Server Inspector module serves as a comprehensive visual diagnostic terminal designed exclusively for administrative roles (`globeadmin` and `admin`). It merges structural schema exploration, interactive SQL runner playgrounds, sandboxed physical file/script browsers, and low-level system configuration metrics.

---

## Architecture & Key Files
- **`modules/inspector/inspector.php`**: Inspector console rendering tab controls, quick-launch database links, directory trees, file view textareas, and SQL inputs.
- **`api/inspector.php`**: Secure administrative backend retrieving physical database statistics, evaluating custom SQL strings, indexing disk assets, reading local directories, and updating server script files.

---

## Technical Details

### Administrative Quick-Launch Controls
The module reads server contexts to construct direct, authenticated redirections to common server dashboards:
- **phpMyAdmin URL**: Scans directories for common local pma paths (`phpmyadmin`, `phpMyAdmin`, `pma`) to resolve routes, otherwise redirects safely to the root cPanel Databases gate.
- **File Manager URL**: Configures relative routes targeting cPanel File Manager direct ports (`:2083/frontend/.../filemanager`).
- **cPanel Direct Link**: Resolves raw ports to target control domains.

*Links can be dynamically customized and overwritten per-user in local browser sandboxes via `localStorage` values.*

### SQL Evaluation Playground
Inputs executed in the SQL Runner undergo validation:
- All `SELECT` statements return structured, formatted interactive tables.
- All non-select write actions (`INSERT`, `UPDATE`, `ALTER`, `DROP`) are evaluated, logging absolute row effects.
- Direct execution parameters are strictly checked via the `Auth` session validator to prevent non-admin escalation.

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/inspector.php?action=tables` | `GET` | `tables` | Lists physical database tables. |
| `/api/inspector.php?action=columns&table=X` | `GET` | `columns` | Extracts schema structures, field properties, null options, primary keys, and defaults. |
| `/api/inspector.php?action=data&table=X` | `GET` | `data` | Retrieves row previews for verification. |
| `/api/inspector.php?action=sql` | `POST` | `sql` | Executes a raw SQL string input. |
| `/api/inspector.php?action=files&path=X` | `GET` | `files` | Lists directory directories/files, or reads content for file viewing. |
| `/api/inspector.php?action=file_write` | `POST` | `file_write` | Writes text content safely to a target asset path. |
| `/api/inspector.php?action=serverinfo` | `GET` | `serverinfo` | Retrieves server PHP/OS information, disk availability, memory limits, and enabled extensions. |

---

## Usage Examples

### Executing an Ad-hoc SQL Statement via fetch API
```javascript
const sqlString = "SELECT usr_username, usr_role FROM nu_users WHERE usr_active = 1 LIMIT 5;";

fetch('api/inspector.php?action=sql', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ sql: sqlString })
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Query returned:", data.rows);
  } else {
      console.error("Query syntax error:", data.error);
  }
});
```

### Navigating Directories programmatically
```javascript
// Requesting specific directory contents
fetch('api/inspector.php?action=files&path=' + encodeURIComponent('core/'))
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Found core directories/files:", data.entries);
  }
});
```
