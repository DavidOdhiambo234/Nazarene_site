<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "config/database.php";

$database = new Database();
$db = $database->getConnection();

// Get the featured sermon (only one)
$query = "SELECT * FROM sermons WHERE is_published = 1 AND is_featured = 1 LIMIT 1";
$result = $db->query($query);
$sermon = $result->fetch(PDO::FETCH_ASSOC);

// If no featured sermon, get the latest one
if(!$sermon) {
    $query = "SELECT * FROM sermons WHERE is_published = 1 ORDER BY preached_date DESC LIMIT 1";
    $result = $db->query($query);
    $sermon = $result->fetch(PDO::FETCH_ASSOC);
}

if($sermon) {
    // Extract YouTube video ID
    if(!empty($sermon['video_url'])) {
        if(preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $sermon['video_url'], $matches)) {
            $sermon['video_id'] = $matches[1];
        }
    }
    echo json_encode(["success" => true, "data" => $sermon]);
} else {
    echo json_encode(["success" => false, "message" => "No sermon found"]);
}
?>