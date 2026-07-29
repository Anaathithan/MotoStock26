<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['userID'])) {
    header("Location: ../../login.php");
    exit;
}

$poID = isset($_GET['poID']) ? (int)$_GET['poID'] : 0;

if (!in_array($_SESSION['role'], ['Owner', 'Cashier'])) {
    header("Location: ../../pages/dashboard.php"); exit;
}

if ($poID === 0) {
    header("Location: list.php");
    exit;
}

// Fetch order + supplier
$order = $conn->query("
    SELECT po.*, s.supplierName, s.contact 
    FROM PurchaseOrder po 
    LEFT JOIN Supplier s ON po.supplierID = s.supplierID 
    WHERE po.poID = $poID
")->fetch_assoc();

if (!$order) {
    header("Location: list.php");
    exit;
}

// Fetch items
$items = $conn->query("SELECT * FROM PurchaseItem WHERE poID = $poID");

// Update status (Owner & Cashier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && in_array($_SESSION['role'], ['Owner', 'Cashier']) && csrf_validate($_POST['csrf_token'] ?? '')) {
    $allowedStatuses = ['Pending', 'Received', 'Partial'];
    $newStatus = trim($_POST['status']);
    if (!in_array($newStatus, $allowedStatuses, true)) {
        header("Location: view.php?poID=$poID");
        exit;
    }

    // ── If marking as Received and not already pushed to inventory ────────────
    if ($newStatus === 'Received' && !$order['inventoryUpdated']) {
        $itemsToAdd = $conn->query("SELECT * FROM PurchaseItem WHERE poID = $poID");

        while ($item = $itemsToAdd->fetch_assoc()) {
            $partName = $conn->real_escape_string($item['partName']);
            $qty      = (int)$item['quantity'];
            $price    = (float)$item['boughtPrice'];

            // Check if a part with this exact name already exists in SparePart
            $existing = $conn->query("SELECT partID, currentQuantity FROM SparePart WHERE partName = '$partName' LIMIT 1")->fetch_assoc();

            if ($existing) {
                // Part exists — just increment quantity and update bought price
                $newQty = $existing['currentQuantity'] + $qty;
                $conn->query("UPDATE SparePart SET currentQuantity = $newQty, boughtPrice = $price WHERE partID = {$existing['partID']}");
                // Link this purchase item to the existing part
                $conn->query("UPDATE PurchaseItem SET partID = {$existing['partID']} WHERE purchaseItemID = {$item['purchaseItemID']}");
            } else {
                // Part doesn't exist — create a new inventory entry
                $stmt = $conn->prepare("INSERT INTO SparePart (partName, boughtPrice, currentQuantity, minQuantity) VALUES (?, ?, ?, 1)");
                $stmt->bind_param("sdi", $partName, $price, $qty);
                $stmt->execute();
                $newPartID = $conn->insert_id;
                // Link this purchase item to the new part
                $conn->query("UPDATE PurchaseItem SET partID = $newPartID WHERE purchaseItemID = {$item['purchaseItemID']}");
            }
        }

        // Mark PO as inventory updated
        $conn->query("UPDATE PurchaseOrder SET status = '$newStatus', inventoryUpdated = 1 WHERE poID = $poID");
        // Collect all partIDs that were newly created (not pre-existing)
        $newPartIDs = [];
        $checkItems = $conn->query("SELECT partID FROM PurchaseItem WHERE poID = $poID AND partID IS NOT NULL");
        while ($r = $checkItems->fetch_assoc()) {
          $newPartIDs[] = $r['partID'];
        }

        if (!empty($newPartIDs)) {
          // Redirect to first part — pass remaining ones as a queue in the URL
          $queue = implode(',', array_slice($newPartIDs, 1));
          $firstID = $newPartIDs[0];
          header("Location: ../../pages/inventory/create.php?id=$firstID&from_po=$poID&queue=" . urlencode($queue));
        } else {
          header("Location: view.php?poID=$poID&updated=1&stocked=1");
        }
        exit;
    }

    $conn->query("UPDATE PurchaseOrder SET status = '$newStatus' WHERE poID = $poID");
    header("Location: view.php?poID=$poID&updated=1");
    exit;
}

