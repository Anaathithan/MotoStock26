<?php
// pages/sale/sales_list.php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$search   = trim($_GET['search']    ?? '');
$payment  = trim($_GET['payment']   ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');

if (!in_array($_SESSION['role'], ['Owner', 'Cashier'])) {
    header("Location: ../../pages/dashboard.php"); exit;
}

$hasCustomerNameCol = false;
$chkCustomerName = $conn->query("SHOW COLUMNS FROM sale LIKE 'customerName'");
if ($chkCustomerName && $chkCustomerName->num_rows > 0) {
    $hasCustomerNameCol = true;
}
$hasCustomerIdCol = false;
$chkCustomerId = $conn->query("SHOW COLUMNS FROM sale LIKE 'customerID'");
if ($chkCustomerId && $chkCustomerId->num_rows > 0) {
    $hasCustomerIdCol = true;
}
$hasSubTotalCol = false;
$chkSubTotal = $conn->query("SHOW COLUMNS FROM sale LIKE 'subTotal'");
if ($chkSubTotal && $chkSubTotal->num_rows > 0) {
    $hasSubTotalCol = true;
}
$hasDiscountPercentCol = false;
$chkDiscountPercent = $conn->query("SHOW COLUMNS FROM sale LIKE 'discountPercent'");
if ($chkDiscountPercent && $chkDiscountPercent->num_rows > 0) {
    $hasDiscountPercentCol = true;
}
$hasDiscountAmountCol = false;
$chkDiscountAmount = $conn->query("SHOW COLUMNS FROM sale LIKE 'discountAmount'");
if ($chkDiscountAmount && $chkDiscountAmount->num_rows > 0) {
    $hasDiscountAmountCol = true;
}
$hasAmountReceivedCol = false;
$chkAmountReceived = $conn->query("SHOW COLUMNS FROM sale LIKE 'amountReceived'");
if ($chkAmountReceived && $chkAmountReceived->num_rows > 0) {
    $hasAmountReceivedCol = true;
}

$where = ["1=1"];
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    if ($hasCustomerNameCol) {
        $where[] = "sa.customerName LIKE '%{$s}%'";
    } elseif ($hasCustomerIdCol) {
        $where[] = "COALESCE(c.name, 'Walk-in') LIKE '%{$s}%'";
    }
}
if ($payment !== '')  $where[] = "paymentMethod = '" . $conn->real_escape_string($payment) . "'";
if ($dateFrom !== '') $where[] = "DATE(saleDate) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo   !== '') $where[] = "DATE(saleDate) <= '" . $conn->real_escape_string($dateTo)   . "'";
$whereSql = implode(' AND ', $where);

$selectCustomer = $hasCustomerNameCol
    ? "sa.customerName AS customerName"
    : ($hasCustomerIdCol ? "COALESCE(c.name, 'Walk-in') AS customerName" : "'Walk-in' AS customerName");
$joinCustomer = $hasCustomerNameCol ? '' : ($hasCustomerIdCol ? "LEFT JOIN customer c ON c.customerID = sa.customerID" : '');
$selectSubTotal = $hasSubTotalCol ? "sa.subTotal" : "sa.grandTotal AS subTotal";
$selectDiscountPercent = $hasDiscountPercentCol ? "sa.discountPercent" : "0 AS discountPercent";
$selectDiscountAmount = $hasDiscountAmountCol ? "sa.discountAmount" : "0 AS discountAmount";
$selectAmountReceived = $hasAmountReceivedCol ? "sa.amountReceived" : "NULL AS amountReceived";

$result = $conn->query("
    SELECT sa.saleID, sa.saleDate, {$selectCustomer}, {$selectSubTotal},
           {$selectDiscountPercent}, {$selectDiscountAmount}, sa.grandTotal,
           sa.paymentMethod, {$selectAmountReceived}
    FROM sale sa
    {$joinCustomer}
    WHERE $whereSql
    ORDER BY sa.saleDate DESC, sa.saleID DESC
");
$sales = [];
if ($result) { while ($row = $result->fetch_assoc()) { $sales[] = $row; } }

$statsRow     = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(grandTotal),0) AS rev FROM sale")->fetch_assoc();
$totalSales   = $statsRow['cnt'];
$totalRevenue = $statsRow['rev'];
$today        = date('Y-m-d');
$todaySales   = $conn->query("SELECT COUNT(*) AS cnt FROM sale WHERE DATE(saleDate)='$today'")->fetch_assoc()['cnt'];
$todayRev     = $conn->query("SELECT COALESCE(SUM(grandTotal),0) AS rev FROM sale WHERE DATE(saleDate)='$today'")->fetch_assoc()['rev'];

