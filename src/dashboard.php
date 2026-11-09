<?php
require_once 'includes/session.php';
requireLogin();
require_once 'config/database.php';
require_once 'includes/functions.php';

$user = $_SESSION['full_name'];

// Get more meaningful stats like hotel system
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$total_products = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(sale_date) = CURDATE()");
$today_sales = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM sales");
$total_sales = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) FROM sales");
$total_revenue = $stmt->fetchColumn() ?: 0;

$low_stock = count(getLowStockProducts());
$zero_stock = count(getZeroStockProducts());

// Get categories count
$stmt = $pdo->query("SELECT COUNT(*) FROM categories");
$total_categories = $stmt->fetchColumn();

// Get suppliers count
$stmt = $pdo->query("SELECT COUNT(*) FROM suppliers");
$total_suppliers = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <h1 class="page-title">📊 Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?= htmlspecialchars($user) ?>! Here's your store overview.</p>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <div class="stat-value"><?= $total_products ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <div class="stat-value"><?= $today_sales ?></div>
                <div class="stat-label">Today's Sales</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📈</span>
                <div class="stat-value">$<?= number_format($total_revenue, 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🏷️</span>
                <div class="stat-value"><?= $total_categories ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🏢</span>
                <div class="stat-value"><?= $total_suppliers ?></div>
                <div class="stat-label">Suppliers</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⚠️</span>
                <div class="stat-value" style="color: <?= $low_stock > 0 ? '#ef4444' : '#10b981' ?>;">
                    <?= $low_stock ?>
                </div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>

        <!-- Quick Action Cards -->
        <h2 style="font-size:22px; margin-bottom:20px;">Quick Actions</h2>
        <div class="dashboard">
            <div class="card">
                <div class="card-header"><h2>🛒 Point of Sale</h2></div>
                <div class="card-body">
                    <a href="pos.php" class="btn btn-success">New Sale</a>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>📦 Products</h2></div>
                <div class="card-body">
                    <a href="products.php" class="btn btn-primary">Manage Products</a>
                    <a href="categories.php" class="btn btn-outline">Categories</a>
                    <a href="suppliers.php" class="btn btn-outline">Suppliers</a>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>📊 Inventory</h2></div>
                <div class="card-body">
                    <a href="inventory.php" class="btn btn-primary">View Inventory</a>
                    <a href="alerts.php" class="btn btn-outline">Stock Alerts</a>
                    <a href="analytics.php" class="btn btn-outline">Analytics</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>