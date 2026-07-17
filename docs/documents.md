# Document Management Module

## Overview
The Document Management module coordinates file sharing, metadata archiving, categorization, and digital signatures. It links file uploads (via the core Files table) with workflow processes, approval cycles, and contains an interactive digital signature drawing pad utilizing HTML5 canvas directly in modal panels.

---

## Architecture & Key Files
- **`modules/documents/documents.php`**: Document view containing category listings, direct upload actions, download bridges, and the interactive drawing canvas signature pad.
- **`api/document.php`**: Server-side document CRUD, file association hooks, and signature image asset binding.

---

## Technical Details

### Database Schemas
The management flow is controlled by the `nu_documents` table, which references physical files loaded inside the core uploads pool (`nu_files` table):

```sql
CREATE TABLE IF NOT EXISTS nu_documents (
    doc_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_title       VARCHAR(255)  NOT NULL,
    doc_description TEXT          DEFAULT NULL,
    doc_category    VARCHAR(64)   DEFAULT NULL,
    doc_status      VARCHAR(32)   NOT NULL DEFAULT 'draft',
    doc_file_id     INT UNSIGNED  DEFAULT NULL,
    doc_created_by  VARCHAR(64)   DEFAULT NULL,
    doc_created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_doc_file (doc_file_id),
    INDEX idx_doc_status (doc_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Usage Examples

### SQL Table Registration and Associations
To link a documents layout with files upload entries:

```sql
-- Retrieve complete document profile with file details
SELECT d.*, f.file_name, f.file_original_name, f.file_mime_type, u.usr_username
FROM nu_documents d
LEFT JOIN nu_files f ON d.doc_file_id = f.file_id
LEFT JOIN nu_users u ON d.doc_created_by = u.usr_id;
```

### Initializing and Drawing Signature in Canvas
HTML Canvas coordinates can be mapped directly to capture base64 drawn paths on touch/mouse triggers:

```javascript
// Drawing Pad setup script example
const canvas = document.getElementById('sigCanvas');
const ctx = canvas.getContext('2d');
let drawing = false;

canvas.addEventListener('mousedown', (e) => {
    drawing = true;
    ctx.beginPath();
    ctx.moveTo(e.offsetX, e.offsetY);
});

canvas.addEventListener('mousemove', (e) => {
    if (drawing) {
        ctx.lineTo(e.offsetX, e.offsetY);
        ctx.strokeStyle = 'var(--text-main)';
        ctx.lineWidth = 2;
        ctx.stroke();
    }
});

canvas.addEventListener('mouseup', () => {
    drawing = false;
});

function getSignatureBase64() {
    return canvas.toDataURL('image/png');
}
```
