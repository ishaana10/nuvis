# File Manager Module

## Overview
The File Manager module organizes global file attachments and physical assets uploaded across form attachments, signature pads, or dynamic document views. It records files systematically inside a secure directory, manages MIME-type and size limitations, and tracks active records inside a unified metadata index.

---

## Architecture & Key Files
- **`modules/files/files.php`**: Frontend grid layout allowing permission-gated direct file uploads, listing file dimensions/sizes, and offering view and delete operations.
- **`api/upload.php`**: Primary upload stream interceptor validating file structures and writing assets securely.

---

## Technical Details

### Database Schema (`nu_files`)
Each uploaded item registers a detailed transaction index in `nu_files`:

```sql
CREATE TABLE IF NOT EXISTS nu_files (
    file_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name          VARCHAR(255)  NOT NULL, -- Random, safe system filename
    file_original_name VARCHAR(255)  NOT NULL, -- Source filename on user machine
    file_size          INT UNSIGNED  NOT NULL, -- Size in bytes
    file_mime_type     VARCHAR(128)  NOT NULL,
    file_path          VARCHAR(500)  NOT NULL, -- Relative location path
    file_user_id       VARCHAR(64)   DEFAULT NULL,
    file_uploaded_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file_user (file_user_id),
    INDEX idx_file_mime (file_mime_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Usage Examples

### SQL Schema Integration
```sql
-- Query physical file metadata alongside linked records
SELECT f.file_name, f.file_original_name, f.file_mime_type
FROM nu_files f
WHERE f.file_user_id = 'usr_admin123';
```

### Uploading a File via fetch API
```javascript
const fileInput = document.getElementById('fileInput');
const formData = new FormData();
formData.append('file', fileInput.files[0]);

fetch('api/upload.php', {
  method: 'POST',
  body: formData
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("File uploaded as:", data.file_name);
  } else {
      console.error("Upload failed:", data.error);
  }
});
```
