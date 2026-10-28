<?php
// C:\xampp\htdocs\Nazarene_site\check-admin.php
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>Admin User Diagnostic</h1>";

try {
    // Check if users table exists
    $query = "SHOW TABLES LIKE 'users'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        echo "<p style='color:red'>❌ Users table does not exist! Creating it now...</p>";
        
        // Create users table
        $createTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(50),
            last_name VARCHAR(50),
            role ENUM('admin', 'pastor', 'volunteer') DEFAULT 'volunteer',
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($createTable);
        echo "<p style='color:green'>✅ Users table created!</p>";
    } else {
        echo "<p style='color:green'>✅ Users table exists</p>";
    }
    
    // Check if admin user exists
    $query = "SELECT id, username, email, password_hash, role, is_active FROM users WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        echo "<p style='color:orange'>⚠️ Admin user not found! Creating admin user...</p>";
        
        // Create password hash for 'Admin123!'
        $password_hash = password_hash('Admin123!', PASSWORD_DEFAULT);
        
        $insert = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
                   VALUES ('admin', 'admin@universitychurch.org', :password, 'Church', 'Admin', 'admin', 1)";
        $insertStmt = $db->prepare($insert);
        $insertStmt->bindParam(':password', $password_hash);
        
        if($insertStmt->execute()) {
            echo "<p style='color:green'>✅ Admin user created successfully!</p>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>Password:</strong> Admin123!</p>";
        } else {
            echo "<p style='color:red'>❌ Failed to create admin user</p>";
        }
    } else {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p style='color:green'>✅ Admin user found</p>";
        echo "<p><strong>Username:</strong> " . $user['username'] . "</p>";
        echo "<p><strong>Email:</strong> " . $user['email'] . "</p>";
        echo "<p><strong>Role:</strong> " . $user['role'] . "</p>";
        echo "<p><strong>Active:</strong> " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Password Hash:</strong> " . substr($user['password_hash'], 0, 30) . "...</p>";
        
        // Test password verification
        $test_password = 'Admin123!';
        if(password_verify($test_password, $user['password_hash'])) {
            echo "<p style='color:green'>✅ Password 'Admin123!' is correct!</p>";
        } else {
            echo "<p style='color:red'>❌ Password 'Admin123!' does NOT match!</p>";
            echo "<p>Updating password...</p>";
            
            $new_hash = password_hash('Admin123!', PASSWORD_DEFAULT);
            $update = "UPDATE users SET password_hash = :hash WHERE username = 'admin'";
            $updateStmt = $db->prepare($update);
            $updateStmt->bindParam(':hash', $new_hash);
            if($updateStmt->execute()) {
                echo "<p style='color:green'>✅ Password has been reset to 'Admin123!'</p>";
            }
        }
    }
    
    // List all users
    echo "<h2>All Users in Database:</h2>";
    $allUsers = $db->query("SELECT id, username, email, role, is_active FROM users");
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th></tr>";
    while($row = $allUsers->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch(PDOException $e) {
    echo "<p style='color:red'>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>