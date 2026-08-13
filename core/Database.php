<?php

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $configFile = __DIR__ . '/../config/database.php';
        if (file_exists($configFile)) {
            require_once $configFile;
        }

        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;

        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        $connection = new mysqli($host, DB_USER, DB_PASS, DB_NAME, $port);

        if ($connection->connect_error) {
            die("Koneksi gagal: " . $connection->connect_error);
        }

        $connection->query("SET time_zone = '+07:00'");
        $connection->set_charset("utf8mb4");
        $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $this->connection = $connection;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    public function getLastId() {
        return $this->connection->insert_id;
    }

    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }

    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

function db() {
    return Database::getInstance();
}

function conn() {
    return db()->getConnection();
}