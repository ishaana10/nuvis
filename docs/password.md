# Password & Security Policy Modules

## Overview
The Password module implements end-user self-service controls, real-time client-side password strength validation indicators, and administrator reset actions. It works in conjunction with the **Password Policy** module to restrict credentials, maintain strict histories, enforce force-on-first-login conditions, and flag credential warnings.

---

## Architecture & Key Files
- **`modules/password/password.php`**: Standard tabbed frontend allowing users to swap passwords, view checklist matrices, and toggle visibility.
- **`modules/password_policy/password_policy.php`**: Administrative endpoint forwarding policy actions directly to the core password policy view.
- **`api/password.php`**: REST gate validating security lengths, comparing histories, and running Admin reset sequences.

---

## Technical Details

### Password Policies Metadata (`nu_password_policy`)
Rules regarding required symbols, histories, and warnings are indexed within `nu_password_policy`:

```sql
CREATE TABLE IF NOT EXISTS nu_password_policy (
    policy_id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    policy_min_length                  INT UNSIGNED  NOT NULL DEFAULT 8,
    policy_require_uppercase           TINYINT(1)    NOT NULL DEFAULT 1,
    policy_require_lowercase           TINYINT(1)    NOT NULL DEFAULT 1,
    policy_require_number              TINYINT(1)    NOT NULL DEFAULT 1,
    policy_require_special             TINYINT(1)    NOT NULL DEFAULT 0,
    policy_disallow_username           TINYINT(1)    NOT NULL DEFAULT 1,
    policy_history_count               INT UNSIGNED  NOT NULL DEFAULT 5,
    policy_expiry_days                 INT UNSIGNED  NOT NULL DEFAULT 0, -- 0 = Never
    policy_expiry_warning_days         INT UNSIGNED  NOT NULL DEFAULT 7,
    policy_force_change_on_first_login TINYINT(1)    NOT NULL DEFAULT 1,
    policy_updated_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/password.php?action=get_policy` | `GET` | `get_policy` | Sells active policy metrics. |
| `/api/password.php?action=save_policy` | `POST` | `save_policy` | Updates global password complexity constraints. |
| `/api/password.php?action=change_password` | `POST` | `change_password` | Evaluates current user session input, validates policies, and saves hashed credentials. |
| `/api/password.php?action=admin_reset` | `POST` | `admin_reset` | Evaluates target users, resets values, and triggers force-change states. |

---

## Usage Examples

### SQL Schema Initialization and Policies Seeds
```sql
-- Seed default strict corporate password requirements
INSERT INTO nu_password_policy (
    policy_min_length, policy_require_uppercase, policy_require_lowercase,
    policy_require_number, policy_require_special, policy_history_count
) VALUES (12, 1, 1, 1, 1, 6);
```

### Self-Service Password Update via fetch API
```javascript
const passwordChangePayload = {
  current_password: "OldPassword1!",
  new_password: "Str0ngNewPassword2!",
  confirm_password: "Str0ngNewPassword2!"
};

fetch('api/password.php?action=change_password', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(passwordChangePayload)
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      alert("Password updated successfully.");
  } else {
      alert("Change failed: " + data.message);
  }
});
```
