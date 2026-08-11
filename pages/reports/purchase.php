<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }
$currentPage = 'purchase'; $base = '../../';

// â”€â”€ CSV Export â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'purchase_orders_' . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['PO #', 'Supplier', 'Order Date', 'Total Cost (Rs.)', 'Status']);
    $res = $conn->query("SELECT po.*, s.supplierName FROM purchaseorder po LEFT JOIN supplier s ON po.supplierID=s.supplierID ORDER BY po.poID DESC");
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            $r['poID'],
            $r['supplierName'] ?? 'Unknown',
            date('d M Y', strtotime($r['orderDate'])),
            number_format($r['totalCost'], 2),
            $r['status']
        ]);
    }
    fclose($out);
    exit;
}

$total   = $conn->query("SELECT COUNT(*) as t, IFNULL(SUM(totalCost),0) as v FROM purchaseorder")->fetch_assoc();
$thisMonth = $conn->query("SELECT COUNT(*) as t, IFNULL(SUM(totalCost),0) as v FROM purchaseorder WHERE MONTH(orderDate)=MONTH(NOW()) AND YEAR(orderDate)=YEAR(NOW())")->fetch_assoc();

// By supplier
$supRes = $conn->query("SELECT s.supplierName, COUNT(po.poID) as cnt, SUM(po.totalCost) as spend FROM purchaseorder po LEFT JOIN supplier s ON po.supplierID=s.supplierID GROUP BY po.supplierID ORDER BY spend DESC LIMIT 8");
$supLabels=[]; $supCounts=[]; $supSpend=[];
while($r=$supRes->fetch_assoc()){ $supLabels[]=$r['supplierName']??'Unknown'; $supCounts[]=(int)$r['cnt']; $supSpend[]=round((float)$r['spend'],2); }

