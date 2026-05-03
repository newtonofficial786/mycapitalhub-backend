<?php

require_once __DIR__ . '/../bootstrap.php';

class Database {
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct() {
        $env = env('APP_ENV') ?: 'production';
        
        $configFile = __DIR__ . '/' . $env . '.php';
        
        if (!file_exists($configFile)) {
            $configFile = __DIR__ . '/local.php';
        }
        
        $this->config = require $configFile;
        
        $this->applyEnvironmentSettings();
        
        $dbConfig = $this->config['db'];
        
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database'],
            $dbConfig['charset']
        );
        
        try {
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_CASE => PDO::CASE_LOWER
            ]);
        } catch (PDOException $e) {
            if ($this->config['debug'] ?? false) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
            throw new Exception("Database connection failed");
        }
    }

    private function applyEnvironmentSettings() {
        if ($this->config['app']['env'] === 'production') {
            error_reporting(0);
            ini_set('display_errors', '0');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', $this->config['display_errors'] ?? true ? '1' : '0');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }

    private function __clone() {}
    public function __wakeup() {}
}

function getDb() {
    return Database::getInstance()->getConnection();
}