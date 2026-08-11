<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }
$currentPage = 'customer'; $base = '../../';

$today = date('Y-m-d');

// â”€â”€ CSV Export â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $type = $_GET['type'] ?? 'all';
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'customers_' . ($type === 'overdue' ? 'overdue_' : '') . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');

    if ($type === 'overdue') {
        fputcsv($out, ['Name', 'Phone', 'Vehicle No', 'Last Service Date', 'Overdue Since']);
        $res = $conn->query("SELECT name, phone, vehicleNo, lastServiceDate, nextServiceDue FROM customer WHERE nextServiceDue < '$today' AND nextServiceDue IS NOT NULL ORDER BY nextServiceDue ASC");
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [
                $r['name'],
                $r['phone'],
                $r['vehicleNo'],
                $r['lastServiceDate'] ? date('d M Y', strtotime($r['lastServiceDate'])) : '',
                $r['nextServiceDue']  ? date('d M Y', strtotime($r['nextServiceDue']))  : ''
            ]);
        }
    } else {
        fputcsv($out, ['Customer ID', 'Name', 'Phone', 'Vehicle No', 'Last Service Date', 'Next Service Due', 'Status']);
        $res = $conn->query("SELECT * FROM customer ORDER BY customerID DESC");
        while ($r = $res->fetch_assoc()) {
            $isOd = $r['nextServiceDue'] && $r['nextServiceDue'] < $today;
            fputcsv($out, [
                $r['customerID'],
                $r['name'],
                $r['phone'],
                $r['vehicleNo'],
                $r['lastServiceDate'] ? date('d M Y', strtotime($r['lastServiceDate'])) : '',
                $r['nextServiceDue']  ? date('d M Y', strtotime($r['nextServiceDue']))  : '',
                $isOd ? 'Overdue' : ($r['nextServiceDue'] ? 'Up to Date' : 'No Date Set')
            ]);
        }
    }
    fclose($out);
    exit;
}

$total   = $conn->query("SELECT COUNT(*) as t FROM customer")->fetch_assoc()['t']??0;
$overdue = $conn->query("SELECT COUNT(*) as t FROM customer WHERE nextServiceDue < '$today' AND nextServiceDue IS NOT NULL")->fetch_assoc()['t']??0;
$upcoming= $conn->query("SELECT COUNT(*) as t FROM customer WHERE nextServiceDue BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)")->fetch_assoc()['t']??0;
$noDate  = $conn->query("SELECT COUNT(*) as t FROM customer WHERE nextServiceDue IS NULL")->fetch_assoc()['t']??0;

