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
    $contactId = filter_var($_POST['contact_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if ($contactId && $action === 'update_status' && in_array($_POST['status'] ?? '', ['unread', 'read', 'replied'], true)) {
        $stmt = $db->prepare('UPDATE contacts SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $_POST['status'], ':id' => $contactId]);
        $_SESSION['admin_notice'] = 'Message status updated.';
    } elseif ($contactId && $action === 'delete_contact') {
        $stmt = $db->prepare('DELETE FROM contacts WHERE id = :id');
        $stmt->execute([':id' => $contactId]);
        $_SESSION['admin_notice'] = 'Message removed.';
    }
    header('Location: contacts.php');
    exit();
}

$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $db->prepare('SELECT * FROM contacts WHERE name LIKE :name_search OR email LIKE :email_search OR subject LIKE :subject_search ORDER BY created_at DESC LIMIT 200');
    $pattern = '%' . $search . '%';
    $stmt->execute([':name_search' => $pattern, ':email_search' => $pattern, ':subject_search' => $pattern]);
} else {
    $stmt = $db->query('SELECT * FROM contacts ORDER BY created_at DESC LIMIT 200');
}
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function contact_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts | University Church</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page-body">
<div class="admin-layout">
    <?php $adminActivePage = 'contacts.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <h1 class="admin-page-title">Contact Messages</h1>
        <p class="admin-page-subtitle">Review incoming messages and keep their response status up to date.</p>
        <?php if ($notice !== ''): ?><div class="admin-notice" role="status"><?php echo contact_h($notice); ?></div><?php endif; ?>
        <section class="admin-panel">
            <h2 class="admin-panel-heading">Inbox</h2>
            <form method="get" action="contacts.php" class="admin-inline-form" style="padding: 14px 18px;">
                <input type="search" name="q" value="<?php echo contact_h($search); ?>" placeholder="Search name, email, subject" aria-label="Search messages">
                <button class="admin-button-primary" type="submit">Search</button>
                <?php if ($search !== ''): ?><a class="admin-button" href="contacts.php">Clear</a><?php endif; ?>
            </form>
            <?php if ($contacts): ?>
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>From</th><th>Subject and message</th><th>Received</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td><strong><?php echo contact_h($contact['name']); ?></strong><small><?php echo contact_h($contact['email']); ?></small><?php if ($contact['phone']): ?><small><?php echo contact_h($contact['phone']); ?></small><?php endif; ?></td>
                            <td><strong><?php echo contact_h($contact['subject']); ?></strong><small><?php echo nl2br(contact_h($contact['message'])); ?></small></td>
                            <td><?php echo contact_h(date('M j, Y', strtotime($contact['created_at']))); ?></td>
                            <td><?php echo contact_h(ucfirst($contact['status'])); ?></td>
                            <td><div class="admin-inline-form">
                                <form method="post" action="contacts.php" class="admin-inline-form"><input type="hidden" name="csrf_token" value="<?php echo contact_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="contact_id" value="<?php echo contact_h($contact['id']); ?>"><input type="hidden" name="action" value="update_status"><select name="status" aria-label="Message status"><option value="unread" <?php echo $contact['status'] === 'unread' ? 'selected' : ''; ?>>Unread</option><option value="read" <?php echo $contact['status'] === 'read' ? 'selected' : ''; ?>>Read</option><option value="replied" <?php echo $contact['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option></select><button class="admin-button" type="submit">Save</button></form>
                                <form method="post" action="contacts.php" onsubmit="return confirm('Delete this message?');"><input type="hidden" name="csrf_token" value="<?php echo contact_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="contact_id" value="<?php echo contact_h($contact['id']); ?>"><input type="hidden" name="action" value="delete_contact"><button class="admin-button-danger" type="submit">Delete</button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p class="admin-empty">No contact messages found.</p><?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>