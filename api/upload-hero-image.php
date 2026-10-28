<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if (!isset($_FILES['hero_image']) || $_FILES['hero_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['hero_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF']);
        exit;
    }
    
    // Validate file size
    if ($file['size'] > $max_file_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max size: 5MB']);
        exit;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'hero-' . uniqid() . '.' . $extension;
    $upload_path = '../uploads/hero/' . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir('../uploads/hero/')) {
        mkdir('../uploads/hero/', 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }
    
    // Update database with new image path
    $dbHost = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
    $dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'nazarene_site');
    $dbUser = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $dbPass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

    $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get old image to delete
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'hero_background_image'");
    $stmt->execute();
    $old_image = $stmt->fetchColumn();
    
    // Update database
    $stmt = $db->prepare("
        INSERT INTO site_settings (setting_key, setting_value, updated_at) 
        VALUES ('hero_background_image', :filename, NOW()) 
        ON DUPLICATE KEY UPDATE 
        setting_value = :filename, 
        updated_at = NOW()
    ");
    $stmt->execute([':filename' => 'uploads/hero/' . $filename]);
    
    // Delete old image if it exists and is not default
    if ($old_image && $old_image !== 'default-hero.jpg' && file_exists('../' . $old_image)) {
        unlink('../' . $old_image);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Image uploaded successfully',
        'image_url' => 'uploads/hero/' . $filename
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>