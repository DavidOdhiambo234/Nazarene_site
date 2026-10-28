<?php
// C:\xampp\htdocs\Nazarene_site\admin\login.php
session_start();
ob_start(); // Buffer output to prevent header issues

// Display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../api/config/database.php";

$error = '';
$setupRequired = false;
$database = new Database();
$db = $database->getConnection();

if ($db) {
    $setupRequired = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
} else {
    $error = 'Unable to connect to the database. Check the database setup and try again.';
}

// If already logged in, redirect to dashboard
if(isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

if($_SERVER["REQUEST_METHOD"] == "POST" && $db) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            // Prepare query to get user
            $query = "SELECT id, username, email, password_hash, role, is_active 
                      FROM users 
                      WHERE username = :username AND role = 'admin' LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            
            if($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if user is active
                if($user['is_active'] != 1) {
                    $error = "Your account is deactivated. Please contact administrator.";
                } else {
                    // Verify password
                    if(password_verify($password, $user['password_hash'])) {
                        // Password is correct - set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['logged_in'] = true;
                        
                        // Update last login timestamp
                        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
                        $updateStmt = $db->prepare($updateQuery);
                        $updateStmt->bindParam(":id", $user['id']);
                        $updateStmt->execute();
                        
                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid username or password";
                    }
                }
            } else {
                $error = "Invalid username or password";
            }
        } catch(Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - University Church</title>
    <style>
        :root {
            --church-red: #9D0A0F;
            --church-green: #14736C;
            --church-blue: #007799;
            --church-gold: #D39512;
            --ink: #211d1c;
            --muted: #625e5a;
            --line: #e5e1dc;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 24px 18px;
            color: var(--ink);
            background-color: #2a090d;
            background-image:
                repeating-linear-gradient(0deg, transparent 0 34px, rgba(211, 149, 18, 0.11) 34px 35px),
                repeating-linear-gradient(90deg, transparent 0 34px, rgba(20, 115, 108, 0.15) 34px 35px),
                linear-gradient(135deg, #6f1b12 0%, #3b0d12 48%, #17080b 100%);
            background-size: 35px 35px, 35px 35px, cover;
            font-family: Georgia, 'Times New Roman', serif;
        }

        .login-container {
            position: relative;
            width: min(100%, 460px);
            padding: 32px 42px 24px;
            overflow: hidden;
            border: 1px solid rgba(157, 10, 15, 0.14);
            border-top: 5px solid var(--church-gold);
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(12, 4, 5, 0.38);
            animation: login-arrive 420ms ease-out both;
        }

        .login-container::before {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--church-red) 0 62%, var(--church-green) 62% 82%, var(--church-blue) 82%);
            content: '';
        }

        @keyframes login-arrive {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header { margin-bottom: 22px; text-align: center; }

        .login-logo {
            display: block;
            width: 76px;
            height: 76px;
            margin: 0 auto 10px;
            object-fit: contain;
        }

        .login-kicker {
            margin: 0 0 6px;
            color: var(--church-green);
            font: 700 10px Arial, sans-serif;
            text-transform: uppercase;
        }

        .login-header h1 {
            margin: 0;
            color: var(--church-red);
            font: 500 29px/1.15 Georgia, 'Times New Roman', serif;
        }

        .login-header p:last-child {
            margin: 8px 0 0;
            color: var(--muted);
            font: 13px/1.5 Arial, sans-serif;
        }

        .form-group { margin-bottom: 17px; }

        label {
            display: block;
            margin-bottom: 7px;
            color: var(--ink);
            font: 700 13px Arial, sans-serif;
        }

        input {
            width: 100%;
            min-height: 48px;
            padding: 12px 13px;
            border: 1px solid #d6d3cf;
            border-radius: 3px;
            background: #fcfbf9;
            color: var(--ink);
            font: 15px Arial, sans-serif;
            transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;
        }

        input::placeholder { color: #8a8580; }

        input:focus {
            outline: none;
            border-color: var(--church-blue);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0, 119, 153, 0.13);
        }

        button {
            width: 100%;
            min-height: 48px;
            margin-top: 4px;
            border: 1px solid var(--church-red);
            border-radius: 3px;
            background: var(--church-red);
            color: #fff;
            cursor: pointer;
            font: 700 14px Arial, sans-serif;
            transition: background 180ms ease, box-shadow 180ms ease, transform 180ms ease;
        }

        button:hover {
            transform: translateY(-1px);
            background: #7d090d;
            box-shadow: 0 7px 18px rgba(157, 10, 15, 0.2);
        }

        button:focus-visible, a:focus-visible { outline: 3px solid rgba(0, 119, 153, 0.45); outline-offset: 3px; }

        .error, .info-box {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-left: 3px solid var(--church-red);
            background: #fbefed;
            color: #712b27;
            font: 13px/1.5 Arial, sans-serif;
        }

        .info-box {
            border-left-color: var(--church-green);
            background: rgba(20, 115, 108, 0.08);
            color: #15574f;
        }

        .info-box strong { display: block; margin-bottom: 4px; }
        .info-box a { color: var(--church-red); font-weight: 700; }

        .footer {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font: 11px/1.5 Arial, sans-serif;
            text-align: center;
        }

        .footer p { margin: 0; }
        .back-link { display: inline-block; margin-top: 8px; color: var(--church-green); text-decoration: none; }
        .back-link:hover { color: var(--church-red); text-decoration: underline; }

        @media (max-width: 520px) {
            body { padding: 20px 14px; }
            .login-container { padding: 30px 23px 23px; }
            .login-logo { width: 74px; height: 74px; }
            .login-header h1 { font-size: 27px; }
        }

        @media (max-height: 640px) {
            body { align-items: start; padding-block: 14px; }
            .login-container { padding: 24px 32px 18px; }
            .login-logo { width: 64px; height: 64px; margin-bottom: 7px; }
            .login-header { margin-bottom: 17px; }
            .form-group { margin-bottom: 13px; }
            .footer { margin-top: 14px; padding-top: 10px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img class="login-logo" src="../images/mylogo.png" alt="University Church of the Nazarene logo">
            <p class="login-kicker">University Church of the Nazarene</p>
            <h1>Admin sign in</h1>
            <p>Use your administrator account to continue.</p>
        </div>
        
        <?php if(isset($_GET['setup']) && $_GET['setup'] === 'created'): ?>
            <div class="info-box">Administrator account created. You can now sign in.</div>
        <?php elseif($setupRequired): ?>
            <div class="info-box">
                No administrator account exists yet.<br>
                <a href="setup-admin.php">Create the first administrator</a>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" placeholder="Enter your username" required autocomplete="username" autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>
            
            <button type="submit">Sign in to dashboard</button>
        </form>
        
        <div class="footer">
            <p>University Church of the Nazarene &copy; <?php echo date('Y'); ?></p>
            <a class="back-link" href="../index.html">Return to the church website</a>
        </div>
    </div>
</body>
</html>