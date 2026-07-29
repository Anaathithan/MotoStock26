<?php
// pages/sale/sale_return.php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$saleID = (int)($_GET['saleID'] ?? 0);
$error  = '';

if (!$saleID) { header("Location: sales_list.php"); exit; }

// ── Fetch Sale ────────────────────────────────────────────
$sale = $conn->query("SELECT * FROM Sale WHERE saleID = $saleID")->fetch_assoc();
if (!$sale) { header("Location: sales_list.php"); exit; }

// ── Fetch Items ───────────────────────────────────────────
$itemsRes = $conn->query("SELECT * FROM SaleItem WHERE saleID = $saleID");
$items = [];
while ($row = $itemsRes->fetch_assoc()) { $items[] = $row; }

// ── Handle Return ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request token. Please refresh and try again.";
    } else {
    $reason = trim($_POST['reason'] ?? '');
    if (empty($reason)) {
        $error = "Please provide a reason for the return.";
    } else {
        $conn->begin_transaction();
        try {
            $isAlreadyReturned = stripos((string)($sale['customerName'] ?? ''), '[RETURNED:') === 0;
            if ($isAlreadyReturned) {
                throw new Exception('This sale is already marked as returned.');
            }
            // Restore stock for each item (match by partName)
            foreach ($items as $item) {
                $qty  = (int)$item['quantity'];
                $name = $conn->real_escape_string($item['partName']);
                $conn->query("
                    UPDATE SparePart
                    SET currentQuantity = currentQuantity + $qty
                    WHERE partName = '$name'
                    LIMIT 1
                ");
            }

            // Delete the sale items and the sale itself (or you can add a status column later)
            // For now we simply mark the sale by prepending [RETURNED] to customerName
            // so the DB schema stays unchanged while tracking the return
            $note = '[RETURNED: ' . $conn->real_escape_string($reason) . '] ' .
                    $conn->real_escape_string($sale['customerName'] ?? 'Walk-in');
            $conn->query("UPDATE Sale SET customerName = '$note' WHERE saleID = $saleID");

            $conn->commit();
            header("Location: sales_list.php?msg=return_success");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Return failed: " . $e->getMessage();
        }
    }
    }
}

$currentPage = 'sale';
$base        = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Return #<?= $saleID ?> — MotoStock26</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
    <div class="topbar">
        <div>
            <div class="topbar-title">Sale Return #<?= $saleID ?></div>
            <div class="topbar-breadcrumb">Sales › Return</div>
        </div>
        <a href="view_invoice.php?saleID=<?= $saleID ?>" class="btn btn-secondary btn-sm">← Cancel</a>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div class="page-title">Record Sale Return</div>
        </div>

        <div class="alert alert-warning mb-3">
            ⚠ <strong>You are about to return this sale.</strong>
            All sold items will be restored to inventory. This action cannot be undone.
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-3">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Sale Summary -->
        <div class="card mb-3">
            <div class="card-header">Sale Summary</div>
            <div class="card-body">
                <div class="form-grid-3" style="margin-bottom:16px">
                    <div>
                        <div class="form-label">Invoice #</div>
                        <div style="font-weight:700;color:var(--amber-dk)">#<?= $saleID ?></div>
                    </div>
                    <div>
                        <div class="form-label">Customer</div>
                        <div style="font-weight:500"><?= htmlspecialchars($sale['customerName'] ?: 'Walk-in') ?></div>
                    </div>
                    <div>
                        <div class="form-label">Grand Total</div>
                        <div style="font-weight:700">Rs. <?= number_format((float)$sale['grandTotal'], 2) ?></div>
                    </div>
                </div>

                <div class="table-wrap" style="border-radius:0;border:none;box-shadow:none">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th><th>Part Name</th><th>Qty</th>
                                <th>Unit Price</th><th>Total</th><th>Stock Action</th>
                            </tr>
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
                                <td>Rs. <?= number_format($itemTotal, 2) ?></td>
                                <td><span class="badge badge-success">+<?= $item['quantity'] ?> back to stock</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Return Form -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="card mb-3" style="max-width:560px">
                <div class="card-header">Return Details</div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Reason for Return <span style="color:var(--danger)">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required
                            placeholder="e.g. Wrong part, damaged item, customer changed mind…"><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-danger"
                                onclick="return confirm('Confirm return? Stock will be restored and this cannot be undone.')">
                            ↩ Confirm Return
                        </button>
                        <a href="view_invoice.php?saleID=<?= $saleID ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>
</body>
</html>
