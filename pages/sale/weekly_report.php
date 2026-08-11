<?php
// pages/sale/weekly_report.php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$isOwner = (isset($_SESSION['role']) && $_SESSION['role'] === 'Owner');

function getThisMonday() {
    $today     = new DateTime();
    $dayOfWeek = (int)$today->format('N');
    $today->modify('-' . ($dayOfWeek - 1) . ' days');
    return $today->format('Y-m-d');
}

$weekStart = (!empty($_GET['week_start']) && $_GET['week_start'] !== 'current')
    ? $_GET['week_start']
    : getThisMonday();

$weekEnd  = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$prevWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($weekStart . ' +7 days'));

$wStart = $conn->real_escape_string($weekStart);
$wEnd   = $conn->real_escape_string($weekEnd);

$stats = $conn->query("
    SELECT COUNT(*) AS totalSales,
           COALESCE(SUM(grandTotal), 0) AS totalRevenue,
           COALESCE(SUM(CASE WHEN discountAmount IS NOT NULL THEN discountAmount
                             ELSE subTotal * discountPercent / 100 END), 0) AS totalDiscount
    FROM sale
    WHERE DATE(saleDate) BETWEEN '$wStart' AND '$wEnd'
")->fetch_assoc();

$totalCost = 0;
if ($isOwner) {
    $costRes = $conn->query("
        SELECT COALESCE(SUM(si.quantity * sp.boughtPrice), 0) AS totalCost
        FROM saleitem si
        JOIN sale s       ON si.saleID   = s.saleID
        JOIN sparepart sp ON sp.partName = si.partName
        WHERE DATE(s.saleDate) BETWEEN '$wStart' AND '$wEnd'
    ");
    if ($costRes) $totalCost = (float)$costRes->fetch_assoc()['totalCost'];
}
$netProfit = (float)$stats['totalRevenue'] - $totalCost;

$dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$dailyMap = [];
for ($d = 0; $d < 7; $d++) {
    $date = date('Y-m-d', strtotime("$weekStart +$d days"));
    $dailyMap[$date] = 0;
}
$dailyRes = $conn->query("
    SELECT DATE(saleDate) AS saleDate, COALESCE(SUM(grandTotal), 0) AS dailyRevenue
    FROM sale
    WHERE DATE(saleDate) BETWEEN '$wStart' AND '$wEnd'
    GROUP BY DATE(saleDate)
");
while ($row = $dailyRes->fetch_assoc()) {
    $dailyMap[$row['saleDate']] = (float)$row['dailyRevenue'];
}
$maxRevenue = max(array_values($dailyMap)) ?: 1;

$topPartsRes = $conn->query("
    SELECT si.partName,
           SUM(si.quantity)   AS qtySold,
           SUM(si.itemTotal)  AS revenue,
           COALESCE(SUM(si.quantity * sp.boughtPrice), 0) AS cost,
           SUM(si.itemTotal) - COALESCE(SUM(si.quantity * sp.boughtPrice), 0) AS profit
    FROM saleitem si
    JOIN sale s ON si.saleID = s.saleID
    LEFT JOIN sparepart sp ON sp.partName = si.partName
    WHERE DATE(s.saleDate) BETWEEN '$wStart' AND '$wEnd'
    GROUP BY si.partName
    ORDER BY qtySold DESC
    LIMIT 10
");
$topParts = [];
while ($row = $topPartsRes->fetch_assoc()) { $topParts[] = $row; }

$weekSalesRes = $conn->query("
    SELECT saleID, saleDate, customerName, grandTotal,
           paymentMethod,
           CASE WHEN discountAmount IS NOT NULL THEN discountAmount
                ELSE subTotal * discountPercent / 100 END AS discountAmount
    FROM sale
    WHERE DATE(saleDate) BETWEEN '$wStart' AND '$wEnd'
    ORDER BY saleDate DESC
");
$weekSales = [];
while ($row = $weekSalesRes->fetch_assoc()) { $weekSales[] = $row; }

// Build chart data for JS
$chartLabels  = [];
$chartValues  = [];
$chartDates   = [];
$d = 0;
foreach ($dailyMap as $date => $rev) {
    $chartLabels[] = $dayNames[$d++];
    $chartValues[] = $rev;
    $chartDates[]  = date('d M', strtotime($date));
}

$currentPage = 'sale';
$base        = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Report â€” MotoStock26</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        /* â”€â”€ Remove browser URL/title from print â”€â”€ */
        @page { margin: 14mm 12mm; size: A4; }

        /* â”€â”€ Week nav â”€â”€ */
        .week-nav {
            display: flex; align-items: center; gap: 10px;
            flex-wrap: wrap; margin-bottom: 22px;
        }
        .week-btn {
            padding: 7px 16px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px; font-size: .85rem;
            text-decoration: none; color: #1e3a5f;
            background: #fff; font-weight: 600;
            transition: background .15s, border-color .15s;
        }
        .week-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .week-label {
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: .95rem;
            color: #1e3a5f; padding: 7px 14px;
            background: #eff6ff; border-radius: 8px;
            border: 1.5px solid #bfdbfe;
        }

        /* â”€â”€ Horizontal stat cards â”€â”€ */
        .h-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px; margin-bottom: 24px;
        }
        @media (max-width: 900px) { .h-stats { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 500px) { .h-stats { grid-template-columns: 1fr; } }

        .h-stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .h-stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.35rem; flex-shrink: 0;
        }
        .icon-green  { background: #f0fdf4; }
        .icon-red    { background: #fef2f2; }
        .icon-blue   { background: #eff6ff; }
        .icon-amber  { background: #fffbeb; }
        .h-stat-label {
            font-size: .75rem; font-weight: 700;
            color: #64748b; text-transform: uppercase;
            letter-spacing: .4px; margin-bottom: 4px;
        }
        .h-stat-value {
            font-family: 'Syne', sans-serif;
            font-size: 1.2rem; font-weight: 800; color: #1e293b;
        }
        .h-stat-value.profit-positive { color: #16a34a; }
        .h-stat-value.profit-negative { color: #dc2626; }

        /* â”€â”€ Canvas chart â”€â”€ */
        #revenueChart { width: 100% !important; }

        /* â”€â”€ Print styles â”€â”€ */
        @media print {
            .sidebar, .main-wrap > .topbar, .no-print { display: none !important; }
            .main-wrap { margin-left: 0 !important; }
            .main-content { padding: 0 !important; }
            body { background: #fff !important; }

            /* Force colour */
            .h-stat-card { border: 1.5px solid #cbd5e1 !important; box-shadow: none !important; break-inside: avoid; }
            .h-stats { gap: 10px !important; }

            /* Table borders visible in print */
            .table th, .table td {
                border: 1px solid #94a3b8 !important;
                color: #1e293b !important;
            }
            .table thead th {
                background: #1e3a5f !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table-wrap { border: 1.5px solid #94a3b8 !important; }

            .card { border: 1.5px solid #94a3b8 !important; box-shadow: none !important; break-inside: avoid; }
            .card-header {
                background: #1e3a5f !important; color: #fff !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }

            /* Print report title */
            .print-header { display: block !important; }

            /* Badge colours */
            .badge { border: 1px solid #374151 !important; }

            /* Chart canvas */
            canvas { max-width: 100% !important; }
        }

        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }
        .print-header h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem; color: #1e3a5f; margin: 0 0 4px;
        }
        .print-header p { font-size: .85rem; color: #475569; margin: 0; }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
    <div class="topbar no-print">
        <div>
            <div class="topbar-title">Weekly Profit Report</div>
            <div class="topbar-breadcrumb">Reports â€º Weekly</div>
        </div>
        <button onclick="window.print()" class="btn btn-amber btn-sm">ðŸ–¨ Print Report</button>
    </div>

    <div class="main-content">

        <!-- Print-only header (replaces browser URL) -->
        <div class="print-header">
            <h1>BIMSARA MOTOSTOCK â€” Weekly Report</h1>
            <p>
                <?= date('d M Y', strtotime($weekStart)) ?> â€“ <?= date('d M Y', strtotime($weekEnd)) ?>
                &nbsp;Â·&nbsp; Printed: <?= date('d M Y H:i') ?>
                <?php if ($isOwner): ?>&nbsp;Â·&nbsp; Cashier: <?= htmlspecialchars($_SESSION['username'] ?? '') ?><?php endif; ?>
            </p>
        </div>

        <div class="page-header no-print">
            <div class="page-title">Weekly Report</div>
        </div>

        <!-- Week Navigation -->
        <div class="week-nav no-print">
            <a href="?week_start=<?= $prevWeek ?>" class="week-btn">â† Prev Week</a>
            <span class="week-label">
                ðŸ“… <?= date('d M Y', strtotime($weekStart)) ?> â€” <?= date('d M Y', strtotime($weekEnd)) ?>
            </span>
            <a href="?week_start=<?= $nextWeek ?>" class="week-btn">Next Week â†’</a>
            <a href="?week_start=current" class="week-btn" style="background:#1e3a5f;color:#fff;border-color:#1e3a5f">This Week</a>
        </div>

        <!-- Stat Cards -->
        <div class="h-stats">
            <div class="h-stat-card">
                <div class="h-stat-icon icon-green">ðŸ’°</div>
                <div>
                    <div class="h-stat-label">Total Revenue</div>
                    <div class="h-stat-value">Rs. <?= number_format((float)$stats['totalRevenue'], 2) ?></div>
                </div>
            </div>

            <?php if ($isOwner): ?>
            <div class="h-stat-card">
                <div class="h-stat-icon icon-red">ðŸ·ï¸</div>
                <div>
                    <div class="h-stat-label">Total Cost</div>
                    <div class="h-stat-value">Rs. <?= number_format($totalCost, 2) ?></div>
                </div>
            </div>
            <div class="h-stat-card">
                <div class="h-stat-icon" style="background:<?= $netProfit >= 0 ? '#f0fdf4' : '#fef2f2' ?>">
                    <?= $netProfit >= 0 ? 'ðŸ“ˆ' : 'ðŸ“‰' ?>
                </div>
                <div>
                    <div class="h-stat-label">Net Profit</div>
                    <div class="h-stat-value <?= $netProfit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                        Rs. <?= number_format($netProfit, 2) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="h-stat-card">
                <div class="h-stat-icon icon-blue">ðŸ§¾</div>
                <div>
                    <div class="h-stat-label">No. of Sales</div>
                    <div class="h-stat-value"><?= $stats['totalSales'] ?></div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="form-grid-2 mb-3">

            <!-- Daily Revenue Bar Chart -->
            <div class="card">
                <div class="card-header">ðŸ“Š Daily Revenue</div>
                <div class="card-body" style="padding:16px 16px 8px">
                    <canvas id="revenueChart" height="220"></canvas>
                </div>
            </div>

            <!-- Top Selling Parts -->
            <div class="card">
                <div class="card-header">ðŸ† Top Selling Parts</div>
                <div class="card-body" style="padding:0">
                    <?php if (empty($topParts)): ?>
                        <p style="color:var(--muted);text-align:center;padding:24px;font-size:.875rem">No sales this week.</p>
                    <?php else: ?>
                    <div class="table-wrap" style="border-radius:0;border:none;box-shadow:none">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Part Name</th><th>Qty</th><th>Revenue</th>
                                    <?php if ($isOwner): ?><th>Profit</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($topParts as $p): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['partName']) ?></strong></td>
                                    <td><?= $p['qtySold'] ?></td>
                                    <td>Rs. <?= number_format((float)$p['revenue'], 2) ?></td>
                                    <?php if ($isOwner): ?>
                                    <td style="color:#16a34a;font-weight:700">
                                        Rs. <?= number_format((float)$p['profit'], 2) ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- All Sales This Week -->
        <div class="card">
            <div class="card-header">ðŸ—“ï¸ Sales This Week</div>
            <div class="card-body" style="padding:0">
                <?php if (empty($weekSales)): ?>
                    <p style="color:var(--muted);text-align:center;padding:24px;font-size:.875rem">No sales recorded this week.</p>
                <?php else: ?>
                <div class="table-wrap" style="border-radius:0;border:none;box-shadow:none">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sale #</th><th>Date</th><th>Customer</th>
                                <th>Total</th><th>Discount</th><th>Payment</th>
                                <th class="no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($weekSales as $ws): ?>
                            <tr>
                                <td style="color:#64748b;font-weight:600">#<?= $ws['saleID'] ?></td>
                                <td><?= date('d M Y', strtotime($ws['saleDate'])) ?></td>
                                <td><?= htmlspecialchars($ws['customerName'] ?: 'Walk-in') ?></td>
                                <td><strong>Rs. <?= number_format((float)$ws['grandTotal'], 2) ?></strong></td>
                                <td>
                                    <?php if ($ws['discountAmount'] > 0): ?>
                                        <span class="badge badge-warning">Rs. <?= number_format((float)$ws['discountAmount'], 2) ?></span>
                                    <?php else: ?>â€”<?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $ws['paymentMethod'] === 'Cash' ? 'badge-success' : 'badge-info' ?>">
                                        <?= htmlspecialchars($ws['paymentMethod']) ?>
                                    </span>
                                </td>
                                <td class="no-print">
                                    <a href="view_invoice.php?saleID=<?= $ws['saleID'] ?>" class="btn btn-sm btn-outline">ðŸ‘ View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const labels  = <?= json_encode($chartLabels) ?>;
const values  = <?= json_encode($chartValues) ?>;
const dates   = <?= json_encode($chartDates) ?>;
const maxVal  = <?= $maxRevenue ?>;

const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Revenue (Rs.)',
            data: values,
            backgroundColor: values.map(v =>
                v === Math.max(...values) && v > 0 ? '#f59e0b' : '#bfdbfe'
            ),
            borderColor: values.map(v =>
                v === Math.max(...values) && v > 0 ? '#d97706' : '#93c5fd'
            ),
            borderWidth: 1.5,
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: (items) => dates[items[0].dataIndex],
                    label: (item) => ' Rs. ' + item.raw.toLocaleString('en-LK', {minimumFractionDigits:2})
                },
                backgroundColor: '#1e3a5f',
                titleColor: '#fbbf24',
                bodyColor: '#e2e8f0',
                padding: 10,
                cornerRadius: 8,
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    color: '#475569',
                    font: { family: 'DM Sans', size: 12, weight: '600' }
                }
            },
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9', lineWidth: 1 },
                border: { dash: [4,4] },
                ticks: {
                    color: '#64748b',
                    font: { family: 'DM Sans', size: 11 },
                    callback: v => v === 0 ? '0' : 'Rs.' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v)
                }
            }
        }
    }
});
</script>
</body>
</html>
