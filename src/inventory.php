<?php
require_once 'includes/session.php';
requireLogin();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Manual stock adjustment (admin only)
if (isset($_POST['adjust_stock']) && $_SESSION['role'] === 'admin') {
    $product_id = $_POST['product_id'];
    
    // 1. Force the input to be a positive number
    $qty_input = abs((int)$_POST['quantity_change']); 
    $reason = $_POST['reason'];
    
    // 2. Automatically determine the math based on the reason
    // If the reason is 'sale' (used for damage/loss/correction), it subtracts. Otherwise, it adds.
    $isSubtract = ($reason === 'sale');
    $qty_change = $isSubtract ? -$qty_input : $qty_input;

    if ($qty_input === 0) {
        $error = "Please enter a valid quantity greater than 0.";
    } else {
        // Check if product exists
        $check = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
        $check->execute([$product_id]);
        $current = $check->fetch();
        
        if (!$current) {
            $error = "Product not found.";
        } elseif ($current['stock_quantity'] + $qty_change < 0) {
            $error = "Insufficient stock! Current: " . $current['stock_quantity'] . ", Trying to subtract: " . $qty_input;
        } else {
            // Pass the calculated $qty_change (which already has the + or - applied)
            if (updateStock($product_id, $qty_change, $reason, null, $_SESSION['user_id'])) {
                header('Location: inventory.php?msg=adjusted');
                exit;
            } else {
                $error = "Adjustment failed. Please try again.";
            }
        }
    }
}

$products = $pdo->query("SELECT product_id, product_name, stock_quantity, reorder_level FROM products ORDER BY product_name")->fetchAll();

$logs = $pdo->query("
    SELECT l.*, p.product_name, u.full_name as user
    FROM inventory_logs l
    JOIN products p ON l.product_id = p.product_id
    JOIN users u ON l.created_by = u.user_id
    ORDER BY l.log_date DESC
    LIMIT 50
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory - Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <h1 class="page-title">📊 Inventory Overview</h1>
        <p class="page-subtitle">Track stock levels and adjust inventory</p>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stock Adjustment (Admin only) -->
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="form-container">
                <h3>📦 Manual Stock Adjustment</h3>
                <form method="post" id="adjustForm">
                    <div class="form-group">
                        <label>Select Product</label>
                        <select name="product_id" id="productSelect" required>
                            <option value="">-- Choose --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['product_id'] ?>" data-stock="<?= $p['stock_quantity'] ?>">
                                    <?= htmlspecialchars($p['product_name']) ?> (Current Stock: <?= $p['stock_quantity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason for Adjustment</label>
                        <select name="reason" id="reasonSelect" required>
                            <option value="purchase">Purchase / Delivery (Adds Stock)</option>
                            <option value="return">Customer Return (Adds Stock)</option>
                            <option value="adjustment">Inventory Correction / Found Extra (Adds Stock)</option>
                            <option value="sale">Damage / Theft / Unrecorded Sale (Subtracts Stock)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity (Enter positive number only)</label>
                        <input type="number" name="quantity_change" id="qtyInput" min="1" placeholder="e.g. 10" required>
                    </div>

                    <!-- Live Preview -->
                    <div class="alert info" id="previewBox" style="display:none; margin-bottom: 15px;">
                        New stock level will be: <strong id="previewStock">0</strong>
                    </div>

                    <button type="submit" name="adjust_stock" class="btn btn-primary">Apply Adjustment</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Current Stock Table -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3 style="margin:0;">Current Stock Levels</h3>
            <a href="export.php?type=products" class="btn btn-outline">⬇️ Export Inventory (CSV)</a>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Product</th><th>Stock</th><th>Reorder Level</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['product_name']) ?></td>
                            <td><?= $p['stock_quantity'] ?></td>
                            <td><?= $p['reorder_level'] ?></td>
                            <td>
                                <?php if ($p['stock_quantity'] == 0): ?>
                                    <span class="badge badge-danger">ZERO STOCK</span>
                                <?php elseif ($p['stock_quantity'] <= $p['reorder_level']): ?>
                                    <span class="badge badge-warning">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge badge-success">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Inventory Logs -->
        <h3>Recent Inventory Logs</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Date</th><th>Product</th><th>Change</th><th>New Qty</th><th>Reason</th><th>By</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i', strtotime($log['log_date'])) ?></td>
                            <td><?= htmlspecialchars($log['product_name']) ?></td>
                            <td style="font-weight:bold; color: <?= $log['change_quantity'] > 0 ? 'var(--ok-tx)' : 'var(--bad-tx)' ?>;">
                                <?= $log['change_quantity'] > 0 ? '+' : '' ?><?= $log['change_quantity'] ?>
                            </td>
                            <td><?= $log['new_quantity'] ?></td>
                            <td><?= ucfirst($log['reason']) ?></td>
                            <td><?= htmlspecialchars($log['user']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- JavaScript for Live Stock Preview -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('adjustForm');
        if (!form) return; // Exit if not admin

        const productSelect = document.getElementById('productSelect');
        const reasonSelect = document.getElementById('reasonSelect');
        const qtyInput = document.getElementById('qtyInput');
        const previewBox = document.getElementById('previewBox');
        const previewStock = document.getElementById('previewStock');

        function updatePreview() {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            
            // If no product is selected or no quantity is entered, hide preview
            if (!selectedOption || !selectedOption.dataset.stock || !qtyInput.value) {
                previewBox.style.display = 'none';
                return;
            }

            const currentStock = parseInt(selectedOption.dataset.stock, 10) || 0;
            const qty = parseInt(qtyInput.value, 10) || 0;
            const reason = reasonSelect.value;

            // If reason is 'sale' (damage/loss), we subtract. Otherwise, we add.
            const isSubtract = (reason === 'sale');
            const change = isSubtract ? -qty : qty;
            const newStock = currentStock + change;

            if (qty > 0) {
                previewBox.style.display = 'block';
                
                if (newStock < 0) {
                    previewBox.className = 'alert error';
                    previewBox.innerHTML = 'Error: Cannot subtract ' + qty + '. You only have ' + currentStock + ' in stock.';
                } else {
                    previewBox.className = 'alert info';
                    previewBox.innerHTML = 'New stock level will be: <strong>' + newStock + '</strong> (Current: ' + currentStock + ', Change: ' + (change > 0 ? '+' : '') + change + ')';
                }
            } else {
                previewBox.style.display = 'none';
            }
        }

        // Listen for changes
        productSelect.addEventListener('change', updatePreview);
        reasonSelect.addEventListener('change', updatePreview);
        qtyInput.addEventListener('input', updatePreview);
    });
    </script>
</body>
</html>