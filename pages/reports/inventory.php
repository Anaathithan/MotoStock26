
<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }
$currentPage = 'inventory'; $base = '../../';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $type = $_GET['type'] ?? 'all';
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'inventory_' . ($type === 'low' ? 'lowstock_' : '') . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');

    if ($type === 'low') {
        fputcsv($out, ['Part ID', 'Part Name', 'Brand', 'Category', 'Selling Price (Rs.)', 'Current Qty', 'Min Qty', 'Stock Value (Rs.)']);
        $res = $conn->query("SELECT * FROM SparePart WHERE currentQuantity < minQuantity ORDER BY currentQuantity ASC");
    } else {
        fputcsv($out, ['Part ID', 'Part Name', 'Brand', 'Category', 'Selling Price (Rs.)', 'Current Qty', 'Min Qty', 'Stock Value (Rs.)', 'Status']);
        $res = $conn->query("SELECT * FROM SparePart ORDER BY category, partName");
    }

    while ($r = $res->fetch_assoc()) {
        $row = [
            $r['partID'],
            $r['partName'],
            $r['brandName'],
            $r['category'],
            number_format($r['sellingPrice'], 2),
            $r['currentQuantity'],
            $r['minQuantity'],
            number_format($r['sellingPrice'] * $r['currentQuantity'], 2),
        ];
        if ($type !== 'low') {
            $row[] = $r['currentQuantity'] < $r['minQuantity'] ? 'Low Stock' : 'In Stock';
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$catRes = $conn->query("SELECT category, COUNT(*) as cnt, SUM(currentQuantity) as totalQty, SUM(sellingPrice*currentQuantity) as totalVal FROM SparePart GROUP BY category");
$catLabels=[]; $catCounts=[]; $catVals=[];
while ($r=$catRes->fetch_assoc()){ $catLabels[]=$r['category']; $catCounts[]=(int)$r['cnt']; $catVals[]=round((float)$r['totalVal'],2); }

$stockRes = $conn->query("SELECT SUM(CASE WHEN currentQuantity>=minQuantity THEN 1 ELSE 0 END) as instock, SUM(CASE WHEN currentQuantity<minQuantity THEN 1 ELSE 0 END) as lowstock FROM SparePart");
$stockRow = $stockRes->fetch_assoc();

$totalParts = $conn->query("SELECT COUNT(*) as t FROM SparePart")->fetch_assoc()['t']??0;
$totalValue = $conn->query("SELECT IFNULL(SUM(sellingPrice*currentQuantity),0) as v FROM SparePart")->fetch_assoc()['v']??0;
$lowCount   = $stockRow['lowstock']??0;

$topRes = $conn->query("SELECT partName, currentQuantity FROM SparePart ORDER BY currentQuantity DESC LIMIT 10");
$topNames=[]; $topQtys=[];
while($r=$topRes->fetch_assoc()){ $topNames[]=$r['partName']; $topQtys[]=(int)$r['currentQuantity']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory Report — MotoStock26</title>
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

    .report-grid { break-inside:avoid; }
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
    <div><div class="topbar-title">Inventory — Report</div><div class="topbar-breadcrumb">Analytics &amp; summary</div></div>
    <div class="d-flex gap-2 no-print">
      <a href="?export=csv&type=all" class="btn btn-sm btn-success">⬇ Export All CSV</a>
      <a href="?export=csv&type=low" class="btn btn-sm btn-success">⬇ Export Low Stock CSV</a>
      <button onclick="window.print()" class="btn btn-sm btn-outline no-print">🖨 Print / Save as PDF</button>
      <a href="../inventory/list.php" class="btn btn-sm btn-secondary">← Back</a>
    </div>
  </div>
  <div class="main-content">

    <!-- Print only header -->
    <div class="print-header" style="display:none;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #ddd;">
      <div>
        <div style="font-size:18pt;font-weight:800;color:#000;">MotoStock26 — Inventory Report</div>
        <div style="font-size:9pt;color:#666;margin-top:4px;">Generated: <?= date('d M Y, H:i') ?> &nbsp;|&nbsp; By: <?= htmlspecialchars($_SESSION['username']) ?></div>
      </div>
      <div style="font-size:9pt;color:#666;text-align:right;">Bimsara Motors<br>Confidential</div>
    </div>

    <!-- Charts — Page 1 -->
    <div class="report-grid">
      <div class="chart-box"><h3>Parts by Category</h3><canvas id="catChart" height="220"></canvas></div>
      <div class="chart-box"><h3>Stock Status</h3><canvas id="stockChart" height="220"></canvas></div>
    </div>

    <!-- Top 10 chart — Page 2 -->
    <div class="chart-box print-page-break" style="margin-bottom:20px;">
      <h3>Top 10 Parts by Quantity</h3>
      <canvas id="topChart" height="130"></canvas>
    </div>

    <!-- Value by category — stays on Page 2 -->
    <div class="chart-box" style="margin-bottom:20px;">
      <h3>Stock Value by Category (Rs.)</h3>
      <canvas id="valChart" height="120"></canvas>
    </div>

    <!-- All parts table — Page 3 -->
    <div class="card print-page-break">
      <div class="card-header">
        <span>All Spare Parts</span>
        <a href="?export=csv&type=all" class="btn btn-sm btn-success no-print" style="margin-left:auto;">⬇ Export CSV</a>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;">
        <table class="table">
          <thead>
            <tr><th>Part Name</th><th>Brand</th><th>Category</th><th>Selling Price</th><th>Qty</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php $all = $conn->query("SELECT * FROM SparePart ORDER BY category, partName");
          while ($r = $all->fetch_assoc()): $low = $r['currentQuantity'] < $r['minQuantity']; ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['partName']) ?></strong></td>
            <td><?= htmlspecialchars($r['brandName']) ?></td>
            <td><span class="badge badge-dark"><?= htmlspecialchars($r['category']) ?></span></td>
            <td>Rs. <?= number_format($r['sellingPrice'], 2) ?></td>
            <td><?= $r['currentQuantity'] ?></td>
            <td><?= $low ? '<span class="badge badge-danger">⚠ Low</span>' : '<span class="badge badge-success">OK</span>' ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="print-footer">
      MotoStock26 &nbsp;|&nbsp; Inventory Report &nbsp;|&nbsp; <?= date('d M Y') ?> &nbsp;|&nbsp; Confidential
    </div>

    </div>
  </div>
</div>
<script>
Chart.defaults.color='#9cb3c9';
new Chart(document.getElementById('catChart'),{type:'doughnut',data:{labels:<?=json_encode($catLabels)?>,datasets:[{data:<?=json_encode($catCounts)?>,backgroundColor:['#f59e0b','#3b82f6','#2dc58e','#f87171','#a78bfa'],borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}}}});
new Chart(document.getElementById('stockChart'),{type:'pie',data:{labels:['In Stock','Low Stock'],datasets:[{data:[<?=$stockRow['instock']??0?>,<?=$lowCount?>],backgroundColor:['#2dc58e','#f87171'],borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}}}});
new Chart(document.getElementById('topChart'),{type:'bar',data:{labels:<?=json_encode($topNames)?>,datasets:[{label:'Qty',data:<?=json_encode($topQtys)?>,backgroundColor:'rgba(96,165,250,.7)',borderRadius:4}]},options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}});
new Chart(document.getElementById('valChart'),{type:'bar',data:{labels:<?=json_encode($catLabels)?>,datasets:[{label:'Value (Rs.)',data:<?=json_encode($catVals)?>,backgroundColor:'rgba(245,158,11,.7)',borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
</script>
</body>
</html>
