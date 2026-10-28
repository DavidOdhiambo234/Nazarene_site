<?php
// C:\xampp\htdocs\church\api\config\database.php
class Database {
    private $host = "localhost";
    private $db_name = "church_database";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $host = getenv('DB_HOST') ?: $this->host;
        $port = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: $this->db_name;
        $username = getenv('DB_USER') ?: $this->username;
        $password = getenv('DB_PASS');
        if ($password === false) {
            $password = $this->password;
        }

        $dsn = getenv('DB_DSN');
        if (!$dsn) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $dbName
            );
        }

        try {
            $this->conn = new PDO($dsn, $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $exception) {
            error_log('Database connection failed: ' . $exception->getMessage());
            throw $exception;
        }
        return $this->conn;
    }
}
?>