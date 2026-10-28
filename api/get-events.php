<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "config/database.php";

$database = new Database();
$db = $database->getConnection();

$limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT);
$limit = max(1, min(100, $limit ?: 20));
$eventType = trim($_GET['type'] ?? '');
$includePast = ($_GET['all'] ?? '') === '1';

$query = "SELECT * FROM events WHERE is_published = 1";
if ($eventType !== '') {
    $query .= " AND event_type = :type";
}
if (!$includePast) {
    $query .= " AND COALESCE(event_date, DATE(start_date)) >= CURDATE()";
}
$query .= " ORDER BY COALESCE(event_date, DATE(start_date)) ASC, id DESC LIMIT :limit";

$stmt = $db->prepare($query);
if ($eventType !== '') {
    $stmt->bindValue(':type', $eventType, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$events = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $events[] = $row;
}

echo json_encode(["success" => true, "data" => $events]);
?>