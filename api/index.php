<?php
// C:\xampp\htdocs\church\api\index.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "config/database.php";
require_once "models/Contact.php";
require_once "models/Event.php";
require_once "models/Sermon.php";
require_once "models/Registration.php";

$database = new Database();
$db = $database->getConnection();

$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse the endpoint
$path = parse_url($request_uri, PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));
$endpoint = $segments[1] ?? '';

try {
    switch($endpoint) {
        case 'contact':
            handleContact($method, $db);
            break;
        case 'events':
            handleEvents($method, $db, $segments);
            break;
        case 'sermons':
            handleSermons($method, $db, $segments);
            break;
        case 'register':
            handleRegistration($method, $db);
            break;
        case 'newsletter':
            handleNewsletter($method, $db);
            break;
        default:
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found"]);
    }
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

function handleContact($method, $db) {
    $contact = new Contact($db);
    
    if($method == 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->name) && !empty($data->email) && !empty($data->message)) {
            $contact->name = $data->name;
            $contact->email = $data->email;
            $contact->phone = $data->phone ?? '';
            $contact->subject = $data->subject ?? 'General Inquiry';
            $contact->message = $data->message;
            
            if($contact->create()) {
                // Send email notification (configure mail in XAMPP)
                $to = "admin@universitychurch.org";
                $subject = "New Contact: " . $contact->subject;
                $message = "Name: $contact->name\nEmail: $contact->email\nMessage: $contact->message";
                mail($to, $subject, $message);
                
                echo json_encode(["success" => true, "message" => "Message sent successfully!"]);
            } else {
                echo json_encode(["success" => false, "message" => "Failed to send message"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Please fill all required fields"]);
        }
    }
}

function handleEvents($method, $db, $segments) {
    $event = new Event($db);
    $id = $segments[2] ?? null;
    
    if($method == 'GET') {
        if(isset($_GET['upcoming'])) {
            $stmt = $event->readUpcoming($_GET['limit'] ?? 10);
        } else if($id) {
            $result = $event->readOne($id);
            echo json_encode(["success" => true, "data" => $result]);
            return;
        } else {
            $stmt = $event->readAll(true, $_GET['limit'] ?? 20, $_GET['offset'] ?? 0);
        }
        
        $events = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = $row;
        }
        echo json_encode(["success" => true, "data" => $events]);
    }
}

function handleSermons($method, $db, $segments) {
    $sermon = new Sermon($db);
    $id = $segments[2] ?? null;
    
    if($method == 'GET') {
        $stmt = $sermon->readAll(true, $_GET['limit'] ?? 20);
        $sermons = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sermons[] = $row;
        }
        echo json_encode(["success" => true, "data" => $sermons]);
    }
}

function handleRegistration($method, $db) {
    $registration = new Registration($db);
    
    if($method == 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        $result = $registration->register($data);
        echo json_encode($result);
    }
}

function handleNewsletter($method, $db) {
    if($method == 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        $query = "INSERT INTO newsletter_subscribers (email, name) VALUES (:email, :name)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":name", $data->name);
        
        if($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Subscribed successfully!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Email already subscribed"]);
        }
    }
}
?>