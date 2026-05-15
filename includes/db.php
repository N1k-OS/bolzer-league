<?php
// includes/db.php

class Database {
    // Ändere diese Daten passend zu deinem InfinityFree Account!
    private $host = "sql311.infinityfree.com"; // Hast du oben im Screenshot verraten 😉
    private $db_name = "if0_41922567_bolzer_league"; 
    private $username = "if0_41922567"; // Meistens gleich dem Präfix der Datenbank
    private $password = "IAyuWewJ21"; // Dein InfinityFree Passwort
    
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            die("Datenbank-Fehler. Bitte später erneut versuchen.");
        }
        return $this->conn;
    }
}
?>