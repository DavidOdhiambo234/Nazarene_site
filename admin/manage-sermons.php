<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once "../api/config/database.php";

$database = new Database();
$db = $database->getConnection();

// Handle setting featured sermon (MUST BE BEFORE ANY OUTPUT)
if(isset($_GET['featured'])) {
    // Remove featured from all sermons
    $db->exec("UPDATE sermons SET is_featured = 0");
    // Set the selected sermon as featured
    $stmt = $db->prepare("UPDATE sermons SET is_featured = 1 WHERE id = :id");
    $stmt->execute([':id' => $_GET['featured']]);
    $success = "Featured sermon updated!";
}

// Check if video_url column exists, if not add it
try {
    $db->query("SELECT video_url FROM sermons LIMIT 1");
} catch(PDOException $e) {
    $db->exec("ALTER TABLE sermons ADD COLUMN video_url VARCHAR(500) AFTER description");
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        if($_POST['action'] == 'add_sermon') {
            $query = "INSERT INTO sermons (title, speaker, scripture, description, video_url, preached_date, is_featured, is_published) 
                      VALUES (:title, :speaker, :scripture, :description, :video_url, :preached_date, 0, 1)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':title' => $_POST['title'],
                ':speaker' => $_POST['speaker'],
                ':scripture' => $_POST['scripture'],
                ':description' => $_POST['description'],
                ':video_url' => $_POST['video_url'],
                ':preached_date' => $_POST['preached_date']
            ]);
            $success = "Sermon added successfully!";
        }
        
        if($_POST['action'] == 'delete_sermon') {
            $query = "DELETE FROM sermons WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $_POST['sermon_id']]);
            $success = "Sermon deleted successfully!";
        }
    }
}

// Get all sermons
$sermons = $db->query("SELECT * FROM sermons ORDER BY is_featured DESC, preached_date DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Sermons</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .form-section { background: white; padding: 25px; border-radius: 10px; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        textarea { resize: vertical; }
        button { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; }
        .sermons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
        .sermon-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: relative; }
        .sermon-card.featured { border: 2px solid #ffd700; box-shadow: 0 4px 15px rgba(255,215,0,0.3); }
        .sermon-video { position: relative; padding-bottom: 56.25%; height: 0; }
        .sermon-video iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .sermon-content { padding: 20px; }
        .sermon-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .delete-btn { background: #dc3545; padding: 5px 10px; font-size: 12px; margin-top: 10px; border: none; border-radius: 3px; cursor: pointer; color: white; }
        .featured-btn { background: #ffd700; color: #333; padding: 5px 10px; font-size: 12px; margin-top: 10px; margin-right: 5px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .featured-badge { background: #ffd700; color: #333; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 10px; }
        .nav-links { margin-bottom: 20px; }
        .nav-links a { color: #667eea; text-decoration: none; margin-right: 15px; }
        .button-group { margin-top: 10px; }
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page-body">
    <div class="admin-layout">
    <?php $adminActivePage = 'manage-sermons.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
    <div class="container">
        <div class="header">
            <h1>Manage Sermons</h1>
            <p>Publish sermons and choose which message appears on the homepage.</p>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Form to Add New Sermon -->
        <div class="form-section">
            <h2>Add New Sermon</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_sermon">
                
                <div class="form-group">
                    <label>Sermon Title *</label>
                    <input type="text" name="title" required placeholder="e.g., Living a Fruitful Life">
                </div>
                
                <div class="form-group">
                    <label>Speaker Name *</label>
                    <input type="text" name="speaker" required placeholder="e.g., Rev. Richard Awanda">
                </div>
                
                <div class="form-group">
                    <label>Scripture Reference</label>
                    <input type="text" name="scripture" placeholder="e.g., John 15:5">
                </div>
                
                <div class="form-group">
                    <label>YouTube Video URL *</label>
                    <input type="url" name="video_url" required placeholder="https://www.youtube.com/watch?v=...">
                    <small>Paste the full YouTube URL</small>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Brief description of the sermon..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Preached Date *</label>
                    <input type="date" name="preached_date" required>
                </div>
                
                <button type="submit">➕ Add Sermon</button>
            </form>
        </div>
        
        <!-- List of Existing Sermons -->
        <h2> Existing Sermons</h2>
        <div class="sermons-grid">
            <?php if(count($sermons) > 0): ?>
                <?php foreach($sermons as $sermon): ?>
                    <div class="sermon-card <?php echo $sermon['is_featured'] ? 'featured' : ''; ?>">
                        <?php if(!empty($sermon['video_url'])): 
                            preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $sermon['video_url'], $matches);
                            $video_id = $matches[1] ?? '';
                        ?>
                            <div class="sermon-video">
                                <iframe src="https://www.youtube.com/embed/<?php echo $video_id; ?>" frameborder="0" allowfullscreen></iframe>
                            </div>
                        <?php endif; ?>
                        <div class="sermon-content">
                            <div class="sermon-title">
                                <?php echo htmlspecialchars($sermon['title']); ?>
                                <?php if($sermon['is_featured']): ?>
                                    <span class="featured-badge">⭐ FEATURED</span>
                                <?php endif; ?>
                            </div>
                            <p><strong>🎤 Speaker:</strong> <?php echo htmlspecialchars($sermon['speaker']); ?></p>
                            <p><strong>📖 Scripture:</strong> <?php echo htmlspecialchars($sermon['scripture']); ?></p>
                            <p><strong>📅 Date:</strong> <?php echo date('F j, Y', strtotime($sermon['preached_date'])); ?></p>
                            <p><?php echo htmlspecialchars($sermon['description']); ?></p>
                            
                            <div class="button-group">
                                <?php if(!$sermon['is_featured']): ?>
                                    <a href="?featured=<?php echo $sermon['id']; ?>" class="featured-btn">⭐ Set as Featured</a>
                                <?php endif; ?>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Delete this sermon?')">
                                    <input type="hidden" name="action" value="delete_sermon">
                                    <input type="hidden" name="sermon_id" value="<?php echo $sermon['id']; ?>">
                                    <button type="submit" class="delete-btn">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No sermons yet. Add your first sermon above!</p>
            <?php endif; ?>
        </div>
    </div>
    </main>
    </div>
</body>
</html>