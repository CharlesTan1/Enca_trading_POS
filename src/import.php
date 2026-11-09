<?php
require_once 'includes/session.php';
requireAdmin();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        header('Location: products.php?msg=upload_error');
        exit;
    }

    $handle = fopen($file, "r");
    if ($handle !== FALSE) {
        // Skip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle); // No BOM, go back to start
        }

        // Skip the header row
        fgetcsv($handle); 

        $successCount = 0;
        $updateCount = 0;

        $pdo->beginTransaction();

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Expected columns: Name, Category, Supplier, Price, Stock, Reorder Level
            if (count($data) < 6) continue;

            $name = trim($data[0]);
            $cat_name = trim($data[1]);
            $sup_name = trim($data[2]);
            $price = (float)$data[3];
            $stock = (int)$data[4];
            $reorder = (int)$data[5] ?: 5;

            if (empty($name)) continue;

            // 1. Resolve Category ID (Create if doesn't exist)
            $cat_id = null;
            if (!empty($cat_name)) {
                $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ?");
                $stmt->execute([$cat_name]);
                $cat = $stmt->fetch();
                if ($cat) {
                    $cat_id = $cat['category_id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
                    $stmt->execute([$cat_name]);
                    $cat_id = $pdo->lastInsertId();
                }
            }

            // 2. Resolve Supplier ID (Create if doesn't exist)
            $sup_id = null;
            if (!empty($sup_name)) {
                $stmt = $pdo->prepare("SELECT supplier_id FROM suppliers WHERE supplier_name = ?");
                $stmt->execute([$sup_name]);
                $sup = $stmt->fetch();
                if ($sup) {
                    $sup_id = $sup['supplier_id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name) VALUES (?)");
                    $stmt->execute([$sup_name]);
                    $sup_id = $pdo->lastInsertId();
                }
            }

            // 3. Insert or Update Product
            $stmt = $pdo->prepare("SELECT product_id FROM products WHERE product_name = ?");
            $stmt->execute([$name]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update
                $stmt = $pdo->prepare("UPDATE products SET category_id=?, supplier_id=?, unit_price=?, stock_quantity=?, reorder_level=? WHERE product_id=?");
                $stmt->execute([$cat_id, $sup_id, $price, $stock, $reorder, $existing['product_id']]);
                $updateCount++;
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO products (product_name, category_id, supplier_id, unit_price, stock_quantity, reorder_level) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $cat_id, $sup_id, $price, $stock, $reorder]);
                
                // Log initial stock
                if ($stock > 0) {
                    $new_id = $pdo->lastInsertId();
                    updateStock($new_id, $stock, 'purchase', null, $_SESSION['user_id']);
                }
                $successCount++;
            }
        }
        fclose($handle);
        $pdo->commit();
        
        header('Location: products.php?msg=imported&new=' . $successCount . '&upd=' . $updateCount);
        exit;
    }
}
header('Location: products.php?msg=import_failed');
exit;
?>