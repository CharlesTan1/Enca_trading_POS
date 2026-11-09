<?php
require_once __DIR__ . '/../config/database.php';

function getProductById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateStock($product_id, $quantity_change, $reason, $reference_id = null, $user_id) {
    global $pdo;
    $product = getProductById($product_id);
    if (!$product) return false;
    $new_qty = $product['stock_quantity'] + $quantity_change;
    if ($new_qty < 0) return false;

    // Check if a transaction is already active
    $hasActiveTransaction = $pdo->inTransaction();

    try {
        // Only start a new transaction if none is active
        if (!$hasActiveTransaction) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
        $stmt->execute([$new_qty, $product_id]);

        $stmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, change_quantity, new_quantity, reason, reference_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $quantity_change, $new_qty, $reason, $reference_id, $user_id]);

        if (!$hasActiveTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if (!$hasActiveTransaction) {
            $pdo->rollBack();
        }
        return false;
    }
}

function getLowStockProducts() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM products WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getZeroStockProducts() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM products WHERE stock_quantity = 0 ORDER BY product_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTopSellingProducts($limit = 5) {
    global $pdo;
    $limit = (int)$limit;
    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, SUM(si.quantity) as total_sold
        FROM sale_items si
        JOIN products p ON si.product_id = p.product_id
        GROUP BY p.product_id
        ORDER BY total_sold DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>