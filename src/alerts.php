<?php
require_once 'includes/session.php';
requireLogin();
require_once 'config/database.php';
require_once 'includes/functions.php';
$low = getLowStockProducts();
$zero = getZeroStockProducts();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stock Alerts - Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <h1 class="page-title">🔔 Stock Alerts</h1>
        <p class="page-subtitle">Monitor low and zero stock items</p>

        <h3>⚠️ Low Stock Items (≤ Reorder Level)</h3>
        <?php if ($low): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Product</th><th>Stock</th><th>Reorder Level</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['product_name']) ?></td>
                                <td>
                                    <span class="badge <?= $p['stock_quantity'] == 0 ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= $p['stock_quantity'] ?>
                                    </span>
                                </td>
                                <td><?= $p['reorder_level'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert success">✅ All products are well-stocked.</div>
        <?php endif; ?>

        <h3 style="margin-top:30px;">🚫 Zero Stock Items</h3>
        <?php if ($zero): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Product</th><th>Stock</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zero as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['product_name']) ?></td>
                                <td><span class="badge badge-danger">0</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert success">✅ No zero-stock items.</div>
        <?php endif; ?>
    </div>
</body>
</html>