<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: list.php"); exit; }

// Fetch part
$part = $conn->query("SELECT * FROM SparePart WHERE partID = $id")->fetch_assoc();
if (!$part) { header("Location: list.php"); exit; }

// Fetch purchase history — all POs that contained this part
$history = $conn->query("
    SELECT pi.*, po.orderDate, po.status, po.poID, s.supplierName, s.supplierID
    FROM PurchaseItem pi
    JOIN PurchaseOrder po ON pi.poID = po.poID
    LEFT JOIN Supplier s ON po.supplierID = s.supplierID
    WHERE pi.partID = $id
    ORDER BY po.orderDate DESC
");

// Fetch sales history — all sales that included this part
$sales = $conn->query("
    SELECT si.*, sa.saleDate, sa.saleID, sa.customerName
    FROM SaleItem si
    JOIN Sale sa ON si.saleID = sa.saleID
    WHERE si.partName = '{$conn->real_escape_string($part['partName'])}'
    ORDER BY sa.saleDate DESC
");

$currentPage = 'inventory';
$base = '../../';
$isLow = $part['currentQuantity'] < $part['minQuantity'];

// Find most recent supplier from purchase history
$supplierRes = $conn->query("
    SELECT s.supplierID, s.supplierName FROM PurchaseItem pi
    JOIN PurchaseOrder po ON pi.poID = po.poID
    JOIN Supplier s ON po.supplierID = s.supplierID
    WHERE pi.partID = $id
    ORDER BY po.orderDate DESC LIMIT 1
");
$lastSupplier = $supplierRes->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($part['partName']) ?> — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <style>
    .detail-grid  { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:8px; }
    .detail-item label { display:block; font-size:.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
    .detail-item span  { font-weight:600; font-size:1rem; }
    .section-title { font-size:1rem; font-weight:700; margin:28px 0 12px; border-bottom:1px solid var(--border); padding-bottom:8px; }
    .supplier-box  { display:flex; align-items:center; justify-content:space-between; background:var(--card-bg); border:1px solid var(--border); border-radius:10px; padding:14px 18px; margin-bottom:24px; }
    .supplier-box .sup-name { font-weight:700; font-size:1rem; }
    .supplier-box .sup-sub  { color:var(--muted); font-size:.85rem; margin-top:2px; }
  </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Part Details</div>
      <div class="topbar-breadcrumb">Inventory → <?= htmlspecialchars($part['partName']) ?></div>
    </div>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div class="page-title"><?= htmlspecialchars($part['partName']) ?></div>
      <div style="display:flex;gap:8px;">
        <?php if (in_array($_SESSION['role'], ['Owner','Cashier'])): ?>
        <a href="create.php?id=<?= $id ?>" class="btn btn-warning">✏ Edit Part</a>
        <?php endif; ?>
        <a href="list.php" class="btn btn-secondary">← Back</a>
      </div>
    </div>

    <!-- Part Details Card -->
    <div class="card mb-3">
      <div class="card-header">Part Information</div>
      <div class="card-body">
        <div class="detail-grid">
          <div class="detail-item">
            <label>Brand</label>
            <span><?= htmlspecialchars($part['brandName'] ?: '—') ?></span>
          </div>
          <div class="detail-item">
            <label>Category</label>
            <span><?= htmlspecialchars($part['category'] ?: '—') ?></span>
          </div>
          <div class="detail-item">
            <label>Size</label>
            <span><?= htmlspecialchars($part['size'] ?: '—') ?></span>
          </div>
          <div class="detail-item">
            <label>Selling Price</label>
            <span>Rs. <?= number_format($part['sellingPrice'], 2) ?></span>
          </div>
          <?php if ($_SESSION['role'] === 'Owner'): ?>
          <div class="detail-item">
            <label>Bought Price</label>
            <span>Rs. <?= number_format($part['boughtPrice'], 2) ?></span>
          </div>
          <?php endif; ?>
          <div class="detail-item">
            <label>Current Stock</label>
            <span>
              <?= $part['currentQuantity'] ?>
              <?php if ($isLow): ?>
                <span class="badge badge-danger" style="margin-left:6px;">⚠ Low</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="detail-item">
            <label>Min. Quantity</label>
            <span><?= $part['minQuantity'] ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Supplier + Quick PO Button -->
    <div class="section-title">🏭 Supplier</div>
    <?php if ($lastSupplier): ?>
    <div class="supplier-box">
      <div>
        <div class="sup-name"><?= htmlspecialchars($lastSupplier['supplierName']) ?></div>
        <div class="sup-sub">Last known supplier for this part</div>
      </div>
      <a href="../purchase/create.php?supplierID=<?= $lastSupplier['supplierID'] ?>&prefill=<?= urlencode($part['partName']) ?>"
         class="btn btn-amber">+ New Purchase Order</a>
    </div>
    <?php else: ?>
    <p style="color:var(--muted);font-size:.9rem;margin-bottom:16px;">No supplier linked yet — supplier is linked automatically when a purchase order is received.</p>
    <a href="../purchase/create.php" class="btn btn-amber">+ New Purchase Order</a>
    <?php endif; ?>

    <!-- Purchase History -->
    <div class="section-title">📦 Purchase History</div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>PO #</th><th>Supplier</th><th>Qty Ordered</th><th>Bought Price</th><th>Date</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($history->num_rows === 0): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:20px;">No purchase history yet.</td></tr>
        <?php else: ?>
          <?php while ($h = $history->fetch_assoc()): ?>
          <tr>
            <td><strong>#<?= $h['poID'] ?></strong></td>
            <td><?= htmlspecialchars($h['supplierName'] ?? '—') ?></td>
            <td><?= $h['quantity'] ?></td>
            <td>Rs. <?= number_format($h['boughtPrice'], 2) ?></td>
            <td><?= date('d M Y', strtotime($h['orderDate'])) ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($h['status']) ?></span></td>
            <td><a href="../purchase/view.php?poID=<?= $h['poID'] ?>" class="btn btn-sm btn-outline">View PO</a></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Sales History -->
    <div class="section-title">🛒 Used in Sales</div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Sale #</th><th>Customer</th><th>Qty Sold</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($sales->num_rows === 0): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px;">This part hasn't been sold yet.</td></tr>
        <?php else: ?>
          <?php while ($s = $sales->fetch_assoc()): ?>
          <tr>
            <td><strong>#<?= $s['saleID'] ?></strong></td>
            <td><?= htmlspecialchars($s['customerName'] ?? '—') ?></td>
            <td><?= $s['quantity'] ?></td>
            <td><?= date('d M Y', strtotime($s['saleDate'])) ?></td>
            <td><a href="../sale/view_invoice.php?saleID=<?= $s['saleID'] ?>" class="btn btn-sm btn-outline">Invoice</a></td>
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