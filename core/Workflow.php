<?php
declare(strict_types=1);
/**
 * WorkflowEngine
 * Core engine for nub5-dev workflow module.
 * Handles starting, advancing, rejecting and cancelling workflow instances.
 */
class WorkflowEngine
{
    private NuDatabase $db;

    public function __construct()
    {
        $this->db = NuDatabase::getInstance();
    }

    // ── Start a new workflow instance ──────────────────────────────────────────
    public function start(int $wfId, int $userId, ?string $table = null, ?string $recordId = null, array $meta = []): int
    {
        $startStage = $this->db->fetchOne(
            'SELECT * FROM nu_workflow_stages
              WHERE wfs_wf_id = :wid AND wfs_is_start = 1
              ORDER BY wfs_order ASC LIMIT 1',
            [':wid' => $wfId]
        );
        if (!$startStage) {
            throw new RuntimeException('Workflow has no start stage defined.');
        }

        $instanceId = $this->db->insert('nu_workflow_instances', [
            'wfi_wf_id'        => $wfId,
            'wfi_stage_id'     => $startStage['wfs_id'],
            'wfi_record_table' => $table,
            'wfi_record_id'    => $recordId,
            'wfi_status'       => 'active',
            'wfi_started_by'   => $userId,
            'wfi_meta'         => $meta ? json_encode($meta) : null,
        ]);

        $this->logHistory($instanceId, null, (int)$startStage['wfs_id'], 'start', $userId, 'Workflow started');
        return $instanceId;
    }

    // ── Advance to next stage via a transition ─────────────────────────────────
    public function advance(int $instanceId, int $transitionId, int $userId, string $comment = ''): bool
    {
        $instance = $this->getInstance($instanceId);
        if (!$instance || $instance['wfi_status'] !== 'active') {
            throw new RuntimeException('Instance not found or not active.');
        }

        $transition = $this->db->fetchOne(
            'SELECT * FROM nu_workflow_transitions WHERE wft_id = :tid AND wft_from_id = :from',
            [':tid' => $transitionId, ':from' => $instance['wfi_stage_id']]
        );
        if (!$transition) {
            throw new RuntimeException('Invalid transition for current stage.');
        }

        $toStage = $this->db->fetchOne(
            'SELECT * FROM nu_workflow_stages WHERE wfs_id = :id',
            [':id' => $transition['wft_to_id']]
        );

        $isEnd     = (bool)($toStage['wfs_is_end'] ?? false);
        $newStatus = $isEnd ? 'completed' : 'active';

        $this->db->update(
            'nu_workflow_instances',
            [
                'wfi_stage_id'     => $transition['wft_to_id'],
                'wfi_status'       => $newStatus,
                'wfi_completed_at' => $isEnd ? date('Y-m-d H:i:s') : null,
            ],
            'wfi_id = :id',
            [':id' => $instanceId]
        );

        $this->logHistory($instanceId, (int)$instance['wfi_stage_id'], (int)$transition['wft_to_id'], $transition['wft_action'], $userId, $comment);

        // Execute Action Hook
        $this->executeHook($transition, $instance, $userId, $comment);

        // Trigger Outgoing Webhooks for workflow_advance
        try {
            require_once __DIR__ . '/WebhookSender.php';
            NuWebhookSender::trigger('workflow_advance', [
                'table'     => 'nu_workflow_instances',
                'record_id' => $instanceId,
                'data'      => [
                    'instance_id'   => $instanceId,
                    'workflow_name' => $instance['wf_name'] ?? '',
                    'workflow_code' => $instance['wf_code'] ?? '',
                    'action'        => $transition['wft_label'] ?? '',
                    'from_stage_id' => $transition['wft_from_id'] ?? '',
                    'to_stage_id'   => $transition['wft_to_id'] ?? '',
                    'comment'       => $comment
                ]
            ]);
        } catch (\Throwable $whe) {
            error_log('[Webhook Workflow Advance Trigger Error] ' . $whe->getMessage());
        }

        return true;
    }

