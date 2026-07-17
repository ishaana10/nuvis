# Integrations & Webhooks Module

## Overview
The Integrations & Webhooks module manages external server connections. It enables developers to configure outgoing webhooks to notify third-party APIs (such as Slack, Teams, or payment gateways) on internal events, and generate secure, scoped REST API keys for incoming data syncs.

---

## Architecture & Key Files
- **`modules/integrations/integrations.php`**: Control panel UI displaying configured webhook routes, API authorization keys, and triggers.
- **`api/webhook.php`**: Secure background dispatcher executing outgoing HTTP POST requests and calculating payload signature checks (HMAC).

---

## Technical Details

### Webhooks Metadata (`nu_webhooks`)
Outgoing routes and active events are managed via the `nu_webhooks` table:

```sql
CREATE TABLE IF NOT EXISTS nu_webhooks (
    webhook_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_name           VARCHAR(255)  NOT NULL,
    webhook_url            VARCHAR(500)  NOT NULL,
    webhook_events         VARCHAR(255)  NOT NULL, -- comma-separated (e.g. form_save, workflow_approve)
    webhook_secret         VARCHAR(255)  DEFAULT NULL, -- HMAC signature generation secret
    webhook_active         TINYINT(1)    NOT NULL DEFAULT 1,
    webhook_last_triggered DATETIME      DEFAULT NULL,
    webhook_created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wh_active (webhook_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Incoming Access Tokens (`nu_api_tokens`)
External systems query nuvis REST endpoints utilizing API authorization records:

```sql
CREATE TABLE IF NOT EXISTS nu_api_tokens (
    token_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_key        VARCHAR(255)  NOT NULL UNIQUE, -- Hashed or direct token key
    token_name       VARCHAR(100)  NOT NULL,
    token_user_id    VARCHAR(64)   NOT NULL,        -- Maps token scope to a system user
    token_expires_at DATETIME      DEFAULT NULL,
    token_created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_key (token_key),
    INDEX idx_token_user (token_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Usage Examples

### Executing Outgoing webhook trigger in PHP (with HMAC Header)
```php
function triggerWebhook(array $webhook, string $event, array $payload) {
    $ch = curl_init($webhook['webhook_url']);

    $jsonData = json_encode(array_merge($payload, [
        'event_type' => $event,
        'timestamp' => time()
    ]));

    $headers = [
        'Content-Type: application/json',
        'X-Nuvis-Event: ' . $event
    ];

    // Calculates HMAC SHA256 header if secret is configured
    if (!empty($webhook['webhook_secret'])) {
        $signature = hash_hmac('sha256', $jsonData, $webhook['webhook_secret']);
        $headers[] = 'X-Nuvis-Signature: ' . $signature;
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_exec($ch);
    curl_close($ch);
}
```

### Authorizing incoming REST API Request using API tokens
```php
require_once 'core/Database.php';

$headers = getallheaders();
$apiKey = $headers['Authorization'] ?? '';

if (strpos($apiKey, 'Bearer ') === 0) {
    $apiKey = substr($apiKey, 7);
}

$db = NuDatabase::getInstance();
$token = $db->fetchOne(
    "SELECT * FROM nu_api_tokens WHERE token_key = :key AND (token_expires_at IS NULL OR token_expires_at > NOW())",
    [':key' => $apiKey]
);

if (!$token) {
    http_response_code(401);
    exit(json_encode(['error' => 'Invalid or expired API token']));
}

// Token authorized, bind scope to token_user_id
$_SESSION['nu_user_id'] = $token['token_user_id'];
```
