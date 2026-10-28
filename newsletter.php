<?php
// Enable error reporting
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

// Get the input data
$input = file_get_contents("php://input");
$data = json_decode($input);

// Log for debugging
error_log("Newsletter request received: " . $input);

if (!$data || empty($data->email)) {
    echo json_encode(["success" => false, "message" => "Email is required"]);
    exit();
}

// Validate email
if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email address"]);
    exit();
}

try {
    require_once "config/database.php";
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Create table if not exists
    $createTable = "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        name VARCHAR(100),
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($createTable);
    
    // Check if already subscribed
    $check = $db->prepare("SELECT id FROM newsletter_subscribers WHERE email = :email");
    $check->bindParam(":email", $data->email);
    $check->execute();
    
    if ($check->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "This email is already subscribed"]);
        exit();
    }
    
    // Insert new subscriber
    $insert = $db->prepare("INSERT INTO newsletter_subscribers (email) VALUES (:email)");
    $insert->bindParam(":email", $data->email);
    
    if ($insert->execute()) {
        echo json_encode(["success" => true, "message" => "Successfully subscribed to our newsletter!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to subscribe"]);
    }
} catch (Exception $e) {
    error_log("Newsletter error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>