<?php
session_start();
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../api/config/database.php';

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    exit('Database connection unavailable. Check the database configuration.');
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$setupOpen = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $username = trim($_POST['username'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!$setupOpen) {
        $error = 'First-admin setup has already been completed.';
    } elseif (!preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username)) {
        $error = 'Use 3 to 50 letters, numbers, dots, underscores, or hyphens for the username.';
    } elseif (!$email) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 12) {
        $error = 'Choose a password with at least 12 characters.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'The passwords do not match.';
    } else {
        $lockAcquired = false;
        try {
            $lockAcquired = (int) $db->query("SELECT GET_LOCK('nazarene_first_admin_setup', 5)")->fetchColumn() === 1;
            if (!$lockAcquired) {
                throw new RuntimeException('Setup is busy. Please try again.');
            }
            if ((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                throw new RuntimeException('First-admin setup has already been completed.');
            }

            $stmt = $db->prepare(
                'INSERT INTO users (username, email, password_hash, role, is_active) '
                . 'VALUES (:username, :email, :password_hash, \'admin\', 1)'
            );
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            header('Location: Login.php?setup=created');
            exit();
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'That username or email is already in use.'
                : 'The administrator account could not be created.';
        } finally {
            if ($lockAcquired) {
                $db->query("SELECT RELEASE_LOCK('nazarene_first_admin_setup')");
            }
        }
    }
}

$setupOpen = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Administrator | Nazarene Church</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 20px; color: #202827; background: #f2f5f2; font: 15px/1.5 Arial, sans-serif; }
        main { width: min(440px, 100%); padding: 30px; border-top: 3px solid #245c4b; background: #fff; }
        h1 { margin: 0 0 8px; font: 500 30px/1.15 Georgia, 'Times New Roman', serif; }
        p { color: #64716e; }
        label { display: block; margin: 15px 0 6px; font-size: 13px; font-weight: 700; }
        input { width: 100%; min-height: 42px; padding: 9px 10px; border: 1px solid #dce3df; border-radius: 3px; font: inherit; }
        button { width: 100%; min-height: 44px; margin-top: 20px; border: 0; border-radius: 3px; color: #fff; background: #245c4b; font: 700 14px Arial, sans-serif; cursor: pointer; }
        button:hover { background: #194538; }
        .error { margin: 15px 0; padding: 10px 12px; border-left: 3px solid #a63e36; color: #712b27; background: #fbefed; }
        a { color: #245c4b; }
    </style>
</head>
<body>
<main>
    <h1>Set up admin access</h1>
    <?php if (!$setupOpen): ?>
        <p>Administrator setup is closed because an account already exists.</p>
        <a href="Login.php">Go to admin login</a>
    <?php else: ?>
        <p>Create the first administrator account. This setup page locks after the account is created.</p>
        <?php if ($error !== ''): ?><div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" action="setup-admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" required>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" autocomplete="email" required>
            <label for="password">Password (at least 12 characters)</label>
            <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
            <label for="password_confirm">Confirm password</label>
            <input id="password_confirm" name="password_confirm" type="password" minlength="12" autocomplete="new-password" required>
            <button type="submit">Create administrator</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>