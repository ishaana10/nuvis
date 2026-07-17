# Plugin Manager Module

## Overview
The Plugin Manager module enables dynamic structural extensibility inside the nuvis platform. It allows developers to register, activate, deactivate, or compile custom code hooks (e.g. `after_save`, `before_render`, `menu_loaded`) that intercept standard execution streams without altering the core codebase.

---

## Architecture & Key Files
- **`modules/plugins/plugins.php`**: The visual administration panel displaying registered plugins, author details, structural hooks, and toggle selectors.
- **`core/PluginManager.php`**: Core system orchestrator initializing active plugin instances, validating security parameters, and executing callbacks when trigger gates fire.

---

## Technical Details

### Database Schema (`nu_plugins`)
Plugin metadata, files paths, status parameters, and associated JSON-formatted execution hooks are saved within `nu_plugins`:

```sql
CREATE TABLE IF NOT EXISTS nu_plugins (
    plugin_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_code         VARCHAR(100)  NOT NULL UNIQUE, -- System identification slug
    plugin_name         VARCHAR(255)  NOT NULL,
    plugin_version      VARCHAR(32)   NOT NULL DEFAULT '1.0.0',
    plugin_author       VARCHAR(255)  DEFAULT NULL,
    plugin_description  TEXT          DEFAULT NULL,
    plugin_hooks        TEXT          DEFAULT NULL,    -- JSON string specifying trigger-to-method pairings
    plugin_active       TINYINT(1)    NOT NULL DEFAULT 0,
    plugin_installed_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plugin_active (plugin_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Usage Examples

### SQL Schema Structure and Default Registrations
```sql
-- Query list of currently active system plugins
SELECT plugin_code, plugin_name, plugin_hooks
FROM nu_plugins
WHERE plugin_active = 1;
```

### Implementing and Executing Trigger Hooks in Core
To invoke and propagate dynamic plugin events inside a core pipeline:

```php
// Example insertion in core/FormRenderer.php or api/form-handler.php
require_once 'core/PluginManager.php';

$pluginManager = PluginManager::getInstance();

$contextData = [
    'form_code' => $formCode,
    'record_id' => $newRecordId,
    'user_id'   => $_SESSION['nu_user_id'] ?? null
];

// Propagates action across all active plugins listening to 'after_save'
$pluginManager->triggerHook('after_save', $contextData);
```

### Registering a New Plugin via AJAX Payload
```javascript
const pluginPayload = {
  plugin_code: "send_slack_alert",
  plugin_name: "Slack Notifications Dispatcher",
  plugin_version: "1.0.4",
  plugin_author: "Dev Team",
  plugin_description: "Automatically forward completed form events to corporate Slack channels.",
  plugin_hooks: JSON.stringify({
     "after_save": "slack_alert_after_save"
  }),
  plugin_active: 1
};

fetch('api/crud.php?table=nu_plugins', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(pluginPayload)
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Plugin installed and activated!");
  }
});
```
