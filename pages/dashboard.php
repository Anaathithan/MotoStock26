<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/notify_events.php';

if (!isset($_SESSION['userID'])) { header("Location: ../login.php"); exit; }

$currentPage = 'dashboard';
$base = '../';
$quickSuccess = '';
$quickError   = '';

// ── Quick Add Bike (Repair Job) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_bike'])) {
    $bikeNo  = trim($_POST['bikeNo']  ?? '');
    $problem = trim($_POST['problem'] ?? '');
    $isWarranty = isset($_POST['warranty']) ? 1 : 0;

    if ($bikeNo === '' || $problem === '') {
        $quickError = 'Bike number and problem description are required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-\s]{2,20}$/', $bikeNo)) {
        $quickError = 'Invalid bike number format.';
    } elseif (strlen($problem) < 5) {
        $quickError = 'Problem description must be at least 5 characters.';
    } else {
        $stmt = $conn->prepare("INSERT INTO servicejob (bikeNo, problemDescription, isWarranty) VALUES (?,?,?)");
        $stmt->bind_param("ssi", $bikeNo, $problem, $isWarranty);
        $stmt->execute();
        $quickSuccess = "Repair job for bike <strong>{$bikeNo}</strong> added successfully!";
    }
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$pendingR = $conn->query("SELECT COUNT(*) as t FROM servicejob WHERE status != 'Finished'")->fetch_assoc()['t']??0;
$lowStockC= $conn->query("SELECT COUNT(*) as t FROM sparepart WHERE currentQuantity < minQuantity")->fetch_assoc()['t']??0;
$custCount= $conn->query("SELECT COUNT(*) as t FROM customer")->fetch_assoc()['t']??0;
$partCount= $conn->query("SELECT COUNT(*) as t FROM sparepart")->fetch_assoc()['t']??0;

// ── Weekly earnings summary ───────────────────────────────────────────────────
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

