<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/notify_events.php';

if (!isset($_SESSION['userID'])) { 
    header("Location: ../../login.php"); 
    exit; 
}

// Delete functionality - Only when status is 'Finished'
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (csrf_validate($_GET['csrf_token'] ?? '')) {
        $check = $conn->query("SELECT status FROM servicejob WHERE jobID = $id")->fetch_assoc();
        if ($check && $check['status'] === 'Finished') {
            $stmt = $conn->prepare("DELETE FROM servicejob WHERE jobID = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            header("Location: list.php?deleted=1");
            exit;
        }
    }
}

// Quick status update
if (isset($_GET['status']) && isset($_GET['id'])) {
    $allowedStatuses = ['Investigating', 'Repairing', 'Finished'];
    $newStatus = trim($_GET['status']);
    $id = (int)$_GET['id'];
    if (in_array($newStatus, $allowedStatuses, true) && csrf_validate($_GET['csrf_token'] ?? '')) {
        $stmt = $conn->prepare("UPDATE servicejob SET status = ? WHERE jobID = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();
        // Auto-notify on finished
        if ($newStatus === 'Finished') {
            $jRes = $conn->query("SELECT * FROM servicejob WHERE jobID = $id");
            if ($jRes && $jRes->num_rows > 0) notify_repair_finished($conn, $jRes->fetch_assoc());
        }
    }
    header("Location: list.php");
    exit;
}

$currentPage = 'repair';
$base = '../../';

// ── Filters ──────────────────────────────────────────────────────────────────
$search      = trim($_GET['search']   ?? '');
$statusFilter = trim($_GET['status_filter'] ?? '');
$warrantyFilter = trim($_GET['warranty'] ?? '');

$where = [];
if ($search !== '')       $where[] = "(bikeNo LIKE '%" . $conn->real_escape_string($search) . "%' OR problemDescription LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($statusFilter !== '') $where[] = "status = '" . $conn->real_escape_string($statusFilter) . "'";
if ($warrantyFilter === '1') $where[] = "isWarranty = 1";
if ($warrantyFilter === '0') $where[] = "isWarranty = 0";

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Repair Jobs — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Repair Jobs</div>
      <div class="topbar-breadcrumb">Manage all service &amp; repair jobs</div>
    </div>
  </div>

  <div class="main-content">
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">✅ Repair job added successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert alert-success">✅ Finished repair job deleted successfully.</div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <div class="page-title">Repair Jobs</div>
      </div>
      <a href="../../pages/reports/repair.php" class="btn btn-sm btn-outline" style="margin-right:4px;">📊 Report</a>
      <a href="create.php" class="btn btn-amber">+ New Repair</a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="list.php" class="filter-bar" id="repairListFilters">
      <span class="filter-label">Filter:</span>
      <input type="text" name="search" class="filter-input" placeholder="Search bike no. or problem…" value="<?= htmlspecialchars($search) ?>">
      <select name="status_filter" class="filter-select">
        <option value="">All Statuses</option>
        <option value="Investigating"   <?= $statusFilter === 'Investigating'   ? 'selected' : '' ?>>🔎 Investigating</option>
        <option value="Repairing" <?= $statusFilter === 'Repairing' ? 'selected' : '' ?>>🔧 Repairing</option>
        <option value="Finished"  <?= $statusFilter === 'Finished'  ? 'selected' : '' ?>>✅ Finished</option>
      </select>
      <select name="warranty" class="filter-select">
        <option value="">Warranty: All</option>
        <option value="1" <?= $warrantyFilter === '1' ? 'selected' : '' ?>>Under Warranty</option>
        <option value="0" <?= $warrantyFilter === '0' ? 'selected' : '' ?>>No Warranty</option>
      </select>
      <button type="submit" class="btn btn-sm btn-amber">Apply</button>
      <a href="list.php" class="filter-reset">Reset</a>
    </form>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Bike No</th>
            <th>Problem</th>
            <th>Status</th>
            <th>Warranty</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $result = $conn->query("SELECT * FROM servicejob $whereSql ORDER BY jobID DESC");
        $count = 0;
        while ($row = $result->fetch_assoc()):
          $count++;
          $warranty = $row['isWarranty'] ? '<span class="badge badge-info">Yes</span>' : '<span class="badge badge-dark">No</span>';
          $badgeClass = match($row['status']) {
            'Finished'  => 'badge-success',
            'Repairing' => 'badge-warning',
            default     => 'badge-info'
          };
        ?>
        <tr>
          <td style="color:var(--muted)"><?= $row['jobID'] ?></td>
          <td><strong><?= htmlspecialchars($row['bikeNo']) ?></strong></td>
          <td style="max-width:260px"><?= htmlspecialchars($row['problemDescription']) ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
          <td><?= $warranty ?></td>
          <td>
            <div class="d-flex gap-2">
              <a href="?status=Repairing&id=<?= $row['jobID'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>" class="btn btn-sm btn-warning">Repairing</a>
              <a href="?status=Finished&id=<?= $row['jobID'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>"  class="btn btn-sm btn-success">Finished</a>
              <?php if ($row['status'] === 'Finished'): ?>
              <a href="?delete=<?= $row['jobID'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>" 
                 class="btn btn-sm btn-danger" 
                 onclick="return confirm('Delete this finished repair job? This action cannot be undone.')">
                 Delete
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px;">No repair jobs found matching your filters.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Filter bar validation
document.querySelector('#repairListFilters')?.addEventListener('submit', function(e) {
  const searchInp = this.querySelector('input[name="search"]');
  const val = searchInp ? searchInp.value.trim() : '';
  if (val.length > 100) {
    searchInp.classList.add('is-invalid');
    e.preventDefault();
    return;
  }
  // Strip dangerous characters client-side
  if (searchInp) searchInp.value = val.replace(/[<>"']/g, '');
  searchInp?.classList.remove('is-invalid');
});
</script>
</body>
</html>
