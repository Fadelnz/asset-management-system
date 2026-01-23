<?php
session_start();

class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "asset_management";
    public $conn;
    public $pdo;

    public function __construct() {
        try {
            // MySQLi connection
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->conn->connect_error) {
                throw new Exception("MySQLi Connection failed: " . $this->conn->connect_error);
            }
            
            // PDO connection
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->database}",
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }

    public function sanitize($data) {
        // For MySQLi
        return $this->conn->real_escape_string(htmlspecialchars(trim($data)));
    }
    
    public function sanitizePDO($data) {
        // For PDO, use prepared statements instead
        return htmlspecialchars(trim($data));
    }
}

$db = new Database();
?>