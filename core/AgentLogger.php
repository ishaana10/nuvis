<?php
declare(strict_types=1);

/**
 * AgentLogger - Persists execution runs, messages, tool calls, and traces
 */
class AgentLogger
{
    private NuDatabase $db;

    public function __construct(NuDatabase $db)
    {
        $this->db = $db;
    }

    public function startRun(int $agentId, string $inputMessage, array $context = []): int
    {
        try {
            return $this->db->insert('nu_agent_runs', [
                'agent_id' => $agentId,
                'run_status' => 'running',
                'input_prompt' => $inputMessage,
                'context' => !empty($context) ? json_encode($context) : null,
                'started_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            error_log('[AgentLogger Error - startRun] ' . $e->getMessage());
            return 0;
        }
    }

    public function logMessage(int $runId, string $role, array $payload): void
    {
        if ($runId <= 0) return;
        try {
            $content = $payload['content'] ?? (is_string($payload) ? $payload : json_encode($payload));
            $this->db->insert('nu_agent_messages', [
                'run_id' => $runId,
                'msg_role' => $role,
                'msg_content' => $content,
                'msg_raw' => json_encode($payload),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            error_log('[AgentLogger Error - logMessage] ' . $e->getMessage());
        }
    }

    public function logToolCall(int $runId, array $toolCall, array $result): void
    {
        if ($runId <= 0) return;
        try {
            $this->db->insert('nu_agent_tool_calls', [
                'run_id' => $runId,
                'tool_call_id' => $toolCall['id'] ?? ('call_' . uniqid()),
                'tool_name' => $toolCall['name'] ?? 'unknown',
                'tool_arguments' => is_array($toolCall['arguments'] ?? null) ? json_encode($toolCall['arguments']) : ($toolCall['arguments'] ?? '{}'),
                'tool_result' => json_encode($result),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            error_log('[AgentLogger Error - logToolCall] ' . $e->getMessage());
        }
    }

    public function completeRun(int $runId, string $status, ?string $finalOutput, ?string $errorMessage = null, array $usage = []): void
    {
        if ($runId <= 0) return;
        try {
            $this->db->update(
                'nu_agent_runs',
                [
                    'run_status' => $status,
                    'output_text' => $finalOutput,
                    'error_message' => $errorMessage,
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                    'completed_at' => date('Y-m-d H:i:s')
                ],
                'run_id = :id',
                [':id' => $runId]
            );
        } catch (Throwable $e) {
            error_log('[AgentLogger Error - completeRun] ' . $e->getMessage());
        }
    }
}
