<?php
declare(strict_types=1);

/**
 * NuWebhookSender - Dispatcher class for outgoing webhooks in nuvis
 * PHP 7.4 - 8.3 compatible
 */
class NuWebhookSender
{
    /**
     * Dispatch an event to all active webhooks subscribed to it
     *
     * @param string $eventType The type of event (e.g. form_insert, workflow_advance, user_login)
     * @param array $payloadData Associated payload data (table, record_id, actor, data)
     */
    public static function trigger(string $eventType, array $payloadData = []): void
    {
        try {
            $db = NuDatabase::getInstance();
            // Fetch all active webhooks
            $webhooks = $db->fetchAll("SELECT * FROM nu_webhooks WHERE webhook_active = 1");

            foreach ($webhooks as $wh) {
                $events = array_map('trim', explode(',', $wh['webhook_events'] ?? ''));
                if (in_array($eventType, $events, true)) {
                    self::send($wh, $eventType, $payloadData);
                }
            }
        } catch (Throwable $e) {
            error_log('[Webhook dispatch error] ' . $e->getMessage());
        }
    }

    /**
     * Execute a single webhook call
     */
    public static function send(array $webhook, string $eventType, array $payloadData): array
    {
        $db = NuDatabase::getInstance();
        $url = $webhook['webhook_url'];
        $method = strtoupper($webhook['webhook_method'] ?: 'POST');

        // Prepare context data
        $actorName = 'System';
        if (isset($_SESSION['nu_user_id']) || isset($_SESSION['user_id'])) {
            try {
                $actorId = $_SESSION['nu_user_id'] ?? $_SESSION['user_id'];
                $actorObj = $db->fetchOne("SELECT usr_name FROM nu_users WHERE usr_id = :id", [':id' => $actorId]);
                if ($actorObj) {
                    $actorName = $actorObj['usr_name'];
                }
            } catch (Throwable $ignore) {}
        }

        $context = [
            'event_type' => $eventType,
            'record_id'  => $payloadData['record_id'] ?? '',
            'table'      => $payloadData['table'] ?? '',
            'actor'      => $actorName,
            'timestamp'  => date('Y-m-d H:i:s'),
            'data'       => $payloadData['data'] ?? []
        ];

        // Format Body
        $body = '';
        $customTemplate = $webhook['webhook_payload_template'] ?? '';
        if (!empty($customTemplate)) {
            $body = self::resolveTemplate($customTemplate, $context);
        } else {
            $body = json_encode($context);
        }

        // Initialize curl
        $ch = curl_init($url);

        // Setup headers
        $headers = [
            'X-Nuvis-Event: ' . $eventType,
            'X-Nuvis-Delivery: ' . uniqid('dlv_', true)
        ];

        // Detect content type
        if (self::isJson($body)) {
            $headers[] = 'Content-Type: application/json';
        } else {
            $headers[] = 'Content-Type: text/plain';
        }

        // Compute Signature (HMAC)
        if (!empty($webhook['webhook_secret'])) {
            $signature = hash_hmac('sha256', $body, $webhook['webhook_secret']);
            $headers[] = 'X-Nuvis-Signature: ' . $signature;
        }

        // Custom headers configured by developer
        $customHeadersJson = $webhook['webhook_headers'] ?? '';
        if (!empty($customHeadersJson)) {
            $customHeaders = json_decode($customHeadersJson, true);
            if (is_array($customHeaders)) {
                foreach ($customHeaders as $k => $v) {
                    $headers[] = trim((string)$k) . ': ' . trim((string)$v);
                }
            }
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds timeout limit

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errStr   = curl_error($ch);
        curl_close($ch);

        // Update trigger stats
        try {
            $db->update(
                'nu_webhooks',
                ['webhook_last_triggered' => date('Y-m-d H:i:s')],
                'webhook_id = :id',
                [':id' => $webhook['webhook_id']]
            );
        } catch (Throwable $ignore) {}

        return [
            'success'   => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response'  => $response,
            'error'     => $errStr,
            'payload'   => $body
        ];
    }

    /**
     * Parse template placeholders
     */
    public static function resolveTemplate(string $template, array $data): string
    {
        $replacements = [
            '{{event_type}}' => (string)($data['event_type'] ?? ''),
            '{{record_id}}'  => (string)($data['record_id'] ?? ''),
            '{{table}}'      => (string)($data['table'] ?? ''),
            '{{actor}}'      => (string)($data['actor'] ?? 'System'),
            '{{timestamp}}'  => (string)($data['timestamp'] ?? ''),
            '{{data}}'       => isset($data['data']) ? json_encode($data['data']) : '{}'
        ];

        $result = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Nested properties search like {{data.name}}
        $result = preg_replace_callback('/\{\{data\.([a-zA-Z0-9_]+)\}\}/', function($matches) use ($data) {
            $key = $matches[1];
            if (isset($data['data']) && is_array($data['data']) && isset($data['data'][$key])) {
                $val = $data['data'][$key];
                if (is_array($val) || is_object($val)) {
                    return json_encode($val);
                }
                return (string)$val;
            }
            return '';
        }, $result);

        return $result;
    }

    /**
     * Check if a string is valid JSON
     */
    private static function isJson(string $string): bool
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}