// Delete entire order (Owner only)
if (isset($_GET['delete']) && $_SESSION['role'] === 'Owner' && csrf_validate($_GET['csrf_token'] ?? '')) {
    $conn->query("DELETE FROM PurchaseItem WHERE poID = $poID");
    $conn->query("DELETE FROM PurchaseOrder WHERE poID = $poID");
    header("Location: list.php?deleted=1");
    exit;
}

// Define variables for sidebar
$currentPage = 'purchase';
$base = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Purchase Order #<?= $poID ?> — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Purchase Order Details</div>
      <div class="topbar-breadcrumb">PO #<?= $poID ?> — <?= htmlspecialchars($order['supplierName'] ?? 'Unknown') ?></div>
    </div>
  </div>

  <div class="main-content">

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success">✅ Status updated successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['stocked'])): ?>
      <div class="alert alert-success">✅ Items have been added to inventory.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-title">Purchase Order #<?= $poID ?></div>
      <a href="list.php" class="btn btn-secondary">← Back to List</a>
    </div>

    <!-- Order Information -->
    <div class="card mb-3">
      <div class="card-header">Order Information</div>
      <div class="card-body">

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
          <div>
            <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">PO ID</div>
            <div style="font-weight:700;font-size:1rem;">#<?= $poID ?></div>
          </div>
          <div>
            <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Supplier</div>
            <div style="font-weight:700;font-size:1rem;"><?= htmlspecialchars($order['supplierName'] ?? 'Unknown') ?></div>
          </div>
          <div>
            <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Order Date</div>
            <div style="font-weight:700;font-size:1rem;"><?= date('d M Y', strtotime($order['orderDate'])) ?></div>
          </div>
          <div>
            <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Total Cost</div>
            <div style="font-weight:700;font-size:1rem;">Rs. <?= number_format($order['totalCost'], 2) ?></div>
          </div>
          <div>
            <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Status</div>
            <div><span class="badge badge-info"><?= htmlspecialchars($order['status']) ?></span></div>
          </div>
        </div>

        <!-- Status Update Form -->
        <?php if (in_array($_SESSION['role'], ['Owner', 'Cashier'])): ?>
        <form method="post" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="form-group" style="margin-bottom:0;min-width:200px;">
            <label class="form-label">Update Status</label>
            <select name="status" class="form-control">
              <option value="Pending"  <?= $order['status'] === 'Pending'  ? 'selected' : '' ?>>Pending</option>
              <option value="Received" <?= $order['status'] === 'Received' ? 'selected' : '' ?>>Received</option>
              <option value="Partial"  <?= $order['status'] === 'Partial'  ? 'selected' : '' ?>>Partial</option>
            </select>
          </div>
          <button type="submit" class="btn btn-amber">Update Status</button>
          <?php if ($order['inventoryUpdated']): ?>
            <span style="color:var(--muted);font-size:.82rem;align-self:center;">✅ Inventory already updated for this order.</span>
          <?php endif; ?>
        </form>
        <?php endif; ?>

      </div>
    </div>

    <!-- Items Table -->
    <div class="card mb-3">
      <div class="card-header">Order Items</div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Part Name / Part No.</th>
              <th>Quantity</th>
              <th>Price per Unit</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
          <?php
          // Re-fetch items since pointer may be exhausted
          $items = $conn->query("SELECT * FROM PurchaseItem WHERE poID = $poID");
          while ($item = $items->fetch_assoc()):
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($item['partName']) ?></strong></td>
            <td><?= $item['quantity'] ?></td>
            <td>Rs. <?= number_format($item['boughtPrice'], 2) ?></td>
            <td><strong>Rs. <?= number_format($item['quantity'] * $item['boughtPrice'], 2) ?></strong></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Delete Button (Owner only) -->
    <?php if ($_SESSION['role'] === 'Owner'): ?>
    <div style="display:flex;justify-content:flex-end;">
      <a href="?poID=<?= $poID ?>&delete=1&csrf_token=<?= urlencode(csrf_token()) ?>"
         class="btn btn-danger"
         onclick="return confirm('Delete this entire purchase order? This cannot be undone.')">
        🗑 Delete Purchase Order
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>
</body>
</html>