    // ── Primary Key Resolution ───────────────────────────────────────────────
    private function getPrimaryKeyColumn(string $table): string
    {
        $cleanTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (stripos($cleanTable, 'demo_customer_requests') !== false) {
            return 'request_id';
        }
        if (stripos($cleanTable, 'demo_service_types') !== false) {
            return 'service_type_id';
        }
        if (stripos($cleanTable, 'demo_staff_services') !== false) {
            return 'service_log_id';
        }
        try {
            $pdo = $this->db->getPdo();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if (strtolower((string)$driver) === 'sqlite') {
                $colStmt = $pdo->query("PRAGMA table_info(`{$cleanTable}`)");
                if ($colStmt) {
                    $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cols as $c) {
                        if (!empty($c['pk'])) {
                            return $c['name'];
                        }
                    }
                }
            } else {
                $colStmt = $pdo->query("SHOW KEYS FROM `{$cleanTable}` WHERE Key_name = 'PRIMARY'");
                if ($colStmt) {
                    $rowCol = $colStmt->fetch(PDO::FETCH_ASSOC);
                    if ($rowCol && !empty($rowCol['Column_name'])) {
                        return $rowCol['Column_name'];
                    }
                }
            }
        } catch (Throwable $ignored) {}
        return 'id';
    }

    // ── Placeholder Replacement ──────────────────────────────────────────────
    private function replacePlaceholders(string $template, array $record, array $instance, string $actorName, string $comment): string
    {
        // Replace {{record.field_name}}
        foreach ($record as $key => $val) {
            $template = str_replace("{{record.{$key}}}", (string)$val, $template);
        }
        // Also replace flat {{field_name}} if present
        foreach ($record as $key => $val) {
            $template = str_replace("{{{$key}}}", (string)$val, $template);
        }
        // Replace other common placeholders
        $template = str_replace('{{wf_name}}', $instance['wf_name'] ?? '', $template);
        $template = str_replace('{{wf_code}}', $instance['wf_code'] ?? '', $template);
        $template = str_replace('{{wfi_id}}', (string)($instance['wfi_id'] ?? ''), $template);
        $template = str_replace('{{instance.wfi_id}}', (string)($instance['wfi_id'] ?? ''), $template);
        $template = str_replace('{{actor_name}}', $actorName, $template);
        $template = str_replace('{{comment}}', $comment, $template);
        return $template;
    }

    // ── Execute Hook ───────────────────────────────────────────────────────────
    private function executeHook(array $transition, array $instance, int $userId, string $comment = ''): void
    {
        $hook = $transition['wft_hook'] ?? null;
        if (!$hook) {
            return;
        }

        // Fetch actor details safely
        $actorName = 'System';
        try {
            $actor = $this->db->fetchOne('SELECT * FROM nu_users WHERE usr_id = :id', [':id' => $userId]);
            if ($actor) {
                $actorName = $actor['usr_name'] ?? ($actor['usr_username'] ?? 'System');
            }
        } catch (Throwable $e) {}

        // Load linked record details if available
        $record = [];
        $table = $instance['wfi_record_table'] ?? null;
        $recId = $instance['wfi_record_id'] ?? null;
        if ($table && $recId) {
            $pkCol = $this->getPrimaryKeyColumn($table);
            $cleanTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            try {
                $record = $this->db->fetchOne("SELECT * FROM `{$cleanTable}` WHERE `{$pkCol}` = :id", [':id' => $recId]) ?: [];
            } catch (Throwable $e) {}
        }

        // Try to decode JSON
        $hookConfigList = [];
        $isJson = false;
        $hookTrimmed = trim((string)$hook);
        if (str_starts_with($hookTrimmed, '{') || str_starts_with($hookTrimmed, '[')) {
            $decoded = json_decode($hook, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $isJson = true;
                if (isset($decoded['action'])) {
                    $hookConfigList[] = $decoded;
                } else {
                    $hookConfigList = $decoded;
                }
            }
        }

        if (!$isJson) {
            // Legacy/String action names
            $hookConfigList[] = [
                'action' => $hook
            ];
        }

        foreach ($hookConfigList as $hookConfig) {
            $action = $hookConfig['action'] ?? null;
            if (!$action) continue;

            switch ($action) {
                case 'send_email':
                    try {
                        require_once __DIR__ . '/EmailService.php';
                        $className = class_exists('NuEmailService') ? 'NuEmailService' : (class_exists('EmailService') ? 'EmailService' : null);
                        if (!$className) {
                            throw new Exception('EmailService class not found');
                        }
                        $service = new $className();

                        // Resolve dynamic recipient
                        $toEmail = null;
                        if (!empty($hookConfig['to'])) {
                            $toRaw = $hookConfig['to'];
                            if (filter_var($toRaw, FILTER_VALIDATE_EMAIL)) {
                                $toEmail = $toRaw;
                            } elseif (isset($record[$toRaw]) && filter_var($record[$toRaw], FILTER_VALIDATE_EMAIL)) {
                                $toEmail = $record[$toRaw];
                            }
                        }
                        if (!$toEmail) {
                            $startedBy = $this->db->fetchOne('SELECT usr_email FROM nu_users WHERE usr_id = :id', [':id' => (int)$instance['wfi_started_by']]);
                            $toEmail = $startedBy['usr_email'] ?? null;
                        }

                        if (!empty($toEmail)) {
                            $subjectTemplate = $hookConfig['subject'] ?? "Workflow Notification: [" . $instance['wf_name'] . "] #" . $instance['wfi_id'];
                            $bodyTemplate = $hookConfig['body'] ?? "<h2>Workflow Notification</h2>" .
                                    "<p>The workflow <b>" . htmlspecialchars($instance['wf_name']) . "</b> (Instance #" . $instance['wfi_id'] . ") has advanced.</p>" .
                                    "<p><b>Action:</b> " . htmlspecialchars($transition['wft_label']) . "</p>" .
                                    "<p><b>By Actor:</b> " . htmlspecialchars($actorName) . "</p>" .
                                    "<p>You can check the dashboard/workflow module for details.</p>";

                            $subject = $this->replacePlaceholders($subjectTemplate, $record, $instance, $actorName, $comment);
                            $body = $this->replacePlaceholders($bodyTemplate, $record, $instance, $actorName, $comment);

                            if (method_exists($service, 'sendEmail')) {
                                $service->sendEmail($toEmail, $subject, $body);
                            } else {
                                $service->send($toEmail, $subject, $body);
                            }
                        }
                    } catch (Throwable $e) {
                        error_log('[Workflow Hook Error - Email] ' . $e->getMessage());
                    }
                    break;

                case 'call_webhook':
                    try {
                        $url = $hookConfig['url'] ?? (getenv('NU_BASE_URL') ?: 'http://127.0.0.1');
                        $payloadData = $hookConfig['payload'] ?? [
                            'event'         => 'workflow_advance',
                            'workflow'      => $instance['wf_name'],
                            'instance_id'   => $instance['wfi_id'],
                            'action'        => $transition['wft_label'],
                            'from_stage_id' => $transition['wft_from_id'],
                            'to_stage_id'   => $transition['wft_to_id'],
                            'actor_id'      => $userId,
                            'timestamp'     => date('Y-m-d H:i:s')
                        ];

                        if (is_array($payloadData)) {
                            // recursively replace placeholders in payload values
                            array_walk_recursive($payloadData, function(&$val) use ($record, $instance, $actorName, $comment) {
                                if (is_string($val)) {
                                    $val = $this->replacePlaceholders($val, $record, $instance, $actorName, $comment);
                                }
                            });
                        }

                        $payload = json_encode($payloadData);

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url . '/api/webhook.php');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                        curl_exec($ch);
                        curl_close($ch);
                    } catch (Throwable $e) {
                        error_log('[Workflow Hook Error - Webhook] ' . $e->getMessage());
                    }
                    break;

                case 'update_record':
                    try {
                        $targetTable = $hookConfig['table'] ?? $instance['wfi_record_table'];
                        $targetField = $hookConfig['field'] ?? 'status';
                        $targetVal = $hookConfig['value'] ?? null;

                        if ($targetVal !== null) {
                            $targetVal = $this->replacePlaceholders($targetVal, $record, $instance, $actorName, $comment);
                        } else {
                            $toStage = $this->db->fetchOne('SELECT wfs_code FROM nu_workflow_stages WHERE wfs_id = :id', [':id' => $transition['wft_to_id']]);
                            $targetVal = $toStage['wfs_code'] ?? '';
                        }

                        if ($targetTable && $targetField) {
                            $cleanTable = preg_replace('/[^a-zA-Z0-9_]/', '', $targetTable);
                            $cleanField = preg_replace('/[^a-zA-Z0-9_]/', '', $targetField);
                            $pkCol = $this->getPrimaryKeyColumn($cleanTable);

                            $recordId = $instance['wfi_record_id'];
                            if ($recordId) {
                                $this->db->query(
                                    "UPDATE `{$cleanTable}` SET `{$cleanField}` = :val WHERE `{$pkCol}` = :id",
                                    [':val' => $targetVal, ':id' => $recordId]
                                );
                            }
                        }
                    } catch (Throwable $e) {
                        error_log('[Workflow Hook Error - Update Record] ' . $e->getMessage());
                    }
                    break;
            }
        }
    }

    // ── Reject / cancel ────────────────────────────────────────────────────────
    public function reject(int $instanceId, int $userId, string $comment = ''): bool
    {
        return $this->terminate($instanceId, 'rejected', $userId, $comment);
    }

    public function cancel(int $instanceId, int $userId, string $comment = ''): bool
    {
        return $this->terminate($instanceId, 'cancelled', $userId, $comment);
    }

    private function terminate(int $instanceId, string $status, int $userId, string $comment): bool
    {
        $instance = $this->getInstance($instanceId);
        if (!$instance || $instance['wfi_status'] !== 'active') {
            throw new RuntimeException('Instance not found or not active.');
        }
        $this->db->update(
            'nu_workflow_instances',
            ['wfi_status' => $status, 'wfi_completed_at' => date('Y-m-d H:i:s')],
            'wfi_id = :id',
            [':id' => $instanceId]
        );
        $this->logHistory($instanceId, (int)$instance['wfi_stage_id'], (int)$instance['wfi_stage_id'], $status, $userId, $comment);
        return true;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    public function getInstance(int $instanceId): ?array
    {
        return $this->db->fetchOne(
            'SELECT i.*, w.wf_name, w.wf_code, s.wfs_name AS stage_name, s.wfs_color AS stage_color
               FROM nu_workflow_instances i
               JOIN nu_workflows w ON w.wf_id = i.wfi_wf_id
               JOIN nu_workflow_stages s ON s.wfs_id = i.wfi_stage_id
              WHERE i.wfi_id = :id',
            [':id' => $instanceId]
        ) ?: null;
    }

    public function getHistory(int $instanceId): array
    {
        return $this->db->fetchAll(
            'SELECT h.*, u.usr_username AS actor_name,
                    fs.wfs_name AS from_stage, ts.wfs_name AS to_stage
               FROM nu_workflow_history h
               LEFT JOIN nu_users u  ON u.usr_id  = h.wfh_actor_id
               LEFT JOIN nu_workflow_stages fs ON fs.wfs_id = h.wfh_from_id
               LEFT JOIN nu_workflow_stages ts ON ts.wfs_id = h.wfh_to_id
              WHERE h.wfh_wfi_id = :id
              ORDER BY h.wfh_acted_at ASC',
            [':id' => $instanceId]
        );
    }

    public function getAvailableTransitions(int $instanceId): array
    {
        $instance = $this->getInstance($instanceId);
        if (!$instance) return [];
        return $this->db->fetchAll(
            'SELECT t.*, ts.wfs_name AS to_stage_name, ts.wfs_color AS to_stage_color
               FROM nu_workflow_transitions t
               JOIN nu_workflow_stages ts ON ts.wfs_id = t.wft_to_id
              WHERE t.wft_from_id = :sid
              ORDER BY t.wft_id ASC',
            [':sid' => $instance['wfi_stage_id']]
        );
    }

    private function logHistory(int $instanceId, ?int $fromId, int $toId, string $action, int $userId, string $comment): void
    {
        $this->db->insert('nu_workflow_history', [
            'wfh_wfi_id'   => $instanceId,
            'wfh_from_id'  => $fromId,
            'wfh_to_id'    => $toId,
            'wfh_action'   => $action,
            'wfh_actor_id' => $userId,
            'wfh_comment'  => $comment ?: null,
        ]);
    }
}
