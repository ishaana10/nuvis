# Email Settings Module

## Overview
The Email Settings module enables system administrators to customize global SMTP server configurations, manage reusable custom HTML templates with standard placeholder syntax (`{{variable_name}}`), and monitor an immutable log of sent/failed messages for delivery auditing.

---

## Architecture & Key Files
- **`modules/email_settings/email_settings.php`**: The dynamic configuration interface rendering SMTP properties, template creators, and server logs.
- **`api/email.php`**: REST endpoint routing setting updates, template operations, logs reading, and test message firing.
- **`core/EmailService.php`**: The low-level mail driver wrapping PHP `mail()` and dedicated SMTP integrations safely.

---

## Technical Details

### Reusable Email Templates (`nu_email_templates`)
Templates support standard dynamic placeholders that compile out variables on dispatch:

```sql
CREATE TABLE IF NOT EXISTS nu_email_templates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255)  NOT NULL,
    slug        VARCHAR(255)  NOT NULL UNIQUE,
    subject     VARCHAR(255)  NOT NULL,
    body        LONGTEXT      NOT NULL,
    description VARCHAR(255)  DEFAULT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/email.php?action=get_settings` | `GET` | `get_settings` | Retrieves active SMTP configuration parameters. |
| `/api/email.php` | `POST` | `save_settings` | Overwrites active system SMTP parameters. |
| `/api/email.php` | `POST` | `test` | Transmits a secure verification test email to a target address. |
| `/api/email.php?action=templates` | `GET` | `templates` | Lists active and inactive templates. |
| `/api/email.php` | `POST` | `save_template` | Edits or inserts a dynamic variable template. |

---

## Usage Examples

### Dispatching an Email from PHP using a Slug and Variables
```php
require_once 'core/EmailService.php';

$service = new EmailService();

// Compile template 'user_welcome' and send to a new registrant
$rendered = EmailService::renderTemplate('user_welcome', [
    'user_name'     => 'Jane Doe',
    'username'      => 'janedoe',
    'app_name'      => 'nuvis Portal',
    'temp_password' => 'Str0ngP@ss1!',
    'login_url'     => 'https://portal.example.com/index.php'
]);

if ($rendered) {
    $result = $service->send('jane.doe@example.com', $rendered['subject'], $rendered['body']);
    if ($result['success']) {
         echo "Welcome email queued successfully.";
    } else {
         echo "Error sending: " . $result['message'];
    }
}
```
