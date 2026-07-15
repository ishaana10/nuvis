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

        // Execute dynamic action hooks configured on transition
        $this->executeHook($transition, $instance, $toStage, $userId, $comment);

        return true;
    }

    // ── Execute custom Action Hooks (send_email, call_webhook, update_record) ────
    private function executeHook(array $transition, array $instance, array $toStage, int $userId, string $comment): void
    {
        $hookRaw = $transition['wft_hook'] ?? null;
        if (empty($hookRaw)) {
            return;
        }

        $hook = json_decode($hookRaw, true);
        if (!is_array($hook) || empty($hook['type'])) {
            return;
        }

        try {
            switch ($hook['type']) {
                case 'send_email':
                    $this->handleSendEmailHook($hook, $instance, $toStage, $userId, $comment);
                    break;
                case 'call_webhook':
                    $this->handleCallWebhookHook($hook, $instance, $toStage, $userId, $comment);
                    break;
                case 'update_record':
                    $this->handleUpdateRecordHook($hook, $instance, $toStage, $userId, $comment);
                    break;
            }
        } catch (\Throwable $e) {
            error_log('[Workflow Hook Error] Instance #' . $instance['wfi_id'] . ': ' . $e->getMessage());
        }
    }

    private function handleSendEmailHook(array $hook, array $instance, array $toStage, int $userId, string $comment): void
    {
        $templateSlug = $hook['template_slug'] ?? 'workflow_notification';
        $to           = $hook['to'] ?? '';

        if (empty($to)) {
            // Try to resolve assignee role of next stage
            if (!empty($toStage['wfs_role'])) {
                $roleUsers = $this->db->fetchAll('SELECT usr_email FROM nu_users WHERE LOWER(usr_role) = LOWER(:role)', [':role' => $toStage['wfs_role']]);
                $emails = [];
                foreach ($roleUsers as $u) {
                    if (!empty($u['usr_email'])) {
                        $emails[] = $u['usr_email'];
                    }
                }
                $to = implode(',', $emails);
            }
            if (empty($to) && !empty($instance['wfi_started_by'])) {
                $startedByUser = $this->db->fetchOne('SELECT usr_email FROM nu_users WHERE usr_id = :uid', [':uid' => $instance['wfi_started_by']]);
                $to = $startedByUser['usr_email'] ?? '';
            }
        }

        if (empty($to)) {
            error_log('[Workflow Hook send_email] No recipient found.');
            return;
        }

        $actorName = 'System';
        if ($userId > 0) {
            $actor = $this->db->fetchOne('SELECT usr_name FROM nu_users WHERE usr_id = :uid', [':uid' => $userId]);
            $actorName = $actor['usr_name'] ?? 'System';
        }

        $variables = [
            'recipient_name' => $to,
            'workflow_name'  => $instance['wf_name'] ?? '',
            'step_name'      => $toStage['wfs_name'] ?? '',
            'message'        => $comment ?: 'No comments provided.',
            'action_url'     => (getenv('NU_BASE_URL') ?: '/nbv5u/m/') . 'index.php#workflow',
            'record_id'      => $instance['wfi_record_id'] ?? '',
            'record_table'   => $instance['wfi_record_table'] ?? '',
            'actor_name'     => $actorName,
        ];

        // Replace placeholders from the linked form record if available
        if (!empty($instance['wfi_record_table']) && !empty($instance['wfi_record_id'])) {
            try {
                $table = preg_replace('/[^a-zA-Z0-9_]/', '', $instance['wfi_record_table']);
                $recordId = $instance['wfi_record_id'];
                $pk = 'id';
                $pkRow = $this->db->fetchOne("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
                if ($pkRow && !empty($pkRow['Column_name'])) {
                    $pk = $pkRow['Column_name'];
                }
                $record = $this->db->fetchOne("SELECT * FROM `{$table}` WHERE `{$pk}` = :id LIMIT 1", [':id' => $recordId]);
                if ($record) {
                    foreach ($record as $k => $v) {
                        $variables[$k] = (string)$v;
                    }
                }
            } catch (\Throwable $ex) {
                // ignore
            }
        }

        if (!class_exists('EmailService')) {
            require_once __DIR__ . '/EmailService.php';
        }

        $rendered = EmailService::renderTemplate($templateSlug, $variables, $this->db->getPdo());
        if (!$rendered) {
            $subject = "Workflow Notification: " . ($instance['wf_name'] ?? '') . " - Step " . ($toStage['wfs_name'] ?? '');
            $body = "<p>The workflow <strong>" . htmlspecialchars($instance['wf_name'] ?? '') . "</strong> advanced to stage <strong>" . htmlspecialchars($toStage['wfs_name'] ?? '') . "</strong>.</p>";
            $body .= "<p><strong>Message / Comments:</strong> " . htmlspecialchars($comment ?: 'None') . "</p>";
            $body .= "<p>Updated by: " . htmlspecialchars($actorName) . "</p>";
            $rendered = ['subject' => $subject, 'body' => $body];
        }

        $svc = new EmailService();
        $recipients = array_map('trim', explode(',', (string)$to));
        foreach ($recipients as $recipient) {
            if (empty($recipient)) continue;
            $svc->send($recipient, $rendered['subject'], $rendered['body']);
        }
    }

    private function handleCallWebhookHook(array $hook, array $instance, array $toStage, int $userId, string $comment): void
    {
        $url = $hook['url'] ?? '';
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $payload = [
            'event'         => 'workflow_transition',
            'workflow'      => [
                'id'          => $instance['wfi_wf_id'],
                'name'        => $instance['wf_name'] ?? '',
                'code'        => $instance['wf_code'] ?? '',
            ],
            'instance_id'   => $instance['wfi_id'],
            'to_stage'      => [
                'id'          => $toStage['wfs_id'],
                'name'        => $toStage['wfs_name'],
                'code'        => $toStage['wfs_code'],
            ],
            'status'        => $instance['wfi_status'],
            'record_table'  => $instance['wfi_record_table'],
            'record_id'     => $instance['wfi_record_id'],
            'comment'       => $comment,
            'actor_id'      => $userId,
            'timestamp'     => date('Y-m-d H:i:s'),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Workflow-Event: Transition'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }

    private function handleUpdateRecordHook(array $hook, array $instance, array $toStage, int $userId, string $comment): void
    {
        $table    = $instance['wfi_record_table'] ?? '';
        $recordId = $instance['wfi_record_id'] ?? '';
        $column   = $hook['column'] ?? '';
        $value    = $hook['value'] ?? '';

        if (empty($table) || empty($recordId) || empty($column)) {
            return;
        }

        $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

        $columns = [];
        try {
            $desc = $this->db->fetchAll("DESCRIBE `{$table}`");
            foreach ($desc as $col) {
                $columns[$col['Field']] = true;
            }
        } catch (\Throwable $e) {
            return;
        }

        if (!isset($columns[$column])) {
            error_log("[Workflow Hook update_record] Column '{$column}' does not exist on table '{$table}'");
            return;
        }

        $pk = 'id';
        $pkRow = $this->db->fetchOne("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        if ($pkRow && !empty($pkRow['Column_name'])) {
            $pk = $pkRow['Column_name'];
        }

        $sql = "UPDATE `{$table}` SET `{$column}` = :val WHERE `{$pk}` = :id";
        $this->db->query($sql, [':val' => $value, ':id' => $recordId]);
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
            'SELECT h.*, u.usr_name AS actor_name,
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
