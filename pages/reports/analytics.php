<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }
$currentPage = 'dashboard'; $base = '../../';

$today     = date('Y-m-d');
$thisMonth = date('Y-m');
$thisYear  = date('Y');

if ($_SESSION['role'] !== 'Owner') {
    header("Location: ../../pages/dashboard.php"); exit;
}

// ── Date range filter ─────────────────────────────────────────────────────────
$rangePreset = $_GET['range'] ?? '30';
switch ($rangePreset) {
    case '7':   $fromDate = date('Y-m-d', strtotime('-7 days'));  $rangeLabel = 'Last 7 Days'; break;
    case '90':  $fromDate = date('Y-m-d', strtotime('-90 days')); $rangeLabel = 'Last 90 Days'; break;
    case '365': $fromDate = date('Y-m-d', strtotime('-1 year'));  $rangeLabel = 'Last 12 Months'; break;
    default:    $fromDate = date('Y-m-d', strtotime('-30 days')); $rangeLabel = 'Last 30 Days'; break;
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — BUSINESS OVERVIEW KPIs
// ══════════════════════════════════════════════════════════════════════════════

// Revenue
$revAll   = $conn->query("SELECT IFNULL(SUM(grandTotal),0) as v, COUNT(*) as c FROM Sale")->fetch_assoc();
$revMonth = $conn->query("SELECT IFNULL(SUM(grandTotal),0) as v, COUNT(*) as c FROM Sale WHERE DATE_FORMAT(saleDate,'%Y-%m')='$thisMonth'")->fetch_assoc();
$revToday = $conn->query("SELECT IFNULL(SUM(grandTotal),0) as v FROM Sale WHERE DATE(saleDate)=CURDATE()")->fetch_assoc();
$revPrevM = $conn->query("SELECT IFNULL(SUM(grandTotal),0) as v FROM Sale WHERE DATE_FORMAT(saleDate,'%Y-%m')=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m')")->fetch_assoc();

// Revenue growth
$growthPct = $revPrevM['v'] > 0 ? round((($revMonth['v'] - $revPrevM['v']) / $revPrevM['v']) * 100, 1) : 0;

// Stock value
$stockVal  = $conn->query("SELECT IFNULL(SUM(sellingPrice*currentQuantity),0) as v, COUNT(*) as c FROM SparePart")->fetch_assoc();
$lowStock  = $conn->query("SELECT COUNT(*) as c FROM SparePart WHERE currentQuantity < minQuantity")->fetch_assoc();

// Customers
$custTotal = $conn->query("SELECT COUNT(*) as c FROM Customer")->fetch_assoc();
$custOverdue = $conn->query("SELECT COUNT(*) as c FROM Customer WHERE nextServiceDue < '$today' AND nextServiceDue IS NOT NULL")->fetch_assoc();
$custNew   = $conn->query("SELECT COUNT(*) as c FROM Customer WHERE DATE_FORMAT(lastServiceDate,'%Y-%m')='$thisMonth'")->fetch_assoc();

// Repair jobs
$jobsAll    = $conn->query("SELECT COUNT(*) as c FROM servicejob")->fetch_assoc();
$jobsPend   = $conn->query("SELECT COUNT(*) as c FROM servicejob WHERE status='Pending'")->fetch_assoc();
$jobsRepair = $conn->query("SELECT COUNT(*) as c FROM servicejob WHERE status='Repairing'")->fetch_assoc();
$jobsDone   = $conn->query("SELECT COUNT(*) as c FROM servicejob WHERE status='Finished'")->fetch_assoc();

// Purchase
$poTotal = $conn->query("SELECT IFNULL(SUM(totalCost),0) as v, COUNT(*) as c FROM PurchaseOrder")->fetch_assoc();
$poMonth = $conn->query("SELECT IFNULL(SUM(totalCost),0) as v FROM PurchaseOrder WHERE DATE_FORMAT(orderDate,'%Y-%m')='$thisMonth'")->fetch_assoc();

// Profit estimate (sales revenue - purchase cost this month)
$profitEst = $revMonth['v'] - $poMonth['v'];

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — SALES TREND (Monthly, last 12 months)
// ══════════════════════════════════════════════════════════════════════════════
$salesTrendRes = $conn->query("
    SELECT DATE_FORMAT(saleDate,'%b %Y') AS mo,
           DATE_FORMAT(saleDate,'%Y-%m') AS ym,
           COUNT(*) AS cnt,
           ROUND(SUM(grandTotal),2) AS rev
    FROM Sale
    WHERE saleDate >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(saleDate,'%Y-%m'), DATE_FORMAT(saleDate,'%b %Y')
    ORDER BY ym
");
$salesMonths=[]; $salesCounts=[]; $salesRevs=[];
while($r=$salesTrendRes->fetch_assoc()){
    $salesMonths[] = $r['mo'];
    $salesCounts[] = (int)$r['cnt'];
    $salesRevs[]   = (float)$r['rev'];
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — TOP SELLING PARTS (by sale item qty)
// ══════════════════════════════════════════════════════════════════════════════
$topPartsRes = $conn->query("
    SELECT si.partName, SUM(si.quantity) AS totalQty, SUM(si.itemTotal) AS totalRev
    FROM SaleItem si
    GROUP BY si.partName
    ORDER BY totalQty DESC LIMIT 10
");
$topPartNames=[]; $topPartQtys=[]; $topPartRevs=[];
if ($topPartsRes) while($r=$topPartsRes->fetch_assoc()){
    $topPartNames[] = $r['partName'];
    $topPartQtys[]  = (int)$r['totalQty'];
    $topPartRevs[]  = round((float)$r['totalRev'], 2);
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — PAYMENT METHOD SPLIT
// ══════════════════════════════════════════════════════════════════════════════
$payRes = $conn->query("SELECT paymentMethod, COUNT(*) as cnt, ROUND(SUM(grandTotal),2) as rev FROM Sale GROUP BY paymentMethod");
$payLabels=[]; $payCounts=[]; $payRevs=[];
while($r=$payRes->fetch_assoc()){ $payLabels[]=$r['paymentMethod']; $payCounts[]=(int)$r['cnt']; $payRevs[]=(float)$r['rev']; }

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — INVENTORY HEALTH
// ══════════════════════════════════════════════════════════════════════════════
$invCatRes = $conn->query("SELECT category, COUNT(*) as cnt, SUM(currentQuantity) as totalQty, ROUND(SUM(sellingPrice*currentQuantity),2) as val FROM SparePart GROUP BY category ORDER BY val DESC");
$invCats=[]; $invCatCounts=[]; $invCatVals=[];
while($r=$invCatRes->fetch_assoc()){ $invCats[]=$r['category']; $invCatCounts[]=(int)$r['cnt']; $invCatVals[]=(float)$r['val']; }

// Low stock items detail
$lowStockItems = $conn->query("SELECT partName, brandName, category, currentQuantity, minQuantity, sellingPrice FROM SparePart WHERE currentQuantity < minQuantity ORDER BY currentQuantity ASC LIMIT 10");

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 6 — PURCHASE SPEND BY SUPPLIER
// ══════════════════════════════════════════════════════════════════════════════
$supRes = $conn->query("
    SELECT s.supplierName, COUNT(po.poID) as orders, ROUND(SUM(po.totalCost),2) as spend
    FROM PurchaseOrder po
    LEFT JOIN Supplier s ON po.supplierID=s.supplierID
    GROUP BY po.supplierID
    ORDER BY spend DESC
    LIMIT 8
");
$supNames=[]; $supOrders=[]; $supSpend=[];
while($r=$supRes->fetch_assoc()){ $supNames[]=$r['supplierName']??'Unknown'; $supOrders[]=(int)$r['orders']; $supSpend[]=(float)$r['spend']; }

// Monthly purchase spend last 12 months
$poTrendRes = $conn->query("
    SELECT DATE_FORMAT(orderDate,'%b %Y') AS mo, DATE_FORMAT(orderDate,'%Y-%m') AS ym, ROUND(SUM(totalCost),2) AS spend
    FROM PurchaseOrder
    WHERE orderDate >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(orderDate,'%Y-%m'), DATE_FORMAT(orderDate,'%b %Y')
    ORDER BY ym
");
$poMonths=[]; $poSpends=[];
while($r=$poTrendRes->fetch_assoc()){ $poMonths[]=$r['mo']; $poSpends[]=(float)$r['spend']; }

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 7 — REPAIR JOB TRENDS
// ══════════════════════════════════════════════════════════════════════════════
$repairTrendRes = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS mo, DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS cnt
    FROM servicejob
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m'), DATE_FORMAT(created_at,'%b %Y')
    ORDER BY ym
");
$repairMonths=[]; $repairCounts=[];
while($r=$repairTrendRes->fetch_assoc()){ $repairMonths[]=$r['mo']; $repairCounts[]=(int)$r['cnt']; }

$repairStatusRes = $conn->query("SELECT status, COUNT(*) as cnt FROM servicejob GROUP BY status");
$repairStatLabels=[]; $repairStatCounts=[];
while($r=$repairStatusRes->fetch_assoc()){ $repairStatLabels[]=$r['status']; $repairStatCounts[]=(int)$r['cnt']; }

// Warranty breakdown
$warrantyRes = $conn->query("SELECT isWarranty, COUNT(*) as cnt FROM servicejob GROUP BY isWarranty");
$warrantyYes=0; $warrantyNo=0;
while($r=$warrantyRes->fetch_assoc()){ if($r['isWarranty']) $warrantyYes=$r['cnt']; else $warrantyNo=$r['cnt']; }

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 8 — CUSTOMER SERVICE STATUS
// ══════════════════════════════════════════════════════════════════════════════
$custServiceRes = $conn->query("
    SELECT DATE_FORMAT(lastServiceDate,'%b %Y') AS mo, DATE_FORMAT(lastServiceDate,'%Y-%m') AS ym, COUNT(*) AS cnt
    FROM Customer
    WHERE lastServiceDate IS NOT NULL AND lastServiceDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(lastServiceDate,'%Y-%m'), DATE_FORMAT(lastServiceDate,'%b %Y')
    ORDER BY ym
");
$custMonths=[]; $custCounts=[];
while($r=$custServiceRes->fetch_assoc()){ $custMonths[]=$r['mo']; $custCounts[]=(int)$r['cnt']; }

$upcoming7 = $conn->query("SELECT COUNT(*) as c FROM Customer WHERE nextServiceDue BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)")->fetch_assoc()['c']??0;
$noDate    = $conn->query("SELECT COUNT(*) as c FROM Customer WHERE nextServiceDue IS NULL")->fetch_assoc()['c']??0;
$upToDate  = max(0, $custTotal['c'] - $custOverdue['c'] - $upcoming7 - $noDate);

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 9 — REVENUE vs SPEND (12 months combined)
// ══════════════════════════════════════════════════════════════════════════════
// Build merged month list
$allMonthsMap = [];
foreach ($salesMonths as $i => $m) { $allMonthsMap[$m]['rev'] = $salesRevs[$i]; }
foreach ($poMonths as $i => $m) { $allMonthsMap[$m]['spend'] = $poSpends[$i]; }
ksort($allMonthsMap);
$combinedMonths=[]; $combinedRevs=[]; $combinedSpend=[]; $combinedProfit=[];
foreach ($allMonthsMap as $mo => $d) {
    $r = $d['rev']??0; $s = $d['spend']??0;
    $combinedMonths[]  = $mo;
    $combinedRevs[]    = $r;
    $combinedSpend[]   = $s;
    $combinedProfit[]  = round($r - $s, 2);
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 10 — RECENT ACTIVITY (last 5 each)
// ══════════════════════════════════════════════════════════════════════════════
$recentSales = $conn->query("
    SELECT s.saleID, s.saleDate, s.grandTotal, s.paymentMethod,
           COALESCE(c.name, 'Walk-in') AS customerName
    FROM Sale s
    LEFT JOIN Customer c ON s.customerID = c.customerID
    ORDER BY s.saleID DESC LIMIT 5
");
$recentRepairs = $conn->query("SELECT jobID, bikeNo, problemDescription, status, created_at FROM servicejob ORDER BY jobID DESC LIMIT 5");
$recentPO      = $conn->query("SELECT po.poID, po.orderDate, po.totalCost, po.status, s.supplierName FROM PurchaseOrder po LEFT JOIN Supplier s ON po.supplierID=s.supplierID ORDER BY po.poID DESC LIMIT 5");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner Analytics — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    /* ── Layout ── */
    .analytics-grid   { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:22px; }
    .analytics-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:22px; }
    .full-width       { grid-column: 1/-1; }
    @media(max-width:900px){ .analytics-grid,.analytics-grid-3{ grid-template-columns:1fr; } }

    /* ── Section heading ── */
    .section-title {
      font-family:'Syne',sans-serif; font-size:.7rem; font-weight:700;
      letter-spacing:2px; text-transform:uppercase;
      color:var(--amber); margin:28px 0 14px; display:flex; align-items:center; gap:10px;
    }
    .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

    /* ── Chart card ── */
    .chart-card {
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); padding:20px 22px;
    }
    .chart-card h3 {
      font-family:'Syne',sans-serif; font-size:.85rem; font-weight:700;
      color:var(--text); margin:0 0 4px;
    }
    .chart-card .sub { font-size:.75rem; color:var(--muted); margin-bottom:16px; }

    /* ── KPI row ── */
    .kpi-grid  { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
    .kpi-grid-3{ display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
    @media(max-width:900px){ .kpi-grid,.kpi-grid-3{ grid-template-columns:1fr 1fr; } }

    .kpi-card {
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); padding:18px 20px;
      position:relative; overflow:hidden;
    }
    .kpi-card::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:var(--kpi-color, var(--amber));
    }
    .kpi-label   { font-size:.72rem; color:var(--muted); font-weight:600; letter-spacing:.05em; text-transform:uppercase; margin-bottom:8px; }
    .kpi-value   { font-family:'Syne',sans-serif; font-size:1.65rem; font-weight:800; color:#f5f9ff; line-height:1; }
    .kpi-sub     { font-size:.73rem; color:var(--muted); margin-top:6px; }
    .kpi-badge   { display:inline-flex; align-items:center; gap:3px; font-size:.72rem; font-weight:700; padding:2px 7px; border-radius:99px; margin-top:6px; }
    .kpi-up      { background:rgba(45,197,142,.15); color:#6ee7c0; }
    .kpi-down    { background:rgba(248,113,113,.15); color:#ffb0b0; }
    .kpi-neutral { background:rgba(148,163,184,.12); color:#94a3b8; }

    /* ── Insight banner ── */
    .insight-bar {
      background:linear-gradient(135deg,rgba(245,158,11,.1) 0%,rgba(59,130,246,.08) 100%);
      border:1px solid rgba(245,158,11,.25);
      border-radius:10px; padding:14px 18px; margin-bottom:22px;
      display:flex; align-items:flex-start; gap:12px; font-size:.83rem;
    }
    .insight-bar .ins-icon { font-size:1.2rem; flex-shrink:0; }
    .insight-bar .ins-body strong { color:#ffd28a; display:block; font-size:.86rem; margin-bottom:2px; }
    .insight-bar .ins-body span   { color:var(--muted); }

    /* ── Table ── */
    .mini-table { width:100%; border-collapse:collapse; font-size:.82rem; }
    .mini-table th { color:rgba(255,255,255,.5); font-size:.68rem; font-weight:600; letter-spacing:.9px; text-transform:uppercase; padding:8px 12px; border-bottom:1px solid var(--border); text-align:left; }
    .mini-table td { padding:9px 12px; border-bottom:1px solid rgba(52,66,85,.5); color:var(--text); vertical-align:middle; }
    .mini-table tr:last-child td { border-bottom:none; }
    .mini-table tr:hover td { background:rgba(255,255,255,.03); }

    /* ── Range pills ── */
    .range-pills { display:flex; gap:6px; }
    .range-pill  {
      padding:5px 12px; border-radius:99px; font-size:.75rem; font-weight:600;
      border:1px solid var(--border); color:var(--muted); text-decoration:none;
      transition:all .15s;
    }
    .range-pill:hover { border-color:var(--amber); color:var(--amber); }
    .range-pill.active { background:rgba(245,158,11,.18); border-color:var(--amber); color:var(--amber); }

    /* ── Progress bars ── */
    .prog-bar { height:6px; background:#1e2d42; border-radius:99px; overflow:hidden; margin-top:4px; }
    .prog-fill { height:100%; border-radius:99px; transition:width .6s ease; }

    @media print {
      .sidebar,.topbar,.no-print{ display:none!important }
      .main-wrap{ margin-left:0!important }
      .main-content{ padding:0!important }
    }
  </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">📊 Owner Analytics</div>
      <div class="topbar-breadcrumb">Full business intelligence dashboard — <?= date('d M Y') ?></div>
    </div>
    <div class="d-flex gap-2 no-print">
      <div class="range-pills">
        <a href="?range=7"   class="range-pill <?= $rangePreset=='7'  ?'active':'' ?>">7D</a>
        <a href="?range=30"  class="range-pill <?= $rangePreset=='30' ?'active':'' ?>">30D</a>
        <a href="?range=90"  class="range-pill <?= $rangePreset=='90' ?'active':'' ?>">90D</a>
        <a href="?range=365" class="range-pill <?= $rangePreset=='365'?'active':'' ?>">1Y</a>
      </div>
      <a href="javascript:window.print()" class="btn btn-sm btn-outline">🖨 Print</a>
    </div>
  </div>

  <div class="main-content">
    <div style="font-size:.75rem;color:var(--muted);margin-bottom:18px;">
      Report generated: <?= date('d M Y, H:i') ?> &nbsp;·&nbsp; By: <?= htmlspecialchars($_SESSION['username']) ?>
      &nbsp;·&nbsp; Showing: <strong style="color:var(--amber)"><?= $rangeLabel ?></strong>
    </div>

    <!-- ── SMART INSIGHTS BANNER ────────────────────────────────────────── -->
    <?php
      $insights = [];
      if ($growthPct > 0)  $insights[] = ['🚀','Revenue is growing!', "Sales this month are up <strong style='color:#6ee7c0'>{$growthPct}%</strong> vs last month (Rs.".number_format($revMonth['v'],0)." vs Rs.".number_format($revPrevM['v'],0).")."];
      if ($growthPct < 0)  $insights[] = ['📉','Revenue dip detected', "Sales this month are down <strong style='color:#ffb0b0'>".abs($growthPct)."%</strong> vs last month. Consider promotions or follow-ups."];
      if ($lowStock['c'] > 0)  $insights[] = ['⚠️','Low stock alert', "<strong style='color:#ffd28a'>{$lowStock['c']} parts</strong> are below minimum quantity. Reorder soon to avoid stockouts."];
      if ($custOverdue['c'] > 0) $insights[] = ['🔧','Service reminders needed', "<strong style='color:#ffd28a'>{$custOverdue['c']} customers</strong> are overdue for service. Contact them to book appointments."];
      if ($jobsPend['c'] > 0)  $insights[] = ['🕐','Pending repairs', "<strong style='color:#bfdaff'>{$jobsPend['c']} repair jobs</strong> are still pending action. Assign a technician."];
      if ($profitEst > 0)  $insights[] = ['💰','Estimated margin this month', "Revenue (Rs.".number_format($revMonth['v'],0).") minus purchases (Rs.".number_format($poMonth['v'],0).") = <strong style='color:#6ee7c0'>Rs.".number_format($profitEst,0)." estimated gross</strong>."];
    ?>
    <?php if (!empty($insights)): ?>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:22px;">
      <?php foreach(array_slice($insights,0,3) as $ins): ?>
      <div class="insight-bar">
        <span class="ins-icon"><?= $ins[0] ?></span>
        <div class="ins-body"><strong><?= $ins[1] ?></strong><span><?= $ins[2] ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 1 — REVENUE KPIs -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="section-title">💰 Revenue Overview</div>

    <div class="kpi-grid">
      <div class="kpi-card" style="--kpi-color:#f59e0b">
        <div class="kpi-label">All-time Revenue</div>
        <div class="kpi-value">Rs.<?= number_format($revAll['v'],0) ?></div>
        <div class="kpi-sub"><?= $revAll['c'] ?> total sales</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#3b82f6">
        <div class="kpi-label">This Month</div>
        <div class="kpi-value">Rs.<?= number_format($revMonth['v'],0) ?></div>
        <?php if ($growthPct != 0): ?>
        <span class="kpi-badge <?= $growthPct>0?'kpi-up':'kpi-down' ?>"><?= $growthPct>0?'↑':'↓' ?> <?= abs($growthPct) ?>% vs last month</span>
        <?php endif; ?>
      </div>
      <div class="kpi-card" style="--kpi-color:#2dc58e">
        <div class="kpi-label">Today's Revenue</div>
        <div class="kpi-value">Rs.<?= number_format($revToday['v'],0) ?></div>
        <div class="kpi-sub">As of <?= date('H:i') ?></div>
      </div>
      <div class="kpi-card" style="--kpi-color:#a78bfa">
        <div class="kpi-label">Est. Gross Profit</div>
        <div class="kpi-value">Rs.<?= number_format(max(0,$profitEst),0) ?></div>
        <div class="kpi-sub">Revenue − purchases this month</div>
      </div>
    </div>

    <!-- Revenue + Spend Line Chart -->
    <div class="chart-card" style="margin-bottom:22px;">
      <h3>Revenue vs Purchase Spend — Last 12 Months</h3>
      <div class="sub">Monthly comparison of sales income against supplier purchases</div>
      <canvas id="revSpendChart" height="110"></canvas>
    </div>

    <!-- Daily sales this month -->
    <?php
    $dailyRes = $conn->query("SELECT DATE(saleDate) as d, COUNT(*) as cnt, ROUND(SUM(grandTotal),2) as rev FROM Sale WHERE DATE_FORMAT(saleDate,'%Y-%m')='$thisMonth' GROUP BY DATE(saleDate) ORDER BY d");
    $dailyDates=[]; $dailyCounts=[]; $dailyRevs=[];
    while($r=$dailyRes->fetch_assoc()){ $dailyDates[]=date('d',strtotime($r['d'])); $dailyCounts[]=(int)$r['cnt']; $dailyRevs[]=(float)$r['rev']; }
    ?>
    <div class="analytics-grid">
      <div class="chart-card">
        <h3>Daily Sales — <?= date('F Y') ?></h3>
        <div class="sub">Transaction count per day this month</div>
        <canvas id="dailySalesChart" height="170"></canvas>
      </div>
      <div class="chart-card">
        <h3>Payment Method Breakdown</h3>
        <div class="sub">How customers pay — split by transaction count</div>
        <canvas id="payChart" height="170"></canvas>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 2 — SALES TREND -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="section-title">📈 Sales Trend</div>

    <div class="chart-card" style="margin-bottom:22px;">
      <h3>Monthly Sales Volume — Last 12 Months</h3>
      <div class="sub">Number of transactions recorded per month</div>
      <canvas id="salesTrendChart" height="110"></canvas>
    </div>

    <!-- Top parts -->
    <div class="analytics-grid">
      <div class="chart-card">
        <h3>Top 10 Parts by Units Sold</h3>
        <div class="sub">Best-performing spare parts by quantity</div>
        <canvas id="topPartsChart" height="260"></canvas>
      </div>
      <div class="chart-card">
        <h3>Top 10 Parts by Revenue</h3>
        <div class="sub">Highest-earning spare parts (Rs.)</div>
        <canvas id="topPartsRevChart" height="260"></canvas>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 3 — INVENTORY HEALTH -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="section-title">📦 Inventory Health</div>

    <div class="kpi-grid">
      <div class="kpi-card" style="--kpi-color:#3b82f6">
        <div class="kpi-label">Total Parts</div>
        <div class="kpi-value"><?= $stockVal['c'] ?></div>
        <div class="kpi-sub">unique SKUs in stock</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#f59e0b">
        <div class="kpi-label">Total Stock Value</div>
        <div class="kpi-value">Rs.<?= number_format($stockVal['v'],0) ?></div>
        <div class="kpi-sub">at selling price</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#f87171">
        <div class="kpi-label">Low Stock Alerts</div>
        <div class="kpi-value"><?= $lowStock['c'] ?></div>
        <div class="kpi-sub">parts below minimum qty</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#2dc58e">
        <div class="kpi-label">Healthy Stock</div>
        <div class="kpi-value"><?= $stockVal['c'] - $lowStock['c'] ?></div>
        <div class="kpi-sub">parts at or above minimum</div>
      </div>
    </div>

    <div class="analytics-grid">
      <div class="chart-card">
        <h3>Stock Value by Category</h3>
        <div class="sub">Rs. value distribution across part categories</div>
        <canvas id="invValChart" height="200"></canvas>
      </div>
      <div class="chart-card">
        <h3>Parts Count by Category</h3>
        <div class="sub">Number of distinct parts per category</div>
        <canvas id="invCatChart" height="200"></canvas>
      </div>
    </div>

    <!-- Low stock detail table -->
    <?php if ($lowStock['c'] > 0): ?>
    <div class="chart-card" style="margin-bottom:22px;">
      <h3 style="margin-bottom:14px;">⚠ Low Stock Parts — Reorder Required</h3>
      <table class="mini-table">
        <thead><tr><th>Part Name</th><th>Brand</th><th>Category</th><th>Current Qty</th><th>Min Qty</th><th>Selling Price</th><th>Urgency</th></tr></thead>
        <tbody>
        <?php $lowStockItems->data_seek(0); while($r=$lowStockItems->fetch_assoc()):
          $pct = $r['minQuantity']>0 ? round(($r['currentQuantity']/$r['minQuantity'])*100) : 0;
          $urg = $r['currentQuantity']==0 ? 'Out of Stock' : ($pct<50?'Critical':'Low');
          $urgColor = $r['currentQuantity']==0 ? '#f87171' : ($pct<50?'#fbbf24':'#ffd28a');
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['partName']) ?></strong></td>
          <td><?= htmlspecialchars($r['brandName']) ?></td>
          <td><span class="badge badge-dark"><?= htmlspecialchars($r['category']) ?></span></td>
          <td><strong style="color:#f87171"><?= $r['currentQuantity'] ?></strong></td>
          <td><?= $r['minQuantity'] ?></td>
          <td>Rs.<?= number_format($r['sellingPrice'],2) ?></td>
          <td>
            <span style="font-size:.73rem;font-weight:700;color:<?= $urgColor ?>"><?= $urg ?></span>
            <div class="prog-bar"><div class="prog-fill" style="width:<?= min(100,$pct) ?>%;background:<?= $urgColor ?>"></div></div>
          </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 4 — REPAIR JOBS -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="section-title">🔧 Repair Jobs</div>

    <div class="kpi-grid">
      <div class="kpi-card" style="--kpi-color:#f59e0b">
        <div class="kpi-label">Total Jobs</div>
        <div class="kpi-value"><?= $jobsAll['c'] ?></div>
        <div class="kpi-sub">all time</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#2dc58e">
        <div class="kpi-label">Completed</div>
        <div class="kpi-value"><?= $jobsDone['c'] ?></div>
        <div class="kpi-sub"><?= $jobsAll['c']>0?round($jobsDone['c']/$jobsAll['c']*100).'%':0 ?> completion rate</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#f59e0b">
        <div class="kpi-label">In Progress</div>
        <div class="kpi-value"><?= $jobsRepair['c'] ?></div>
        <div class="kpi-sub">being repaired now</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#f87171">
        <div class="kpi-label">Pending</div>
        <div class="kpi-value"><?= $jobsPend['c'] ?></div>
        <div class="kpi-sub">awaiting assignment</div>
      </div>
    </div>

    <div class="analytics-grid">
      <div class="chart-card">
        <h3>Jobs per Month — Last 6 Months</h3>
        <div class="sub">Repair volume trends over time</div>
        <canvas id="repairTrendChart" height="180"></canvas>
      </div>
      <div class="chart-card">
        <h3>Job Status Breakdown</h3>
        <div class="sub">Current distribution of all repair jobs</div>
        <canvas id="repairStatusChart" height="180"></canvas>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 5 — CUSTOMERS -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="section-title">👥 Customer Analytics</div>

    <div class="kpi-grid">
      <div class="kpi-card" style="--kpi-color:#3b82f6">
        <div class="kpi-label">Total Customers</div>
        <div class="kpi-value"><?= $custTotal['c'] ?></div>
        <div class="kpi-sub">registered</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#f87171">
        <div class="kpi-label">Overdue Service</div>
        <div class="kpi-value"><?= $custOverdue['c'] ?></div>
        <div class="kpi-sub">past due date</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#f59e0b">
        <div class="kpi-label">Due This Week</div>
        <div class="kpi-value"><?= $upcoming7 ?></div>
        <div class="kpi-sub">service within 7 days</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#2dc58e">
        <div class="kpi-label">Up to Date</div>
        <div class="kpi-value"><?= $upToDate ?></div>
        <div class="kpi-sub">no action needed</div>
      </div>
    </div>

    <div class="analytics-grid">
      <div class="chart-card">
        <h3>Service Activity — Last 6 Months</h3>
        <div class="sub">Number of customers serviced per month</div>
        <canvas id="custServiceChart" height="180"></canvas>
      </div>
      <div class="chart-card">
        <h3>Service Due Status</h3>
        <div class="sub">Current overview of customer service health</div>
        <canvas id="custStatusChart" height="180"></canvas>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 6 — PURCHASING -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="section-title">🛒 Purchase Orders</div>

    <div class="kpi-grid-3">
      <div class="kpi-card" style="--kpi-color:#f59e0b">
        <div class="kpi-label">Total Spend</div>
        <div class="kpi-value">Rs.<?= number_format($poTotal['v'],0) ?></div>
        <div class="kpi-sub"><?= $poTotal['c'] ?> purchase orders</div>
      </div>
      <div class="kpi-card" style="--kpi-color:#3b82f6">
        <div class="kpi-label">This Month's Spend</div>
        <div class="kpi-value">Rs.<?= number_format($poMonth['v'],0) ?></div>
        <div class="kpi-sub"><?= date('F Y') ?></div>
      </div>
      <div class="kpi-card" style="--kpi-color:#a78bfa">
        <div class="kpi-label">Suppliers Used</div>
        <div class="kpi-value"><?= count($supNames) ?></div>
        <div class="kpi-sub">active suppliers</div>
      </div>
    </div>

    <div class="analytics-grid">
      <div class="chart-card">
        <h3>Spend by Supplier</h3>
        <div class="sub">Top 8 suppliers by total spend (Rs.)</div>
        <canvas id="supChart" height="230"></canvas>
      </div>
      <div class="chart-card">
        <h3>Monthly Purchase Spend</h3>
        <div class="sub">Supplier spend trend — last 12 months</div>
        <canvas id="poTrendChart" height="230"></canvas>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 7 — RECENT ACTIVITY -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="section-title">🕐 Recent Activity</div>

    <div class="analytics-grid-3">
      <!-- Recent Sales -->
      <div class="chart-card">
        <h3 style="margin-bottom:12px;">Latest Sales</h3>
        <table class="mini-table">
          <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Method</th></tr></thead>
          <tbody>
          <?php while($r=$recentSales->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--muted)">#<?= $r['saleID'] ?></td>
            <td><?= $r['customerName']?htmlspecialchars($r['customerName']):'Walk-in' ?></td>
            <td><strong style="color:#f59e0b">Rs.<?= number_format($r['grandTotal'],2) ?></strong></td>
            <td><span class="badge badge-<?= $r['paymentMethod']==='Cash'?'success':'info' ?>"><?= $r['paymentMethod'] ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <a href="../sale/weekly_report.php" class="btn btn-sm btn-secondary" style="margin-top:12px;width:100%;justify-content:center;">View Full Report →</a>
      </div>

      <!-- Recent Repairs -->
      <div class="chart-card">
        <h3 style="margin-bottom:12px;">Latest Repairs</h3>
        <table class="mini-table">
          <thead><tr><th>Bike</th><th>Problem</th><th>Status</th></tr></thead>
          <tbody>
          <?php while($r=$recentRepairs->fetch_assoc()):
            $bc = match($r['status']){'Finished'=>'success','Repairing'=>'warning',default=>'info'};
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['bikeNo']) ?></strong></td>
            <td style="color:var(--muted);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(substr($r['problemDescription'],0,30)) ?>…</td>
            <td><span class="badge badge-<?= $bc ?>"><?= $r['status'] ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <a href="../reports/repair.php" class="btn btn-sm btn-secondary" style="margin-top:12px;width:100%;justify-content:center;">View Full Report →</a>
      </div>

      <!-- Recent POs -->
      <div class="chart-card">
        <h3 style="margin-bottom:12px;">Latest Purchase Orders</h3>
        <table class="mini-table">
          <thead><tr><th>Supplier</th><th>Cost</th><th>Status</th></tr></thead>
          <tbody>
          <?php while($r=$recentPO->fetch_assoc()): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['supplierName']??'Unknown') ?></strong></td>
            <td style="color:#f59e0b">Rs.<?= number_format($r['totalCost'],2) ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($r['status']) ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <a href="../reports/purchase.php" class="btn btn-sm btn-secondary" style="margin-top:12px;width:100%;justify-content:center;">View Full Report →</a>
      </div>
    </div>

  </div><!-- /main-content -->
</div><!-- /main-wrap -->

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- CHART.JS SCRIPTS -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<script>
Chart.defaults.color = '#9cb3c9';
Chart.defaults.font.family = "'DM Sans', sans-serif";

const PALETTE = ['#f59e0b','#3b82f6','#2dc58e','#f87171','#a78bfa','#fb923c','#34d399','#60a5fa','#f472b6','#facc15'];

// ── 1. Revenue vs Spend ───────────────────────────────────────────────────
new Chart(document.getElementById('revSpendChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($combinedMonths ?: ['No data']) ?>,
    datasets: [
      { label: 'Revenue (Rs.)',  data: <?= json_encode($combinedRevs  ?: [0]) ?>, backgroundColor: 'rgba(45,197,142,.7)',  borderRadius: 4, order: 2 },
      { label: 'Purchases (Rs.)',data: <?= json_encode($combinedSpend ?: [0]) ?>, backgroundColor: 'rgba(248,113,113,.6)', borderRadius: 4, order: 2 },
      { label: 'Gross Profit',   data: <?= json_encode($combinedProfit?: [0]) ?>, type:'line', borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,.08)', fill:true, tension:.4, pointRadius:4, borderWidth:2.5, order:1 }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position:'top' }, tooltip: { mode:'index' } },
    scales: { y: { beginAtZero:true, grid:{ color:'#1e2d42' } }, x:{ grid:{ color:'#1e2d42' } } }
  }
});

// ── 2. Daily sales this month ─────────────────────────────────────────────
new Chart(document.getElementById('dailySalesChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($dailyDates ?: ['—']) ?>,
    datasets: [{ label:'Sales', data: <?= json_encode($dailyRevs ?: [0]) ?>, backgroundColor:'rgba(96,165,250,.7)', borderRadius:4 }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#1e2d42'}},x:{grid:{display:false}}} }
});

// ── 3. Payment method ─────────────────────────────────────────────────────
new Chart(document.getElementById('payChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($payLabels ?: ['No data']) ?>,
    datasets: [{ data: <?= json_encode($payCounts ?: [0]) ?>, backgroundColor: PALETTE, borderWidth:0 }]
  },
  options: { plugins:{ legend:{ position:'bottom', labels:{ padding:12, font:{ size:11 } } } }, cutout:'65%' }
});

// ── 4. Sales trend ────────────────────────────────────────────────────────
new Chart(document.getElementById('salesTrendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($salesMonths ?: ['No data']) ?>,
    datasets: [{
      label: 'Transactions',
      data: <?= json_encode($salesCounts ?: [0]) ?>,
      borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,.1)', fill:true, tension:.4, pointRadius:4, borderWidth:2.5
    }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#1e2d42'}},x:{grid:{color:'#1e2d42'}}} }
});

// ── 5. Top parts by qty ───────────────────────────────────────────────────
<?php if (!empty($topPartNames)): ?>
new Chart(document.getElementById('topPartsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($topPartNames) ?>,
    datasets: [{ label:'Qty Sold', data: <?= json_encode($topPartQtys) ?>, backgroundColor: PALETTE, borderRadius:4 }]
  },
  options: { indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,grid:{color:'#1e2d42'}},y:{grid:{display:false}}} }
});

new Chart(document.getElementById('topPartsRevChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($topPartNames) ?>,
    datasets: [{ label:'Revenue (Rs.)', data: <?= json_encode($topPartRevs) ?>, backgroundColor:'rgba(245,158,11,.7)', borderRadius:4 }]
  },
  options: { indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,grid:{color:'#1e2d42'}},y:{grid:{display:false}}} }
});
<?php else: ?>
['topPartsChart','topPartsRevChart'].forEach(id=>{
  const ctx=document.getElementById(id); if(ctx){ ctx.parentElement.innerHTML='<p style="text-align:center;color:#475569;padding:40px;font-size:13px;">No sales item data yet.</p>'; }
});
<?php endif; ?>

// ── 6. Inventory ──────────────────────────────────────────────────────────
new Chart(document.getElementById('invValChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($invCats ?: ['No data']) ?>,
    datasets: [{ label:'Value (Rs.)', data: <?= json_encode($invCatVals ?: [0]) ?>, backgroundColor: PALETTE, borderRadius:6 }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#1e2d42'}},x:{grid:{display:false}}} }
});

new Chart(document.getElementById('invCatChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($invCats ?: ['No data']) ?>,
    datasets: [{ data: <?= json_encode($invCatCounts ?: [0]) ?>, backgroundColor: PALETTE, borderWidth:0 }]
  },
  options: { plugins:{ legend:{ position:'bottom', labels:{ padding:10, font:{size:11} } } }, cutout:'60%' }
});

// ── 7. Repair trends ──────────────────────────────────────────────────────
new Chart(document.getElementById('repairTrendChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($repairMonths ?: ['No data']) ?>,
    datasets: [{ label:'Jobs', data: <?= json_encode($repairCounts ?: [0]) ?>, backgroundColor:'rgba(245,158,11,.7)', borderRadius:6 }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'#1e2d42'}},x:{grid:{display:false}}} }
});

new Chart(document.getElementById('repairStatusChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode($repairStatLabels ?: ['No data']) ?>,
    datasets: [{ data: <?= json_encode($repairStatCounts ?: [0]) ?>, backgroundColor:['#2dc58e','#f59e0b','#f87171','#60a5fa'], borderWidth:0 }]
  },
  options: { plugins:{ legend:{ position:'bottom', labels:{ padding:12, font:{size:11} } } } }
});

// ── 8. Customer service ───────────────────────────────────────────────────
new Chart(document.getElementById('custServiceChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($custMonths ?: ['No data']) ?>,
    datasets: [{ label:'Customers Serviced', data: <?= json_encode($custCounts ?: [0]) ?>, backgroundColor:'rgba(96,165,250,.7)', borderRadius:6 }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'#1e2d42'}},x:{grid:{display:false}}} }
});

