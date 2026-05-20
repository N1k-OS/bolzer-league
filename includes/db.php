<?php
// includes/db.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;

    public function __construct() {
        $config = require __DIR__ . '/../config/db_credentials.php';
        $this->host = $config['host'];
        $this->db_name = $config['db_name'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }
    
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('DB connection failed: ' . $exception->getMessage());
            die('Datenbank-Verbindung fehlgeschlagen. Bitte später erneut versuchen.');
        }
        return $this->conn;
    }
}
