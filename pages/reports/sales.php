<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }
$currentPage = 'sale'; $base = '../../';

// â”€â”€ CSV Export â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $type = $_GET['type'] ?? 'all';
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'sales_' . ($type === 'today' ? 'today_' : '') . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sale #', 'Date', 'Customer Name', 'Grand Total (Rs.)', 'Payment Method']);

    if ($type === 'today') {
        $res = $conn->query("SELECT * FROM sale WHERE DATE(saleDate)=CURDATE() ORDER BY saleID DESC");
    } else {
        $res = $conn->query("SELECT * FROM sale ORDER BY saleID DESC");
    }

    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            $r['saleID'],
            date('d M Y, H:i', strtotime($r['saleDate'])),
            $r['customerName'] ?? 'Walk-in',
            number_format($r['grandTotal'], 2),
            $r['paymentMethod']
        ]);
    }
    fclose($out);
    exit;
}

// Sales per day - last 14 days
$dayRes = $conn->query("SELECT DATE(saleDate) as d, COUNT(*) as cnt, SUM(grandTotal) as rev FROM sale WHERE saleDate >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(saleDate) ORDER BY d");
$dayLabels=[]; $dayCounts=[]; $dayRevs=[];
while($r=$dayRes->fetch_assoc()){ $dayLabels[]=date('d M',strtotime($r['d'])); $dayCounts[]=(int)$r['cnt']; $dayRevs[]=round((float)$r['rev'],2); }

// Payment method breakdown
$payRes = $conn->query("SELECT paymentMethod, COUNT(*) as cnt, SUM(grandTotal) as rev FROM sale GROUP BY paymentMethod");
$payLabels=[]; $payCounts=[]; $payRevs=[];
while($r=$payRes->fetch_assoc()){ $payLabels[]=$r['paymentMethod']; $payCounts[]=(int)$r['cnt']; $payRevs[]=round((float)$r['rev'],2); }

// Monthly revenue - last 6 months
$monRes = $conn->query("SELECT DATE_FORMAT(saleDate,'%b %Y') as mo, SUM(grandTotal) as rev FROM sale WHERE saleDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(saleDate,'%Y-%m') ORDER BY DATE_FORMAT(saleDate,'%Y-%m')");
$monLabels=[]; $monRevs=[];
while($r=$monRes->fetch_assoc()){ $monLabels[]=$r['mo']; $monRevs[]=round((float)$r['rev'],2); }

$totals = $conn->query("SELECT COUNT(*) as t, IFNULL(SUM(grandTotal),0) as r, IFNULL(AVG(grandTotal),0) as avg FROM sale")->fetch_assoc();
$todayR = $conn->query("SELECT IFNULL(SUM(grandTotal),0) as r FROM sale WHERE DATE(saleDate)=CURDATE()")->fetch_assoc()['r']??0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Report â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    .report-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
    .chart-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;}
    .chart-box h3{font-family:'Syne',sans-serif;font-size:.9rem;margin:0 0 16px;color:var(--text);}
    .summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;}
    @media print{.sidebar,.topbar,.no-print{display:none!important}.main-wrap{margin-left:0!important}.main-content{padding:0!important}}
  </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-wrap">
  <div class="topbar">
    <div><div class="topbar-title">Sales â€” Report</div><div class="topbar-breadcrumb">Analytics &amp; revenue summary</div></div>
    <div class="d-flex gap-2 no-print">
      <a href="?export=csv&type=all" class="btn btn-sm btn-success">â¬‡ Export All CSV</a>
      <a href="?export=csv&type=today" class="btn btn-sm btn-success">â¬‡ Export Today CSV</a>
      <a href="javascript:window.print()" class="btn btn-sm btn-outline">ðŸ–¨ Print / PDF</a>
      <a href="../sale/sales_list.php" class="btn btn-sm btn-secondary">â† Back</a>
    </div>
  </div>
  <div class="main-content">
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:16px;">Generated: <?=date('d M Y, H:i')?> | By: <?=htmlspecialchars($_SESSION['username'])?></div>

    <div class="summary-grid">
      <div class="stat-card"><div class="stat-icon blue">ðŸ§¾</div><div class="stat-info"><div class="s-value"><?=$totals['t']?></div><div class="s-label">Total Sales</div></div></div>
      <div class="stat-card"><div class="stat-icon green">ðŸ’°</div><div class="stat-info"><div class="s-value" style="font-size:.9rem;">Rs.<?=number_format($totals['r'],0)?></div><div class="s-label">Total Revenue</div></div></div>
      <div class="stat-card"><div class="stat-icon amber">ðŸ“ˆ</div><div class="stat-info"><div class="s-value" style="font-size:.9rem;">Rs.<?=number_format($totals['avg'],0)?></div><div class="s-label">Avg Sale</div></div></div>
      <div class="stat-card"><div class="stat-icon green">ðŸ“…</div><div class="stat-info"><div class="s-value" style="font-size:.9rem;">Rs.<?=number_format($todayR,0)?></div><div class="s-label">Today's Revenue</div></div></div>
    </div>

    <div class="chart-box" style="margin-bottom:20px;">
      <h3>Daily Revenue â€” Last 14 Days (Rs.)</h3>
      <canvas id="dayRevChart" height="120"></canvas>
    </div>
    <div class="report-grid">
      <div class="chart-box"><h3>Sales by Payment Method</h3><canvas id="payChart" height="220"></canvas></div>
      <div class="chart-box"><h3>Monthly Revenue (Last 6 Months)</h3><canvas id="monChart" height="220"></canvas></div>
    </div>

    <div class="card">
      <div class="card-header">
        <span>All Sales</span>
        <a href="?export=csv&type=all" class="btn btn-sm btn-success no-print" style="margin-left:auto;">â¬‡ Export CSV</a>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;">
        <table class="table">
          <thead><tr><th>Sale #</th><th>Date</th><th>Customer</th><th>Grand Total</th><th>Payment</th></tr></thead>
          <tbody>
          <?php $all=$conn->query("SELECT * FROM sale ORDER BY saleID DESC");
          while($r=$all->fetch_assoc()):$pb=$r['paymentMethod']==='Cash'?'badge-success':'badge-info';?>
          <tr>
            <td style="color:var(--muted)">#<?=$r['saleID']?></td>
            <td><?=date('d M Y, H:i',strtotime($r['saleDate']))?></td>
            <td><?=$r['customerName']?htmlspecialchars($r['customerName']):'<span class="text-muted">Walk-in</span>'?></td>
            <td><strong>Rs.<?=number_format($r['grandTotal'],2)?></strong></td>
            <td><span class="badge <?=$pb?>"><?=htmlspecialchars($r['paymentMethod'])?></span></td>
          </tr>
          <?php endwhile;?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
Chart.defaults.color='#9cb3c9';
new Chart(document.getElementById('dayRevChart'),{type:'line',data:{labels:<?=json_encode($dayLabels?:['No data'])?>,datasets:[{label:'Revenue (Rs.)',data:<?=json_encode($dayRevs?:[0])?>,borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.15)',fill:true,tension:.4,pointRadius:4}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('payChart'),{type:'doughnut',data:{labels:<?=json_encode($payLabels)?>,datasets:[{data:<?=json_encode($payCounts)?>,backgroundColor:['#2dc58e','#60a5fa','#a78bfa'],borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}}}});
new Chart(document.getElementById('monChart'),{type:'bar',data:{labels:<?=json_encode($monLabels?:['No data'])?>,datasets:[{label:'Revenue',data:<?=json_encode($monRevs?:[0])?>,backgroundColor:'rgba(45,197,142,.7)',borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
</script>
</body>
</html>
