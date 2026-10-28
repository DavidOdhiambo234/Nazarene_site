<?php
// C:\xampp\htdocs\Nazarene_site\admin\dashboard.php
session_start();
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: Login.php");
    exit();
}

require_once "../api/config/database.php";

$database = new Database();
$db = $database->getConnection();

// Get statistics with correct column names
$stats = [];

// Total contacts
$query = "SELECT COUNT(*) as count FROM contacts WHERE status = 'unread'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['unread_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Upcoming events - FIXED: using event_date instead of start_date
$query = "SELECT COUNT(*) as count FROM events WHERE COALESCE(event_date, DATE(start_date)) >= CURDATE() AND is_published = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['upcoming_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total registrations
$query = "SELECT COUNT(*) as count FROM registrations WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_registrations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total sermons
$query = "SELECT COUNT(*) as count FROM sermons WHERE is_published = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_sermons'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stats['people_records'] = (int) $db->query(
    "SELECT (SELECT COUNT(*) FROM registrations) +
            (SELECT COUNT(*) FROM event_registrations) +
            (SELECT COUNT(*) FROM newsletter_subscribers)"
)->fetchColumn();

// Recent contacts
$query = "SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent events - FIXED: using event_date
$query = "SELECT * FROM events
          WHERE is_published = 1 AND COALESCE(event_date, DATE(start_date)) >= CURDATE()
          ORDER BY COALESCE(event_date, DATE(start_date)) ASC, id DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Church Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .sidebar-nav a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 12px 25px;
            margin: 5px 0;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 30px;
        }
        
        .sidebar-nav a.active {
            background: #667eea;
            border-left: 4px solid white;
        }
        
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        
        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h1 {
            font-size: 24px;
            color: #333;
        }
        
        .user-info {
            color: #666;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        
        .recent-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .recent-section h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .contact-list, .events-list {
            overflow-x: auto;
        }
        
        .contact-table, .events-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .contact-table th,
        .contact-table td,
        .events-table th,
        .events-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .contact-table th,
        .events-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-unread {
            background: #fee;
            color: #c53030;
        }
        
        .status-read {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .event-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .event-hiking {
            background: #4caf50;
            color: white;
        }
        
        .event-worship {
            background: #9c27b0;
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }
            .main-content {
                margin-left: 0;
            }
        }

        :root {
            --brand-red: #9D0A0F;
            --brand-green: #14736C;
            --brand-blue: #007799;
            --brand-yellow: #D39512;
            --ink: #211d1c;
            --muted: #625e5a;
            --line: #e5e1dc;
            --surface: #ffffff;
            --canvas: #f5f5f5;
        }

        body {
            color: var(--ink);
            font-family: Georgia, 'Times New Roman', serif;
            background-color: var(--canvas);
            background-image: linear-gradient(rgba(20, 115, 108, 0.035) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(20, 115, 108, 0.035) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .sidebar {
            width: 252px;
            background: linear-gradient(165deg, #9D0A0F 0%, #76080c 58%, #26090a 100%);
            box-shadow: 5px 0 24px rgba(37, 9, 10, 0.14);
        }

        .sidebar-header {
            padding: 28px 22px 24px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: inset 0 3px 0 var(--brand-yellow);
        }

        .sidebar-header h3 {
            margin-bottom: 7px;
            color: #fff;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 19px;
            font-weight: 600;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.72);
            font: 12px Arial, sans-serif;
            letter-spacing: 0;
        }

        .sidebar-nav {
            padding: 18px 12px 0;
        }

        .sidebar-nav a {
            padding: 12px 14px;
            margin: 4px 0;
            border-left: 3px solid transparent;
            border-radius: 3px;
            color: rgba(255, 255, 255, 0.84);
            font: 14px Arial, sans-serif;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .sidebar-nav a:hover {
            padding-left: 14px;
            border-left-color: var(--brand-yellow);
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .sidebar-nav a.active {
            border-left-color: var(--brand-yellow);
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }

        .logout-btn {
            right: 12px;
            bottom: 14px;
            left: 12px;
            padding: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 0;
        }

        .logout-btn a { color: #fff; }

        .main-content {
            min-height: 100vh;
            margin-left: 252px;
            padding: 32px clamp(20px, 3.2vw, 48px) 48px;
        }

        .top-bar {
            position: relative;
            overflow: hidden;
            min-height: 112px;
            padding: 24px 30px;
            border: 1px solid rgba(157, 10, 15, 0.1);
            border-radius: 5px;
            background: var(--surface);
            box-shadow: 0 8px 26px rgba(33, 29, 28, 0.06);
        }

        .top-bar::before {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 5px;
            background: linear-gradient(var(--brand-yellow) 0 36%, var(--brand-red) 36% 72%, var(--brand-green) 72%);
            content: '';
        }

        .top-bar h1 {
            color: var(--brand-red);
            font: 500 30px/1.2 Georgia, 'Times New Roman', serif;
        }

        .user-info {
            color: var(--muted);
            font: 13px Arial, sans-serif;
        }

        .user-info strong { color: var(--brand-green); }

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
            gap: 16px;
            margin: 20px 0 28px;
        }

        .stat-card {
            position: relative;
            min-height: 136px;
            padding: 22px 22px 18px;
            border: 1px solid var(--line);
            border-top: 3px solid var(--brand-red);
            border-radius: 4px;
            background: var(--surface);
            box-shadow: 0 5px 18px rgba(33, 29, 28, 0.045);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:nth-child(2) { border-top-color: var(--brand-green); }
        .stat-card:nth-child(3) { border-top-color: var(--brand-yellow); }
        .stat-card:nth-child(4) { border-top-color: var(--brand-blue); }
        .stat-card:nth-child(5) { border-top-color: var(--brand-red); }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(33, 29, 28, 0.1);
        }

        .stat-card h3 {
            margin-bottom: 14px;
            color: var(--muted);
            font: 700 11px/1.4 Arial, sans-serif;
            letter-spacing: 0;
        }

        .stat-number {
            color: var(--brand-red);
            font: 600 36px/1 Georgia, 'Times New Roman', serif;
        }

        .stat-card:nth-child(2) .stat-number { color: var(--brand-green); }
        .stat-card:nth-child(3) .stat-number { color: #9c6a00; }
        .stat-card:nth-child(4) .stat-number { color: var(--brand-blue); }
        .stat-card:nth-child(5) .stat-number { color: var(--brand-red); }

        .recent-section {
            margin-bottom: 18px;
            padding: 0;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: var(--surface);
            box-shadow: 0 5px 18px rgba(33, 29, 28, 0.045);
        }

        .recent-section h2 {
            margin: 0;
            padding: 17px 22px;
            border-bottom: 1px solid var(--line);
            color: var(--brand-red);
            font: 500 20px/1.3 Georgia, 'Times New Roman', serif;
        }

        .contact-list, .events-list { padding: 0 10px 8px; }

        .contact-table, .events-table { min-width: 620px; }

        .contact-table th, .contact-table td,
        .events-table th, .events-table td {
            padding: 13px 12px;
            border-bottom: 1px solid var(--line);
            font-size: 13px;
        }

        .contact-table th, .events-table th {
            background: #f7f7f5;
            color: var(--muted);
            font: 700 10px Arial, sans-serif;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .contact-table tbody tr:hover, .events-table tbody tr:hover { background: #faf8f5; }

        .status-badge, .event-badge {
            padding: 5px 9px;
            border-radius: 3px;
            font: 700 10px Arial, sans-serif;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .status-unread { background: rgba(157, 10, 15, 0.09); color: var(--brand-red); }
        .status-read { background: rgba(0, 119, 153, 0.1); color: #00627d; }
        .status-replied { background: rgba(20, 115, 108, 0.11); color: var(--brand-green); }
        .event-hiking { background: rgba(20, 115, 108, 0.11); color: var(--brand-green); }
        .event-worship { background: rgba(211, 149, 18, 0.16); color: #805700; }

        .recent-section > p {
            padding: 2px 22px 18px;
            color: var(--muted);
            font: 14px Arial, sans-serif;
        }

        .recent-section > p a { color: var(--brand-green); font-weight: 700; }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                min-height: 0;
                overflow: visible;
                transform: none;
            }

            .sidebar-header { padding: 18px 20px 14px; }
            .sidebar-nav { display: flex; flex-wrap: wrap; gap: 2px; padding: 8px 12px 12px; }
            .sidebar-nav a { display: inline-block; margin: 0; padding: 9px 10px; }
            .sidebar-nav a:hover { padding-left: 10px; }
            .logout-btn { position: static; margin: 0; border-top: 1px solid rgba(255, 255, 255, 0.18); }
            .logout-btn a { display: inline-block; }
            .main-content { margin: 0; padding: 18px 16px 36px; }
            .top-bar { min-height: 92px; padding: 20px 20px 20px 24px; }
            .top-bar h1 { font-size: 26px; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .stat-card { min-height: 122px; padding: 18px 16px; }
            .recent-section h2 { padding: 15px 16px; }
        }

        @media (max-width: 430px) {
            .top-bar { align-items: flex-start; flex-direction: column; gap: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card h3 { font-size: 10px; }
            .stat-number { font-size: 31px; }
        }
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page-body">
    <div class="admin-layout">
    <?php $adminActivePage = 'dashboard.php'; include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <div class="top-bar">
            <h1>Dashboard</h1>
            <div class="user-info">
                Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='contacts.php'">
                <h3>Unread Messages</h3>
                <div class="stat-number"><?php echo $stats['unread_messages']; ?></div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-events.php'">
                <h3>Upcoming Events</h3>
                <div class="stat-number"><?php echo $stats['upcoming_events']; ?></div>
            </div>
            <div class="stat-card" onclick="window.location.href='registrations.php'">
                <h3>Pending Registrations</h3>
                <div class="stat-number"><?php echo $stats['pending_registrations']; ?></div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-sermons.php'">
                <h3>Published Sermons</h3>
                <div class="stat-number"><?php echo $stats['total_sermons']; ?></div>
            </div>
            <div class="stat-card" onclick="window.location.href='users.php'">
                <h3>People &amp; Signups</h3>
                <div class="stat-number"><?php echo $stats['people_records']; ?></div>
            </div>
        </div>
        
        <div class="recent-section">
            <h2>📅 Upcoming Events</h2>
            <?php if(count($recent_events) > 0): ?>
                <div class="events-list">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_events as $event): ?>
                            <tr onclick="window.location.href='manage-events.php'" style="cursor: pointer;">
                                <td><img class="dashboard-event-image" src="<?php echo htmlspecialchars($event['image_url'] && preg_match('/\A(?:images|uploads)\//', $event['image_url']) ? '../' . $event['image_url'] : ($event['image_url'] ?: '../images/home-hero-1.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.onerror=null;this.src='../images/home-hero-1.jpg';"></td>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                <td><?php echo htmlspecialchars($event['location']); ?></td>
                                <td><span class="event-badge event-<?php echo $event['event_type']; ?>"><?php echo ucfirst($event['event_type']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No upcoming events. <a href="manage-events.php">Add an event</a></p>
            <?php endif; ?>
        </div>
        
        <div class="recent-section">
            <h2>✉️ Recent Contact Messages</h2>
            <?php if(count($recent_contacts) > 0): ?>
                <div class="contact-list">
                    <table class="contact-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_contacts as $contact): ?>
                            <tr onclick="window.location.href='contacts.php'" style="cursor: pointer;">
                                <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                <td><?php echo htmlspecialchars($contact['subject']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $contact['status']; ?>">
                                        <?php echo $contact['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No contact messages yet.</p>
            <?php endif; ?>
        </div>
    </main>
    </div>
</body>
</html>