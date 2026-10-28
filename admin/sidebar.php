<?php
$adminActivePage = $adminActivePage ?? basename($_SERVER['PHP_SELF']);
$adminNavigation = [
    'dashboard.php' => ['Dashboard', '▦'],
    'users.php' => ['People & Signups', '♙'],
    'manage-events.php' => ['Manage Events', '▣'],
    'manage-sermons.php' => ['Manage Sermons', '♫'],
    'leader-photos.php' => ['Manage Leaders', '♙'],
    'contacts.php' => ['Contacts', '✉'],
    'registrations.php' => ['Registrations', '☷'],
    'settings.php' => ['Settings', '⚙'],
];
?>
<aside class="admin-sidebar">
    <a class="admin-brand" href="dashboard.php">
        <img class="admin-brand-logo" src="../images/mylogo.png" alt="University Church of the Nazarene logo">
        <span><strong> Nazarene University Church</strong><small>ADMINISTRATION</small></span>
    </a>
    <nav class="admin-nav" aria-label="Admin navigation">
        <?php foreach ($adminNavigation as $href => [$label, $icon]): ?>
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $adminActivePage === $href ? 'aria-current="page"' : ''; ?>>
                <span class="admin-nav-icon" aria-hidden="true"><?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <a class="admin-signout" href="logout.php"><span aria-hidden="true">↗</span> Sign out</a>
</aside>
