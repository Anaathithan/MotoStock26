<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$currentPage = 'repair';
$base = '../../';

// ── CSV Export ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $type = $_GET['type'] ?? 'all';
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'repair_jobs_' . ($type !== 'all' ? $type . '_' : '') . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Job ID', 'Bike No', 'Problem Description', 'Status', 'Warranty', 'Created At']);

    $where = $type !== 'all' ? "WHERE status = '" . $conn->real_escape_string(ucfirst($type)) . "'" : '';
    $res = $conn->query("SELECT * FROM ServiceJob $where ORDER BY jobID DESC");
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            $r['jobID'],
            $r['bikeNo'],
            $r['problemDescription'],
            $r['status'],
            $r['isWarranty'] ? 'Yes' : 'No',
            isset($r['created_at']) ? date('d M Y, H:i', strtotime($r['created_at'])) : ''
        ]);
    }
    fclose($out);
    exit;
}

// ── Data queries ─────────────────────────────────────────────────────────────
$statusRes = $conn->query("SELECT status, COUNT(*) as cnt FROM ServiceJob GROUP BY status");
$statusLabels = []; $statusData = [];
while ($r = $statusRes->fetch_assoc()) { $statusLabels[] = $r['status']; $statusData[] = (int)$r['cnt']; }

$warrantyRes = $conn->query("SELECT isWarranty, COUNT(*) as cnt FROM ServiceJob GROUP BY isWarranty");
$warrantyYes = 0; $warrantyNo = 0;
while ($r = $warrantyRes->fetch_assoc()) { if ($r['isWarranty']) $warrantyYes = $r['cnt']; else $warrantyNo = $r['cnt']; }

// Jobs per month (last 6 months) - FIXED for only_full_group_by
$monthRes = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') AS mo, 
        COUNT(*) AS cnt 
    FROM ServiceJob 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m')
");
$monthLabels = [];
$monthData = [];
if ($monthRes) while ($r = $monthRes->fetch_assoc()) { $monthLabels[] = $r['mo']; $monthData[] = (int)$r['cnt']; }