// Monthly spend - last 6 months
// Monthly spend - last 6 months
$monRes = $conn->query("
    SELECT 
        DATE_FORMAT(orderDate,'%b %Y') as mo, 
        DATE_FORMAT(orderDate,'%Y-%m') as mo_sort,
        SUM(totalCost) as spend 
    FROM purchaseorder 
    WHERE orderDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    GROUP BY DATE_FORMAT(orderDate,'%Y-%m'), DATE_FORMAT(orderDate,'%b %Y')
    ORDER BY mo_sort
");
$monLabels=[]; $monSpend=[];
while($r=$monRes->fetch_assoc()){ $monLabels[]=$r['mo']; $monSpend[]=round((float)$r['spend'],2); }

// Status breakdown
$statRes = $conn->query("SELECT status, COUNT(*) as cnt FROM purchaseorder GROUP BY status");
$statLabels=[]; $statCounts=[];
while($r=$statRes->fetch_assoc()){ $statLabels[]=$r['status']??'Unknown'; $statCounts[]=(int)$r['cnt']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Purchase Report â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    .report-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
    .chart-box { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
    .chart-box h3 { font-family:'Syne',sans-serif; font-size:.9rem; margin:0 0 16px; color:var(--text); }
    .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px; }

    @media print {
      @page { size: A4 landscape; margin: 12mm 14mm; }

      .sidebar, .topbar, .no-print { display:none !important; }
      .main-wrap { margin-left:0 !important; }
      .main-content { padding:0 !important; background:white !important; color:#000 !important; }
      body, html { background:white !important; color:#000 !important; }

      .print-header { display:flex !important; }
      .print-page-break { break-before:page; }

      .chart-box, .card {
        background: white !important;
        border: 1px solid #ddd !important;
        border-radius: 6px !important;
        box-shadow: none !important;
        break-inside: avoid;
        color: #000 !important;
      }

      .report-grid { break-inside: avoid; }
      .chart-box h3 { color:#000 !important; border-bottom:1px solid #eee; padding-bottom:8px; }

      .card .card-header { background:#f3f4f6 !important; color:#000 !important; border-bottom:1px solid #ddd !important; }
      .table thead th { background:#f3f4f6 !important; color:#000 !important; border-bottom:2px solid #ddd !important; }
      .table tbody tr td { color:#000 !important; border-bottom:1px solid #eee !important; }
      .table tbody tr:last-child td { border-bottom:none !important; }
      .badge { background:#f3f4f6 !important; color:#000 !important; border:1px solid #ddd !important; }

      .print-footer { display:block !important; text-align:center; font-size:8pt; color:#999; margin-top:12px; border-top:1px solid #eee; padding-top:8px; }
    }

    .print-header { display:none; }
    .print-footer { display:none; }
  </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>
<div class="main-wrap">
  <div class="topbar">
    <div><div class="topbar-title">Purchase Orders â€” Report</div><div class="topbar-breadcrumb">Analytics &amp; spend summary</div></div>
    <div class="d-flex gap-2 no-print">
      <a href="?export=csv" class="btn btn-sm btn-success">â¬‡ Export CSV</a>
      <button onclick="window.print()" class="btn btn-sm btn-outline no-print">ðŸ–¨ Print / Save as PDF</button>
      <a href="../purchase/list.php" class="btn btn-sm btn-secondary">â† Back</a>
    </div>
  </div>
  <div class="main-content">

    <!-- Print only header -->
    <div class="print-header" style="display:none;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #ddd;">
      <div>
        <div style="font-size:18pt;font-weight:800;color:#000;">MotoStock26 â€” Purchase Orders Report</div>
        <div style="font-size:9pt;color:#666;margin-top:4px;">Generated: <?= date('d M Y, H:i') ?> &nbsp;|&nbsp; By: <?= htmlspecialchars($_SESSION['username']) ?></div>
      </div>
      <div style="font-size:9pt;color:#666;text-align:right;">Bimsara Motors<br>Confidential</div>
    </div>

    <!-- Monthly spend chart â€” Page 1 -->
    <div class="chart-box" style="margin-bottom:20px;">
      <h3>Monthly Spend â€” Last 6 Months (Rs.)</h3>
      <canvas id="monChart" height="120"></canvas>
    </div>

    <!-- Supplier + Status charts â€” Page 2 -->
    <div class="report-grid print-page-break">
      <div class="chart-box"><h3>Spend by Supplier (Rs.)</h3><canvas id="supChart" height="250"></canvas></div>
      <div class="chart-box"><h3>Orders by Status</h3><canvas id="statChart" height="250"></canvas></div>
    </div>

    <!-- All POs table â€” Page 3 -->
    <div class="card print-page-break">
      <div class="card-header">
        <span>All Purchase Orders</span>
        <a href="?export=csv" class="btn btn-sm btn-success no-print" style="margin-left:auto;">â¬‡ Export CSV</a>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;">
        <table class="table">
          <thead>
            <tr><th>PO #</th><th>Supplier</th><th>Date</th><th>Total</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php $all = $conn->query("SELECT po.*, s.supplierName FROM purchaseorder po LEFT JOIN supplier s ON po.supplierID = s.supplierID ORDER BY po.poID DESC");
          while ($r = $all->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--muted)">#<?= $r['poID'] ?></td>
            <td><strong><?= htmlspecialchars($r['supplierName'] ?? 'Unknown') ?></strong></td>
            <td><?= date('d M Y', strtotime($r['orderDate'])) ?></td>
            <td>Rs. <?= number_format($r['totalCost'], 2) ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($r['status']) ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="print-footer">
      MotoStock26 &nbsp;|&nbsp; Purchase Orders Report &nbsp;|&nbsp; <?= date('d M Y') ?> &nbsp;|&nbsp; Confidential
    </div>

  </div>
</div>
<script>
Chart.defaults.color='#9cb3c9';
new Chart(document.getElementById('monChart'),{type:'line',data:{labels:<?=json_encode($monLabels?:['No data'])?>,datasets:[{label:'Spend (Rs.)',data:<?=json_encode($monSpend?:[0])?>,borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.15)',fill:true,tension:.4,pointRadius:4}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('supChart'),{type:'bar',data:{labels:<?=json_encode($supLabels)?>,datasets:[{label:'Spend',data:<?=json_encode($supSpend)?>,backgroundColor:'rgba(245,158,11,.7)',borderRadius:4}]},options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}});
new Chart(document.getElementById('statChart'),{type:'doughnut',data:{labels:<?=json_encode($statLabels)?>,datasets:[{data:<?=json_encode($statCounts)?>,backgroundColor:['#60a5fa','#2dc58e','#f87171','#a78bfa'],borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}}}});
</script>
</body>
</html>
