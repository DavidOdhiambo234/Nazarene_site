<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

echo json_encode([
    "success" => true,
    "message" => "API is working!",
    "timestamp" => date('Y-m-d H:i:s'),
    "server" => "PHP Built-in Server"
]);
?>