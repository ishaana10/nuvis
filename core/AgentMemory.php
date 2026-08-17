<?php
declare(strict_types=1);

/**
 * AgentMemory - Manages active conversation memory and persistent long-term state
 */
class AgentMemory
{
    private NuDatabase $db;
    private AgentDefinition $definition;
    private array $context;
    private array $messages = [];

    public function __construct(NuDatabase $db, AgentDefinition $definition, array $context = [])
    {
        $this->db = $db;
        $this->definition = $definition;
        $this->context = $context;

        // Initialize system message
        $this->messages[] = $this->definition->getSystemMessage();

        // Load relevant long-term memory if configured
        $this->loadLongTermMemory();
    }

    public function addUserMessage(string $content): void
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $content
        ];
    }

    public function addAssistantMessage(?string $content, array $toolCalls = []): void
    {
        $msg = [
            'role' => 'assistant',
            'content' => $content ?? ''
        ];
        if (!empty($toolCalls)) {
            $msg['tool_calls'] = $toolCalls;
        }
        $this->messages[] = $msg;
    }

    public function addToolResult(string $toolCallId, string $name, mixed $result): void
    {
        $content = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->messages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCallId,
            'name' => $name,
            'content' => $content
        ];
    }

    public function getMessagesForLLM(): array
    {
        return $this->messages;
    }

    private function loadLongTermMemory(): void
    {
        if ($this->definition->memoryType === 'none') {
            return;
        }

        try {
            $entityKey = $this->context['record_id'] ?? $this->context['user_id'] ?? null;
            if (!$entityKey) return;

            $memories = $this->db->fetchAll(
                "SELECT mem_key, mem_value FROM nu_agent_memory WHERE agent_id = :aid AND entity_key = :ek ORDER BY mem_updated_at DESC LIMIT 10",
                [':aid' => $this->definition->id, ':ek' => (string)$entityKey]
            );

            if (!empty($memories)) {
                $facts = [];
                foreach ($memories as $m) {
                    $facts[] = "- {$m['mem_key']}: {$m['mem_value']}";
                }
                $factContext = "Known facts about this context:\n" . implode("\n", $facts);
                $this->messages[0]['content'] .= "\n\n" . $factContext;
            }
        } catch (Throwable $e) {}
    }

    public function saveFact(string $key, string $value): void
    {
        $entityKey = $this->context['record_id'] ?? $this->context['user_id'] ?? 'global';
        try {
            $existing = $this->db->fetchOne(
                "SELECT mem_id FROM nu_agent_memory WHERE agent_id = :aid AND entity_key = :ek AND mem_key = :k",
                [':aid' => $this->definition->id, ':ek' => (string)$entityKey, ':k' => $key]
            );

            if ($existing) {
                $this->db->update(
                    'nu_agent_memory',
                    ['mem_value' => $value, 'mem_updated_at' => date('Y-m-d H:i:s')],
                    'mem_id = :id',
                    [':id' => $existing['mem_id']]
                );
            } else {
                $this->db->insert('nu_agent_memory', [
                    'agent_id' => $this->definition->id,
                    'entity_key' => (string)$entityKey,
                    'mem_key' => $key,
                    'mem_value' => $value,
                    'mem_created_at' => date('Y-m-d H:i:s'),
                    'mem_updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (Throwable $e) {}
    }
}