new Chart(document.getElementById('custStatusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Overdue','Due This Week','No Date Set','Up to Date'],
    datasets: [{ data: [<?= $custOverdue['c'] ?>,<?= $upcoming7 ?>,<?= $noDate ?>,<?= $upToDate ?>], backgroundColor:['#f87171','#f59e0b','#94a3b8','#2dc58e'], borderWidth:0 }]
  },
  options: { plugins:{ legend:{ position:'bottom', labels:{ padding:12, font:{size:11} } } }, cutout:'60%' }
});

// ── 9. Supplier spend ─────────────────────────────────────────────────────
<?php if (!empty($supNames)): ?>
new Chart(document.getElementById('supChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($supNames) ?>,
    datasets: [{ label:'Spend (Rs.)', data: <?= json_encode($supSpend) ?>, backgroundColor: PALETTE, borderRadius:4 }]
  },
  options: { indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,grid:{color:'#1e2d42'}},y:{grid:{display:false}}} }
});
<?php else: ?>
document.getElementById('supChart').parentElement.innerHTML='<p style="text-align:center;color:#475569;padding:40px;font-size:13px;">No purchase order data yet.</p>';
<?php endif; ?>

new Chart(document.getElementById('poTrendChart'), {
  type: 'area',
  type: 'line',
  data: {
    labels: <?= json_encode($poMonths ?: ['No data']) ?>,
    datasets: [{
      label: 'Spend (Rs.)',
      data: <?= json_encode($poSpends ?: [0]) ?>,
      borderColor:'#f87171', backgroundColor:'rgba(248,113,113,.1)', fill:true, tension:.4, pointRadius:4, borderWidth:2
    }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#1e2d42'}},x:{grid:{color:'#1e2d42'}}} }
});
</script>
</body>
</html>
