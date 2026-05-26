<?php
// nuBuilder Next - Safe Query Executor v2
// Enhanced SQL validation with parser-level checks

class NuQueryExecutor {
    private $db;
    private $auth;
    private $allowedPrefixes = ['SELECT'];
    private $forbiddenKeywords = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
        'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'UNION', 'INTO OUTFILE',
        'LOAD_FILE', 'SLEEP', 'BENCHMARK', 'DELAYED', 'LOW_PRIORITY',
        'HIGH_PRIORITY', 'IGNORE', 'FORCE', 'STRAIGHT_JOIN'
    ];

    public function __construct() {
        $this->db = NuDatabase::getInstance();
        $this->auth = new NuAuth();
    }

    public function execute($queryCode, $parameters = []) {
        $query = $this->db->fetchOne(
            "SELECT * FROM nu_queries WHERE query_code = :code AND query_active = 1",
            [':code' => $queryCode]
        );
        if (!$query) return ['error' => 'Query not found'];

        $sql = trim($query['query_sql']);

        // Validate SQL structure
        $validation = $this->validateSql($sql);
        if (!$validation['valid']) {
            return ['error' => $validation['message']];
        }

        // Extract and bind parameters
        $boundParams = [];
        $queryParams = json_decode($query['query_parameters'], true) ?? [];
        foreach ($queryParams as $key => $config) {
            if (isset($parameters[$key]) && $parameters[$key] !== '') {
                $boundParams[':' . $key] = $this->sanitizeParam($parameters[$key], $config['type'] ?? 'text');
            }
        }

        try {
            $records = $this->db->fetchAll($sql, $boundParams);
        } catch (Exception $e) {
            return ['error' => 'Query execution failed: ' . $e->getMessage()];
        }

        return [
            'query' => $query,
            'records' => $records,
            'columns' => !empty($records) ? array_keys($records[0]) : [],
            'total' => count($records),
            'parameters_used' => $boundParams
        ];
    }

    private function validateSql($sql) {
        // Remove comments
        $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $cleanSql = preg_replace('/--.*?
/', "
", $cleanSql);
        $cleanSql = preg_replace('/#.*?
/', "
", $cleanSql);

        $upperSql = strtoupper(trim($cleanSql));

        // Must start with SELECT
        if (!preg_match('/^SELECT\s/', $upperSql)) {
            return ['valid' => false, 'message' => 'Query must start with SELECT'];
        }

        // Check for forbidden keywords
        foreach ($this->forbiddenKeywords as $keyword) {
            if (preg_match('/' . $keyword . '/', $upperSql)) {
                return ['valid' => false, 'message' => 'Forbidden keyword detected: ' . $keyword];
            }
        }

        // Check for multiple statements
        if (substr_count($cleanSql, ';') > 1) {
            return ['valid' => false, 'message' => 'Multiple statements not allowed'];
        }

        // Check for subqueries that might be dangerous
        if (preg_match('/INTO\s+/', $upperSql)) {
            return ['valid' => false, 'message' => 'INTO clause not allowed'];
        }

        return ['valid' => true, 'message' => 'Valid'];
    }

    private function sanitizeParam($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'date':
                return date('Y-m-d', strtotime($value)) ?: date('Y-m-d');
            case 'select':
                return preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $value);
            default:
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
    }

    public function renderParameterForm($queryCode) {
        $query = $this->db->fetchOne(
            "SELECT * FROM nu_queries WHERE query_code = :code",
            [':code' => $queryCode]
        );
        if (!$query) return '';

        $params = json_decode($query['query_parameters'], true) ?? [];
        if (empty($params)) return '';

        $html = '<form class="nu-query-params" onsubmit="runQuery(event, '' . $queryCode . '')">';
        $html .= '<div class="nu-form-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">';

        foreach ($params as $key => $config) {
            $type = $config['type'] ?? 'text';
            $label = $config['label'] ?? ucwords(str_replace('_', ' ', $key));
            $html .= '<div class="nu-field">';
            $html .= '<label>' . htmlspecialchars($label) . '</label>';

            if ($type === 'select' && !empty($config['options'])) {
                $html .= '<select name="' . $key . '" class="nu-input">';
                $html .= '<option value="">-- All --</option>';
                foreach ($config['options'] as $opt) {
                    $html .= '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>';
                }
                $html .= '</select>';
            } elseif ($type === 'date') {
                $html .= '<input type="date" name="' . $key . '" class="nu-input">';
            } elseif ($type === 'number') {
                $html .= '<input type="number" name="' . $key . '" class="nu-input">';
            } else {
                $html .= '<input type="text" name="' . $key . '" class="nu-input" placeholder="' . htmlspecialchars($label) . '">';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<div style="margin-top:12px;">';
        $html .= '<button type="submit" class="nu-btn nu-btn-primary">Run Query</button>';
        $html .= '<button type="button" class="nu-btn nu-btn-ghost" onclick="exportQuery('' . $queryCode . '')">Export CSV</button>';
        $html .= '</div>';
        $html .= '</form>';

        return $html;
    }

    public function exportCsv($queryCode, $parameters = []) {
        $result = $this->execute($queryCode, $parameters);
        if (isset($result['error'])) return $result;

        $records = $result['records'];
        $columns = $result['columns'];

        $output = fopen('php://temp', 'w');
        fputcsv($output, $columns);
        foreach ($records as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return ['csv' => $csv, 'filename' => $queryCode . '_' . date('Y-m-d') . '.csv'];
    }
}
?>
