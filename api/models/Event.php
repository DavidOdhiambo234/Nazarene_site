<?php
// C:\xampp\htdocs\church\api\models\Event.php
class Event {
    private $conn;
    private $table_name = "events";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll($published_only = true, $limit = 20, $offset = 0) {
        $query = "SELECT * FROM " . $this->table_name;
        if($published_only) {
            $query .= " WHERE is_published = 1";
        }
        $query .= " ORDER BY start_date DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    public function readUpcoming($limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE is_published = 1 AND start_date > NOW()
                  ORDER BY start_date ASC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    public function readOne($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND is_published = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>