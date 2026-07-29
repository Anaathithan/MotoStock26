<?php
// pages/sale/edit_sale.php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$saleID  = (int)($_GET['saleID'] ?? 0);
$error   = '';
$success = '';

if (!$saleID) { header("Location: sales_list.php"); exit; }

// ── Fetch Sale ────────────────────────────────────────────
$sale = $conn->query("SELECT * FROM Sale WHERE saleID = $saleID")->fetch_assoc();
if (!$sale) { header("Location: sales_list.php"); exit; }

// ── Fetch Items ───────────────────────────────────────────
$itemsRes = $conn->query("SELECT * FROM SaleItem WHERE saleID = $saleID");
$items = [];
while ($row = $itemsRes->fetch_assoc()) { $items[] = $row; }

$discountAmount = isset($sale['discountAmount'])
    ? (float)$sale['discountAmount']
    : round((float)$sale['subTotal'] * ((float)$sale['discountPercent'] / 100), 2);

// ── Handle Update ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPaymentMethod  = $_POST['paymentMethod']  ?? $sale['paymentMethod'];
    $newCustomerName   = trim($_POST['customerName'] ?? $sale['customerName']);
    $newAmountReceived = !empty($_POST['amountReceived']) ? (float)$_POST['amountReceived'] : null;

    // Build update query (amountReceived may be a new column — use ALTER if needed)
    $stmt = $conn->prepare("
        UPDATE Sale
        SET customerName   = ?,
            paymentMethod  = ?,
            amountReceived = ?
        WHERE saleID = ?
    ");
    $stmt->bind_param("ssdi", $newCustomerName, $newPaymentMethod, $newAmountReceived, $saleID);

    if ($stmt->execute()) {
        $success = "Sale updated successfully!";
        $sale    = $conn->query("SELECT * FROM Sale WHERE saleID = $saleID")->fetch_assoc();
    } else {
        $error = "Update failed: " . $conn->error;
    }
    $stmt->close();
}

$currentPage = 'sale';
$base        = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sale #<?= $saleID ?> — MotoStock26</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
    <div class="topbar">
        <div>
            <div class="topbar-title">Edit Sale #<?= $saleID ?></div>
            <div class="topbar-breadcrumb">Sales › Edit</div>
        </div>
        <a href="view_invoice.php?saleID=<?= $saleID ?>" class="btn btn-secondary btn-sm">← Back to Invoice</a>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div class="page-title">Edit Sale</div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-3">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success mb-3">✓ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Sale Info (read-only) -->
        <div class="card mb-3">
            <div class="card-header">Sale Information</div>
            <div class="card-body">
                <div class="form-grid-3">
                    <div>
                        <div class="form-label">Invoice #</div>
                        <div style="font-weight:700;color:var(--amber-dk)">#<?= $saleID ?></div>
                    </div>
                    <div>
                        <div class="form-label">Sale Date</div>
                        <div style="font-weight:500"><?= date('d M Y, H:i', strtotime($sale['saleDate'])) ?></div>
                    </div>
                    <div>
                        <div class="form-label">Grand Total</div>
                        <div style="font-weight:700">Rs. <?= number_format((float)$sale['grandTotal'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items (read-only) -->
        <div class="card mb-3">
            <div class="card-header">
                Sale Items
                <span style="font-size:.78rem;color:var(--muted);font-weight:400">(Items cannot be changed — use Return if needed)</span>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-wrap" style="border-radius:0;border:none;box-shadow:none">
                    <table class="table">
                        <thead>
                            <tr><th>#</th><th>Part Name</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i => $item): ?>
                            <?php
                                $unitPrice = (float)($item['sellingPrice'] ?? $item['unitPrice'] ?? 0);
                                $itemTotal = (float)($item['itemTotal'] ?? $item['quantity'] * $unitPrice);
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($item['partName']) ?></strong></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>Rs. <?= number_format($unitPrice, 2) ?></td>
                                <td><strong>Rs. <?= number_format($itemTotal, 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align:right;color:var(--muted)">Sub Total</td>
                                <td><strong>Rs. <?= number_format((float)$sale['subTotal'], 2) ?></strong></td>
                            </tr>
                            <?php if ($discountAmount > 0): ?>
                            <tr>
                                <td colspan="4" style="text-align:right;color:var(--green)">Discount (<?= (float)$sale['discountPercent'] ?>%)</td>
                                <td style="color:var(--green)">− Rs. <?= number_format($discountAmount, 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="pos-grand">
                                <td colspan="4" style="text-align:right">Grand Total</td>
                                <td>Rs. <?= number_format((float)$sale['grandTotal'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Editable Fields -->
        <form method="POST">
            <div class="card mb-3" style="max-width:560px">
                <div class="card-header">Editable Details</div>
                <div class="card-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customerName" class="form-control"
                                   value="<?= htmlspecialchars($sale['customerName'] ?? '') ?>"
                                   placeholder="Walk-in Customer">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Method</label>
                            <select name="paymentMethod" class="form-control">
                                <option value="Cash"            <?= $sale['paymentMethod'] === 'Cash'            ? 'selected' : '' ?>>💵 Cash</option>
                                <option value="Online Transfer" <?= $sale['paymentMethod'] === 'Online Transfer' ? 'selected' : '' ?>>🏦 Online Transfer</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Amount Received (Rs.) <span style="color:var(--muted);font-size:.78rem">(optional)</span></label>
                            <input type="number" name="amountReceived" class="form-control" step="0.01"
                                   value="<?= htmlspecialchars($sale['amountReceived'] ?? '') ?>"
                                   placeholder="Enter amount received">
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-bottom:32px">
                <button type="submit" class="btn btn-amber">💾 Save Changes</button>
                <a href="view_invoice.php?saleID=<?= $saleID ?>" class="btn btn-secondary">Cancel</a>
                <a href="sale_return.php?saleID=<?= $saleID ?>" class="btn btn-danger"
                   onclick="return confirm('Record a return? This cannot be undone.')">↩ Record Return</a>
            </div>
        </form>

    </div>
</div>
</body>
</html>
