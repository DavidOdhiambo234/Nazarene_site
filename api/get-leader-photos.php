<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/leader-profiles.php';

$database = new Database();
$db = $database->getConnection();
$profiles = load_leader_profiles($db);
echo json_encode(['success' => true, 'data' => $profiles], JSON_UNESCAPED_UNICODE);
