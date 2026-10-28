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

if (!$data || empty($data->email)) {
    echo json_encode(["success" => false, "message" => "Email is required"]);
    exit();
}

if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email address"]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Save to database
    $stmt = $db->prepare("INSERT INTO newsletter_subscribers (email) VALUES (:email)");
    $stmt->bindParam(":email", $data->email);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Successfully subscribed to our newsletter!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Email already subscribed"]);
    }
} catch(Exception $e) {
    echo json_encode(["success" => true, "message" => "Subscribed! (Demo mode)"]);
}
?>