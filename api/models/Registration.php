<?php
// C:\xampp\htdocs\church\api\models\Registration.php
class Registration {
    private $conn;
    private $table_name = "registrations";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($data) {
        // Check if already registered
        $checkQuery = "SELECT id FROM " . $this->table_name . " 
                       WHERE event_id = :event_id AND email = :email";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(":event_id", $data->event_id);
        $checkStmt->bindParam(":email", $data->email);
        $checkStmt->execute();
        
        if($checkStmt->rowCount() > 0) {
            return ["success" => false, "message" => "You have already registered for this event"];
        }
        
        // Insert registration
        $query = "INSERT INTO " . $this->table_name . "
                  SET event_id=:event_id, full_name=:full_name, email=:email,
                      phone=:phone, number_of_guests=:number_of_guests";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":event_id", $data->event_id);
        $stmt->bindParam(":full_name", $data->full_name);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":phone", $data->phone);
        $stmt->bindParam(":number_of_guests", $data->number_of_guests);
        
        if($stmt->execute()) {
            // Update event registered count
            $updateQuery = "UPDATE events SET registered_count = registered_count + 1 
                           WHERE id = :event_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(":event_id", $data->event_id);
            $updateStmt->execute();
            
            return ["success" => true, "message" => "Registration successful!"];
        }
        
        return ["success" => false, "message" => "Registration failed"];
    }
}
?>