<?php
declare(strict_types=1);
// nuBuilder Next - Audit Trail

class NuAudit {
    private $db;

    public function __construct() {
        $this->db = NuDatabase::getInstance();
    }

    /**
     * Record an audit event.
     *
     * $recordId and $userId accept string|int to support both UUID and
     * auto_increment primary key modes. Never cast these to (int) before
     * passing — doing so will silently zero any UUID string.
     *
     * @param string           $action   e.g. 'login', 'create', 'update', 'delete'
     * @param string           $table    e.g. 'nu_users'
     * @param string|int|null  $recordId PK of the affected row
     * @param array|null       $oldData  Snapshot before change
     * @param array|null       $newData  Snapshot after change
     */
    public function log(
        string $action,
        string $table,
        string|int|null $recordId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        // Silently skip if audit log table does not exist
        try {
            $check = $this->db->fetchOne("SHOW TABLES LIKE 'nu_audit_log'");
            if (!$check) return;

            // Preserve usr_id exactly as stored in session — may be UUID string or int
            $userId    = $_SESSION['nu_user_id'] ?? null;
            $username  = $_SESSION['nu_username'] ?? 'system';
            $ip        = $_SERVER['REMOTE_ADDR'] ?? 'cli';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $this->db->insert('nu_audit_log', [
                'audit_action'     => $action,
                'audit_table'      => $table,
                'audit_record_id'  => $recordId,
                'audit_old_data'   => $oldData ? json_encode($oldData) : null,
                'audit_new_data'   => $newData ? json_encode($newData) : null,
                'audit_user_id'    => $userId,
                'audit_username'   => $username,
                'audit_ip'         => $ip,
                'audit_user_agent' => $userAgent,
                'audit_timestamp'  => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Never let audit failure affect the session or request
            error_log('[NuAudit] ' . $e->getMessage());
        }
    }

    /**
     * Retrieve audit log entries with optional filters.
     *
     * Filters:
     *   action   string       — exact match on audit_action
     *   table    string       — exact match on audit_table
     *   user_id  string|int   — exact match on audit_user_id; pass as-is (no (int) cast)
     *   from     string       — ISO 8601 / MySQL datetime lower bound
     *   to       string       — ISO 8601 / MySQL datetime upper bound
     *
     * Do NOT cast filters['user_id'] to int before calling — in UUID mode
     * the value is a VARCHAR(36) string and an int cast will produce 0.
     */
    public function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['action']))  { $where[] = 'audit_action = :action';       $params[':action']  = $filters['action']; }
        if (!empty($filters['table']))   { $where[] = 'audit_table = :table';         $params[':table']   = $filters['table'];  }
        if (!empty($filters['user_id'])) { $where[] = 'audit_user_id = :user_id';     $params[':user_id'] = $filters['user_id']; } // string|int — no cast
        if (!empty($filters['from']))    { $where[] = 'audit_timestamp >= :from';     $params[':from']    = $filters['from'];   }
        if (!empty($filters['to']))      { $where[] = 'audit_timestamp <= :to';       $params[':to']      = $filters['to'];     }

        $sql = 'SELECT * FROM nu_audit_log WHERE ' . implode(' AND ', $where)
             . ' ORDER BY audit_timestamp DESC LIMIT :limit OFFSET :offset';

        $params[':limit']  = $limit;   // already typed int by signature
        $params[':offset'] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count audit log entries matching optional filters.
     * Same filter rules as getLogs() — do not cast user_id to int.
     */
    public function getLogCount(array $filters = []): int {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['action']))  { $where[] = 'audit_action = :action';   $params[':action']  = $filters['action']; }
        if (!empty($filters['table']))   { $where[] = 'audit_table = :table';     $params[':table']   = $filters['table'];  }
        if (!empty($filters['user_id'])) { $where[] = 'audit_user_id = :user_id'; $params[':user_id'] = $filters['user_id']; } // string|int — no cast

        $sql    = 'SELECT COUNT(*) as count FROM nu_audit_log WHERE ' . implode(' AND ', $where);
        $result = $this->db->fetchOne($sql, $params);
        return (int)($result['count'] ?? 0);
    }
}
