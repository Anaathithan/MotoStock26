<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

if (isset($_GET['delete']) && in_array($_SESSION['role'], ['Owner', 'Cashier']) && csrf_validate($_GET['csrf_token'] ?? '')) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM Customer WHERE customerID = $id");
    header("Location: list.php?deleted=1");
    exit;
}

$currentPage = 'customer';
$base = '../../';

// ── Filters ──────────────────────────────────────────────────────────────────
$search     = trim($_GET['search']  ?? '');
$serviceDue = trim($_GET['due']     ?? '');

$today = date('Y-m-d');
$where = [];
if ($search !== '') $where[] = "(name LIKE '%" . $conn->real_escape_string($search) . "%' OR phone LIKE '%" . $conn->real_escape_string($search) . "%' OR vehicleNo LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($serviceDue === 'overdue')   $where[] = "nextServiceDue IS NOT NULL AND nextServiceDue < '$today'";
if ($serviceDue === 'upcoming')  $where[] = "nextServiceDue IS NOT NULL AND nextServiceDue >= '$today' AND nextServiceDue <= DATE_ADD('$today', INTERVAL 7 DAY)";

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Customers</div>
      <div class="topbar-breadcrumb">Manage customer records</div>
    </div>
  </div>

  <div class="main-content">
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">✅ Customer saved successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert alert-warning">🗑 Customer deleted.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-title">Customers</div>
      <a href="../../pages/reports/customers.php" class="btn btn-sm btn-outline" style="margin-right:4px;">📊 Report</a>
      <a href="create.php" class="btn btn-amber">+ Add Customer</a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="list.php" class="filter-bar" id="custListFilters">
      <span class="filter-label">Filter:</span>
      <input type="text" name="search" class="filter-input" placeholder="Search name, phone, vehicle…" value="<?= htmlspecialchars($search) ?>">
      <select name="due" class="filter-select">
        <option value="">Service Due: All</option>
        <option value="overdue"  <?= $serviceDue === 'overdue'  ? 'selected' : '' ?>>⚠ Overdue</option>
        <option value="upcoming" <?= $serviceDue === 'upcoming' ? 'selected' : '' ?>>📅 Due This Week</option>
      </select>
      <button type="submit" class="btn btn-sm btn-amber">Apply</button>
      <a href="list.php" class="filter-reset">Reset</a>
    </form>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Vehicle No</th>
            <th>Last Service</th>
            <th>Next Due</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $result = $conn->query("SELECT * FROM Customer $whereSql ORDER BY customerID DESC");
        $count = 0;
        while ($row = $result->fetch_assoc()):
          $count++;
          $isOverdue = $row['nextServiceDue'] && $row['nextServiceDue'] < $today;
        ?>
        <tr>
          <td><a href="view.php?id=<?= $row['customerID'] ?>" style="color:var(--amber);font-weight:700;text-decoration:none;"><?= htmlspecialchars($row['name']) ?></a></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td><span class="badge badge-dark"><?= htmlspecialchars($row['vehicleNo']) ?></span></td>
          <td><?= $row['lastServiceDate'] ? date('d M Y', strtotime($row['lastServiceDate'])) : '<span class="text-muted">—</span>' ?></td>
          <td>
            <?php if ($row['nextServiceDue']): ?>
              <?php if ($isOverdue): ?>
                <span class="badge badge-danger">⚠ <?= date('d M Y', strtotime($row['nextServiceDue'])) ?></span>
              <?php else: ?>
                <?= date('d M Y', strtotime($row['nextServiceDue'])) ?>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-2">
              <a href="create.php?id=<?= $row['customerID'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <?php if (in_array($_SESSION['role'], ['Owner','Cashier'])): ?>
              <a href="?delete=<?= $row['customerID'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete this customer?')">Delete</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px;">No customers found matching your filters.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  var form = document.getElementById('custListFilters');
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
