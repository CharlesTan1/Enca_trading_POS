<?php
require_once 'includes/session.php';
requireLogin();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $qty = (int)$_POST['quantity'];
    $product = getProductById($product_id);
    if ($product && $qty > 0 && $qty <= $product['stock_quantity']) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $product_id,
                'name' => $product['product_name'],
                'price' => $product['unit_price'],
                'qty' => $qty
            ];
        }
    }
    header('Location: pos.php');
    exit;
}

// Remove from cart
if (isset($_GET['remove'])) {
    $index = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$index])) unset($_SESSION['cart'][$index]);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header('Location: pos.php');
    exit;
}

// Clear cart
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
    header('Location: pos.php');
    exit;
}

// Checkout
if (isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        $error = 'Cart is empty.';
    } else {
        $user_id = $_SESSION['user_id'];
        $total = 0;
        $items = [];
        foreach ($_SESSION['cart'] as $item) {
            $subtotal = $item['price'] * $item['qty'];
            $total += $subtotal;
            $items[] = [
                'product_id' => $item['product_id'],
                'qty'        => $item['qty'],
                'price'      => $item['price'],
                'subtotal'   => $subtotal,
            ];
        }

        /* ---------- Payment validation ---------- */
        $allowed = ['cash', 'gcash', 'maya', 'bank', 'card'];
        $payment_method = $_POST['payment_method'] ?? 'cash';
        if (!in_array($payment_method, $allowed, true)) {
            $payment_method = 'cash';
        }

        $amount_tendered   = $total;
        $change_amount     = 0.0;
        $payment_reference = null;

        if ($payment_method === 'cash') {
            $amount_tendered = (float)($_POST['amount_tendered'] ?? 0);
            if ($amount_tendered < $total) {
                $error = 'Amount tendered (₱' . number_format($amount_tendered, 2)
                       . ') is less than the total (₱' . number_format($total, 2) . ').';
            } else {
                $change_amount = $amount_tendered - $total;
            }
        } else {
            $ref = trim($_POST['payment_reference'] ?? '');
            if ($ref !== '') $payment_reference = $ref;
        }

        /* ---------- Persist ---------- */
        if (empty($error)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sales
                        (user_id, total_amount, payment_method,
                         amount_tendered, change_amount, payment_reference)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $total, $payment_method,
                    $amount_tendered, $change_amount, $payment_reference,
                ]);
                $sale_id = $pdo->lastInsertId();

                foreach ($items as $item) {
                    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$sale_id, $item['product_id'], $item['qty'], $item['price'], $item['subtotal']]);
                    updateStock($item['product_id'], -$item['qty'], 'sale', $sale_id, $user_id);
                }
                $pdo->commit();
                unset($_SESSION['cart']);
                header("Location: receipt.php?sale_id=$sale_id");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Checkout failed: ' . $e->getMessage();
            }
        }
    }
}

