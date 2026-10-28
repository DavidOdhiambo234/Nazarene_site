<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = file_get_contents("php://input");
$data = json_decode($input);

error_log("Registration request received: " . $input);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received"]);
    exit();
}

if (empty($data->full_name) || empty($data->email) || empty($data->phone)) {
    echo json_encode(["success" => false, "message" => "All fields are required"]);
    exit();
}

try {
    require_once "config/database.php";
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Create table if not exists
    $createTable = "CREATE TABLE IF NOT EXISTS registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        event_id INT DEFAULT 1,
        number_of_guests INT DEFAULT 1,
        status VARCHAR(20) DEFAULT 'pending',
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($createTable);
    
    // Check if already registered
    $check = $db->prepare("SELECT id FROM registrations WHERE email = :email");
    $check->bindParam(":email", $data->email);
    $check->execute();
    
    if ($check->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "You have already registered for this event"]);
        exit();
    }
    
    // Insert registration
    $insert = $db->prepare("INSERT INTO registrations (full_name, email, phone, event_id) VALUES (:name, :email, :phone, :event_id)");
    $insert->bindParam(":name", $data->full_name);
    $insert->bindParam(":email", $data->email);
    $insert->bindParam(":phone", $data->phone);
    $event_id = $data->event_id ?? 1;
    $insert->bindParam(":event_id", $event_id);
    
    if ($insert->execute()) {
        echo json_encode(["success" => true, "message" => "Registration successful! Thank you for signing up."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to save registration"]);
    }
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>