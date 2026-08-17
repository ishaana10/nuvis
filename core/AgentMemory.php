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

    private function getMem0ApiKey(): ?string
    {
        try {
            $setting = $this->db->fetchOne("SELECT setting_value FROM nu_system_settings WHERE setting_key = 'mem0_api_key'");
            if ($setting && !empty($setting['setting_value'])) {
                return $setting['setting_value'];
            }
        } catch (Throwable $e) {}
        return getenv('MEM0_API_KEY') ?: null;
    }

    private function loadLongTermMemory(): void
    {
        if ($this->definition->memoryType === 'none') {
            return;
        }

        $entityKey = $this->context['record_id'] ?? $this->context['user_id'] ?? 'global';

        // Check if Mem0.ai integration is active
        if ($this->definition->memoryType === 'mem0') {
            $apiKey = $this->getMem0ApiKey();
            if (!empty($apiKey)) {
                $this->loadMem0Memory($apiKey, (string)$entityKey);
                return;
            }
        }

        // Default local DB memory fallback
        try {
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

    private function loadMem0Memory(string $apiKey, string $entityKey): void
    {
        try {
            $url = 'https://api.mem0.ai/v1/memories/search/';
            $payload = [
                'user_id' => $entityKey,
                'agent_id' => (string)$this->definition->id,
                'query' => 'relevant background facts and context'
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Token ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                $data = json_decode($response, true);
                $memories = is_array($data) ? ($data['results'] ?? $data) : [];
                if (!empty($memories)) {
                    $facts = [];
                    foreach ($memories as $m) {
                        $text = $m['memory'] ?? $m['text'] ?? (is_string($m) ? $m : '');
                        if (!empty($text)) {
                            $facts[] = "- " . $text;
                        }
                    }
                    if (!empty($facts)) {
                        $this->messages[0]['content'] .= "\n\nMem0 Persistent Context:\n" . implode("\n", $facts);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[Mem0 Memory Load Error] ' . $e->getMessage());
        }
    }

    public function saveFact(string $key, string $value): void
    {
        $entityKey = $this->context['record_id'] ?? $this->context['user_id'] ?? 'global';

        // Check if Mem0 is configured
        if ($this->definition->memoryType === 'mem0') {
            $apiKey = $this->getMem0ApiKey();
            if (!empty($apiKey)) {
                $this->saveMem0Memory($apiKey, (string)$entityKey, "{$key}: {$value}");
                return;
            }
        }

        // Local DB fallback
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

    public function searchMemories(string $query): array
    {
        $entityKey = $this->context['record_id'] ?? $this->context['user_id'] ?? 'global';

        // Check if Mem0 is configured
        if ($this->definition->memoryType === 'mem0') {
            $apiKey = $this->getMem0ApiKey();
            if (!empty($apiKey)) {
                try {
                    $url = 'https://api.mem0.ai/v1/memories/search/';
                    $payload = [
                        'user_id' => (string)$entityKey,
                        'agent_id' => (string)$this->definition->id,
                        'query' => $query
                    ];

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Token ' . $apiKey
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200 && !empty($response)) {
                        $data = json_decode($response, true);
                        return is_array($data) ? ($data['results'] ?? $data) : [];
                    }
                } catch (Throwable $e) {}
            }
        }

        // Local DB search fallback
        try {
            return $this->db->fetchAll(
                "SELECT mem_key, mem_value FROM nu_agent_memory WHERE agent_id = :aid AND entity_key = :ek AND (mem_key LIKE :q OR mem_value LIKE :q) ORDER BY mem_updated_at DESC LIMIT 10",
                [':aid' => $this->definition->id, ':ek' => (string)$entityKey, ':q' => '%' . $query . '%']
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public function saveMem0Memory(string $apiKey, string $entityKey, string $text): bool
    {
        try {
            $url = 'https://api.mem0.ai/v1/memories/';
            $payload = [
                'messages' => [['role' => 'user', 'content' => $text]],
                'user_id' => $entityKey,
                'agent_id' => (string)$this->definition->id
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Token ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 300;
        } catch (Throwable $e) {
            error_log('[Mem0 Memory Save Error] ' . $e->getMessage());
            return false;
        }
    }
}
