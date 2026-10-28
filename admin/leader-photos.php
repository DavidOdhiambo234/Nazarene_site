<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: Login.php');
    exit();
}

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/leader-profiles.php';
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

    try {
        $profiles = load_leader_profiles($db);
        $action = $_POST['action'] ?? '';
        $profileId = trim($_POST['profile_id'] ?? '');
        $existingIndex = null;
        foreach ($profiles as $index => $profile) {
            if ($profile['id'] === $profileId) {
                $existingIndex = $index;
                break;
            }
        }

        if ($action === 'delete_profile') {
            if ($existingIndex === null) {
                throw new InvalidArgumentException('That leader profile could not be found.');
            }
            $removedPhoto = $profiles[$existingIndex]['photo'];
            array_splice($profiles, $existingIndex, 1);
            save_leader_profiles($db, $profiles);
            if (preg_match('/\Auploads\/leaders\/[A-Za-z0-9._-]+\z/', $removedPhoto)) {
                $removedPhotoPath = __DIR__ . '/../' . $removedPhoto;
                if (is_file($removedPhotoPath)) {
                    unlink($removedPhotoPath);
                }
            }
            $_SESSION['admin_notice'] = 'Leader profile removed.';
            header('Location: leader-photos.php');
            exit();
        }

        if (!in_array($action, ['add_profile', 'save_profile'], true)) {
            throw new InvalidArgumentException('Choose a valid profile action.');
        }
        if ($action === 'save_profile' && $existingIndex === null) {
            throw new InvalidArgumentException('That leader profile could not be found.');
        }

        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '' || strlen($name) > 160 || $role === '' || strlen($role) > 120 || strlen($description) > 2000) {
            throw new InvalidArgumentException('Enter a name and role. Keep the name to 160 characters, role to 120, and description to 2,000.');
        }

        $profileId = $action === 'add_profile' ? 'leader_' . bin2hex(random_bytes(12)) : $profileId;
        $profile = [
            'id' => $profileId,
            'name' => $name,
            'role' => $role,
            'description' => $description,
            'photo' => $existingIndex !== null ? $profiles[$existingIndex]['photo'] : '',
        ];
        $previousPhotoPath = $profile['photo'];
        $newPhotoPath = null;
        $upload = $_FILES['leader_photo'] ?? null;
        if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($upload['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('The image could not be uploaded. Please try again.');
            }
            if ($upload['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('The image must be 5 MB or smaller.');
            }

            $imageTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($upload['tmp_name']);
            if (!isset($imageTypes[$mimeType]) || !getimagesize($upload['tmp_name'])) {
                throw new InvalidArgumentException('Choose a valid JPG, PNG, WebP, or GIF image.');
            }

            $uploadDirectory = __DIR__ . '/../uploads/leaders';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new InvalidArgumentException('Unable to create the leader photo upload directory.');
            }

            $filename = $profileId . '-' . bin2hex(random_bytes(12)) . '.' . $imageTypes[$mimeType];
            $newPhotoPath = 'uploads/leaders/' . $filename;
            if (!move_uploaded_file($upload['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
                throw new InvalidArgumentException('Unable to save the uploaded leader photo.');
            }
            $profile['photo'] = $newPhotoPath;
        }

        try {
            if ($existingIndex === null) {
                $profiles[] = $profile;
            } else {
                $profiles[$existingIndex] = $profile;
            }
            save_leader_profiles($db, $profiles);
            if ($newPhotoPath !== null && preg_match('/\Auploads\/leaders\/[A-Za-z0-9._-]+\z/', $previousPhotoPath)) {
                $oldPhotoPath = __DIR__ . '/../' . $previousPhotoPath;
                if (is_file($oldPhotoPath)) {
                    unlink($oldPhotoPath);
                }
            }
        } catch (PDOException | JsonException $exception) {
            if ($newPhotoPath !== null && is_file(__DIR__ . '/../' . $newPhotoPath)) {
                unlink(__DIR__ . '/../' . $newPhotoPath);
            }
            throw new RuntimeException('The profile could not be saved. Please try again.');
        }

        $_SESSION['admin_notice'] = $action === 'add_profile' ? 'Leader profile added.' : 'Leader profile updated.';
    } catch (InvalidArgumentException $exception) {
        $_SESSION['admin_notice'] = $exception->getMessage();
    } catch (RuntimeException $exception) {
        $_SESSION['admin_notice'] = $exception->getMessage();
    }

    header('Location: leader-photos.php');
    exit();
}

