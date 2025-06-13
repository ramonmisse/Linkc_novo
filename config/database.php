<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        // Usa as constantes definidas em config.php
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        // Log para debug
        error_log("Database Constructor - Usando credenciais:");
        error_log("Host: " . $this->host);
        error_log("Database: " . $this->db_name);
        error_log("Username: " . $this->username);
        error_log("Password: " . (empty($this->password) ? "vazia" : "definida"));
    }
    
    public function getConnection() {
        try {
            if ($this->conn === null) {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                error_log("Tentando conectar ao banco de dados com DSN: " . $dsn);
                error_log("Username: " . $this->username);
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            }
            return $this->conn;
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}
?>
