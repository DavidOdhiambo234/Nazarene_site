<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/auth.php'; // Your authentication system

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Support DB credentials defined as constants, variables, or environment vars
    $db_host = defined('DB_HOST') ? DB_HOST : (isset($DB_HOST) ? $DB_HOST : getenv('DB_HOST'));
    $db_name = defined('DB_NAME') ? DB_NAME : (isset($DB_NAME) ? $DB_NAME : getenv('DB_NAME'));
    $db_user = defined('DB_USER') ? DB_USER : (isset($DB_USER) ? $DB_USER : getenv('DB_USER'));
    $db_pass = defined('DB_PASS') ? DB_PASS : (isset($DB_PASS) ? $DB_PASS : getenv('DB_PASS'));

    if (empty($db_host) || empty($db_name)) {
        throw new PDOException('Database host or name not configured');
    }

    $db = new PDO("mysql:host={$db_host};dbname={$db_name}", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['settings']) || !is_array($input['settings'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $db->beginTransaction();
    
    foreach ($input['settings'] as $key => $value) {
        $stmt = $db->prepare("
            INSERT INTO site_settings (setting_key, setting_value, updated_at) 
            VALUES (:key, :value, NOW()) 
            ON DUPLICATE KEY UPDATE 
            setting_value = :value, 
            updated_at = NOW()
        ");
        $stmt->execute([':key' => $key, ':value' => $value]);
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
} catch (PDOException $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>