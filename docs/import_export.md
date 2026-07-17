# Import / Export Module

## Overview
The Import / Export module manages structured batch operations. It supports exporting database table records into CSV or JSON files and importing CSV arrays safely back into user tables with dynamic field mapping.

---

## Architecture & Key Files
- **`modules/import_export/import_export.php`**: Standard control panel UI to choose source/target tables and upload CSV streams.
- **`api/export.php`**: Pulls table datasets and formats them into a CSV or JSON attachment stream for browser download.
- **`api/import.php`**: Resolves files uploaded via Multipart Forms, parses lines, maps header indexes to columns, and runs bulk transaction inserts safely.

---

## Technical Details

### Security Gateways
Bulk operations are highly restricted and automatically block system core metadata tables (`nu_users`, `nu_forms`, `nu_menus`, `nu_roles`) unless accessed by a user possessing authenticated `globeadmin` permission levels. This prevents unvalidated users from injecting structural modifications into tables or overwriting security configurations.

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/export.php?table=X&format=Y` | `GET` | N/A | Extracts complete table records to a download stream. |
| `/api/import.php` | `POST` | N/A | Receives a target table parameter and a CSV file, then runs batch insertion loops. |

---

## Usage Examples

### Executing an Export via JavaScript Client
```javascript
const table = "customers";
const format = "json"; // or "csv"

// Opens a new tab or window to initiate the standard browser download stream
window.open(`api/export.php?table=${encodeURIComponent(table)}&format=${format}`, '_blank');
```

### Direct Import Upload stream
```javascript
const importFile = document.getElementById('importFile').files[0];
const targetTable = document.getElementById('importTable').value;

const payload = new FormData();
payload.append('table', targetTable);
payload.append('file', importFile);

fetch('api/import.php', {
  method: 'POST',
  body: payload
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      alert(`Import completed! Successfully loaded ${data.imported_rows} records.`);
  } else {
      alert("Error uploading records: " + data.error);
  }
});
```
