<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

if (isset($_GET['delete']) && in_array($_SESSION['role'], ['Owner', 'Cashier'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM sparepart WHERE partID = $id");
    header("Location: list.php?deleted=1");
    exit;
}

$currentPage = 'inventory';
$base = '../../';

$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');
$stockFilter = trim($_GET['stock'] ?? '');

$where = [];
if ($search !== '')   $where[] = "(partName LIKE '%" . $conn->real_escape_string($search) . "%' OR brandName LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($category !== '') $where[] = "category = '" . $conn->real_escape_string($category) . "'";
if ($stockFilter === 'low')    $where[] = "currentQuantity < minQuantity";
if ($stockFilter === 'instock') $where[] = "currentQuantity >= minQuantity";

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$catRes = $conn->query("SELECT DISTINCT category FROM sparepart ORDER BY category");
$categories = [];
while ($c = $catRes->fetch_assoc()) $categories[] = $c['category'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Spare Parts Inventory</div>
      <div class="topbar-breadcrumb">Track and manage all parts</div>
    </div>
  </div>

  <div class="main-content">
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">âœ… Part saved successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert alert-warning">ðŸ—‘ Part deleted.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-title">Spare Parts Inventory</div>
      <a href="../../pages/reports/inventory.php" class="btn btn-sm btn-outline" style="margin-right:4px;">ðŸ“Š Report</a>
      <a href="create.php" class="btn btn-amber">+ Add New Part</a>
    </div>

    <form method="GET" action="list.php" class="filter-bar" id="invListFilters">
      <span class="filter-label">Filter:</span>
      <input type="text" name="search" class="filter-input" placeholder="Search part or brandâ€¦" value="<?= htmlspecialchars($search) ?>">
      <select name="category" class="filter-select">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="stock" class="filter-select">
        <option value="">All Stock</option>
        <option value="low"     <?= $stockFilter === 'low'     ? 'selected' : '' ?>>âš  Low Stock</option>
        <option value="instock" <?= $stockFilter === 'instock' ? 'selected' : '' ?>>âœ… In Stock</option>
      </select>
      <button type="submit" class="btn btn-sm btn-amber">Apply</button>
      <a href="list.php" class="filter-reset">Reset</a>
    </form>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Part Name</th>
            <th>Brand</th>
            <th>Category</th>
            <th>Size</th>
            <th>Selling Price</th>
            <th>Quantity</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $result = $conn->query("SELECT * FROM sparepart $whereSql ORDER BY partID DESC");
        $count = 0;
        while ($row = $result->fetch_assoc()):
          $count++;
          $isLow = $row['currentQuantity'] < $row['minQuantity'];
        ?>
        <tr>
          <td><a href="view.php?id=<?= $row['partID'] ?>" style="color:var(--amber);font-weight:700;text-decoration:none;"><?= htmlspecialchars($row['partName']) ?></a></td>
          <td><?= htmlspecialchars($row['brandName']) ?></td>
          <td><span class="badge badge-dark"><?= htmlspecialchars($row['category']) ?></span></td>
          <td><?= htmlspecialchars($row['size']) ?: '<span class="text-muted">â€”</span>' ?></td>
          <td>Rs. <?= number_format($row['sellingPrice'], 2) ?></td>
          <td><strong><?= $row['currentQuantity'] ?></strong></td>
          <td>
            <?php if ($isLow): ?>
              <span class="badge badge-danger">âš  Low Stock</span>
            <?php else: ?>
              <span class="badge badge-success">In Stock</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-2">
              <a href="create.php?id=<?= $row['partID'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <?php if (in_array($_SESSION['role'], ['Owner','Cashier'])): ?>
              <a href="?delete=<?= $row['partID'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete this part?')">Delete</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px;">No parts found matching your filters.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  var form = document.getElementById('invListFilters');
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
