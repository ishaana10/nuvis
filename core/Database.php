<?php

class Database {
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct() {
        global $nuConfig;

        if (!is_array($nuConfig)) {
            throw new Exception('nuConfig not available');
        }

        $this->config = $nuConfig;

        $dsn = "mysql:host={$nuConfig['dbHost']};dbname={$nuConfig['dbName']};charset={$nuConfig['dbCharset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO(
                $dsn,
                $nuConfig['dbUser'],
                $nuConfig['dbPassword'],
                $options
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function getConnection() {
        return self::getInstance()->getPdo();
    }

    public function getPdo() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function insert($table, $data) {
        $cols = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $k => $v) {
            $set[] = "{$k} = :{$k}";
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
        $this->query($sql, array_merge($data, $whereParams));
        return true;
    }

    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->query($sql, $whereParams);
        return true;
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }
}