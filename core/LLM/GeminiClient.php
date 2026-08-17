<?php
declare(strict_types=1);

require_once __DIR__ . '/LLMClientInterface.php';

/**
 * GeminiClient - Native REST implementation for Google Gemini API
 */
class GeminiClient implements LLMClientInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(?string $apiKey = null)
    {
        if (empty($apiKey)) {
            // Fetch from system settings or environment
            if (class_exists('NuDatabase')) {
                try {
                    $db = NuDatabase::getInstance();
                    $setting = $db->fetchOne("SELECT setting_value FROM nu_system_settings WHERE setting_key = 'gemini_api_key'");
                    if ($setting && !empty($setting['setting_value'])) {
                        $apiKey = $setting['setting_value'];
                    }
                } catch (Throwable $e) {}
            }
        }
        if (empty($apiKey)) {
            $apiKey = getenv('GEMINI_API_KEY') ?: '';
        }
        $this->apiKey = $apiKey;
    }

    public function chat(array $params): array
    {
        $rawModel = trim($params['model'] ?? 'gemini-1.5-flash');
        if (empty($rawModel)) {
            $rawModel = 'gemini-1.5-flash';
        }

        // Clean model name (strip leading 'models/' if user or API passed it)
        $model = preg_replace('/^models\//i', '', $rawModel);

        // Handle offline / unconfigured mock mode gracefully if no API key is set
        if (empty($this->apiKey)) {
            return $this->mockResponse($params);
        }

        return $this->sendChatRequest($model, $params);
    }

    private function sendChatRequest(string $model, array $params, bool $isRetry = false): array
    {
        $url = $this->baseUrl . $model . ':generateContent?key=' . urlencode($this->apiKey);

        // Convert messages format to Gemini contents schema
        $contents = [];
        $systemInstruction = null;

        foreach ($params['messages'] ?? [] as $msg) {
            $role = $msg['role'] ?? 'user';
            $text = $msg['content'] ?? '';

            if ($role === 'system') {
                $systemInstruction = ['parts' => [['text' => $text]]];
            } elseif ($role === 'user') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $text]]
                ];
            } elseif ($role === 'assistant') {
                $parts = [];
                if (!empty($text)) {
                    $parts[] = ['text' => $text];
                }
                if (!empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $parts[] = [
                            'functionCall' => [
                                'name' => $tc['name'],
                                'args' => $tc['arguments'] ?? new stdClass()
                            ]
                        ];
                    }
                }
                if (empty($parts)) {
                    $parts[] = ['text' => ''];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];
            } elseif ($role === 'tool') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $msg['name'] ?? 'tool_result',
                                'response' => ['result' => $msg['content'] ?? '']
                            ]
                        ]
                    ]
                ];
            }
        }

        // Build tools schema for Gemini
        $geminiTools = [];
        if (!empty($params['tools'])) {
            $funcDecls = [];
            foreach ($params['tools'] as $tool) {
                if (($tool['type'] ?? '') === 'function' && !empty($tool['function'])) {
                    $fn = $tool['function'];
                    $funcDecls[] = [
                        'name' => $fn['name'],
                        'description' => $fn['description'] ?? '',
                        'parameters' => $fn['parameters'] ?? new stdClass()
                    ];
                }
            }
            if (!empty($funcDecls)) {
                $geminiTools[] = ['functionDeclarations' => $funcDecls];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $params['temperature'] ?? 0.2,
            ]
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        if (!empty($geminiTools)) {
            $payload['tools'] = $geminiTools;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            $errData = json_decode((string)$response, true);
            $msg = $errData['error']['message'] ?? $error ?: "HTTP Error {$httpCode}";

            // If model is not found or unsupported on v1beta, try fallback to gemini-2.0-flash once
            if (!$isRetry && (str_contains($msg, 'not found') || str_contains($msg, 'not supported')) && $model !== 'gemini-2.0-flash') {
                return $this->sendChatRequest('gemini-2.0-flash', $params, true);
            }

            throw new RuntimeException("Gemini API Error: " . $msg);
        }

        $resData = json_decode($response, true);
        return $this->parseGeminiResponse($resData);
    }

    private function parseGeminiResponse(array $data): array
    {
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $content = '';
        $toolCalls = [];

        foreach ($parts as $idx => $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                $toolCalls[] = [
                    'id' => 'call_' . uniqid(),
                    'name' => $fc['name'],
                    'arguments' => $fc['args'] ?? []
                ];
            }
        }

        $tokenCount = $data['usageMetadata'] ?? [];

        return [
            'content' => $content ?: null,
            'tool_calls' => $toolCalls,
            'usage' => [
                'prompt_tokens' => $tokenCount['promptTokenCount'] ?? 0,
                'completion_tokens' => $tokenCount['candidatesTokenCount'] ?? 0,
                'total_tokens' => $tokenCount['totalTokenCount'] ?? 0,
            ]
        ];
    }

    private function mockResponse(array $params): array
    {
        // Simple deterministic mock engine when API key is missing or for offline sandbox tests
        $lastUserMsg = '';
        foreach (array_reverse($params['messages'] ?? []) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $lastUserMsg = strtolower($m['content'] ?? '');
                break;
            }
        }

        // If tools are provided and user mentions query/get/summarize, simulate a tool call step first
        $hasTools = !empty($params['tools']);
        $hasToolResults = false;
        foreach ($params['messages'] ?? [] as $m) {
            if (($m['role'] ?? '') === 'tool') {
                $hasToolResults = true;
                break;
            }
        }

        if ($hasTools && !$hasToolResults && (str_contains($lastUserMsg, 'query') || str_contains($lastUserMsg, 'record') || str_contains($lastUserMsg, 'customer'))) {
            return [
                'content' => "I am checking the records to fulfill your request.",
                'tool_calls' => [
                    [
                        'id' => 'call_mock_' . rand(1000, 9999),
                        'name' => 'query_records',
                        'arguments' => ['table' => 'demo_customer_requests', 'limit' => 5]
                    ]
                ],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20, 'total_tokens' => 70]
            ];
        }

        return [
            'content' => "This is an automated response from Nuvis Agent Engine (Offline Mode). Your request: " . ($params['messages'][count($params['messages'])-1]['content'] ?? 'N/A'),
            'tool_calls' => [],
            'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 30, 'total_tokens' => 70]
        ];
    }
}
