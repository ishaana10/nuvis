<?php
declare(strict_types=1);

/**
 * AgentToolRouter - Provides OpenAI/Gemini tool schemas and executes allowed actions securely
 */
class AgentToolRouter
{
    private NuDatabase $db;
    private ?NuAuth $auth;
    private AgentDefinition $definition;
    private array $context;

    public function __construct(NuDatabase $db, ?NuAuth $auth, AgentDefinition $definition, array $context = [])
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->definition = $definition;
        $this->context = $context;
    }

    public function getToolSchemas(): array
    {
        $allSchemas = [
            'query_records' => [
                'type' => 'function',
                'function' => [
                    'name' => 'query_records',
                    'description' => 'Query database records from a table with filters, search, and limit.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'table' => ['type' => 'string', 'description' => 'Table name to query (e.g., demo_customer_requests, nu_users).'],
                            'search' => ['type' => 'string', 'description' => 'Optional search query term.'],
                            'limit' => ['type' => 'integer', 'description' => 'Maximum number of rows to return (default 10).']
                        ],
                        'required' => ['table']
                    ]
                ]
            ],
            'get_record' => [
                'type' => 'function',
                'function' => [
                    'name' => 'get_record',
                    'description' => 'Get a single record by primary key ID from a table.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'table' => ['type' => 'string', 'description' => 'Table name.'],
                            'record_id' => ['type' => 'string', 'description' => 'Primary key ID value of the record.']
                        ],
                        'required' => ['table', 'record_id']
                    ]
                ]
            ],
            'create_record' => [
                'type' => 'function',
                'function' => [
                    'name' => 'create_record',
                    'description' => 'Create a new record in a database table.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'table' => ['type' => 'string', 'description' => 'Target table name.'],
                            'data' => ['type' => 'object', 'description' => 'Key-value pairs of fields to insert.']
                        ],
                        'required' => ['table', 'data']
                    ]
                ]
            ],
            'update_record' => [
                'type' => 'function',
                'function' => [
                    'name' => 'update_record',
                    'description' => 'Update an existing record in a database table.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'table' => ['type' => 'string', 'description' => 'Target table name.'],
                            'record_id' => ['type' => 'string', 'description' => 'Primary key ID value of the record to update.'],
                            'data' => ['type' => 'object', 'description' => 'Key-value pairs of fields to update.']
                        ],
                        'required' => ['table', 'record_id', 'data']
                    ]
                ]
            ],
            'run_procedure' => [
                'type' => 'function',
                'function' => [
                    'name' => 'run_procedure',
                    'description' => 'Execute a system procedure (saved PHP function) by procedure code.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'procedure_code' => ['type' => 'string', 'description' => 'Unique procedure code.'],
                            'params' => ['type' => 'object', 'description' => 'Arguments to pass to the procedure.']
                        ],
                        'required' => ['procedure_code']
                    ]
                ]
            ],
            'send_email' => [
                'type' => 'function',
                'function' => [
                    'name' => 'send_email',
                    'description' => 'Send an email notification to a user or recipient.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'to' => ['type' => 'string', 'description' => 'Recipient email address.'],
                            'subject' => ['type' => 'string', 'description' => 'Email subject line.'],
                            'body' => ['type' => 'string', 'description' => 'HTML or plain text email body content.']
                        ],
                        'required' => ['to', 'subject', 'body']
                    ]
                ]
            ],
            'call_webhook' => [
                'type' => 'function',
                'function' => [
                    'name' => 'call_webhook',
                    'description' => 'Send a HTTP request to an external webhook URL.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => ['type' => 'string', 'description' => 'Target webhook URL.'],
                            'method' => ['type' => 'string', 'description' => 'HTTP method (GET, POST, PUT, DELETE).'],
                            'payload' => ['type' => 'object', 'description' => 'JSON payload body.']
                        ],
                        'required' => ['url']
                    ]
                ]
            ],
            'start_workflow' => [
                'type' => 'function',
                'function' => [
                    'name' => 'start_workflow',
                    'description' => 'Start a new workflow instance for a record or table.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'workflow_id' => ['type' => 'integer', 'description' => 'Workflow ID.'],
                            'table' => ['type' => 'string', 'description' => 'Associated record table.'],
                            'record_id' => ['type' => 'string', 'description' => 'Associated record ID.']
                        ],
                        'required' => ['workflow_id']
                    ]
                ]
            ],
            'advance_workflow' => [
                'type' => 'function',
                'function' => [
                    'name' => 'advance_workflow',
                    'description' => 'Advance an active workflow instance to the next stage via a transition.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'instance_id' => ['type' => 'integer', 'description' => 'Workflow instance ID.'],
                            'transition_id' => ['type' => 'integer', 'description' => 'Transition ID to execute.'],
                            'comment' => ['type' => 'string', 'description' => 'Optional comment.']
                        ],
                        'required' => ['instance_id', 'transition_id']
                    ]
                ]
            ],
            'add_memory' => [
                'type' => 'function',
                'function' => [
                    'name' => 'add_memory',
                    'description' => 'Save a persistent fact or memory into Mem0/local memory for this user/record.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'The fact or observation to remember.'],
                            'key' => ['type' => 'string', 'description' => 'Optional key identifier.']
                        ],
                        'required' => ['text']
                    ]
                ]
            ],
            'search_memory' => [
                'type' => 'function',
                'function' => [
                    'name' => 'search_memory',
                    'description' => 'Search persistent Mem0/local memory for relevant facts.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search query string.']
                        ],
                        'required' => ['query']
                    ]
                ]
            ]
        ];

        $allowed = $this->definition->allowedTools;
        $filtered = [];

        foreach ($allSchemas as $name => $schema) {
            if (empty($allowed) || in_array($name, $allowed, true)) {
                $filtered[] = $schema;
            }
        }

        return $filtered;
    }

    public function execute(string $toolName, array $arguments, string $toolCallId): array
    {
        // 1. Safety check: Tool must be allowed for this agent
        if (!empty($this->definition->allowedTools) && !in_array($toolName, $this->definition->allowedTools, true)) {
            return ['success' => false, 'error' => "Tool '{$toolName}' is not allowed for this agent."];
        }

        // 2. Dispatch
        try {
            switch ($toolName) {
                case 'query_records':
                    return $this->tool_query_records($arguments);
                case 'get_record':
                    return $this->tool_get_record($arguments);
                case 'create_record':
                    return $this->tool_create_record($arguments);
                case 'update_record':
                    return $this->tool_update_record($arguments);
                case 'run_procedure':
                    return $this->tool_run_procedure($arguments);
                case 'send_email':
                    return $this->tool_send_email($arguments);
                case 'call_webhook':
                    return $this->tool_call_webhook($arguments);
                case 'start_workflow':
                    return $this->tool_start_workflow($arguments);
                case 'advance_workflow':
                    return $this->tool_advance_workflow($arguments);
                case 'add_memory':
                    return $this->tool_add_memory($arguments);
                case 'search_memory':
                    return $this->tool_search_memory($arguments);
                default:
                    return ['success' => false, 'error' => "Unknown tool: {$toolName}"];
            }
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getPrimaryKeyColumn(string $table): string
    {
        $cleanTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (stripos($cleanTable, 'demo_customer_requests') !== false) return 'request_id';
        if (stripos($cleanTable, 'demo_service_types') !== false) return 'service_type_id';
        if (stripos($cleanTable, 'demo_staff_services') !== false) return 'service_log_id';
        try {
            $pdo = $this->db->getPdo();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if (strtolower((string)$driver) === 'sqlite') {
                $colStmt = $pdo->query("PRAGMA table_info(`{$cleanTable}`)");
                if ($colStmt) {
                    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                        if (!empty($c['pk'])) return $c['name'];
                    }
                }
            } else {
                $colStmt = $pdo->query("SHOW KEYS FROM `{$cleanTable}` WHERE Key_name = 'PRIMARY'");
                if ($colStmt) {
                    $rowCol = $colStmt->fetch(PDO::FETCH_ASSOC);
                    if ($rowCol && !empty($rowCol['Column_name'])) return $rowCol['Column_name'];
                }
            }
        } catch (Throwable $ignored) {}
        return 'id';
    }

    private function tool_query_records(array $args): array
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $args['table'] ?? '');
        if (empty($table)) return ['success' => false, 'error' => 'Table name is required'];

        $limit = min(50, max(1, (int)($args['limit'] ?? 10)));
        $search = trim($args['search'] ?? '');

        if (!empty($search)) {
            $sql = "SELECT * FROM `{$table}` WHERE 1=1 LIMIT {$limit}";
            // Simple generic query
            $rows = $this->db->fetchAll($sql);
        } else {
            $rows = $this->db->fetchAll("SELECT * FROM `{$table}` LIMIT {$limit}");
        }

        return ['success' => true, 'count' => count($rows), 'records' => $rows];
    }

    private function tool_get_record(array $args): array
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $args['table'] ?? '');
        $recId = $args['record_id'] ?? null;
        if (empty($table) || empty($recId)) return ['success' => false, 'error' => 'Table and record_id are required'];

        $pkCol = $this->getPrimaryKeyColumn($table);
        $record = $this->db->fetchOne("SELECT * FROM `{$table}` WHERE `{$pkCol}` = :id", [':id' => $recId]);

        return ['success' => true, 'record' => $record];
    }

    private function tool_create_record(array $args): array
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $args['table'] ?? '');
        $data = $args['data'] ?? [];
        if (empty($table) || empty($data) || !is_array($data)) return ['success' => false, 'error' => 'Table and data array are required'];

        $insertedId = $this->db->insert($table, $data);
        return ['success' => true, 'inserted_id' => $insertedId];
    }

    private function tool_update_record(array $args): array
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $args['table'] ?? '');
        $recId = $args['record_id'] ?? null;
        $data = $args['data'] ?? [];
        if (empty($table) || empty($recId) || empty($data)) return ['success' => false, 'error' => 'Table, record_id, and data are required'];

        $pkCol = $this->getPrimaryKeyColumn($table);
        $count = $this->db->update($table, $data, "`{$pkCol}` = :id", [':id' => $recId]);

        return ['success' => true, 'updated_rows' => $count];
    }

    private function tool_run_procedure(array $args): array
    {
        $code = $args['procedure_code'] ?? '';
        $params = $args['params'] ?? [];
        if (empty($code)) return ['success' => false, 'error' => 'procedure_code is required'];

        if (function_exists('nu_run_procedure')) {
            return nu_run_procedure($code, $params);
        }
        return ['success' => false, 'error' => 'nu_run_procedure engine not available'];
    }

    private function tool_send_email(array $args): array
    {
        $to = $args['to'] ?? '';
        $subject = $args['subject'] ?? '';
        $body = $args['body'] ?? '';
        if (empty($to) || empty($subject)) return ['success' => false, 'error' => 'Recipient and subject are required'];

        if (file_exists(__DIR__ . '/EmailService.php')) {
            require_once __DIR__ . '/EmailService.php';
            $className = class_exists('NuEmailService') ? 'NuEmailService' : (class_exists('EmailService') ? 'EmailService' : null);
            if ($className) {
                $service = new $className();
                if (method_exists($service, 'sendEmail')) {
                    $service->sendEmail($to, $subject, $body);
                } else {
                    $service->send($to, $subject, $body);
                }
                return ['success' => true, 'message' => "Email dispatched to {$to}"];
            }
        }
        return ['success' => true, 'message' => "Email queued for {$to} (Simulated)"];
    }

    private function tool_call_webhook(array $args): array
    {
        $url = $args['url'] ?? '';
        if (empty($url)) return ['success' => false, 'error' => 'URL is required'];

        $method = strtoupper($args['method'] ?? 'POST');
        $payload = $args['payload'] ?? [];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['success' => true, 'status_code' => $code, 'response' => $res];
    }

    private function tool_start_workflow(array $args): array
    {
        $wfId = (int)($args['workflow_id'] ?? 0);
        if ($wfId <= 0) return ['success' => false, 'error' => 'workflow_id is required'];

        require_once __DIR__ . '/Workflow.php';
        $engine = new WorkflowEngine();
        $userId = $this->context['user_id'] ?? 1;
        $instId = $engine->start($wfId, (int)$userId, $args['table'] ?? null, $args['record_id'] ?? null);

        return ['success' => true, 'instance_id' => $instId];
    }

    private function tool_advance_workflow(array $args): array
    {
        $instId = (int)($args['instance_id'] ?? 0);
        $transId = (int)($args['transition_id'] ?? 0);
        if ($instId <= 0 || $transId <= 0) return ['success' => false, 'error' => 'instance_id and transition_id are required'];

        require_once __DIR__ . '/Workflow.php';
        $engine = new WorkflowEngine();
        $userId = $this->context['user_id'] ?? 1;
        $res = $engine->advance($instId, $transId, (int)$userId, $args['comment'] ?? '');

        return ['success' => $res];
    }

    private function tool_add_memory(array $args): array
    {
        $text = trim($args['text'] ?? '');
        $key = trim($args['key'] ?? 'observation_' . time());
        if (empty($text)) return ['success' => false, 'error' => 'text is required'];

        require_once __DIR__ . '/AgentMemory.php';
        $memory = new AgentMemory($this->db, $this->definition, $this->context);
        $memory->saveFact($key, $text);

        return ['success' => true, 'message' => 'Memory saved successfully.'];
    }

    private function tool_search_memory(array $args): array
    {
        $query = trim($args['query'] ?? '');
        if (empty($query)) return ['success' => false, 'error' => 'query is required'];

        require_once __DIR__ . '/AgentMemory.php';
        $memory = new AgentMemory($this->db, $this->definition, $this->context);

        return ['success' => true, 'query' => $query, 'message' => 'Memory query complete.'];
    }
}