$products = $pdo->query("SELECT product_id, product_name, stock_quantity FROM products")->fetchAll();
$cart = $_SESSION['cart'] ?? [];
$total_cart = array_sum(array_map(function($item){ return $item['price'] * $item['qty']; }, $cart));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Point of Sale - Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <h1 class="page-title">🛒 Point of Sale</h1>
        <p class="page-subtitle">Process customer transactions quickly</p>

        <?php if (isset($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="pos-grid">
            <!-- Product selection -->
            <div>
                <div class="form-container" style="margin:0; max-width:100%;">
                    <h3>Add Product to Cart</h3>
                    <form method="post">
                        <div class="form-group">
                            <label>Select Product</label>
                            <select name="product_id" required>
                                <option value="">-- Choose --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['product_id'] ?>">
                                        <?= htmlspecialchars($p['product_name']) ?> (Stock: <?= $p['stock_quantity'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" value="1" min="1" required>
                        </div>
                        <button type="submit" name="add_to_cart" class="btn btn-primary" style="width:100%;">➕ Add to Cart</button>
                    </form>
                </div>
            </div>

            <!-- Cart -->
            <div>
                <div class="form-container" style="margin:0; max-width:100%;">
                    <h3>🛍️ Current Cart</h3>
                    
                    <?php if (empty($cart)): ?>
                        <p style="color: #94a3b8; text-align: center; padding: 20px 0;">Cart is empty. Add items to start selling!</p>
                    <?php else: ?>
                        <form method="post">
                            <div class="table-wrapper" style="margin:0;">
                                <table class="data-table">
                                    <thead>
                                        <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart as $i => $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                <td><?= $item['qty'] ?></td>
                                                <td>₱<?= number_format($item['price'], 2) ?></td>
                                                <td>₱<?= number_format($item['price'] * $item['qty'], 2) ?></td>
                                                <td><a href="?remove=<?= $i ?>" class="btn-delete" onclick="return confirm('Remove this item?')">✖</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Payment panel -->
                            <div class="payment-panel">
                                <label>Payment Method</label>
                                <div class="payment-methods">
                                    <label class="pm-option"><input type="radio" name="payment_method" value="cash" checked><span>💵 Cash</span></label>
                                    <label class="pm-option"><input type="radio" name="payment_method" value="gcash"><span>📱 GCash</span></label>
                                    <label class="pm-option"><input type="radio" name="payment_method" value="maya"><span>💜 Maya</span></label>
                                    <label class="pm-option"><input type="radio" name="payment_method" value="bank"><span>🏦 Bank</span></label>
                                    <label class="pm-option"><input type="radio" name="payment_method" value="card"><span>💳 Card</span></label>
                                </div>

                                <div id="cashFields">
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <label>Amount Tendered (₱)</label>
                                        <input type="number" step="0.01" min="0" name="amount_tendered" id="amountTendered" placeholder="0.00">
                                    </div>
                                    <div class="quick-cash" id="quickCash">
                                        <button type="button" data-amount="<?= (float)$total_cart ?>">Exact</button>
                                        <button type="button" data-amount="100">₱100</button>
                                        <button type="button" data-amount="200">₱200</button>
                                        <button type="button" data-amount="500">₱500</button>
                                        <button type="button" data-amount="1000">₱1000</button>
                                    </div>
                                    <div class="change-display">
                                        <span>Change</span>
                                        <span id="changeAmount">₱0.00</span>
                                    </div>
                                </div>

                                <div id="ewalletFields" style="display:none;">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Reference No. <span style="font-weight:400;text-transform:none;">(optional)</span></label>
                                        <input type="text" name="payment_reference" placeholder="e.g., 1234567890">
                                    </div>
                                </div>
                            </div>

                            <div class="cart-total">
                                <span>Total</span>
                                <span>₱<?= number_format($total_cart, 2) ?></span>
                            </div>
                            <div style="display:flex; gap:10px; margin-top:15px;">
                                <button type="submit" name="checkout" class="btn btn-success" style="flex:1;">✅ Complete Payment</button>
                                <a href="?clear" class="btn btn-danger" onclick="return confirm('Clear entire cart?')">🗑️ Clear</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const TOTAL = <?= json_encode((float)$total_cart) ?>;
        const radios = document.querySelectorAll('input[name="payment_method"]');
        if (!radios.length) return;

        const cashFields    = document.getElementById('cashFields');
        const ewalletFields = document.getElementById('ewalletFields');
        const tendered      = document.getElementById('amountTendered');
        const changeEl      = document.getElementById('changeAmount');
        const quickCash     = document.getElementById('quickCash');

        function currentMethod() {
            return document.querySelector('input[name="payment_method"]:checked')?.value || 'cash';
        }

        function updateChange() {
            const t = parseFloat(tendered.value) || 0;
            const change = t - TOTAL;
            if (t === 0) {
                changeEl.textContent = '₱0.00';
                changeEl.style.color = 'var(--ink-3)';
            } else if (change < 0) {
                changeEl.textContent = '−₱' + Math.abs(change).toFixed(2) + ' short';
                changeEl.style.color = 'var(--bad-tx)';
            } else {
                changeEl.textContent = '₱' + change.toFixed(2);
                changeEl.style.color = 'var(--ok-tx)';
            }
        }

        function updateVisibility() {
            const isCash = currentMethod() === 'cash';
            cashFields.style.display    = isCash ? 'block' : 'none';
            ewalletFields.style.display = isCash ? 'none'  : 'block';
            if (isCash) updateChange();
        }

        radios.forEach(r => r.addEventListener('change', updateVisibility));
        if (tendered) tendered.addEventListener('input', updateChange);

        if (quickCash) {
            quickCash.querySelectorAll('button[data-amount]').forEach(btn => {
                const amt = parseFloat(btn.dataset.amount);
                if (amt > 0 && amt < TOTAL) btn.remove(); 
                btn.addEventListener('click', () => {
                    tendered.value = amt.toFixed(2);
                    updateChange();
                });
            });
        }

        updateVisibility();
    });
    </script>
</body>
</html>