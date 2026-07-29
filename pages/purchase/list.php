<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$currentPage = 'purchase';
$base = '../../';

// ── Filters ──────────────────────────────────────────────────────────────────
$search       = trim($_GET['search']   ?? '');
$statusFilter = trim($_GET['status_filter'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to']   ?? '');

$where = [];
if ($search !== '')       $where[] = "s.supplierName LIKE '%" . $conn->real_escape_string($search) . "%'";
if ($statusFilter !== '') $where[] = "po.status = '" . $conn->real_escape_string($statusFilter) . "'";
if ($dateFrom !== '')     $where[] = "po.orderDate >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo !== '')       $where[] = "po.orderDate <= '" . $conn->real_escape_string($dateTo) . "'";

if (!in_array($_SESSION['role'], ['Owner', 'Cashier'])) {
    header("Location: ../../pages/dashboard.php"); exit;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Distinct statuses
$statusRes = $conn->query("SELECT DISTINCT status FROM PurchaseOrder ORDER BY status");
$statuses = [];
while ($s = $statusRes->fetch_assoc()) $statuses[] = $s['status'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Purchase Orders — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Purchase Orders</div>
      <div class="topbar-breadcrumb">Manage supplier purchase orders</div>
    </div>
  </div>

  <div class="main-content">
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">✅ Purchase order saved successfully!</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-title">Purchase Orders</div>
      <a href="../../pages/reports/purchase.php" class="btn btn-sm btn-outline" style="margin-right:4px;">📊 Report</a>
      <a href="create.php" class="btn btn-amber">+ New Purchase Order</a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="list.php" class="filter-bar" id="poListFilters">
      <span class="filter-label">Filter:</span>
      <input type="text" name="search" class="filter-input" placeholder="Search supplier…" value="<?= htmlspecialchars($search) ?>">
      <select name="status_filter" class="filter-select">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $st): ?>
        <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" class="filter-input" style="min-width:130px;" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
      <input type="date" name="date_to"   class="filter-input" style="min-width:130px;" value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
      <button type="submit" class="btn btn-sm btn-amber">Apply</button>
      <a href="list.php" class="filter-reset">Reset</a>
    </form>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>PO #</th>
            <th>Supplier</th>
            <th>Order Date</th>
            <th>Total Cost</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $result = $conn->query("
            SELECT po.*, s.supplierName
            FROM PurchaseOrder po
            LEFT JOIN Supplier s ON po.supplierID = s.supplierID
            $whereSql
            ORDER BY po.poID DESC
        ");
        $count = 0;
        while ($row = $result->fetch_assoc()):
          $count++;
        ?>
        <tr>
          <td style="color:var(--muted)">#<?= $row['poID'] ?></td>
          <td><strong><?= htmlspecialchars($row['supplierName'] ?? 'Unknown') ?></strong></td>
          <td><?= date('d M Y', strtotime($row['orderDate'])) ?></td>
          <td><strong>Rs. <?= number_format($row['totalCost'], 2) ?></strong></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($row['status']) ?></span></td>
          <td>
            <a href="view.php?poID=<?= $row['poID'] ?>" class="btn btn-sm btn-warning">View Items</a>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px;">No purchase orders found matching your filters.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  var form = document.getElementById('poListFilters');
  if (!form) return;
  form.addEventListener('submit', function(e) {
    var valid = true;
    form.querySelectorAll('input[type="text"], input[type="search"]').forEach(function(inp) {
      var val = inp.value.trim();
      if (val.length > 100) {
        inp.classList.add('is-invalid');
        valid = false;
      } else {
        inp.value = val.replace(/[<>"']/g, '');
        inp.classList.remove('is-invalid');
      }
    });
    if (!valid) e.preventDefault();
  });
  // live feedback
  form.querySelectorAll('input[type="text"], input[type="search"]').forEach(function(inp) {
    inp.addEventListener('input', function() {
      if (inp.value.length > 100) inp.classList.add('is-invalid');
      else inp.classList.remove('is-invalid');
    });
  });
})();
</script>
</body>
</html>
