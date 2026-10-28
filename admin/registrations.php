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
    $registrationId = filter_var($_POST['registration_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if ($registrationId && $action === 'update_status' && in_array($_POST['status'] ?? '', ['pending', 'confirmed', 'cancelled'], true)) {
        $stmt = $db->prepare('UPDATE registrations SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $_POST['status'], ':id' => $registrationId]);
        $_SESSION['admin_notice'] = 'Registration status updated.';
    } elseif ($registrationId && $action === 'delete_registration') {
        $stmt = $db->prepare('DELETE FROM registrations WHERE id = :id');
        $stmt->execute([':id' => $registrationId]);
        $_SESSION['admin_notice'] = 'Registration removed.';
    }
    header('Location: registrations.php');
    exit();
}

$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$search = trim($_GET['q'] ?? '');
$baseSql = 'SELECT r.*, e.title AS event_title FROM registrations r LEFT JOIN events e ON e.id = r.event_id';
if ($search !== '') {
    $pattern = '%' . $search . '%';
    $stmt = $db->prepare($baseSql . ' WHERE r.full_name LIKE :name_search OR r.email LIKE :email_search OR e.title LIKE :event_search ORDER BY r.created_at DESC LIMIT 200');
    $stmt->execute([':name_search' => $pattern, ':email_search' => $pattern, ':event_search' => $pattern]);
} else {
    $stmt = $db->query($baseSql . ' ORDER BY r.created_at DESC LIMIT 200');
}
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

function registration_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrations | University Church</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page-body">
<div class="admin-layout">
    <?php $adminActivePage = 'registrations.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <h1 class="admin-page-title">Event Registrations</h1>
        <p class="admin-page-subtitle">Review attendees and update registration status.</p>
        <?php if ($notice !== ''): ?><div class="admin-notice" role="status"><?php echo registration_h($notice); ?></div><?php endif; ?>
        <section class="admin-panel">
            <h2 class="admin-panel-heading">Attendees</h2>
            <form method="get" action="registrations.php" class="admin-inline-form" style="padding: 14px 18px;">
                <input type="search" name="q" value="<?php echo registration_h($search); ?>" placeholder="Search name, email, event" aria-label="Search registrations">
                <button class="admin-button-primary" type="submit">Search</button>
                <?php if ($search !== ''): ?><a class="admin-button" href="registrations.php">Clear</a><?php endif; ?>
            </form>
            <?php if ($registrations): ?>
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>Attendee</th><th>Event</th><th>Guests</th><th>Registered</th><th>Status / actions</th></tr></thead>
                    <tbody><?php foreach ($registrations as $registration): ?>
                        <tr>
                            <td><strong><?php echo registration_h($registration['full_name']); ?></strong><small><?php echo registration_h($registration['email']); ?></small><small><?php echo registration_h($registration['phone']); ?></small></td>
                            <td><?php echo registration_h($registration['event_title'] ?: 'Event #' . $registration['event_id']); ?></td>
                            <td><?php echo registration_h($registration['number_of_guests']); ?></td>
                            <td><?php echo registration_h(date('M j, Y', strtotime($registration['created_at']))); ?></td>
                            <td><div class="admin-inline-form">
                                <form method="post" action="registrations.php" class="admin-inline-form"><input type="hidden" name="csrf_token" value="<?php echo registration_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="registration_id" value="<?php echo registration_h($registration['id']); ?>"><input type="hidden" name="action" value="update_status"><select name="status" aria-label="Registration status"><option value="pending" <?php echo $registration['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="confirmed" <?php echo $registration['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option><option value="cancelled" <?php echo $registration['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select><button class="admin-button" type="submit">Save</button></form>
                                <form method="post" action="registrations.php" onsubmit="return confirm('Delete this registration?');"><input type="hidden" name="csrf_token" value="<?php echo registration_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="registration_id" value="<?php echo registration_h($registration['id']); ?>"><input type="hidden" name="action" value="delete_registration"><button class="admin-button-danger" type="submit">Delete</button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p class="admin-empty">No event registrations found.</p><?php endif; ?>
        </section>
        <p class="admin-page-subtitle"><a href="users.php">View legacy signups and newsletter subscribers</a></p>
    </main>
</div>
</body>
</html>