$total     = array_sum($statusData);
$finished  = 0; $pending = 0; $repairing = 0;
foreach ($statusLabels as $i => $s) {
    if ($s === 'Finished')  $finished  = $statusData[$i];
    if ($s === 'Pending')   $pending   = $statusData[$i];
    if ($s === 'Repairing') $repairing = $statusData[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Repair Jobs Report — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
  .report-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
  .chart-box { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
  .chart-box h3 { font-family:'Syne',sans-serif; font-size:.9rem; margin:0 0 16px; color:var(--text); }
  .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px; }

  /* ── Print styles ───────────────────────────────────────────────────── */
  @media print {
    @page { size: A4 landscape; margin: 12mm 14mm; }

    /* Hide everything except the report */
    .sidebar, .topbar, .no-print { display:none !important; }
    .main-wrap { margin-left:0 !important; }
    .main-content { padding:0 !important; background:white !important; color:#000 !important; }
    body, html { background:white !important; color:#000 !important; }

    /* Report header */
    .print-header { display:flex !important; }

    /* Each section stays on its own page */
    .print-page-break { break-before:page; }

    /* Cards become clean white boxes */
    .stat-card, .chart-box, .card {
      background: white !important;
      border: 1px solid #ddd !important;
      border-radius: 6px !important;
      box-shadow: none !important;
      break-inside: avoid;
      color: #000 !important;
    }

    /* Summary cards in a row on page 1 */
    .summary-grid {
      grid-template-columns: repeat(4,1fr) !important;
      break-inside: avoid;
      margin-bottom: 14px !important;
    }

    .stat-card .s-value { color:#000 !important; font-size:1.6rem !important; }
    .stat-card .s-label { color:#555 !important; }
    .stat-icon { background:#f3f4f6 !important; }

    /* Charts side by side on page 2 */
    .report-grid {
      grid-template-columns: 1fr 1fr !important;
      break-inside: avoid;
    }

    .chart-box h3 { color:#000 !important; border-bottom:1px solid #eee; padding-bottom:8px; }

    /* Month chart full width below */
    .month-chart-box { break-inside: avoid; }

    /* Table on its own page */
    .card .card-header { background:#f3f4f6 !important; color:#000 !important; border-bottom:1px solid #ddd !important; }
    .table thead th { background:#f3f4f6 !important; color:#000 !important; border-bottom:2px solid #ddd !important; }
    .table tbody tr td { color:#000 !important; border-bottom:1px solid #eee !important; }
    .table tbody tr:last-child td { border-bottom:none !important; }

    /* Badges become simple text labels */
    .badge { background:#f3f4f6 !important; color:#000 !important; border:1px solid #ddd !important; }

    /* Muted text */
    [style*="color:var(--muted)"] { color:#666 !important; }

    /* Footer on every page */
    .print-footer { display:block !important; text-align:center; font-size:8pt; color:#999; margin-top:12px; border-top:1px solid #eee; padding-top:8px; }
  }

  /* Hidden on screen */
  .print-header { display:none; }
  .print-footer { display:none; }
  </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Repair Jobs — Report</div>
      <div class="topbar-breadcrumb">Analytics &amp; summary</div>
    </div>
    <div class="d-flex gap-2 no-print">
      <a href="?export=csv&type=all" class="btn btn-sm btn-success">⬇ Export All CSV</a>
      <a href="?export=csv&type=pending" class="btn btn-sm btn-success">⬇ Export Pending CSV</a>
      <button onclick="window.print()" class="btn btn-sm btn-outline no-print">🖨 Print / Save as PDF</button>
      <a href="../repair/list.php" class="btn btn-sm btn-secondary">← Back</a>
    </div>
  </div>

  <div class="main-content" id="reportContent">


    <!-- Print only header -->
    <div class="print-header" style="display:none;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #ddd;">
      <div>
        <div style="font-size:18pt;font-weight:800;color:#000;">MotoStock26 — Repair Jobs Report</div>
        <div style="font-size:9pt;color:#666;margin-top:4px;">Generated: <?= date('d M Y, H:i') ?> &nbsp;|&nbsp; By: <?= htmlspecialchars($_SESSION['username']) ?></div>
      </div>
      <div style="font-size:9pt;color:#666;text-align:right;">
        Bimsara Motors<br>Confidential
      </div>
    </div>

    <div style="font-size:.78rem;color:var(--muted);margin-bottom:16px;">
      Generated: <?= date('d M Y, H:i') ?> &nbsp;|&nbsp; By: <?= htmlspecialchars($_SESSION['username']) ?>
    </div>

    <!-- Charts -->
    <div class="report-grid">
      <div class="chart-box">
        <h3>Jobs by Status</h3>
        <canvas id="statusChart" height="220"></canvas>
      </div>
      <div class="chart-box">
        <h3>Warranty vs Non-Warranty</h3>
        <canvas id="warrantyChart" height="220"></canvas>
      </div>
    </div>
    <div class="chart-box month-chart-box" style="margin-bottom:20px;">
      <h3>Jobs per Month (Last 6 Months)</h3>
      <canvas id="monthChart" height="120"></canvas>
    </div>

    <!-- Full table -->
    <div class="card print-page-break">
      <div class="card-header">
        <span>All Repair Jobs</span>
        <a href="?export=csv&type=all" class="btn btn-sm btn-success no-print" style="margin-left:auto;">⬇ Export CSV</a>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;">
        <table class="table">
          <thead><tr><th>#</th><th>Bike No</th><th>Problem</th><th>Status</th><th>Warranty</th></tr></thead>
          <tbody>
          <?php
          $all = $conn->query("SELECT * FROM ServiceJob ORDER BY jobID DESC");
          while ($r = $all->fetch_assoc()):
            $bc = match($r['status']) { 'Finished'=>'badge-success','Repairing'=>'badge-warning',default=>'badge-info' };
          ?>
          <tr>
            <td style="color:var(--muted)"><?= $r['jobID'] ?></td>
            <td><strong><?= htmlspecialchars($r['bikeNo']) ?></strong></td>
            <td><?= htmlspecialchars(substr($r['problemDescription'],0,70)) ?><?= strlen($r['problemDescription'])>70?'…':'' ?></td>
            <td><span class="badge <?= $bc ?>"><?= $r['status'] ?></span></td>
            <td><?= $r['isWarranty'] ? '<span class="badge badge-info">Yes</span>' : '<span class="badge badge-dark">No</span>' ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="print-footer">
      MotoStock26 &nbsp;|&nbsp; Repair Jobs Report &nbsp;|&nbsp; <?= date('d M Y') ?> &nbsp;|&nbsp; Confidential
    </div>
  </div>
</div>

<script>
const chartDefaults = { color:'#9cb3c9', borderColor:'rgba(255,255,255,.1)' };
Chart.defaults.color = '#9cb3c9';

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($statusLabels) ?>,
    datasets: [{ data: <?= json_encode($statusData) ?>, backgroundColor: ['#f59e0b','#3b82f6','#2dc58e','#f87171'], borderWidth:0 }]
  },
  options: { plugins:{ legend:{ position:'bottom' } } }
});

new Chart(document.getElementById('warrantyChart'), {
  type: 'pie',
  data: {
    labels: ['Under Warranty','No Warranty'],
    datasets: [{ data: [<?= $warrantyYes ?>, <?= $warrantyNo ?>], backgroundColor:['#60a5fa','#94a3b8'], borderWidth:0 }]
  },
  options: { plugins:{ legend:{ position:'bottom' } } }
});

new Chart(document.getElementById('monthChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthLabels ?: ['No data']) ?>,
    datasets: [{ label:'Jobs', data: <?= json_encode($monthData ?: [0]) ?>, backgroundColor:'rgba(245,158,11,.7)', borderRadius:6 }]
  },
  options: { plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
});
</script>
</body>
</html>
