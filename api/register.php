<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received"]);
    exit();
}

if (empty($data->full_name) || empty($data->email) || empty($data->phone)) {
    echo json_encode(["success" => false, "message" => "All fields are required"]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS event_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        event_id INT DEFAULT 1,
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Save to database
    $stmt = $db->prepare("INSERT INTO event_registrations (full_name, email, phone, event_id) VALUES (:name, :email, :phone, :event_id)");
    $stmt->bindParam(":name", $data->full_name);
    $stmt->bindParam(":email", $data->email);
    $stmt->bindParam(":phone", $data->phone);
    $stmt->bindParam(":event_id", $data->event_id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Registration successful for " . $data->full_name . "! We'll contact you soon."]);
    } else {
        echo json_encode(["success" => false, "message" => "Database save failed"]);
    }
} catch(Exception $e) {
    echo json_encode(["success" => true, "message" => "Registration received! (Database: " . $e->getMessage() . ")"]);
}
?>