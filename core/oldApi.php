<?php
// nuBuilder Next - API Layer

class NuApi {
    private $db;
    private $config;
    private $auth;

    public function __construct() {
        global $nuConfig;
        $this->config = $nuConfig;
        $this->db = NuDatabase::getInstance();
        $this->auth = new NuAuth();
    }

    public function authenticate() {
        // Check for browser session auth first
        if (isset($_SESSION['nu_user_id'])) {
            $user = $this->db->fetchOne(
                "SELECT usr_id as token_user_id, usr_username, usr_role FROM nu_users WHERE usr_id = :id AND usr_active = 1",
                [':id' => $_SESSION['nu_user_id']]
            );
            if ($user) return $user;
        }

        // Fall back to API key auth
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        if (!$apiKey) {
            $this->error('API key required', 401);
        }

        $token = $this->db->fetchOne(
            "SELECT t.*, u.usr_username, u.usr_role FROM nu_api_tokens t
             JOIN nu_users u ON t.token_user_id = u.usr_id
             WHERE t.token_key = :key AND t.token_active = 1 AND (t.token_expires IS NULL OR t.token_expires > NOW())",
            [':key' => $apiKey]
        );

        if (!$token) {
            $this->error('Invalid or expired API key', 401);
        }

        // Rate limiting
        $this->checkRateLimit($token['token_user_id']);

        return $token;
    }

    public function response($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function error($message, $code = 400) {
        $this->response(['success' => false, 'error' => $message], $code);
    }

    public function success($data = null, $message = 'Success') {
        $this->response(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function checkRateLimit($userId) {
        $hour = date('Y-m-d H:00:00');
        $this->db->query(
            "INSERT INTO nu_api_usage (usage_user_id, usage_hour, usage_count)
             VALUES (:uid, :hour, 1)
             ON DUPLICATE KEY UPDATE usage_count = usage_count + 1",
            [':uid' => $userId, ':hour' => $hour]
        );

        $usage = $this->db->fetchOne(
            "SELECT usage_count FROM nu_api_usage WHERE usage_user_id = :uid AND usage_hour = :hour",
            [':uid' => $userId, ':hour' => $hour]
        );

        if ($usage && $usage['usage_count'] > $this->config['apiRateLimit']) {
            $this->error('Rate limit exceeded', 429);
        }
    }

    // CRUD Operations
    public function listRecords($table, $filters = [], $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $where = ['1=1'];
        $params = [];

        foreach ($filters as $key => $value) {
            $where[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) .
               " LIMIT :limit OFFSET :offset";
        $params[':limit'] = (int)$perPage;
        $params[':offset'] = (int)$offset;

        $records = $this->db->fetchAll($sql, $params);

        $countSql = "SELECT COUNT(*) as total FROM {$table} WHERE " . implode(' AND ', $where);
        unset($params[':limit'], $params[':offset']);
        $total = $this->db->fetchOne($countSql, $params);

        return [
            'records' => $records,
            'total' => $total['total'],
            'page' => $page,
            'per_page' => $perPage,
            'pages' => ceil($total['total'] / $perPage)
        ];
    }

    public function getRecord($table, $id) {
        return $this->db->fetchOne("SELECT * FROM {$table} WHERE id = :id", [':id' => $id]);
    }

    public function createRecord($table, $data) {
        $id = $this->db->insert($table, $data);
        $this->logAudit('api_create', $table, $id, null, $data);
        return $id;
    }

    public function updateRecord($table, $id, $data) {
        $old = $this->getRecord($table, $id);
        $this->db->update($table, $data, "id = :id", [':id' => $id]);
        $this->logAudit('api_update', $table, $id, $old, $data);
        return true;
    }

    public function deleteRecord($table, $id) {
        $old = $this->getRecord($table, $id);
        $this->db->delete($table, "id = :id", [':id' => $id]);
        $this->logAudit('api_delete', $table, $id, $old, null);
        return true;
    }

    private function logAudit($action, $table, $recordId, $old = null, $new = null) {
        if (!$this->config['enableAuditTrail']) return;
        $audit = new NuAudit();
        $audit->log($action, $table, $recordId, $old, $new);
    }
}
?>