$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$profiles = load_leader_profiles($db);

function leader_photo_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leaders | University Church</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .leader-photo-admin-list { display: grid; gap: 16px; padding: 20px; }
        .leader-photo-admin-item { display: grid; grid-template-columns: 96px minmax(0, 1fr); align-items: start; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--admin-line); }
        .leader-photo-admin-preview { width: 96px; height: 96px; border-radius: 50%; background: var(--admin-canvas); object-fit: cover; }
        @media (max-width: 520px) { .leader-photo-admin-item { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="admin-page-body">
<div class="admin-layout">
    <?php $adminActivePage = 'leader-photos.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <h1 class="admin-page-title">Manage Leaders</h1>
        <p class="admin-page-subtitle">Edit existing leadership profiles or add new people. Photos are optional; JPG, PNG, WebP, or GIF files up to 5 MB are supported.</p>
        <?php if ($notice !== ''): ?><div class="admin-notice" role="status"><?php echo leader_photo_h($notice); ?></div><?php endif; ?>

        <section class="admin-panel">
            <h2 class="admin-panel-heading">Add a leader</h2>
            <form method="post" action="leader-photos.php" enctype="multipart/form-data" style="padding: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo leader_photo_h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_profile">
                <div class="admin-form-grid">
                    <div class="admin-field"><label for="new-name">Name</label><input id="new-name" name="name" maxlength="160" required></div>
                    <div class="admin-field"><label for="new-role">Role</label><input id="new-role" name="role" maxlength="120" required></div>
                    <div class="admin-field admin-field-wide"><label for="new-description">Description</label><textarea id="new-description" name="description" maxlength="2000"></textarea></div>
                    <div class="admin-field admin-field-wide"><label for="new-photo">Profile photo (optional)</label><input id="new-photo" name="leader_photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
                </div>
                <div class="admin-form-actions"><button class="admin-button-primary" type="submit">Add leader</button></div>
            </form>
        </section>

        <section class="admin-panel">
            <h2 class="admin-panel-heading">Leadership profiles</h2>
            <div class="leader-photo-admin-list">
                <?php foreach ($profiles as $profile): ?>
                    <div class="leader-photo-admin-item">
                        <?php if (!empty($profile['photo'])): ?>
                            <img class="leader-photo-admin-preview" src="../<?php echo leader_photo_h($profile['photo']); ?>" alt="">
                        <?php else: ?>
                            <div class="leader-photo-admin-preview" aria-hidden="true"></div>
                        <?php endif; ?>
                        <div>
                            <form method="post" action="leader-photos.php" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo leader_photo_h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="save_profile">
                                <input type="hidden" name="profile_id" value="<?php echo leader_photo_h($profile['id']); ?>">
                                <div class="admin-form-grid">
                                    <div class="admin-field"><label for="name-<?php echo leader_photo_h($profile['id']); ?>">Name</label><input id="name-<?php echo leader_photo_h($profile['id']); ?>" name="name" maxlength="160" required value="<?php echo leader_photo_h($profile['name']); ?>"></div>
                                    <div class="admin-field"><label for="role-<?php echo leader_photo_h($profile['id']); ?>">Role</label><input id="role-<?php echo leader_photo_h($profile['id']); ?>" name="role" maxlength="120" required value="<?php echo leader_photo_h($profile['role']); ?>"></div>
                                    <div class="admin-field admin-field-wide"><label for="description-<?php echo leader_photo_h($profile['id']); ?>">Description</label><textarea id="description-<?php echo leader_photo_h($profile['id']); ?>" name="description" maxlength="2000"><?php echo leader_photo_h($profile['description']); ?></textarea></div>
                                    <div class="admin-field admin-field-wide"><label for="photo-<?php echo leader_photo_h($profile['id']); ?>">Replace photo (optional)</label><input id="photo-<?php echo leader_photo_h($profile['id']); ?>" name="leader_photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
                                </div>
                                <div class="admin-form-actions"><button class="admin-button-primary" type="submit">Save changes</button></div>
                            </form>
                            <form method="post" action="leader-photos.php" onsubmit="return confirm('Remove this leader profile from the Who We Are page?');">
                                <input type="hidden" name="csrf_token" value="<?php echo leader_photo_h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete_profile">
                                <input type="hidden" name="profile_id" value="<?php echo leader_photo_h($profile['id']); ?>">
                                <div class="admin-form-actions"><button class="admin-button-danger" type="submit">Remove profile</button></div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
