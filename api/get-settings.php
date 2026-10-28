<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "config/database.php";

$database = new Database();
$db = $database->getConnection();

$settings = [];
$query = "SELECT setting_key, setting_value FROM settings";
$result = $db->query($query);

while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

echo json_encode(["success" => true, "data" => $settings]);
?>