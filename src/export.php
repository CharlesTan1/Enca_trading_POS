<?php
require_once 'includes/session.php';
requireAdmin(); // Only admins can export data
require_once 'config/database.php';

$type = $_GET['type'] ?? '';

if ($type === 'sales') {
    // Export Detailed Sales (one row per item sold)
    $filename = "enca_sales_export_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for Excel
    fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // Headers
    fputcsv($output, ['Sale ID', 'Date', 'Cashier', 'Payment Method', 'Total Amount', 'Product', 'Qty', 'Unit Price', 'Subtotal']);

    $stmt = $pdo->query("
        SELECT s.sale_id, s.sale_date, u.full_name AS cashier, s.payment_method, s.total_amount,
               p.product_name, si.quantity, si.unit_price, si.subtotal
        FROM sales s
        JOIN users u ON s.user_id = u.user_id
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN products p ON si.product_id = p.product_id
        ORDER BY s.sale_date DESC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;

} elseif ($type === 'products') {
    // Export Products
    $filename = "enca_products_export_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF))); // BOM
    
    // Headers matching the Import format
    fputcsv($output, ['Product Name', 'Category', 'Supplier', 'Unit Price', 'Stock Quantity', 'Reorder Level']);

    $stmt = $pdo->query("
        SELECT p.product_name, COALESCE(c.category_name, '') AS category, 
               COALESCE(s.supplier_name, '') AS supplier, 
               p.unit_price, p.stock_quantity, p.reorder_level
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        ORDER BY p.product_name
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// If no valid type, go back to dashboard
header('Location: dashboard.php');
exit;
?>