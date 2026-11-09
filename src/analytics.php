<?php
require_once 'includes/session.php';
requireLogin();
require_once 'config/database.php';
require_once 'includes/functions.php';

/* ---------- Period selector ---------- */
$period = isset($_GET['period']) ? (int)$_GET['period'] : 30;
if (!in_array($period, [7, 30, 90, 365], true)) $period = 30;
$days = $period;

/* ---------- KPI queries ---------- */
$kpi = $pdo->query("
    SELECT COUNT(*) AS orders,
           COALESCE(SUM(total_amount), 0) AS revenue
    FROM sales
    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
")->fetch();

$items = $pdo->query("
    SELECT COALESCE(SUM(si.quantity), 0) AS items
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
")->fetchColumn();

$kpi['items'] = (int)$items;
$kpi['aov'] = $kpi['orders'] > 0 ? $kpi['revenue'] / $kpi['orders'] : 0;

/* Prior period for comparison */
$prev = $pdo->query("
    SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue
    FROM sales
    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL " . ($days * 2) . " DAY)
      AND sale_date <  DATE_SUB(NOW(), INTERVAL {$days} DAY)
")->fetch();

$revChange = $prev['revenue'] > 0
    ? (($kpi['revenue'] - $prev['revenue']) / $prev['revenue']) * 100
    : 0;

/* =========================================================
   CHART DATA & LINEAR REGRESSION FORECAST
   ========================================================= */
$rawDaily = $pdo->query("
    SELECT DATE(sale_date) AS d, SUM(total_amount) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY DATE(sale_date)
")->fetchAll();

$dailyMap = [];
foreach ($rawDaily as $r) $dailyMap[$r['d']] = (float)$r['total'];

$chartLabels = [];
$actualData = [];
$forecastData = [];
$sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;

// 1. Build Historical Data
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M j', strtotime($date));
    
    $val = $dailyMap[$date] ?? 0;
    $actualData[] = $val;
    $forecastData[] = null; // No forecast for past days
    
    // Accumulators for regression math
    $x = $days - 1 - $i;
    $sumX += $x;
    $sumY += $val;
    $sumXY += ($x * $val);
    $sumX2 += ($x * $x);
}

// 2. Calculate Regression (m = slope, b = intercept)
$n = count($actualData);
$denominator = ($n * $sumX2) - ($sumX * $sumX);
$m = $denominator != 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
$b = ($sumY - ($m * $sumX)) / ($n > 0 ? $n : 1);

// 3. Connect the Forecast to the last actual point
$forecastData[count($forecastData) - 1] = end($actualData);

// 4. Generate 7 Days of Future Predictions
$forecastTotal = 0;
for ($i = 1; $i <= 7; $i++) {
    $futureDate = date('M j', strtotime("+$i days"));
    $chartLabels[] = $futureDate;
    
    $actualData[] = null; // No actual data for future days
    
    $futureX = ($n - 1) + $i;
    $predictedVal = max(0, ($m * $futureX) + $b); // Prevent negative predictions
    $forecastData[] = $predictedVal;
    $forecastTotal += $predictedVal;
}

/* ---------- Other Chart Data ---------- */
$monthly = $pdo->query("
    SELECT DATE_FORMAT(sale_date, '%Y-%m') AS m, SUM(total_amount) AS total
    FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY m ORDER BY m
")->fetchAll();

$byCategory = $pdo->query("
    SELECT COALESCE(c.category_name, 'Uncategorised') AS name, SUM(si.subtotal) AS revenue
    FROM sale_items si JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY c.category_id, c.category_name ORDER BY revenue DESC
")->fetchAll();

$topProducts = $pdo->query("
    SELECT p.product_name, SUM(si.quantity) AS qty, SUM(si.subtotal) AS revenue
    FROM sale_items si JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY p.product_id, p.product_name ORDER BY revenue DESC LIMIT 10
")->fetchAll();

$hourly = $pdo->query("
    SELECT HOUR(sale_date) AS h, SUM(total_amount) AS total
    FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY HOUR(sale_date) ORDER BY h
")->fetchAll();

$staff = $pdo->query("
    SELECT u.full_name, COUNT(s.sale_id) AS orders, COALESCE(SUM(s.total_amount), 0) AS revenue
    FROM sales s JOIN users u ON s.user_id = u.user_id
    WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY u.user_id, u.full_name ORDER BY revenue DESC
")->fetchAll();

$paymentMix = $pdo->query("
    SELECT COALESCE(payment_method, 'cash') AS method, COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue
    FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY payment_method ORDER BY revenue DESC
")->fetchAll();

$deadStock = $pdo->query("
    SELECT p.product_name, p.stock_quantity, (p.stock_quantity * p.unit_price) AS tied_capital
    FROM products p
    LEFT JOIN (SELECT DISTINCT si.product_id FROM sale_items si JOIN sales s ON si.sale_id = s.sale_id WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) recent ON p.product_id = recent.product_id
    WHERE recent.product_id IS NULL AND p.stock_quantity > 0
    ORDER BY tied_capital DESC LIMIT 15
")->fetchAll();

/* ---------- Prescriptive Data ---------- */
$affinity = $pdo->query("
    SELECT p1.product_name AS prod1, p2.product_name AS prod2, COUNT(*) AS frequency
    FROM sale_items si1 JOIN sale_items si2 ON si1.sale_id = si2.sale_id AND si1.product_id < si2.product_id
    JOIN products p1 ON si1.product_id = p1.product_id JOIN products p2 ON si2.product_id = p2.product_id
    WHERE si1.sale_id IN (SELECT sale_id FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY))
    GROUP BY p1.product_id, p2.product_id ORDER BY frequency DESC LIMIT 1
")->fetch();

$slowestDay = $pdo->query("
    SELECT DAYNAME(sale_date) AS day_name, SUM(total_amount) AS revenue
    FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DAYNAME(sale_date) ORDER BY revenue ASC LIMIT 1
")->fetch();

$urgent = $pdo->query("
    SELECT p.product_name, p.stock_quantity, SUM(si.quantity) AS sold_recently
    FROM products p
    JOIN sale_items si ON p.product_id = si.product_id
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND p.stock_quantity <= p.reorder_level
    GROUP BY p.product_id ORDER BY sold_recently DESC LIMIT 3
")->fetchAll();

/* ---------- Insights Engine ---------- */
$recs = [];

$recent7 = $pdo->query("SELECT COALESCE(SUM(total_amount),0) AS rev FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$prev7 = $pdo->query("SELECT COALESCE(SUM(total_amount),0) AS rev FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND sale_date <  DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

if ($prev7 > 0) {
    $chg = (($recent7 - $prev7) / $prev7) * 100;
    if ($chg <= -10) {
        $recs[] = ['critical', '📉', 'Sales down ' . round(abs($chg)) . '% week over week',
            'Revenue dropped to ₱' . number_format($recent7, 2) . ' (down ₱' . number_format($prev7 - $recent7, 2) . '). ACTION: Run a weekend flash sale. A 10% discount on your top 3 items could recover roughly ₱' . number_format(($prev7 - $recent7) * 1.1, 2) . ' in lost volume.'];
    } elseif ($chg >= 10) {
        $recs[] = ['success', '📈', 'Sales up ' . round($chg) . '% week over week',
            'Momentum is strong, adding ₱' . number_format($recent7 - $prev7, 2) . ' vs last week. ACTION: Increase your reorder levels for your top 5 products immediately. If you maintain this trend, you will generate an extra ₱' . number_format(($recent7 - $prev7) * 4, 2) . ' this month.'];
    }
}

if ($affinity && $affinity['frequency'] >= 2) {
    $recs[] = ['info', '🤝', 'Bundling Opportunity: "' . $affinity['prod1'] . '" & "' . $affinity['prod2'] . '"',
        'These were bought together ' . $affinity['frequency'] . ' times. ACTION: Bundle them at a 10% discount. If you sell 1 bundle a day, that is ₱' . number_format(($affinity['frequency'] * 1.5) * 30, 2) . ' in added revenue this month.'];
}

if ($topProducts) {
    $hero = $topProducts[0];
    $recs[] = ['info', '🏆', 'Anchor Strategy: Your Star Product is "' . $hero['product_name'] . '"',
        'It generated ₱' . number_format($hero['revenue'], 2) . '. ACTION: Never let this go out of stock. If this goes out of stock for 3 days, you could lose up to ₱' . number_format(($hero['revenue'] / max($days, 1)) * 3, 2) . ' based on your current daily average.'];
}

if ($slowestDay && $slowestDay['revenue'] > 0) {
    $recs[] = ['warning', '📅', 'Traffic Builder: ' . $slowestDay['day_name'] . 's are your slowest days',
        'You only made ₱' . number_format($slowestDay['revenue'], 2) . ' on ' . $slowestDay['day_name'] . 's. ACTION: Introduce a "' . $slowestDay['day_name'] . ' Special". If you drive just 5 extra customers spending ₱150, you add ₱' . number_format(5 * 150, 2) . ' to your weekly revenue.'];
}

if ($urgent) {
    $list = array_map(function($p) {
        $dailyVelocity = $p['sold_recently'] / 14;
        $daysLeft = $dailyVelocity > 0 ? floor($p['stock_quantity'] / $dailyVelocity) : 999;
        return $p['product_name'] . ' (' . $p['stock_quantity'] . ' left, ~' . $daysLeft . ' days remaining)';
    }, $urgent);
    $recs[] = ['critical', '🚨', 'Restock Alert: High-velocity items running out',
        implode('; ', $list) . '. ACTION: Contact your supplier today. Every day these are out of stock, you are handing sales to a competitor.'];
}

if (count($deadStock) >= 1) {
    $totalTiedCapital = array_sum(array_column($deadStock, 'tied_capital'));
    $totalUnits = array_sum(array_column($deadStock, 'stock_quantity'));
    $recs[] = ['warning', '💤', 'Clearance Protocol: ₱' . number_format($totalTiedCapital, 2) . ' tied up in dead stock',
        'You are holding ' . number_format($totalUnits) . ' units that haven\'t moved in 30 days. ACTION: Run a "Clearance Bundle" to free up cash. Discounting these by 30% would recover roughly ₱' . number_format($totalTiedCapital * 0.7, 2) . ' in liquid cash for better sellers.'];
}

/* ---------- Bridge to JS ---------- */
$monthlyLabels  = array_map(fn($r) => date('M Y', strtotime($r['m'] . '-01')), $monthly);
$monthlyRevenue = array_map(fn($r) => (float)$r['total'], $monthly);
$catLabels = array_column($byCategory, 'name');
$catData   = array_map(fn($r) => (float)$r['revenue'], $byCategory);
$topLabels = array_column($topProducts, 'product_name');
$topData   = array_map(fn($r) => (float)$r['revenue'], $topProducts);
$hourLabels = array_map(fn($r) => str_pad($r['h'], 2, '0', STR_PAD_LEFT) . ':00', $hourly);
$hourData   = array_map(fn($r) => (float)$r['total'], $hourly);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Analytics — Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime('assets/css/style.css') ?>">
    <script src="assets/js/script.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px;">
            <div>
                <h1 class="page-title">📈 Analytics & Insights</h1>
                <p class="page-subtitle">
                    Performance for the last <strong><?= $days ?></strong> days
                    <?php if ($prev['revenue'] > 0): ?>
                        — revenue
                        <span class="badge <?= $revChange >= 0 ? 'badge-success' : 'badge-danger' ?>">
                            <?= $revChange >= 0 ? '▲' : '▼' ?> <?= number_format(abs($revChange), 1) ?>%
                        </span>
                        vs previous period
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="export.php?type=sales" class="btn btn-outline">⬇️ Export Sales (CSV)</a>
            </div>
        </div>

        <!-- Period selector -->
        <div class="period-tabs">
            <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'] as $d => $label): ?>
                <a href="?period=<?= $d ?>" class="period-tab <?= $period === $d ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <!-- KPI cards -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <div class="stat-value">₱<?= number_format($kpi['revenue'], 2) ?></div>
                <div class="stat-label">Revenue</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🧾</span>
                <div class="stat-value"><?= number_format($kpi['orders']) ?></div>
                <div class="stat-label">Transactions</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <div class="stat-value"><?= number_format($kpi['items']) ?></div>
                <div class="stat-label">Items Sold</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🛒</span>
                <div class="stat-value">₱<?= number_format($kpi['aov'], 2) ?></div>
                <div class="stat-label">Avg Order Value</div>
            </div>
            <!-- FORECAST KPI -->
            <div class="stat-card" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe); border-color: #c4b5fd;">
                <span class="stat-icon" style="background: #ddd6fe; color: #5b21b6;">🔮</span>
                <div class="stat-value" style="color: #5b21b6;">₱<?= number_format($forecastTotal, 2) ?></div>
                <div class="stat-label" style="color: #6d28d9;">Next 7 Days (Forecast)</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card chart-wide">
                <h3>Sales Trend & 7-Day Forecast</h3>
                <p style="font-size:12px; color:var(--ink-3); margin-bottom:15px;">Forecast based on linear regression of the last <?= $days ?> days.</p>
                <div class="chart-canvas-wrap"><canvas id="chartDaily"></canvas></div>
            </div>

            <div class="chart-card chart-wide">
                <h3>Monthly Revenue (12 months)</h3>
                <div class="chart-canvas-wrap"><canvas id="chartMonthly"></canvas></div>
            </div>

            <div class="chart-card">
                <h3>Revenue by Category</h3>
                <div class="chart-canvas-wrap"><canvas id="chartCategory"></canvas></div>
            </div>

            <div class="chart-card">
                <h3>Peak Sales Hours</h3>
                <div class="chart-canvas-wrap"><canvas id="chartHourly"></canvas></div>
            </div>

            <div class="chart-card chart-wide">
                <h3>Top 10 Products by Revenue</h3>
                <div class="chart-canvas-wrap"><canvas id="chartTopProducts"></canvas></div>
            </div>
        </div>

        <!-- AI Insights -->
        <h2 class="section-title">🤖 Prescriptive Actions</h2>
        <p class="section-sub">
            These are not just statistics. Based on your sales data, <strong>here is exactly what you should do next</strong> to increase revenue.
        </p>

        <?php if (empty($recs)): ?>
            <div class="alert info">Not enough data yet. Insights will appear once you have a few days of sales.</div>
        <?php else: ?>
            <div class="rec-grid">
                <?php foreach ($recs as $r): ?>
                    <div class="rec-card rec-<?= $r[0] ?>">
                        <div class="rec-icon"><?= $r[1] ?></div>
                        <div class="rec-body">
                            <div class="rec-title"><?= htmlspecialchars($r[2]) ?></div>
                            <p class="rec-text"><?= htmlspecialchars($r[3]) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Staff performance -->
        <?php if ($staff): ?>
            <h2 class="section-title" style="margin-top:40px;">👥 Staff Performance</h2>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Cashier</th><th>Transactions</th><th>Revenue</th><th>Avg / Sale</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= number_format($s['orders']) ?></td>
                                <td>₱<?= number_format($s['revenue'], 2) ?></td>
                                <td>₱<?= number_format($s['revenue'] / max($s['orders'], 1), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Payment Method Mix -->
        <?php if ($paymentMix): ?>
            <h2 class="section-title" style="margin-top:40px;">💳 Payment Method Mix</h2>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Method</th><th>Orders</th><th>Revenue</th><th>% of Revenue</th></tr></thead>
                    <tbody>
                        <?php
                        $pmLabels = ['cash'=>'Cash','gcash'=>'GCash','maya'=>'Maya','bank'=>'Bank Transfer','card'=>'Card'];
                        $totalRev = array_sum(array_column($paymentMix, 'revenue'));
                        foreach ($paymentMix as $pm):
                            $label = $pmLabels[$pm['method']] ?? ucfirst($pm['method']);
                            $pct   = $totalRev > 0 ? ($pm['revenue'] / $totalRev) * 100 : 0;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($label) ?></td>
                                <td><?= number_format($pm['orders']) ?></td>
                                <td>₱<?= number_format($pm['revenue'], 2) ?></td>
                                <td><?= number_format($pct, 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Dead stock -->
        <?php if ($deadStock): ?>
            <h2 class="section-title" style="margin-top:40px;">💤 Dead Stock (no sales in 30 days)</h2>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Product</th><th>Units Sitting</th><th>Tied Capital</th><th>Action Required</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deadStock as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['product_name']) ?></td>
                                <td><?= (int)$p['stock_quantity'] ?></td>
                                <td style="color:var(--bad-tx); font-weight:bold;">₱<?= number_format($p['tied_capital'], 2) ?></td>
                                <td>
                                    <?php if ($p['stock_quantity'] > 20): ?>
                                        <span class="badge badge-danger">Discount 30% to clear</span>
                                    <?php elseif ($p['stock_quantity'] > 5): ?>
                                        <span class="badge badge-warning">Bundle with bestseller</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Phase out (Do not reorder)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.Chart) return;

        Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
        Chart.defaults.color = '#6b7280';
        Chart.defaults.borderColor = 'rgba(148,163,184,.15)';

        const palette = {
            indigo:  '#6366f1',
            emerald: '#10b981',
            amber:   '#f59e0b',
            sky:     '#0ea5e9',
            violet:  '#8b5cf6',
            rose:    '#f43f5e',
            slate:   '#94a3b8'
        };

        const currency = v => '₱' + Number(v).toLocaleString(undefined, {maximumFractionDigits: 0});

        // --- Daily trend with forecast ---
        new Chart(document.getElementById('chartDaily'), {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Actual Sales',
                        data: <?= json_encode($actualData) ?>,
                        borderColor: palette.indigo,
                        backgroundColor: 'rgba(99,102,241,.12)',
                        borderWidth: 2,
                        tension: 0.2, 
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: palette.indigo
                    },
                    {
                        label: 'Forecast Prediction',
                        data: <?= json_encode($forecastData) ?>,
                        borderColor: palette.violet,
                        borderWidth: 2.5,
                        borderDash: [6, 4], // Dashed line for forecast
                        tension: 0.2,
                        pointRadius: 4, // Visible dots for forecast days
                        pointHoverRadius: 7,
                        pointBackgroundColor: palette.violet,
                        fill: false,
                        spanGaps: true // Ensures the line connects properly from the last actual day
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { 
                    legend: { display: true, position: 'top', labels: { usePointStyle: true, boxWidth: 8 } }, 
                    tooltip: { callbacks: { label: c => c.dataset.label + ': ' + currency(c.parsed.y) } } 
                },
                scales: { 
                    y: { beginAtZero: true, ticks: { callback: currency } }, 
                    x: { grid: { display: false } } 
                }
            }
        });

        new Chart(document.getElementById('chartMonthly'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($monthlyRevenue) ?>,
                    backgroundColor: 'rgba(16,185,129,.7)',
                    hoverBackgroundColor: palette.emerald,
                    borderRadius: 6,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => currency(c.parsed.y) } } },
                scales: { y: { beginAtZero: true, ticks: { callback: currency } }, x: { grid: { display: false } } }
            }
        });

        new Chart(document.getElementById('chartCategory'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    data: <?= json_encode($catData) ?>,
                    backgroundColor: [palette.indigo, palette.emerald, palette.amber, palette.sky, palette.violet, palette.rose, palette.slate],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } },
                    tooltip: { callbacks: { label: c => c.label + ': ' + currency(c.parsed) } }
                }
            }
        });

        new Chart(document.getElementById('chartHourly'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($hourLabels) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($hourData) ?>,
                    backgroundColor: 'rgba(139,92,246,.7)',
                    hoverBackgroundColor: palette.violet,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => currency(c.parsed.y) } } },
                scales: { y: { beginAtZero: true, ticks: { callback: currency } }, x: { grid: { display: false } } }
            }
        });

        new Chart(document.getElementById('chartTopProducts'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($topLabels) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($topData) ?>,
                    backgroundColor: 'rgba(99,102,241,.75)',
                    hoverBackgroundColor: palette.indigo,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => currency(c.parsed.x) } } },
                scales: { x: { beginAtZero: true, ticks: { callback: currency } }, y: { grid: { display: false } } }
            }
        });
    });
    </script>
</body>
</html>