<?php
$current = basename($_SERVER['PHP_SELF']);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

function nav_link($href, $label, $icon, $current) {
    $active = $current === $href ? ' active' : '';
    echo '<a href="' . $href . '" class="nav-item' . $active . '">'
       . '<span class="nav-icon">' . $icon . '</span>'
       . '<span class="nav-label">' . $label . '</span>'
       . '</a>';
}

$initial = strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1));
?>
<nav class="nav-menu">
    <div class="nav-brand">
        <span class="nav-brand-mark">🏪</span>
        <span class="nav-brand-text">Enca<strong>Trading</strong></span>
    </div>

    <div class="nav-links">
        <?php
        nav_link('dashboard.php', 'Dashboard', '🏠', $current);
        nav_link('pos.php',       'POS',       '🛒', $current);
        nav_link('products.php',  'Products',  '📦', $current);
        if ($isAdmin) {
            nav_link('categories.php', 'Categories', '📂', $current);
            nav_link('suppliers.php',  'Suppliers',  '🏢', $current);
        }
        nav_link('inventory.php', 'Inventory', '📊', $current);
        nav_link('alerts.php',    'Alerts',    '🔔', $current);
        nav_link('analytics.php', 'Analytics', '📈', $current);
        if ($isAdmin) {
            nav_link('add_user.php', 'Users', '🔐', $current);
        }
        ?>
    </div>

    <div class="nav-user">
        <span class="user-avatar"><?= htmlspecialchars($initial) ?></span>
        <span class="user-meta">
            <span class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
            <span class="user-role"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'staff')) ?></span>
        </span>
        <a href="logout.php" class="nav-logout" title="Logout">⏻</a>
    </div>
</nav>