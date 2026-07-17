# Forms Module

## Overview
The Forms module is the visual drag-and-drop core of the nuvis application builder. It provides a visual editor used to layout relational forms (with rows and columns spanning up to a 12-column grid system), auto-generate underlying database tables, integrate custom JS events (on-load, before-save, after-save), inject scoped CSS layouts, configure role-based browse filters, and define custom PHP hooks for advanced runtime validations.

---

## Architecture & Key Files
- **`modules/forms/forms.php`**: The drag-and-drop builder canvas utilizing custom, responsive, grid-based card models.
- **`assets/js/nb-form-builder.js`**: Core drag-and-drop state manager, element drop-zone validation, and layout JSON generation.
- **`core/FormRenderer.php`**: Renders live operational HTML pages by reading JSON form structures from metadata.
- **`api/form.php`**: Form rendering layout provider, relational lookup resolution, subform record syncing, and select grid configurations.
- **`api/form-handler.php`**: Target destination processing form validations, record inserts, relational updates, and transaction auditing.

---

## Technical Details

### Metadata Schema (`nu_forms`)
The physical configuration details and JSON representations are saved within `nu_forms`:

```sql
CREATE TABLE IF NOT EXISTS nu_forms (
    form_id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_code             VARCHAR(100)  NOT NULL UNIQUE, -- System identification slug
    form_name             VARCHAR(255)  NOT NULL,
    form_table            VARCHAR(100)  NOT NULL,        -- Associated DB table
    form_pk_type          VARCHAR(32)   NOT NULL DEFAULT 'autoincrement', -- autoincrement | uuid
    form_table_mode       VARCHAR(32)   NOT NULL DEFAULT 'new',           -- new | existing
    form_type             VARCHAR(20)   NOT NULL DEFAULT 'main',          -- main | subform | popup | report
    form_layout           LONGTEXT      NOT NULL,        -- Complete fields hierarchy serialized to JSON
    browse_display_mode   VARCHAR(32)   NOT NULL DEFAULT 'inline',        -- inline | modal | fullpage
    form_browse_sql       TEXT          DEFAULT NULL,
    form_browse_columns   TEXT          DEFAULT NULL,
    form_browse_page_size INT UNSIGNED  DEFAULT 20,
    form_custom_js        LONGTEXT      DEFAULT NULL,
    form_js_before_save   LONGTEXT      DEFAULT NULL,
    form_js_after_save    LONGTEXT      DEFAULT NULL,
    form_custom_php       LONGTEXT      DEFAULT NULL,
    form_custom_css       LONGTEXT      DEFAULT NULL,
    form_browse_php       LONGTEXT      DEFAULT NULL,
    form_active           TINYINT(1)    NOT NULL DEFAULT 1,
    form_created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    form_updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_form_type (form_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Usage Examples

### SQL Schema Structure of Form Definitions
```sql
-- Select all active standalone forms configured for dashboard display
SELECT form_code, form_name, form_table, browse_display_mode
FROM nu_forms
WHERE form_active = 1 AND form_type = 'main'
ORDER BY form_name ASC;
```

### Advanced Layout Grid JSON Format (Serialized in `form_layout`)
The visual layout represents rows containing nested collections of 12-column span grids:

```json
[
  {
    "id": "row_1680000001",
    "fields": [
      {
        "id": "f_name",
        "type": "text",
        "name": "cust_name",
        "label": "Customer Name",
        "col": 6,
        "required": true
      },
      {
        "id": "f_email",
        "type": "email",
        "name": "cust_email",
        "label": "Email Address",
        "col": 6,
        "required": true
      }
    ]
  },
  {
    "id": "row_1680000002",
    "fields": [
      {
        "id": "f_signature",
        "type": "signaturepad",
        "name": "cust_sig",
        "label": "Authorize Signature",
        "col": 12,
        "canvas_width": 500,
        "canvas_height": 200,
        "storage_mode": "base64"
      }
    ]
  }
]
```
