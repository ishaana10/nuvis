<?php
declare(strict_types=1);

/**
 * AgentDefinition - Loads and holds agent configuration
 */
class AgentDefinition
{
    public int $id;
    public string $name;
    public string $systemPrompt;
    public string $model;
    public array $allowedTools;
    public array $toolConfig;
    public string $memoryType;
    public ?int $maxTokens;
    public bool $active;

    public static function load(NuDatabase $db, int|string $id): self
    {
        $def = new self();
        $row = null;

        if (is_numeric($id)) {
            $row = $db->fetchOne("SELECT * FROM nu_agents WHERE agent_id = :id", [':id' => (int)$id]);
        } else {
            $row = $db->fetchOne("SELECT * FROM nu_agents WHERE agent_code = :code OR agent_id = :code", [':code' => $id]);
        }

        if (!$row) {
            // Default fallback virtual agent if no definition in DB yet
            $def->id = 1;
            $def->name = 'Default Assistant';
            $def->systemPrompt = 'You are a helpful Nuvis enterprise AI assistant. You have access to database records, workflows, procedures, and communication tools. Use the provided tools to retrieve real-time facts before answering.';
            $def->model = 'gemini-1.5-flash';
            $def->allowedTools = ['query_records', 'get_record', 'create_record', 'update_record', 'run_procedure', 'send_email', 'call_webhook', 'start_workflow', 'advance_workflow'];
            $def->toolConfig = [];
            $def->memoryType = 'conversation';
            $def->maxTokens = 2000;
            $def->active = true;
            return $def;
        }

        $def->id = (int)$row['agent_id'];
        $def->name = $row['agent_name'] ?? 'Agent #' . $row['agent_id'];
        $def->systemPrompt = $row['agent_system_prompt'] ?? 'You are a helpful Nuvis AI assistant.';
        $def->model = !empty($row['agent_model']) ? $row['agent_model'] : 'gemini-1.5-flash';
        $def->allowedTools = !empty($row['agent_tools']) ? (is_array($row['agent_tools']) ? $row['agent_tools'] : (json_decode($row['agent_tools'], true) ?: [])) : [];
        $def->toolConfig = !empty($row['agent_tool_config']) ? (json_decode($row['agent_tool_config'], true) ?: []) : [];
        $def->memoryType = $row['agent_memory_type'] ?? 'conversation';
        $def->maxTokens = !empty($row['agent_max_tokens']) ? (int)$row['agent_max_tokens'] : 2000;
        $def->active = (bool)($row['agent_active'] ?? 1);

        return $def;
    }

    public function getSystemMessage(): array
    {
        return [
            'role' => 'system',
            'content' => $this->systemPrompt
        ];
    }
}
