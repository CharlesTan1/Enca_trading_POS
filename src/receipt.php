<?php
require_once 'includes/session.php';
requireLogin();
require_once 'config/database.php';
require_once 'includes/functions.php';

$sale_id = $_GET['sale_id'] ?? 0;
if (!$sale_id) die('No sale specified.');

$stmt = $pdo->prepare("SELECT s.*, u.full_name as cashier FROM sales s JOIN users u ON s.user_id = u.user_id WHERE s.sale_id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();
if (!$sale) die('Sale not found.');

$stmt = $pdo->prepare("SELECT si.*, p.product_name FROM sale_items si JOIN products p ON si.product_id = p.product_id WHERE si.sale_id = ?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

$currency = '₱';
$barcodeValue = 'SALE-' . str_pad($sale_id, 6, '0', STR_PAD_LEFT);

$paymentLabels = [
    'cash'  => 'Cash',
    'gcash' => 'GCash',
    'maya'  => 'Maya',
    'bank'  => 'Bank Transfer',
    'card'  => 'Card',
];
$pm = $sale['payment_method'] ?? 'cash';
$payLabel = $paymentLabels[$pm] ?? ucfirst($pm);

$qrPayload = "Enca Trading\n"
           . "Receipt #{$sale_id}\n"
           . date('Y-m-d H:i', strtotime($sale['sale_date'])) . "\n"
           . "Payment: " . $payLabel . "\n"
           . "Total: {$currency}" . number_format($sale['total_amount'], 2);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= (int)$sale_id ?> — Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime('assets/css/style.css') ?>">
    <script src="assets/js/script.js" defer></script>

    <!-- Barcode + QR libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>

    <style>
        .receipt-page { max-width: 460px; margin: 40px auto; padding: 0 20px 40px; }

        .receipt {
            background: #fff; color: #131926;
            border-radius: 18px; padding: 32px 28px;
            box-shadow: 0 24px 48px -20px rgba(16,24,40,.25), 0 2px 6px rgba(16,24,40,.06);
            border: 1px solid #e9ecf3;
        }

        .receipt-head { text-align: center; padding-bottom: 18px; }
        .receipt-head h1 { font-size: 22px; font-weight: 800; letter-spacing: -.02em; margin-bottom: 4px; }
        .receipt-head .meta { color: #4b5568; font-size: 13px; line-height: 1.7; }
        .receipt-head .meta strong { color: #131926; font-weight: 600; }

        .receipt-divider { border: none; border-top: 1px dashed #cbd5e1; margin: 18px 0; }

        .receipt-items { width: 100%; border-collapse: collapse; font-size: 14px; }
        .receipt-items th {
            text-align: left; padding: 8px 4px; color: #6b7280;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; border-bottom: 1px solid #e9ecf3;
        }
        .receipt-items td { padding: 10px 4px; border-bottom: 1px solid #f1f3f7; vertical-align: top; }
        .receipt-items td.num, .receipt-items th.num { text-align: right; }

        .receipt-total {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 18px; padding: 14px 16px; background: #eef2ff;
            border-radius: 12px; font-size: 17px; font-weight: 800; letter-spacing: -.01em;
        }
        .receipt-total span:last-child { color: #4f46e5; }

        .receipt-foot { margin-top: 22px; text-align: center; color: #6b7280; font-size: 13px; }

        /* Codes block */
        .receipt-codes {
            display: flex; flex-direction: column; align-items: center; gap: 14px;
            margin-top: 22px; padding-top: 22px; border-top: 1px dashed #cbd5e1;
        }
        .receipt-codes svg { max-width: 100%; height: auto; }
        .receipt-qr {
            display: grid; place-items: center;
            padding: 8px; background: #fff;
            border-radius: 10px; border: 1px solid #e9ecf3;
        }
        .receipt-codes .hint {
            color: #8a94a6; font-size: 11px; letter-spacing: .06em;
            text-transform: uppercase; font-weight: 700;
        }

        .receipt-actions { display: flex; gap: 10px; justify-content: center; margin-top: 22px; }

        /* Print */
        @media print {
            body { background: #fff !important; margin: 0; padding: 0; }
            .nav-menu, .receipt-actions { display: none !important; }
            .receipt-page { max-width: none; margin: 0; padding: 0; }
            .receipt {
                width: 72mm; max-width: 72mm; margin: 0 auto;
                padding: 6mm 3mm; border: none; border-radius: 0; box-shadow: none;
                font-family: 'Courier New', monospace;
                font-size: 12px; color: #000;
            }
            .receipt-head h1 { font-size: 15px; }
            .receipt-head .meta { font-size: 11px; color: #000; }
            .receipt-divider { border-top: 1px dashed #000; }
            .receipt-items { font-size: 11px; }
            .receipt-items th, .receipt-items td {
                padding: 3px 2px; border-bottom: none; color: #000;
            }
            .receipt-total {
                background: none; padding: 6px 0; font-size: 13px; color: #000;
            }
            .receipt-total span:last-child { color: #000; }
            .receipt-foot { font-size: 11px; color: #000; }

            .receipt-codes {
                margin-top: 10px; padding-top: 10px; border-top: 1px dashed #000;
                gap: 8px;
            }
            .receipt-codes .hint { color: #000; font-size: 9px; }
            .receipt-qr { border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="receipt-page">
        <div class="receipt">
            <div class="receipt-head">
                <h1>Enca Trading</h1>
                <div class="meta">
                    <div>Receipt <strong>#<?= (int)$sale_id ?></strong></div>
                    <div><?= date('Y-m-d H:i', strtotime($sale['sale_date'])) ?></div>
                    <div>Cashier: <strong><?= htmlspecialchars($sale['cashier']) ?></strong></div>
                </div>
            </div>

            <hr class="receipt-divider">

            <table class="receipt-items">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="num">Qty</th>
                        <th class="num">Price</th>
                        <th class="num">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td class="num"><?= (int)$item['quantity'] ?></td>
                            <td class="num"><?= $currency ?><?= number_format($item['unit_price'], 2) ?></td>
                            <td class="num"><?= $currency ?><?= number_format($item['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-total">
                <span>Total</span>
                <span><?= $currency ?><?= number_format($sale['total_amount'], 2) ?></span>
            </div>

            <div class="receipt-payment">
                <div class="row"><span>Payment</span><span><?= htmlspecialchars($payLabel) ?></span></div>
                <?php if ($pm === 'cash'): ?>
                    <div class="row"><span>Tendered</span><span><?= $currency ?><?= number_format($sale['amount_tendered'] ?? $sale['total_amount'], 2) ?></span></div>
                    <div class="row"><span>Change</span><span><?= $currency ?><?= number_format($sale['change_amount'] ?? 0, 2) ?></span></div>
                <?php elseif (!empty($sale['payment_reference'])): ?>
                    <div class="row"><span>Ref No.</span><span><?= htmlspecialchars($sale['payment_reference']) ?></span></div>
                <?php endif; ?>
            </div>

            <div class="receipt-foot">Thank you for your purchase!</div>

            <!-- Barcode + QR -->
            <div class="receipt-codes">
                <svg id="barcode"></svg>
                <div class="hint">Scan for receipt copy</div>
                <div class="receipt-qr" id="qrcode"></div>
            </div>
        </div>

        <div class="receipt-actions">
            <button type="button" id="printReceipt" class="btn btn-primary">🖨️ Print Receipt</button>
            <a href="pos.php" class="btn btn-outline">New Sale</a>
            <a href="dashboard.php" class="btn btn-outline">Dashboard</a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Barcode
        if (window.JsBarcode && document.getElementById('barcode')) {
            JsBarcode('#barcode', <?= json_encode($barcodeValue) ?>, {
                format: 'CODE128',
                width: 1.7,
                height: 46,
                displayValue: true,
                fontSize: 12,
                margin: 0,
                textMargin: 4
            });
        }

        // QR
        if (window.QRCode && document.getElementById('qrcode')) {
            new QRCode(document.getElementById('qrcode'), {
                text: <?= json_encode($qrPayload) ?>,
                width: 108,
                height: 108,
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
    </script>
</body>
</html>