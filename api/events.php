<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Return some sample events
$events = [
    [
        "id" => 1,
        "title" => "Sunday Worship Service",
        "description" => "Join us for powerful worship and teaching",
        "start_date" => date('Y-m-d H:i:s', strtotime('this Sunday')),
        "location" => "University Church Sanctuary",
        "requires_registration" => false
    ],
    [
        "id" => 2,
        "title" => "Hiking Event at Ngong Hills",
        "description" => "A refreshing outdoor hike with faith and fellowship",
        "start_date" => date('Y-m-d H:i:s', strtotime('next Saturday')),
        "location" => "Ngong Hills",
        "requires_registration" => true
    ]
];

echo json_encode(["success" => true, "data" => $events]);
?>