<?php
class Database {
    // Database credentials
    private $host = "localhost";
    private $db_name = "healthconnect";
    private $username = "root";
    private $password = "";
    private $conn = null;
    
    // Get database connection
    public function getConnection() {
        try {
            if ($this->conn === null) {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password
                );
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->conn->exec("SET NAMES utf8");
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
}
?> 