<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: Login.php');
    exit();
}

require_once __DIR__ . '/../api/config/database.php';
$database = new Database();
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $key = trim($_POST['setting_key'] ?? '');
    $value = trim($_POST['setting_value'] ?? '');
    $notice = 'Setting saved.';

    if (!preg_match('/\A[a-zA-Z0-9._-]{1,100}\z/', $key)) {
        $notice = 'Use 1 to 100 letters, numbers, dots, underscores, or hyphens for the setting key.';
    } elseif (strlen($value) > 60000) {
        $notice = 'The setting value is too long.';
    } else {
        $db->beginTransaction();
        try {
            if ($action === 'delete_setting') {
                foreach (['settings', 'site_settings'] as $table) {
                    $stmt = $db->prepare("DELETE FROM {$table} WHERE setting_key = :setting_key");
                    $stmt->execute([':setting_key' => $key]);
                }
                $notice = 'Setting removed.';
            } elseif ($action === 'save_setting' || $action === 'add_setting') {
                foreach (['settings', 'site_settings'] as $table) {
                    $stmt = $db->prepare("INSERT INTO {$table} (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([':setting_key' => $key, ':setting_value' => $value]);
                }
                $notice = $action === 'add_setting' ? 'Setting added.' : 'Setting saved.';
            }
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            $notice = 'The setting could not be saved.';
        }
    }

    $_SESSION['admin_notice'] = $notice;
    header('Location: settings.php');
    exit();
}

$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$settings = [];
foreach (['settings', 'site_settings'] as $table) {
    $rows = $db->query("SELECT setting_key, setting_value FROM {$table} ORDER BY setting_key")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
ksort($settings);

function setting_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | University Church</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page-body">
<div class="admin-layout">
    <?php $adminActivePage = 'settings.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <h1 class="admin-page-title">Site Settings</h1>
        <p class="admin-page-subtitle">Manage the key/value settings used by the public website.</p>
        <?php if ($notice !== ''): ?><div class="admin-notice" role="status"><?php echo setting_h($notice); ?></div><?php endif; ?>

        <section class="admin-panel">
            <h2 class="admin-panel-heading">Add a setting</h2>
            <form method="post" action="settings.php" class="admin-form-grid" style="padding: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo setting_h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_setting">
                <div class="admin-field"><label for="new-key">Setting key</label><input id="new-key" name="setting_key" pattern="[A-Za-z0-9._-]+" maxlength="100" required placeholder="e.g. hero_background_image"></div>
                <div class="admin-field"><label for="new-value">Setting value</label><input id="new-value" name="setting_value" maxlength="60000"></div>
                <div class="admin-field admin-field-wide"><button class="admin-button-primary" type="submit">Add setting</button></div>
            </form>
        </section>

        <section class="admin-panel">
            <h2 class="admin-panel-heading">Current settings</h2>
            <?php if ($settings): ?>
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>Key</th><th>Value</th><th>Actions</th></tr></thead>
                    <tbody><?php foreach ($settings as $key => $value): ?>
                        <tr>
                            <td><code><?php echo setting_h($key); ?></code></td>
                            <td><form id="setting-<?php echo setting_h($key); ?>" method="post" action="settings.php"><input type="hidden" name="csrf_token" value="<?php echo setting_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="action" value="save_setting"><input type="hidden" name="setting_key" value="<?php echo setting_h($key); ?>"><textarea name="setting_value" aria-label="Value for <?php echo setting_h($key); ?>" rows="2" style="width: min(100%, 620px); min-width: 240px; padding: 8px; border: 1px solid #e5e1dc; border-radius: 3px; font: 13px Arial, sans-serif;"><?php echo setting_h($value); ?></textarea></form></td>
                            <td><div class="admin-inline-form"><button class="admin-button-primary" type="submit" form="setting-<?php echo setting_h($key); ?>">Save</button>
                                <form method="post" action="settings.php" onsubmit="return confirm('Remove this setting?');"><input type="hidden" name="csrf_token" value="<?php echo setting_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="action" value="delete_setting"><input type="hidden" name="setting_key" value="<?php echo setting_h($key); ?>"><button class="admin-button-danger" type="submit">Delete</button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p class="admin-empty">No site settings have been added.</p><?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>