// New customers per month - last 6 months (FIXED for only_full_group_by)
$monLabels = []; 
$monCounts = [];
$monRes = $conn->query("
    SELECT 
        DATE_FORMAT(lastServiceDate, '%b %Y') AS mo, 
        COUNT(*) AS cnt 
    FROM customer 
    WHERE lastServiceDate IS NOT NULL 
      AND lastServiceDate >= DATE_SUB('$today', INTERVAL 6 MONTH) 
    GROUP BY DATE_FORMAT(lastServiceDate, '%Y-%m'), DATE_FORMAT(lastServiceDate, '%b %Y')
    ORDER BY DATE_FORMAT(lastServiceDate, '%Y-%m')
");
if ($monRes) {
    while ($r = $monRes->fetch_assoc()) {
        $monLabels[] = $r['mo'];
        $monCounts[] = (int)$r['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Report â€” MotoStock26</title>
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

    .stat-card, .chart-box, .card {
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
    <div><div class="topbar-title">Customers â€” Report</div><div class="topbar-breadcrumb">Analytics &amp; service summary</div></div>
    <div class="d-flex gap-2 no-print">
      <a href="?export=csv&type=all" class="btn btn-sm btn-success">â¬‡ Export All CSV</a>
      <a href="?export=csv&type=overdue" class="btn btn-sm btn-success">â¬‡ Export Overdue CSV</a>
      <button onclick="window.print()" class="btn btn-sm btn-outline no-print">ðŸ–¨ Print / Save as PDF</button>
      <a href="../customer/list.php" class="btn btn-sm btn-secondary">â† Back</a>
    </div>
  </div>
  <div class="main-content">

    <div class="print-header" style="display:none;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #ddd;">
      <div>
        <div style="font-size:18pt;font-weight:800;color:#000;">MotoStock26 â€” Customer Report</div>
        <div style="font-size:9pt;color:#666;margin-top:4px;">Generated: <?= date('d M Y, H:i') ?> &nbsp;|&nbsp; By: <?= htmlspecialchars($_SESSION['username']) ?></div>
      </div>
      <div style="font-size:9pt;color:#666;text-align:right;">Bimsara Motors<br>Confidential</div>
    </div>

    <div style="font-size:.78rem;color:var(--muted);margin-bottom:16px;">Generated: <?=date('d M Y, H:i')?> | By: <?=htmlspecialchars($_SESSION['username'])?></div>

    <div class="report-grid">
      <div class="chart-box">
        <h3>Service Status Breakdown</h3>
        <canvas id="serviceChart" height="220"></canvas>
      </div>
      <div class="chart-box">
        <h3>Service Activity (Last 6 Months)</h3>
        <canvas id="monChart" height="220"></canvas>
      </div>
    </div>

    <!-- Overdue customers -->
    <div class="card mb-3 print-page-break">
      <div class="card-header">
        <span>âš  Overdue Service Customers</span>
        <a href="?export=csv&type=overdue" class="btn btn-sm btn-success no-print" style="margin-left:auto;">â¬‡ Export CSV</a>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;">
        <table class="table">
          <thead><tr><th>Name</th><th>Phone</th><th>Vehicle No</th><th>Last Service</th><th>Overdue Since</th></tr></thead>
          <tbody>
          <?php $od=$conn->query("SELECT * FROM customer WHERE nextServiceDue < '$today' AND nextServiceDue IS NOT NULL ORDER BY nextServiceDue ASC");
          $cnt=0;
          while($r=$od->fetch_assoc()): $cnt++; ?>
          <tr>
            <td><strong><?=htmlspecialchars($r['name'])?></strong></td>
            <td><?=htmlspecialchars($r['phone'])?></td>
            <td><span class="badge badge-dark"><?=htmlspecialchars($r['vehicleNo'])?></span></td>
            <td><?=$r['lastServiceDate']?date('d M Y',strtotime($r['lastServiceDate'])):'â€”'?></td>
            <td><span class="badge badge-danger"><?=date('d M Y',strtotime($r['nextServiceDue']))?></span></td>
          </tr>
          <?php endwhile; if($cnt===0): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px;">No overdue customers.</td></tr><?php endif;?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card print-page-break">
      <div class="card-header">
        <span>All Customers</span>
        <a href="?export=csv&type=all" class="btn btn-sm btn-success no-print" style="margin-left:auto;">â¬‡ Export CSV</a>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;">
        <table class="table">
          <thead><tr><th>Name</th><th>Phone</th><th>Vehicle No</th><th>Last Service</th><th>Next Due</th></tr></thead>
          <tbody>
          <?php $all=$conn->query("SELECT * FROM customer ORDER BY customerID DESC");
          while($r=$all->fetch_assoc()): $isOd=$r['nextServiceDue']&&$r['nextServiceDue']<$today; ?>
          <tr>
            <td><strong><?=htmlspecialchars($r['name'])?></strong></td>
            <td><?=htmlspecialchars($r['phone'])?></td>
            <td><span class="badge badge-dark"><?=htmlspecialchars($r['vehicleNo'])?></span></td>
            <td><?=$r['lastServiceDate']?date('d M Y',strtotime($r['lastServiceDate'])):'<span class="text-muted">â€”</span>'?></td>
            <td><?=$r['nextServiceDue']?($isOd?'<span class="badge badge-danger">âš  '.date('d M Y',strtotime($r['nextServiceDue'])).'</span>':date('d M Y',strtotime($r['nextServiceDue']))):'<span class="text-muted">â€”</span>'?></td>
          </tr>
          <?php endwhile;?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="print-footer">
      MotoStock26 &nbsp;|&nbsp; Customer Report &nbsp;|&nbsp; <?= date('d M Y') ?> &nbsp;|&nbsp; Confidential
    </div>
  </div>
</div>
<script>
Chart.defaults.color='#9cb3c9';
new Chart(document.getElementById('serviceChart'),{type:'doughnut',data:{labels:['Overdue','Due This Week','No Date Set','Up to Date'],datasets:[{data:[<?=$overdue?>,<?=$upcoming?>,<?=$noDate?>,<?=max(0,$total-$overdue-$upcoming-$noDate)?>],backgroundColor:['#f87171','#f59e0b','#94a3b8','#2dc58e'],borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}}}});
new Chart(document.getElementById('monChart'),{type:'bar',data:{labels:<?=json_encode($monLabels?:['No data'])?>,datasets:[{label:'Services',data:<?=json_encode($monCounts?:[0])?>,backgroundColor:'rgba(96,165,250,.7)',borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});
</script>
</body>
</html>