$weekRes = $conn->query("
    SELECT DAYNAME(saleDate) as day, SUM(grandTotal) as rev, COUNT(*) as cnt
    FROM sale
    WHERE DATE(saleDate) BETWEEN '$weekStart' AND '$weekEnd'
    GROUP BY DATE(saleDate), DAYNAME(saleDate)
    ORDER BY DATE(saleDate)
");
$weekDays = ['Monday'=>0,'Tuesday'=>0,'Wednesday'=>0,'Thursday'=>0,'Friday'=>0,'Saturday'=>0,'Sunday'=>0];
$weekTotal = 0; $weekSales = 0;
if ($weekRes) while ($w = $weekRes->fetch_assoc()) {
    $weekDays[$w['day']] = (float)$w['rev'];
    $weekTotal += (float)$w['rev'];
    $weekSales += (int)$w['cnt'];
}

// Last week for comparison
$lastWeekStart = date('Y-m-d', strtotime('monday last week'));
$lastWeekEnd   = date('Y-m-d', strtotime('sunday last week'));
$lastWeekRev   = $conn->query("SELECT IFNULL(SUM(grandTotal),0) as v FROM sale WHERE DATE(saleDate) BETWEEN '$lastWeekStart' AND '$lastWeekEnd'")->fetch_assoc()['v']??0;
$weekChange = $lastWeekRev > 0 ? round((($weekTotal - $lastWeekRev) / $lastWeekRev) * 100, 1) : null;

// Today's revenue
$todayRev = $conn->query("SELECT IFNULL(SUM(grandTotal),0) as v FROM sale WHERE DATE(saleDate)=CURDATE()")->fetch_assoc()['v']??0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — MotoStock26</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    .week-bar-wrap { display:flex; align-items:flex-end; gap:8px; height:80px; margin-bottom:8px; }
    .week-bar-col  { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; }
    .week-bar      { width:100%; background:rgba(245,158,11,.25); border-radius:4px 4px 0 0; min-height:4px; transition:height .3s; position:relative; }
    .week-bar.today{ background:rgba(245,158,11,.85); }
    .week-bar-label{ font-size:.65rem; color:var(--muted); }
    .week-bar-val  { font-size:.62rem; color:var(--amber); font-weight:600; }
    .ts-wrapper.is-invalid .ts-control { border-color: var(--danger, #ef4444) !important; }
  </style>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Dashboard</div>
      <div class="topbar-breadcrumb">Overview &amp; quick access</div>
    </div>
    <div style="font-size:.8rem;color:var(--muted);"><?= date('l, d M Y') ?></div>
  </div>

  <div class="main-content">

    <!-- Stats row -->
    <div class="grid-4 mb-3">
      <div class="stat-card">
        <div class="stat-icon amber">🔧</div>
        <div class="stat-info"><div class="s-value"><?= $pendingR ?></div><div class="s-label">Pending Repairs</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red">⚠️</div>
        <div class="stat-info"><div class="s-value"><?= $lowStockC ?></div><div class="s-label">Low Stock Items</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">👥</div>
        <div class="stat-info"><div class="s-value"><?= $custCount ?></div><div class="s-label">Customers</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div class="stat-info"><div class="s-value"><?= $partCount ?></div><div class="s-label">Parts in Stock</div></div>
      </div>
    </div>

    <!-- Weekly earnings + quick add -->
    <div class="mb3">

      <!-- Weekly Earnings -->
      <div class="card">
        <div class="card-header">
          <span>📈 Weekly Earnings</span>
          <span style="font-size:.75rem;color:var(--muted);"><?= date('d M', strtotime($weekStart)) ?> – <?= date('d M', strtotime($weekEnd)) ?></span>
        </div>
        <div class="card-body">
          <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:16px;">
            <span style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;color:var(--amber);">Rs.<?= number_format($weekTotal, 2) ?></span>
            <?php if ($weekChange !== null): ?>
              <span style="font-size:.78rem;padding:2px 8px;border-radius:99px;<?= $weekChange >= 0 ? 'background:rgba(45,197,142,.15);color:#9ff3cf;' : 'background:rgba(248,113,113,.15);color:#ffb0b0;' ?>">
                <?= $weekChange >= 0 ? '▲' : '▼' ?> <?= abs($weekChange) ?>% vs last week
              </span>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:16px;font-size:.8rem;margin-bottom:16px;color:var(--muted);">
            <span>🧾 <strong style="color:var(--text)"><?= $weekSales ?></strong> sales this week</span>
            <span>📅 <strong style="color:var(--text)">Rs.<?= number_format($todayRev,2) ?></strong> today</span>
          </div>
          <!-- Bar chart -->
          <canvas id="weekChart" height="100"></canvas>
        </div>
      </div>
      <br>
    </div>

    <!-- Reports quick links -->
    <div class="card mb-3">
      <div class="card-header">📊 Reports</div>
      <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="reports/repair.php"    class="btn btn-sm btn-outline">🔧 Repair Report</a>
          <a href="reports/customers.php" class="btn btn-sm btn-outline">👥 Customer Report</a>
          <a href="reports/inventory.php" class="btn btn-sm btn-outline">📦 Inventory Report</a>
          <a href="reports/purchase.php"  class="btn btn-sm btn-outline">🛒 Purchase Report</a>
          <a href="reports/sales.php"     class="btn btn-sm btn-outline">🧾 Sales Report</a>
        </div>
      </div>
    </div>

    <!-- Low stock + quick access -->
    <div class="grid-2 mb-3" style="align-items:start">
      <div class="card">
        <div class="card-header">
          <span>⚠ Low Stock Alerts</span>
          <a href="inventory/list.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body">
          <?php
          $low = $conn->query("SELECT partName, currentQuantity FROM SparePart WHERE currentQuantity < minQuantity ORDER BY currentQuantity ASC LIMIT 6");
          if ($low && $low->num_rows > 0):
            while ($l = $low->fetch_assoc()):
          ?>
          <div class="low-stock-item">
            <span class="p-name"><?= htmlspecialchars($l['partName']) ?></span>
            <span class="p-qty">Only <?= $l['currentQuantity'] ?> left</span>
          </div>
          <?php endwhile; else: ?>
          <p style="color:var(--muted);font-size:.875rem;">✅ All parts are adequately stocked.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Quick Access</div>
        <div class="card-body">
          <div class="quick-grid">
            <a href="repair/list.php"    class="quick-btn"><span class="q-icon">🔧</span>Repair Jobs</a>
            <a href="customer/list.php"  class="quick-btn"><span class="q-icon">👥</span>Customers</a>
            <a href="inventory/list.php" class="quick-btn"><span class="q-icon">📦</span>Inventory</a>
            <a href="purchase/list.php"  class="quick-btn"><span class="q-icon">🛒</span>Purchase Orders</a>
            <a href="sale/new_sale.php"    class="quick-btn" style="grid-column:span 2;"><span class="q-icon">🧾</span>New Sale / POS Checkout</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent repair jobs -->
    <div class="card">
      <div class="card-header">
        <span>Recent Repair Jobs</span>
        <a href="repair/list.php" class="btn btn-sm btn-primary">View All</a>
      </div>
      <div class="table-wrap" style="border-radius:0;border:none;box-shadow:none;">
        <table class="table">
          <thead><tr><th>Bike No</th><th>Problem</th><th>Status</th><th>Warranty</th></tr></thead>
          <tbody>
          <?php
          $jobs = $conn->query("SELECT * FROM servicejob ORDER BY jobID DESC LIMIT 5");
          while ($j = $jobs->fetch_assoc()):
            $bc = match($j['status']) { 'Finished'=>'badge-success','Repairing'=>'badge-warning',default=>'badge-info' };
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($j['bikeNo']) ?></strong></td>
            <td><?= htmlspecialchars(substr($j['problemDescription'],0,60)) ?><?= strlen($j['problemDescription'])>60?'…':'' ?></td>
            <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($j['status']) ?></span></td>
            <td><?= $j['isWarranty']?'<span class="badge badge-info">Yes</span>':'<span class="badge badge-dark">No</span>' ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
// Weekly bar chart
const weekData  = <?= json_encode(array_values($weekDays)) ?>;
const weekLabels= <?= json_encode(array_map(fn($d)=>substr($d,0,3), array_keys($weekDays))) ?>;
const todayIdx  = <?= (int)date('N') - 1 ?>; // 0=Mon
Chart.defaults.color = '#9cb3c9';
new Chart(document.getElementById('weekChart'), {
  type: 'bar',
  data: {
    labels: weekLabels,
    datasets: [{
      data: weekData,
      backgroundColor: weekData.map((_, i) => i === todayIdx ? 'rgba(245,158,11,.85)' : 'rgba(245,158,11,.25)'),
      borderRadius: 5, borderSkipped: false
    }]
  },
  options: {
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => 'Rs. ' + ctx.parsed.y.toLocaleString() } } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs.'+(v>=1000?(v/1000).toFixed(0)+'k':v) } }, x: { grid: { display: false } } }
  }
});

// Quick form validation
const qBikeNo  = document.getElementById('qBikeNo');
const qProblem = document.getElementById('qProblem');
const quickForm = document.getElementById('quickForm');

function qValidBike() {
  const v = qBikeNo.value.trim();
  const err = document.getElementById('qBikeErr');
  if (v===''){ qBikeNo.classList.add('is-invalid'); err.textContent='Required.'; err.style.display='block'; return false; }
  if (!/^[A-Za-z0-9\-\s]{2,20}$/.test(v)){ qBikeNo.classList.add('is-invalid'); err.textContent='Invalid format.'; err.style.display='block'; return false; }
  qBikeNo.classList.remove('is-invalid'); err.style.display='none'; return true;
}
function qValidProb() {
  const v = qProblem.value.trim();
  const err = document.getElementById('qProbErr');
  if (v.length<5){ qProblem.classList.add('is-invalid'); err.textContent='Min 5 characters.'; err.style.display='block'; return false; }
  qProblem.classList.remove('is-invalid'); err.style.display='none'; return true;
}

if (qBikeNo && qProblem && quickForm) {
  qBikeNo.addEventListener('blur', qValidBike);
  qProblem.addEventListener('blur', qValidProb);
  quickForm.addEventListener('submit', e => {
    if (!qValidBike() || !qValidProb()) e.preventDefault();
  });
}
</script>
</body>
</html>
