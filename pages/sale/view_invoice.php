<?php
// pages/sale/view_invoice.php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$saleID = (int)($_GET['saleID'] ?? 0);
$isNew  = isset($_GET['new']);
if (!$saleID) { header("Location: sales_list.php"); exit; }

$sale = $conn->query("SELECT * FROM Sale WHERE saleID = $saleID")->fetch_assoc();
if (!$sale) { header("Location: sales_list.php"); exit; }

$itemsRes = $conn->query("SELECT * FROM SaleItem WHERE saleID = $saleID");
$items = [];
while ($row = $itemsRes->fetch_assoc()) { $items[] = $row; }

$discountAmount = isset($sale['discountAmount'])
    ? (float)$sale['discountAmount']
    : round((float)$sale['subTotal'] * ((float)$sale['discountPercent'] / 100), 2);

$currentPage = 'sale';
$base        = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $saleID ?> — MotoStock26</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        /* ── Remove browser-injected URL/title on print ── */
        @page {
            margin: 14mm 12mm;
            size: A4;
        }

        @media print {
            .sidebar, .main-wrap > .topbar, .no-print { display: none !important; }
            .main-wrap { margin-left: 0 !important; }
            .main-content { padding: 0 !important; }
            body { background: #fff !important; }

            .invoice-wrapper {
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                max-width: 100% !important;
            }

            /* Force print colours */
            .inv-header {
                background: #1e3a5f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .inv-grand {
                background: #1e3a5f !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .inv-customer { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            /* Clear table borders in print */
            .inv-row { border-bottom: 1px solid #d1d5db !important; }
            .inv-head { border-bottom: 2px solid #374151 !important; }
            .inv-subtotal { border-top: 2px solid #374151 !important; }

            /* Footer stamp */
            .inv-footer { border-top: 1px solid #d1d5db !important; }
        }

        /* ── Screen styles ── */
        .invoice-wrapper {
            max-width: 760px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        /* Header */
        .inv-header {
            background: #1e3a5f;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 26px 32px;
        }
        .inv-header .shop-name {
            font-family: 'Syne', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: .5px;
        }
        .inv-header .shop-sub {
            font-size: .82rem;
            opacity: .8;
            margin-top: 5px;
            line-height: 1.6;
        }
        .inv-header .inv-no {
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            text-align: right;
            color: #fbbf24;
        }
        .inv-header .inv-dt {
            font-size: .82rem;
            opacity: .85;
            text-align: right;
            margin-top: 5px;
            line-height: 1.7;
        }

        /* Customer band */
        .inv-customer {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            padding: 16px 32px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .inv-customer .lbl {
            color: #475569;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 3px;
        }
        .inv-customer .val {
            font-weight: 700;
            font-size: .9rem;
            color: #1e293b;
        }

        /* Body */
        .inv-body { padding: 0 32px 28px; }

        .inv-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .inv-table thead th {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #475569;
            padding: 10px 8px;
            border-bottom: 2px solid #1e3a5f;
            background: #f8fafc;
        }
        .inv-table thead th:not(:first-child) { text-align: right; }
        .inv-table tbody td {
            padding: 11px 8px;
            font-size: .88rem;
            color: #1e293b;
            border-bottom: 1px solid #e9edf2;
        }
        .inv-table tbody td:not(:first-child):not(:nth-child(2)) { text-align: right; }
        .inv-table tbody tr:last-child td { border-bottom: none; }
        .inv-table tbody td .item-no { color: #94a3b8; font-size: .8rem; }
        .inv-table tbody td .part-name { font-weight: 700; color: #1e293b; }

        /* Totals section */
        .inv-totals {
            margin-top: 4px;
            border-top: 2px solid #1e3a5f;
        }
        .inv-total-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0;
            padding: 8px 0;
            border-bottom: 1px solid #e9edf2;
        }
        .inv-total-row:last-child { border-bottom: none; }
        .inv-total-label {
            min-width: 160px;
            text-align: right;
            padding-right: 16px;
            font-size: .88rem;
            color: #475569;
            font-weight: 600;
        }
        .inv-total-value {
            min-width: 130px;
            text-align: right;
            font-size: .88rem;
            font-weight: 700;
            color: #1e293b;
        }
        .inv-total-row.discount .inv-total-label,
        .inv-total-row.discount .inv-total-value { color: #16a34a; }
        .inv-total-row.grand {
            background: #1e3a5f;
            border-radius: 8px;
            margin-top: 8px;
            padding: 14px 12px;
        }
        .inv-total-row.grand .inv-total-label {
            color: #e2e8f0;
            font-family: 'Syne', sans-serif;
            font-size: .95rem;
            font-weight: 700;
        }
        .inv-total-row.grand .inv-total-value {
            color: #fbbf24;
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
        }

        /* Footer */
        .inv-footer {
            padding: 14px 32px 20px;
            text-align: center;
            font-size: .8rem;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .inv-footer strong { color: #1e3a5f; }

        .print-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
    <div class="topbar no-print">
        <div>
            <div class="topbar-title">Invoice #<?= $saleID ?></div>
            <div class="topbar-breadcrumb">Sales › Invoice</div>
        </div>
        <a href="sales_list.php" class="btn btn-secondary btn-sm">← Back to Sales</a>
    </div>

    <div class="main-content">

        <div class="page-header no-print">
            <div class="page-title">Invoice / Bill</div>
        </div>

        <?php if ($isNew): ?>
        <div class="alert alert-success mb-3 no-print">
            ✓ Sale saved! Invoice <strong>#<?= $saleID ?></strong> has been generated.
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="print-bar no-print">
            <button onclick="window.print()" class="btn btn-amber">🖨 Print Invoice</button>
            <a href="edit_sale.php?saleID=<?= $saleID ?>" class="btn btn-secondary">✏ Edit Sale</a>
            <a href="sale_return.php?saleID=<?= $saleID ?>" class="btn btn-danger"
               onclick="return confirm('Record a return for this sale? This cannot be undone.')">↩ Return Sale</a>
            <a href="new_sale.php" class="btn btn-outline">+ New Sale</a>
        </div>

        <!-- Invoice -->
        <div class="invoice-wrapper">

            <!-- Header -->
            <div class="inv-header">
                <div>
                    <div class="shop-name">BIMSARA MOTOSTOCK</div>
                    <div class="shop-sub">
                        Motorcycle Dealership &amp; Service Centre<br>
                        Chilaw, Sri Lanka &nbsp;·&nbsp; Tel: 011-XXXXXXX
                    </div>
                </div>
                <div>
                    <div class="inv-no">INVOICE #<?= str_pad($saleID, 4, '0', STR_PAD_LEFT) ?></div>
                    <div class="inv-dt">
                        Date: <?= date('d M Y', strtotime($sale['saleDate'])) ?><br>
                        Time: <?= date('H:i', strtotime($sale['saleDate'])) ?><br>
                        Cashier: <?= htmlspecialchars($_SESSION['username'] ?? '—') ?>
                    </div>
                </div>
            </div>

            <!-- Customer Info Band -->
            <div class="inv-customer">
                <div>
                    <div class="lbl">Bill To</div>
                    <div class="val"><?= htmlspecialchars($sale['customerName'] ?: 'Walk-in Customer') ?></div>
                </div>
                <div>
                    <div class="lbl">Payment Method</div>
                    <div class="val"><?= htmlspecialchars($sale['paymentMethod']) ?></div>
                </div>
                <?php if (!empty($sale['amountReceived'])): ?>
                <div>
                    <div class="lbl">Amount Received</div>
                    <div class="val">Rs. <?= number_format((float)$sale['amountReceived'], 2) ?></div>
                </div>
                <div>
                    <div class="lbl">Change Given</div>
                    <div class="val" style="color:#16a34a">Rs. <?= number_format(max(0, (float)$sale['amountReceived'] - (float)$sale['grandTotal']), 2) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items Table -->
            <div class="inv-body">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;width:36px">#</th>
                            <th style="text-align:left">Item Description</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $i => $item):
                        $unitPrice = (float)($item['sellingPrice'] ?? $item['unitPrice'] ?? 0);
                        $itemTotal = (float)($item['itemTotal']    ?? $item['quantity'] * $unitPrice);
                    ?>
                        <tr>
                            <td><span class="item-no"><?= $i + 1 ?></span></td>
                            <td><span class="part-name"><?= htmlspecialchars($item['partName']) ?></span></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>Rs. <?= number_format($unitPrice, 2) ?></td>
                            <td><strong>Rs. <?= number_format($itemTotal, 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="inv-totals">
                    <div class="inv-total-row">
                        <span class="inv-total-label">Sub Total</span>
                        <span class="inv-total-value">Rs. <?= number_format((float)$sale['subTotal'], 2) ?></span>
                    </div>

                    <?php if ($discountAmount > 0): ?>
                    <div class="inv-total-row discount">
                        <span class="inv-total-label">Discount (<?= (float)$sale['discountPercent'] ?>%)</span>
                        <span class="inv-total-value">− Rs. <?= number_format($discountAmount, 2) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="inv-total-row grand">
                        <span class="inv-total-label">GRAND TOTAL</span>
                        <span class="inv-total-value">Rs. <?= number_format((float)$sale['grandTotal'], 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="inv-footer">
                <strong>Thank you for choosing Bimsara MotoStock!</strong><br>
                Chilaw, Sri Lanka &nbsp;·&nbsp; Tel: 011-XXXXXXX &nbsp;·&nbsp; All sales are final unless returned within 7 days.
            </div>
        </div>

    </div>
</div>
</body>
</html>