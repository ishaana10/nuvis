<?php
declare(strict_types=1);

require_once __DIR__ . '/AgentDefinition.php';
require_once __DIR__ . '/AgentMemory.php';
require_once __DIR__ . '/AgentToolRouter.php';
require_once __DIR__ . '/AgentLogger.php';
require_once __DIR__ . '/LLM/LLMClientInterface.php';
require_once __DIR__ . '/LLM/GeminiClient.php';

/**
 * AgentRuntime - Main Orchestrator for Nuvis AI Agents
 */
class AgentRuntime
{
    private NuDatabase $db;
    private ?NuAuth $auth;
    private LLMClientInterface $llm;
    private AgentLogger $logger;

    private int $maxSteps = 10;
    private float $temperature = 0.2;

    public function __construct(
        NuDatabase $db,
        ?NuAuth $auth = null,
        ?LLMClientInterface $llm = null
    ) {
        $this->db = $db;
        $this->auth = $auth;
        $this->llm = $llm ?? new GeminiClient();
        $this->logger = new AgentLogger($this->db);
    }

    /**
     * Primary execution entry point
     *
     * @param int|string $agentId ID or Code of the Agent Definition
     * @param string $userMessage User instruction or trigger prompt
     * @param array $context Additional execution context
     * @return array Run results
     */
    public function run(int|string $agentId, string $userMessage, array $context = []): array
    {
        $definition = AgentDefinition::load($this->db, $agentId);
        $memory = new AgentMemory($this->db, $definition, $context);
        $tools = new AgentToolRouter($this->db, $this->auth, $definition, $context);

        $runId = $this->logger->startRun($definition->id, $userMessage, $context);

        try {
            $memory->addUserMessage($userMessage);

            $step = 0;
            $finalAnswer = null;
            $totalPromptTokens = 0;
            $totalCompletionTokens = 0;

            while ($step < $this->maxSteps) {
                $step++;

                $response = $this->llm->chat([
                    'model' => $definition->model,
                    'messages' => $memory->getMessagesForLLM(),
                    'tools' => $tools->getToolSchemas(),
                    'temperature' => $this->temperature,
                ]);

                if (!empty($response['usage'])) {
                    $totalPromptTokens += $response['usage']['prompt_tokens'] ?? 0;
                    $totalCompletionTokens += $response['usage']['completion_tokens'] ?? 0;
                }

                $this->logger->logMessage($runId, 'assistant', $response);

                // Case 1: Tool Calls
                if (!empty($response['tool_calls'])) {
                    $memory->addAssistantMessage($response['content'] ?? null, $response['tool_calls']);

                    foreach ($response['tool_calls'] as $tc) {
                        $toolResult = $tools->execute(
                            $tc['name'],
                            $tc['arguments'] ?? [],
                            $tc['id'] ?? ('call_' . uniqid())
                        );

                        $this->logger->logToolCall($runId, $tc, $toolResult);
                        $memory->addToolResult($tc['id'] ?? ('call_' . uniqid()), $tc['name'], $toolResult);
                    }
                    continue; // Loop back to LLM with tool outputs
                }

                // Case 2: Final Text Answer
                $finalAnswer = $response['content'] ?? '';
                $memory->addAssistantMessage($finalAnswer);
                break;
            }

            $usageStats = [
                'prompt_tokens' => $totalPromptTokens,
                'completion_tokens' => $totalCompletionTokens,
                'total_tokens' => $totalPromptTokens + $totalCompletionTokens
            ];

            $this->logger->completeRun($runId, 'completed', $finalAnswer, null, $usageStats);

            return [
                'success' => true,
                'run_id' => $runId,
                'agent_id' => $definition->id,
                'agent_name' => $definition->name,
                'answer' => $finalAnswer,
                'steps' => $step,
                'usage' => $usageStats,
                'context' => $context,
            ];

        } catch (Throwable $e) {
            $this->logger->completeRun($runId, 'failed', null, $e->getMessage());
            return [
                'success' => false,
                'run_id' => $runId,
                'error' => $e->getMessage()
            ];
        }
    }

    public function setMaxSteps(int $steps): self
    {
        $this->maxSteps = $steps;
        return $this;
    }

    public function setTemperature(float $temp): self
    {
        $this->temperature = $temp;
        return $this;
    }
}
