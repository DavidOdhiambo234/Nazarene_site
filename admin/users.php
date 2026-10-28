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
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $recordId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($action === 'add_subscriber') {
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $name = trim($_POST['name'] ?? '');
            if (!$email) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $stmt = $db->prepare('INSERT INTO newsletter_subscribers (email, name) VALUES (:email, :name)');
            $stmt->execute([':email' => $email, ':name' => $name !== '' ? $name : null]);
            $notice = 'subscriber-added';
        } elseif ($action === 'update_registration' && $recordId) {
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
                throw new InvalidArgumentException('Choose a valid registration status.');
            }
            $stmt = $db->prepare('UPDATE registrations SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $recordId]);
            $notice = 'registration-updated';
        } elseif ($action === 'delete_registration' && $recordId) {
            $stmt = $db->prepare('DELETE FROM registrations WHERE id = :id');
            $stmt->execute([':id' => $recordId]);
            $notice = 'registration-deleted';
        } elseif ($action === 'delete_legacy_registration' && $recordId) {
            $stmt = $db->prepare('DELETE FROM event_registrations WHERE id = :id');
            $stmt->execute([':id' => $recordId]);
            $notice = 'registration-deleted';
        } elseif ($action === 'delete_subscriber' && $recordId) {
            $stmt = $db->prepare('DELETE FROM newsletter_subscribers WHERE id = :id');
            $stmt->execute([':id' => $recordId]);
            $notice = 'subscriber-deleted';
        } else {
            throw new InvalidArgumentException('That action could not be completed.');
        }
    } catch (PDOException $exception) {
        $notice = $exception->getCode() === '23000' ? 'duplicate-subscriber' : 'save-error';
    } catch (InvalidArgumentException $exception) {
        $notice = 'invalid-input';
    }

    header('Location: users.php?notice=' . urlencode($notice));
    exit();
}

$search = trim($_GET['q'] ?? '');
$searchPattern = '%' . $search . '%';

$registrationSql = "SELECT r.id, r.event_id, r.full_name, r.email, r.phone, r.number_of_guests,
                           r.status, r.created_at, e.title AS event_title
                    FROM registrations r
                    LEFT JOIN events e ON e.id = r.event_id";
if ($search !== '') {
    $registrationSql .= ' WHERE r.full_name LIKE :name_search OR r.email LIKE :email_search OR e.title LIKE :event_search';
}
$registrationSql .= ' ORDER BY r.created_at DESC LIMIT 100';
$registrationStmt = $db->prepare($registrationSql);
if ($search !== '') {
    $registrationStmt->execute([
        ':name_search' => $searchPattern,
        ':email_search' => $searchPattern,
        ':event_search' => $searchPattern,
    ]);
} else {
    $registrationStmt->execute();
}
$registrations = $registrationStmt->fetchAll(PDO::FETCH_ASSOC);

$legacySql = "SELECT id, full_name, email, phone, event_id, registered_at
              FROM event_registrations";
if ($search !== '') {
    $legacySql .= ' WHERE full_name LIKE :name_search OR email LIKE :email_search';
}
$legacySql .= ' ORDER BY registered_at DESC LIMIT 100';
$legacyStmt = $db->prepare($legacySql);
if ($search !== '') {
    $legacyStmt->execute([':name_search' => $searchPattern, ':email_search' => $searchPattern]);
} else {
    $legacyStmt->execute();
}
$legacyRegistrations = $legacyStmt->fetchAll(PDO::FETCH_ASSOC);

$subscriberSql = 'SELECT id, name, email, subscribed_at FROM newsletter_subscribers';
if ($search !== '') {
    $subscriberSql .= ' WHERE name LIKE :name_search OR email LIKE :email_search';
}
$subscriberSql .= ' ORDER BY subscribed_at DESC LIMIT 100';
$subscriberStmt = $db->prepare($subscriberSql);
if ($search !== '') {
    $subscriberStmt->execute([':name_search' => $searchPattern, ':email_search' => $searchPattern]);
} else {
    $subscriberStmt->execute();
}
$subscribers = $subscriberStmt->fetchAll(PDO::FETCH_ASSOC);

