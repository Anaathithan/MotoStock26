<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: list.php"); exit; }

// â”€â”€ Fetch customer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$res = $conn->query("SELECT * FROM customer WHERE customerID = $id");
if ($res->num_rows === 0) { header("Location: list.php"); exit; }
$customer = $res->fetch_assoc();

// â”€â”€ Fetch service job history â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$jobs = $conn->query("SELECT * FROM servicejob WHERE customerID = $id ORDER BY created_at DESC");

// â”€â”€ Fetch sales history â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$sales = $conn->query("SELECT * FROM sale WHERE customerID = $id ORDER BY saleDate DESC");

$currentPage = 'customer';
$base = '../../';
$today = date('Y-m-d');
$isOverdue = $customer['nextServiceDue'] && $customer['nextServiceDue'] < $today;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($customer['name']) ?> â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <style>
    .profile-header { display:flex; align-items:center; gap:20px; margin-bottom:24px; }
    .profile-avatar { width:64px; height:64px; border-radius:50%; background:var(--amber); display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:800; color:#000; flex-shrink:0; }
    .profile-name   { font-size:1.4rem; font-weight:700; }
    .profile-sub    { color:var(--muted); font-size:.9rem; margin-top:2px; }
    .detail-grid    { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
    .detail-item label { display:block; font-size:.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
    .detail-item span  { font-weight:600; }
    .section-title  { font-size:1rem; font-weight:700; margin:28px 0 12px; border-bottom:1px solid var(--border); padding-bottom:8px; }
    .empty-state    { color:var(--muted); font-size:.9rem; padding:16px 0; }
    .status-badge   { display:inline-block; padding:2px 10px; border-radius:20px; font-size:.78rem; font-weight:600; }
    .status-Investigating  { background:#2d3a4a; color:#7eb8f7; }
    .status-InformingCustomer { background:#3a2d10; color:#f7c97e; }
    .status-Repairing      { background:#1a3a2d; color:#7ef7b8; }
    .status-Finished       { background:#1a2d1a; color:#7ef77e; }
  </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Customer Profile</div>
      <div class="topbar-breadcrumb">Customers â†’ <?= htmlspecialchars($customer['name']) ?></div>
    </div>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div class="page-title">Customer Profile</div>
      <div style="display:flex;gap:8px;">
        <?php if (in_array($_SESSION['role'], ['Owner','Cashier'])): ?>
        <a href="create.php?id=<?= $id ?>" class="btn btn-warning">âœ Edit Customer</a>
        <?php endif; ?>
        <a href="list.php" class="btn btn-secondary">â† Back to List</a>
      </div>
    </div>

    <!-- Profile Header -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="profile-header">
          <div class="profile-avatar"><?= strtoupper(mb_substr($customer['name'], 0, 1)) ?></div>
          <div>
            <div class="profile-name"><?= htmlspecialchars($customer['name']) ?></div>
            <div class="profile-sub">
              <?= $customer['phone'] ? 'ðŸ“ž ' . htmlspecialchars($customer['phone']) : '' ?>
              <?= $customer['email'] ? ' &nbsp;âœ‰ ' . htmlspecialchars($customer['email']) : '' ?>
            </div>
          </div>
        </div>

        <div class="detail-grid">
          <div class="detail-item">
            <label>Vehicle Number</label>
            <span><?= $customer['vehicleNo'] ? htmlspecialchars($customer['vehicleNo']) : 'â€”' ?></span>
          </div>
          <div class="detail-item">
            <label>Last Service</label>
            <span><?= $customer['lastServiceDate'] ? date('d M Y', strtotime($customer['lastServiceDate'])) : 'â€”' ?></span>
          </div>
          <div class="detail-item">
            <label>Next Service Due</label>
            <span>
              <?php if ($customer['nextServiceDue']): ?>
                <?php if ($isOverdue): ?>
                  <span class="badge badge-danger">âš  <?= date('d M Y', strtotime($customer['nextServiceDue'])) ?></span>
                <?php else: ?>
                  <?= date('d M Y', strtotime($customer['nextServiceDue'])) ?>
                <?php endif; ?>
              <?php else: ?>â€”<?php endif; ?>
            </span>
          </div>
          <div class="detail-item">
            <label>Customer Since</label>
            <span><?= date('d M Y', strtotime($customer['createdAt'] ?? 'now')) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Service Job History -->
    <div class="section-title">ðŸ”§ Service Job History</div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Problem</th>
            <th>Status</th>
            <th>Warranty</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($jobs->num_rows === 0): ?>
          <tr><td colspan="6" class="empty-state" style="text-align:center">No service jobs found for this customer.</td></tr>
        <?php else: ?>
          <?php while ($job = $jobs->fetch_assoc()): ?>
          <tr>
            <td><strong>#<?= $job['jobID'] ?></strong></td>
            <td><?= htmlspecialchars($job['problemDescription']) ?></td>
            <td><span class="status-badge status-<?= htmlspecialchars(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$job['status'])) ?>"><?= htmlspecialchars($job['status']) ?></span></td>
            <td><?= $job['isWarranty'] ? '<span class="badge badge-success">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
            <td><?= date('d M Y', strtotime($job['created_at'])) ?></td>
            <td><a href="../repair/list.php?search=<?= urlencode($job['bikeNo']) ?>" class="btn btn-sm btn-outline">View</a></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Sales History -->
    <div class="section-title">ðŸ›’ Purchase History</div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Sale #</th>
            <th>Date</th>
            <th>Total</th>
            <th>Discount</th>
            <th>Payment</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($sales->num_rows === 0): ?>
          <tr><td colspan="6" class="empty-state" style="text-align:center">No purchases found for this customer.</td></tr>
        <?php else: ?>
          <?php while ($sale = $sales->fetch_assoc()): ?>
          <tr>
            <td><strong>#<?= $sale['saleID'] ?></strong></td>
            <td><?= date('d M Y', strtotime($sale['saleDate'])) ?></td>
            <td>Rs. <?= number_format($sale['grandTotal'], 2) ?></td>
            <td><?= $sale['discountPercent'] > 0 ? $sale['discountPercent'] . '%' : '<span class="text-muted">â€”</span>' ?></td>
            <td><?= htmlspecialchars($sale['paymentMethod']) ?></td>
            <td><a href="../sale/view_invoice.php?saleID=<?= $sale['saleID'] ?>" class="btn btn-sm btn-outline">Invoice</a></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</body>
</html>
