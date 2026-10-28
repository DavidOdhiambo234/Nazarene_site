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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
    try {
        if ($action === 'save_event') {
            $title = trim($_POST['title'] ?? '');
            $eventDate = trim($_POST['event_date'] ?? '');
            $startTime = trim($_POST['start_time'] ?? '00:00');
            $endDateTime = trim($_POST['end_date'] ?? '');
            $imageUrl = trim($_POST['image_url'] ?? '');
            $parsedDate = DateTime::createFromFormat('!Y-m-d', $eventDate);
            $parsedTime = DateTime::createFromFormat('!H:i', $startTime);
            $parsedEnd = $endDateTime === '' ? null : DateTime::createFromFormat('!Y-m-d\TH:i', $endDateTime);

            if ($title === '' || strlen($title) > 200 || !$parsedDate || $parsedDate->format('Y-m-d') !== $eventDate || !$parsedTime || ($endDateTime !== '' && !$parsedEnd)) {
                throw new InvalidArgumentException('Enter an event title and valid date and time.');
            }

            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageFile = $_FILES['event_image'];
                if ($imageFile['error'] !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('The event image could not be uploaded. Please try again.');
                }
                if ($imageFile['size'] > 5 * 1024 * 1024) {
                    throw new InvalidArgumentException('The event image must be 5 MB or smaller.');
                }

                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $fileInfo->file($imageFile['tmp_name']);
                $imageExtensions = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                ];
                if (!isset($imageExtensions[$mimeType])) {
                    throw new InvalidArgumentException('Choose a JPG, PNG, WebP, or GIF image.');
                }

                $uploadDirectory = __DIR__ . '/../uploads/events';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new InvalidArgumentException('Unable to create the event image upload directory.');
                }

                $filename = 'event-' . bin2hex(random_bytes(16)) . '.' . $imageExtensions[$mimeType];
                if (!move_uploaded_file($imageFile['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
                    throw new InvalidArgumentException('Unable to save the uploaded event image.');
                }
                $imageUrl = 'uploads/events/' . $filename;
            }

            if ($imageUrl !== '') {
                $isHttpsUrl = filter_var($imageUrl, FILTER_VALIDATE_URL) && strtolower((string) parse_url($imageUrl, PHP_URL_SCHEME)) === 'https';
                $isLocalPath = preg_match('/\A(?:images|uploads)\/[A-Za-z0-9._\/-]+\z/', $imageUrl)
                    && !preg_match('/(?:^|\/)\.\.(?:\/|$)/', $imageUrl);

                if (!$isHttpsUrl && !$isLocalPath) {
                    throw new InvalidArgumentException('Use an HTTPS image URL or an existing path under images/ or uploads/.');
                }

                if ($isLocalPath) {
                    $projectRoot = realpath(__DIR__ . '/..');
                    $imageFile = realpath(__DIR__ . '/../' . $imageUrl);
                    if (!$imageFile || !$projectRoot || !is_file($imageFile) || strpos($imageFile, $projectRoot . DIRECTORY_SEPARATOR) !== 0) {
                        throw new InvalidArgumentException('That local image file was not found inside the site.');
                    }
                }
            }

            $values = [
                ':title' => $title,
                ':description' => trim($_POST['description'] ?? ''),
                ':event_type' => trim($_POST['event_type'] ?? ''),
                ':event_date' => $eventDate,
                ':start_date' => $eventDate . ' ' . $startTime . ':00',
                ':end_date' => $parsedEnd ? $parsedEnd->format('Y-m-d H:i:s') : null,
                ':location' => trim($_POST['location'] ?? ''),
                ':image_url' => $imageUrl !== '' ? $imageUrl : null,
                ':capacity' => ($_POST['capacity'] ?? '') !== '' ? max(0, (int) $_POST['capacity']) : null,
                ':is_published' => isset($_POST['is_published']) ? 1 : 0,
            ];

            if ($eventId) {
                $values[':id'] = $eventId;
                $stmt = $db->prepare('UPDATE events SET title=:title, description=:description, event_type=:event_type, event_date=:event_date, start_date=:start_date, end_date=:end_date, location=:location, image_url=:image_url, capacity=:capacity, is_published=:is_published WHERE id=:id');
                $stmt->execute($values);
                $message = 'Event updated.';
            } else {
                $stmt = $db->prepare('INSERT INTO events (title, description, event_type, event_date, start_date, end_date, location, image_url, capacity, is_published) VALUES (:title, :description, :event_type, :event_date, :start_date, :end_date, :location, :image_url, :capacity, :is_published)');
                $stmt->execute($values);
                $message = 'Event created.';
            }
        } elseif ($action === 'toggle_publish' && $eventId) {
            $stmt = $db->prepare('UPDATE events SET is_published = 1 - is_published WHERE id = :id');
            $stmt->execute([':id' => $eventId]);
            $message = 'Event visibility updated.';
        }
    } catch (InvalidArgumentException $exception) {
        $message = $exception->getMessage();
    }

    if ($message !== '') {
        $_SESSION['admin_notice'] = $message;
    }
    header('Location: manage-events.php');
    exit();
}