$noticeMessages = [
    'subscriber-added' => 'Subscriber added.',
    'subscriber-deleted' => 'Subscriber removed.',
    'registration-updated' => 'Registration status updated.',
    'registration-deleted' => 'Registration removed.',
    'duplicate-subscriber' => 'That email is already subscribed.',
    'invalid-input' => 'Check the submitted details and try again.',
    'save-error' => 'The change could not be saved.',
];
$notice = $noticeMessages[$_GET['notice'] ?? ''] ?? '';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>People &amp; Signups | Nazarene Admin</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #202827;
            --muted: #64716e;
            --line: #dce3df;
            --paper: #fff;
            --canvas: #f2f5f2;
            --green: #245c4b;
            --green-dark: #194538;
            --red: #a63e36;
        }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: var(--canvas); font: 15px/1.5 Georgia, 'Times New Roman', serif; }
        a { color: inherit; }
        .layout { min-height: 100vh; display: grid; grid-template-columns: 230px minmax(0, 1fr); }
        aside { display: flex; flex-direction: column; padding: 28px 18px; color: #f7faf7; background: var(--green-dark); }
        .brand { padding: 0 10px 24px; border-bottom: 1px solid #ffffff30; }
        .brand strong { display: block; font-size: 19px; font-weight: 600; }
        .brand span { color: #cad8d1; font: 12px/1.4 Arial, sans-serif; }
        nav { display: grid; gap: 5px; padding-top: 20px; }
        nav a { padding: 10px 11px; color: #e7eee9; font: 14px Arial, sans-serif; text-decoration: none; border-radius: 4px; }
        nav a:hover, nav a[aria-current="page"] { background: #ffffff19; color: #fff; }
        .logout { margin-top: auto; }
        main { width: min(1500px, 100%); padding: 34px clamp(18px, 4vw, 54px) 60px; }
        .page-head { display: flex; justify-content: space-between; align-items: end; gap: 20px; margin-bottom: 24px; }
        .eyebrow { margin: 0 0 6px; color: var(--green); font: 700 11px Arial, sans-serif; letter-spacing: 1px; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(29px, 4vw, 42px); font-weight: 500; line-height: 1.1; }
        .intro { margin: 8px 0 0; color: var(--muted); font: 14px Arial, sans-serif; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; }
        .toolbar form { display: flex; gap: 8px; flex: 1 1 320px; }
        input, select, button { min-height: 40px; border: 1px solid var(--line); border-radius: 3px; background: var(--paper); color: var(--ink); font: 14px Arial, sans-serif; }
        input, select { padding: 8px 10px; }
        .toolbar input { width: 100%; }
        button { padding: 8px 12px; cursor: pointer; }
        button:hover { border-color: var(--green); }
        .primary { border-color: var(--green); color: white; background: var(--green); }
        .primary:hover { background: var(--green-dark); }
        .danger { color: var(--red); }
        .panel { margin: 18px 0 24px; border-top: 2px solid var(--green); background: var(--paper); }
        .panel-head { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 18px 20px 10px; }
        h2 { margin: 0; font-size: 21px; font-weight: 500; }
        .count { color: var(--muted); font: 12px Arial, sans-serif; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; font: 14px Arial, sans-serif; }
        th, td { padding: 12px 16px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        th { color: var(--muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        td small { display: block; color: var(--muted); margin-top: 2px; }
        .row-actions { display: flex; align-items: center; gap: 6px; }
        .row-actions select, .row-actions button { min-height: 34px; font-size: 12px; }
        .empty { padding: 22px 20px; color: var(--muted); font: 14px Arial, sans-serif; }
        .notice { margin-bottom: 16px; padding: 11px 14px; border-left: 3px solid var(--green); background: #e6efe9; font: 14px Arial, sans-serif; }
        .subscriber-form { display: flex; flex-wrap: wrap; gap: 8px; }
        .subscriber-form input { width: min(230px, 100%); }
        @media (max-width: 760px) {
            .layout { grid-template-columns: 1fr; }
            aside { padding: 15px; }
            .brand { padding: 0 4px 12px; }
            nav { display: flex; flex-wrap: wrap; padding-top: 10px; }
            nav a { padding: 8px 9px; }
            .logout { margin-top: 0; }
            main { padding-top: 24px; }
            .page-head { align-items: start; flex-direction: column; }
            .panel-head { align-items: start; flex-direction: column; }
        }
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page-body">
<div class="admin-layout">
    <?php $adminActivePage = 'users.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <header class="page-head">
            <div>
                <p class="eyebrow">Community records</p>
                <h1>People &amp; Signups</h1>
                <p class="intro">Manage event registrations and newsletter subscribers.</p>
            </div>
            <span class="count">Showing up to 100 records per list</span>
        </header>

        <?php if ($notice !== ''): ?><div class="notice" role="status"><?php echo h($notice); ?></div><?php endif; ?>

        <div class="toolbar">
            <form method="get" action="users.php" role="search">
                <input type="search" name="q" value="<?php echo h($search); ?>" placeholder="Search name, email, or event" aria-label="Search people and signups">
                <button class="primary" type="submit">Search</button>
                <?php if ($search !== ''): ?><a href="users.php"><button type="button">Clear</button></a><?php endif; ?>
            </form>
            <form class="subscriber-form" method="post" action="users.php">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_subscriber">
                <input type="text" name="name" maxlength="150" placeholder="Name (optional)" aria-label="Subscriber name">
                <input type="email" name="email" maxlength="255" required placeholder="Email address" aria-label="Subscriber email">
                <button class="primary" type="submit">Add subscriber</button>
            </form>
        </div>

        <section class="panel" aria-labelledby="registrations-heading">
            <div class="panel-head"><h2 id="registrations-heading">Event registrations</h2><span class="count"><?php echo count($registrations); ?> shown</span></div>
            <?php if ($registrations): ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Person</th><th>Event</th><th>Guests</th><th>Registered</th><th>Status / actions</th></tr></thead>
                    <tbody><?php foreach ($registrations as $registration): ?>
                        <tr>
                            <td><?php echo h($registration['full_name']); ?><small><?php echo h($registration['email']); ?><?php if ($registration['phone']): ?> · <?php echo h($registration['phone']); ?><?php endif; ?></small></td>
                            <td><?php echo h($registration['event_title'] ?: 'Event #' . $registration['event_id']); ?></td>
                            <td><?php echo h($registration['number_of_guests']); ?></td>
                            <td><?php echo h(date('M j, Y', strtotime($registration['created_at']))); ?></td>
                            <td><div class="row-actions">
                                <form method="post" action="users.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="update_registration">
                                    <input type="hidden" name="id" value="<?php echo h($registration['id']); ?>">
                                    <select name="status" aria-label="Registration status">
                                        <?php foreach (['pending', 'confirmed', 'cancelled'] as $status): ?>
                                            <option value="<?php echo h($status); ?>" <?php echo $registration['status'] === $status ? 'selected' : ''; ?>><?php echo h(ucfirst($status)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Save</button>
                                </form>
                                <form method="post" action="users.php" onsubmit="return confirm('Remove this event registration?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_registration">
                                    <input type="hidden" name="id" value="<?php echo h($registration['id']); ?>">
                                    <button class="danger" type="submit">Delete</button>
                                </form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p class="empty">No event registrations found.</p><?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="legacy-heading">
            <div class="panel-head"><h2 id="legacy-heading">Older event signups</h2><span class="count"><?php echo count($legacyRegistrations); ?> shown</span></div>
            <?php if ($legacyRegistrations): ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Person</th><th>Event ID</th><th>Phone</th><th>Registered</th><th>Action</th></tr></thead>
                    <tbody><?php foreach ($legacyRegistrations as $registration): ?>
                        <tr>
                            <td><?php echo h($registration['full_name']); ?><small><?php echo h($registration['email']); ?></small></td>
                            <td><?php echo h($registration['event_id']); ?></td>
                            <td><?php echo h($registration['phone']); ?></td>
                            <td><?php echo h(date('M j, Y', strtotime($registration['registered_at']))); ?></td>
                            <td><form method="post" action="users.php" onsubmit="return confirm('Remove this signup?');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete_legacy_registration">
                                <input type="hidden" name="id" value="<?php echo h($registration['id']); ?>">
                                <button class="danger" type="submit">Delete</button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p class="empty">No older event signups found.</p><?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="subscribers-heading">
            <div class="panel-head"><h2 id="subscribers-heading">Newsletter subscribers</h2><span class="count"><?php echo count($subscribers); ?> shown</span></div>
            <?php if ($subscribers): ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Name</th><th>Email</th><th>Subscribed</th><th>Action</th></tr></thead>
                    <tbody><?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td><?php echo h($subscriber['name'] ?: 'Not provided'); ?></td>
                            <td><?php echo h($subscriber['email']); ?></td>
                            <td><?php echo h(date('M j, Y', strtotime($subscriber['subscribed_at']))); ?></td>
                            <td><form method="post" action="users.php" onsubmit="return confirm('Remove this subscriber?');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete_subscriber">
                                <input type="hidden" name="id" value="<?php echo h($subscriber['id']); ?>">
                                <button class="danger" type="submit">Remove</button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p class="empty">No newsletter subscribers found.</p><?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>