$msg         = $_GET['msg'] ?? '';
$currentPage = 'sale';
$base        = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales &amp; Invoices â€” MotoStock26</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        /* â”€â”€ Horizontal Stat Cards â”€â”€ */
        .h-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 900px) { .h-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .h-stats { grid-template-columns: 1fr; } }

        .h-stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            transition: box-shadow .2s;
        }
        .h-stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
        .h-stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .icon-blue   { background: #eff6ff; }
        .icon-green  { background: #f0fdf4; }
        .icon-amber  { background: #fffbeb; }
        .icon-purple { background: #faf5ff; }
        .h-stat-info {}
        .h-stat-label {
            font-size: .78rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 4px;
        }
        .h-stat-value {
            font-family: 'Syne', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1.1;
        }
        .h-stat-sub {
            font-size: .75rem;
            color: #94a3b8;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
    <div class="topbar">
        <div>
            <div class="topbar-title">Sales &amp; Invoices</div>
            <div class="topbar-breadcrumb">Sales â€º All Sales</div>
        </div>
        <a href="new_sale.php" class="btn btn-amber btn-sm">+ New Sale / POS</a>
    </div>

    <div class="main-content">

        <div class="page-header">
            <div class="page-title">Sales List</div>
        </div>

        <?php if ($msg === 'return_success'): ?>
        <div class="alert alert-success mb-3">âœ“ Sale has been returned and stock restored.</div>
        <?php endif; ?>

        <!-- Horizontal Stat Cards -->
        <div class="h-stats">
            <div class="h-stat-card">
                <div class="h-stat-icon icon-blue">ðŸ§¾</div>
                <div class="h-stat-info">
                    <div class="h-stat-label">Total Sales</div>
                    <div class="h-stat-value"><?= number_format($totalSales) ?></div>
                    <div class="h-stat-sub">All time</div>
                </div>
            </div>
            <div class="h-stat-card">
                <div class="h-stat-icon icon-green">ðŸ’°</div>
                <div class="h-stat-info">
                    <div class="h-stat-label">Total Revenue</div>
                    <div class="h-stat-value">Rs. <?= number_format($totalRevenue, 0) ?></div>
                    <div class="h-stat-sub">All time</div>
                </div>
            </div>
            <div class="h-stat-card">
                <div class="h-stat-icon icon-amber">ðŸ“…</div>
                <div class="h-stat-info">
                    <div class="h-stat-label">Today's Sales</div>
                    <div class="h-stat-value"><?= number_format($todaySales) ?></div>
                    <div class="h-stat-sub"><?= date('d M Y') ?></div>
                </div>
            </div>
            <div class="h-stat-card">
                <div class="h-stat-icon icon-purple">ðŸ“Š</div>
                <div class="h-stat-info">
                    <div class="h-stat-label">Today's Revenue</div>
                    <div class="h-stat-value">Rs. <?= number_format($todayRev, 0) ?></div>
                    <div class="h-stat-sub"><?= date('d M Y') ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="sales_list.php" class="filter-bar">
            <span class="filter-label">Filter:</span>
            <input type="text" name="search" class="filter-input" placeholder="Search customerâ€¦" value="<?= htmlspecialchars($search) ?>">
            <select name="payment" class="filter-select">
                <option value="">All Payments</option>
                <option value="Cash"            <?= $payment === 'Cash'            ? 'selected' : '' ?>>ðŸ’µ Cash</option>
                <option value="Online Transfer" <?= $payment === 'Online Transfer' ? 'selected' : '' ?>>ðŸ¦ Online Transfer</option>
            </select>
            <input type="date" name="date_from" class="filter-input" style="min-width:130px" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
            <input type="date" name="date_to"   class="filter-input" style="min-width:130px" value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
            <button type="submit" class="btn btn-sm btn-amber">Apply</button>
            <a href="sales_list.php" class="filter-reset">Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Date &amp; Time</th>
                        <th>Customer</th>
                        <th>Grand Total</th>
                        <th>Discount</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--muted);padding:24px">
                            No sales found.
                            <?php if ($search || $payment || $dateFrom || $dateTo): ?>
                                <a href="sales_list.php">Clear filters</a>
                            <?php else: ?>
                                <a href="new_sale.php">Create your first sale â†’</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                    <?php
                        $discAmt  = $s['discountAmount'] ?? ($s['subTotal'] * $s['discountPercent'] / 100);
                        $payBadge = $s['paymentMethod'] === 'Cash' ? 'badge-success' : 'badge-info';
                    ?>
                    <tr>
                        <td style="color:var(--muted)">#<?= $s['saleID'] ?></td>
                        <td><?= date('d M Y, H:i', strtotime($s['saleDate'])) ?></td>
                        <td><?= $s['customerName'] ? htmlspecialchars($s['customerName']) : '<span style="color:var(--muted)">Walk-in</span>' ?></td>
                        <td><strong>Rs. <?= number_format($s['grandTotal'], 2) ?></strong></td>
                        <td>
                            <?php if ($discAmt > 0): ?>
                                <span class="badge badge-warning">Rs. <?= number_format($discAmt, 2) ?></span>
                            <?php else: ?>â€”<?php endif; ?>
                        </td>
                        <td><span class="badge <?= $payBadge ?>"><?= htmlspecialchars($s['paymentMethod']) ?></span></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="view_invoice.php?saleID=<?= $s['saleID'] ?>" class="btn btn-sm btn-outline">ðŸ‘ View</a>
                            <a href="edit_sale.php?saleID=<?= $s['saleID'] ?>" class="btn btn-sm btn-secondary">âœ Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Weekly Report Button -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;display:flex;align-items:center;gap:12px">
            <a href="weekly_report.php" class="btn btn-amber">
                ðŸ“Š Weekly Report
            </a>
            <span style="font-size:.82rem;color:#94a3b8">View revenue, profit &amp; top-selling parts for the current week</span>
        </div>

    </div>
</div>
</body>
</html>