$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$editingEvent = null;
if ($editId) {
    $stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editingEvent = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$events = $db->query('SELECT * FROM events ORDER BY event_date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);

function event_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function event_image_src($value) {
    $value = (string) $value;
    return preg_match('/\A(?:images|uploads)\//', $value) ? '../' . $value : $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | University Church</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page-body">
<div class="admin-layout">
    <?php $adminActivePage = 'manage-events.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <h1 class="admin-page-title">Manage Events</h1>
        <p class="admin-page-subtitle">Create events and control which ones appear on the public site.</p>
        <?php if ($notice !== ''): ?><div class="admin-notice" role="status"><?php echo event_h($notice); ?></div><?php endif; ?>

        <section class="admin-panel">
            <h2 class="admin-panel-heading"><?php echo $editingEvent ? 'Edit event' : 'Add an event'; ?></h2>
            <form method="post" action="manage-events.php" enctype="multipart/form-data" style="padding: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo event_h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_event">
                <?php if ($editingEvent): ?><input type="hidden" name="event_id" value="<?php echo event_h($editingEvent['id']); ?>"><?php endif; ?>
                <div class="admin-form-grid">
                    <div class="admin-field"><label for="title">Event title</label><input id="title" name="title" maxlength="200" required value="<?php echo event_h($editingEvent['title'] ?? ''); ?>"></div>
                    <div class="admin-field"><label for="event_type">Category</label><input id="event_type" name="event_type" maxlength="80" placeholder="Worship, outreach, youth" value="<?php echo event_h($editingEvent['event_type'] ?? ''); ?>"></div>
                    <div class="admin-field"><label for="event_date">Date</label><input id="event_date" name="event_date" type="date" required value="<?php echo event_h($editingEvent['event_date'] ?? ''); ?>"></div>
                    <div class="admin-field"><label for="start_time">Start time</label><input id="start_time" name="start_time" type="time" value="<?php echo $editingEvent && $editingEvent['start_date'] ? event_h(date('H:i', strtotime($editingEvent['start_date']))) : '10:00'; ?>"></div>
                    <div class="admin-field"><label for="end_date">End date and time (optional)</label><input id="end_date" name="end_date" type="datetime-local" value="<?php echo $editingEvent && $editingEvent['end_date'] ? event_h(date('Y-m-d\TH:i', strtotime($editingEvent['end_date']))) : ''; ?>"></div>
                    <div class="admin-field"><label for="location">Location</label><input id="location" name="location" maxlength="255" value="<?php echo event_h($editingEvent['location'] ?? ''); ?>"></div>
                    <div class="admin-field"><label for="event_image">Choose image from files</label><input id="event_image" name="event_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><small>Optional. Choose a JPG, PNG, WebP, or GIF image up to 5 MB. For clearer display, use an image at least 1000 pixels wide. Choosing a file replaces the current image.</small></div>
                    <div class="admin-field"><label for="image_url">Or use an image URL/path</label><input id="image_url" name="image_url" maxlength="500" value="<?php echo event_h($editingEvent['image_url'] ?? ''); ?>" placeholder="images/events/community-day.jpg"><small>Use an existing images/ or uploads/ file, or an HTTPS image URL.</small></div>
                    <div class="admin-field"><label for="capacity">Capacity (optional)</label><input id="capacity" name="capacity" type="number" min="0" value="<?php echo event_h($editingEvent['capacity'] ?? ''); ?>"></div>
                    <div class="admin-field admin-field-wide"><label for="description">Description</label><textarea id="description" name="description"><?php echo event_h($editingEvent['description'] ?? ''); ?></textarea></div>
                    <div class="admin-field admin-field-wide"><label class="admin-checkbox"><input type="checkbox" name="is_published" value="1" <?php echo !$editingEvent || $editingEvent['is_published'] ? 'checked' : ''; ?>> Publish this event</label></div>
                </div>
                <div class="admin-form-actions"><button class="admin-button-primary" type="submit"><?php echo $editingEvent ? 'Save event' : 'Create event'; ?></button><?php if ($editingEvent): ?><a class="admin-button" href="manage-events.php">Cancel</a><?php endif; ?></div>
            </form>
        </section>

        <section class="admin-panel">
            <h2 class="admin-panel-heading">Events</h2>
            <?php if ($events): ?>
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>Image</th><th>Event</th><th>Date</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><?php foreach ($events as $event): ?>
                        <tr>
                            <td><?php if ($event['image_url']): ?><img class="event-admin-thumbnail" src="<?php echo event_h(event_image_src($event['image_url'])); ?>" alt="" onerror="this.hidden=true"><?php else: ?>No image<?php endif; ?></td>
                            <td><strong><?php echo event_h($event['title']); ?></strong><small><?php echo event_h($event['event_type']); ?></small></td>
                            <td><?php echo $event['event_date'] ? event_h(date('M j, Y', strtotime($event['event_date']))) : 'Not set'; ?></td>
                            <td><?php echo event_h($event['location']); ?></td>
                            <td><?php echo $event['is_published'] ? 'Published' : 'Draft'; ?></td>
                            <td><div class="admin-inline-form"><a class="admin-button" href="manage-events.php?edit=<?php echo event_h($event['id']); ?>">Edit</a>
                                <form method="post" action="manage-events.php"><input type="hidden" name="csrf_token" value="<?php echo event_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="action" value="toggle_publish"><input type="hidden" name="event_id" value="<?php echo event_h($event['id']); ?>"><button class="admin-button-primary" type="submit"><?php echo $event['is_published'] ? 'Unpublish' : 'Publish'; ?></button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p class="admin-empty">No events yet. Create the first one above.</p><?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>