<?php
// api/config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "church_database";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        // 1. Read environment variables from Aiven
        $host = getenv('DB_HOST') ?: $this->host;
        $port = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: $this->db_name;
        $username = getenv('DB_USER') ?: $this->username;
        $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $this->password;

        // 2. Build the Data Source Name (DSN)
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
            // 3. Add AWS RDS SSL Options
            // AWS requires an encrypted connection. 
            // Setting SSL_VERIFY_SERVER_CERT to false allows connection without needing the exact CA file.
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Crucial for AWS
            ];

            $this->conn = new PDO($dsn, $username, $password, $options);
            
        } catch(PDOException $exception) {
            // Log the error so you can see it in Aiven's log viewer
            error_log('Database connection failed: ' . $exception->getMessage());
            
            // Return a clean error to the frontend instead of crashing the whole page
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed. Please check server logs.']);
            exit;
        }
        
        return $this->conn;
    }
}
?>