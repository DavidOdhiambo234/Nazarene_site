<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$sermons = [
    [
        "id" => 1,
        "title" => "Living a Fruitful Life",
        "speaker" => "Rev. Richard Awanda",
        "scripture" => "John 15:5",
        "youtube_url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
        "preached_date" => date('Y-m-d')
    ],
    [
        "id" => 2,
        "title" => "The Power of Faith",
        "speaker" => "Rev. Charles Onyango",
        "scripture" => "Hebrews 11:1",
        "youtube_url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
        "preached_date" => date('Y-m-d', strtotime('-7 days'))
    ]
];

echo json_encode(["success" => true, "data" => $sermons]);
?>