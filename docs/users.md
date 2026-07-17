# Users & Custom Meta Fields Module

## Overview
The Users module handles complete user account lifecycles. It coordinates login registration, role mappings, and password hashing (via bcrypt), and supports extensible developer-defined User Metadata attributes (such as locations or regions) parsed directly from the `config.user_fields.php` metadata configuration file.

---

## Architecture & Key Files
- **`modules/users/users.php`**: Standard account list displaying core login stats, metadata parameters, edit modals, and security triggers.
- **`api/users.php`**: REST endpoint routing user creations, overrides, password resets, and metadata key updates.
- **`core/Auth.php`**: The foundational `NuAuth` system class handling active credentials validation, session regeneration, and privilege checks.
- **`config.user_fields.php`**: Developer configuration file specifying extensible custom metadata attributes, types, selection menus, and display parameters.

---

## Technical Details

### Extensible User Meta Schema
Traditional schemas restrict attributes strictly to pre-compiled structural columns. The nuvis platform bypasses this constraint using an Entity-Attribute-Value (EAV) model mapping extra custom user fields to the `nu_user_meta` table:

```sql
-- Main user credentials
CREATE TABLE IF NOT EXISTS nu_users (
    usr_id         VARCHAR(36)   NOT NULL PRIMARY KEY, -- Supports VARCHAR(36) UUIDs
    usr_username   VARCHAR(100)  NOT NULL UNIQUE,
    usr_email      VARCHAR(255)  DEFAULT NULL,
    usr_password   VARCHAR(255)  NOT NULL,             -- Bcrypt hashed string
    usr_role       VARCHAR(50)   NOT NULL,
    usr_active     TINYINT(1)    NOT NULL DEFAULT 1,
    usr_created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usr_username (usr_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dynamic metadata attributes
CREATE TABLE IF NOT EXISTS nu_user_meta (
    umeta_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    umeta_user_id  VARCHAR(36)   NOT NULL,             -- Relational foreign key
    umeta_key      VARCHAR(100)  NOT NULL,             -- e.g. 'location', 'region'
    umeta_value    LONGTEXT      DEFAULT NULL,
    UNIQUE KEY uq_user_meta (umeta_user_id, umeta_key),
    INDEX idx_umeta_user (umeta_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/users.php?action=create` | `POST` | `create` | Registers new credentials, hashes passwords, and saves metadata pairings. |
| `/api/users.php?action=update` | `POST` | `update` | Overwrites active account details, resets emails, and synchronizes EAV metadata blocks. |
| `/api/users.php?action=delete` | `POST` | `delete` | Disposes of custom accounts and linked dynamic metadata. |

---

## Usage Examples

### Defining Custom Fields inside `config.user_fields.php`
```php
<?php
// config.user_fields.php
return [
    [
        'key'     => 'location',
        'label'   => 'Office Location',
        'type'    => 'select',
        'options' => ['New York', 'London', 'Tokyo', 'Sydney'],
        'global'  => true -- Injected globally into active user session context
    ],
    [
        'key'     => 'department',
        'label'   => 'Department',
        'type'    => 'text'
    ]
];
```

### Creating a New Account with Meta Attributes
```javascript
const newUserPayload = {
  username: "janesmith",
  email: "jane.smith@example.com",
  role: "manager",
  password: "Str0ngP@ssword1!",
  active: 1,
  meta: {
    location: "Tokyo",
    department: "Quality Assurance"
  }
};

fetch('api/users.php?action=create', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(newUserPayload)
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Account spawned successfully.");
  }
});
```
