<?php
declare(strict_types=1);

/**
 * LLM Client Interface for Nuvis Agent Runtime
 */
interface LLMClientInterface
{
    /**
     * Sends a chat completion payload to the LLM
     *
     * @param array $params Contains:
     *   - 'model' (string)
     *   - 'messages' (array)
     *   - 'tools' (array, optional)
     *   - 'temperature' (float, optional)
     *   - 'system_prompt' (string, optional)
     * @return array Standardized response:
     *   - 'content' => ?string
     *   - 'tool_calls' => array of ['id' => string, 'name' => string, 'arguments' => array]
     *   - 'usage' => ['prompt_tokens' => int, 'completion_tokens' => int, 'total_tokens' => int]
     */
    public function chat(array $params): array;
}
