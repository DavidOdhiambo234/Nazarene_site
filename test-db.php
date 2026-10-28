<?php
// C:\xampp\htdocs\Nazarene_site\test-db.php
require_once 'api/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if($db) {
        echo "<h1 style='color:green'>✓ Database Connected Successfully!</h1>";
        echo "<p>Host: localhost</p>";
        echo "<p>Database: church_database</p>";
        
        // Test query
        $stmt = $db->query("SELECT COUNT(*) as count FROM events");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Total events in database: " . $result['count'] . "</p>";
    } else {
        echo "<h1 style='color:red'>✗ Database Connection Failed</h1>";
    }
} catch(Exception $e) {
    echo "<h1 style='color:red'>✗ Error: " . $e->getMessage() . "</h1>";
}
?>