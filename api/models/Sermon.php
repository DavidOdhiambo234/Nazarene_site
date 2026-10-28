<?php
// C:\xampp\htdocs\church\api\models\Sermon.php
class Sermon {
    private $conn;
    private $table_name = "sermons";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll($published_only = true, $limit = 20) {
        $query = "SELECT * FROM " . $this->table_name;
        if($published_only) {
            $query .= " WHERE is_published = 1";
        }
        $query .= " ORDER BY preached_date DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }
}
?>