<?php
// pages/sale/new_sale.php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request token. Please refresh and try again.";
    } else {

    $customerName   = trim($_POST['customerName'] ?? 'Walk-in Customer');
    if ($customerName === '') $customerName = 'Walk-in Customer';
    $paymentMethod  = $_POST['paymentMethod']  ?? 'Cash';
    $amountReceived = !empty($_POST['amountReceived']) ? (float)$_POST['amountReceived'] : null;
    $partNames      = $_POST['partName']  ?? [];
    $quantities     = $_POST['quantity']  ?? [];
    $prices         = $_POST['price']     ?? [];

    if (empty($partNames)) {
        $error = "Please add at least one item to the sale.";
    } else {
        $conn->begin_transaction();
        try {
            $subTotal = 0;
            $items    = [];
            foreach ($partNames as $i => $name) {
                $name  = trim($name);
                $qty   = (int)($quantities[$i] ?? 0);
                $price = (float)($prices[$i]    ?? 0);
                if (!$name || $qty <= 0 || $price <= 0) continue;
                $total     = $qty * $price;
                $subTotal += $total;
                $items[]   = ['partName' => $name, 'qty' => $qty, 'price' => $price, 'total' => $total];
            }

            if (empty($items)) throw new Exception("Please add at least one valid item.");

            $discountPercent = $subTotal >= 50000 ? 5.00 : 0.00;
            $discountAmount  = round($subTotal * ($discountPercent / 100), 2);
            $grandTotal      = $subTotal - $discountAmount;

            $stmt = $conn->prepare("
                INSERT INTO Sale (customerName, saleDate, subTotal, discountPercent,
                                  grandTotal, paymentMethod)
                VALUES (?, NOW(), ?, ?, ?, ?)
            ");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("sddds",
                $customerName, $subTotal, $discountPercent,
                $grandTotal, $paymentMethod
            );
            $stmt->execute();
            $saleID = $conn->insert_id;
            $stmt->close();

            foreach ($items as $item) {
                $up = $conn->prepare("
                    UPDATE SparePart
                    SET currentQuantity = currentQuantity - ?
                    WHERE partName = ?
                    AND currentQuantity >= ?
                    LIMIT 1
                ");
                if (!$up) throw new Exception("Prepare stock update failed: " . $conn->error);
                $up->bind_param("isi", $item['qty'], $item['partName'], $item['qty']);
                $up->execute();
                if ($up->affected_rows !== 1) {
                    $up->close();
                    throw new Exception("Insufficient stock for: " . $item['partName']);
                }
                $up->close();

                $si = $conn->prepare("
                    INSERT INTO SaleItem (saleID, partName, quantity, sellingPrice, itemTotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                if (!$si) throw new Exception("Prepare SaleItem failed: " . $conn->error);
                $si->bind_param("isidd", $saleID, $item['partName'], $item['qty'], $item['price'], $item['total']);
                $si->execute();
                $si->close();
            }

            $conn->commit();
            header("Location: view_invoice.php?saleID=$saleID&new=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
    }
}

$partsResult = $conn->query("
    SELECT partID, partName, brandName, sellingPrice, currentQuantity, category
    FROM SparePart ORDER BY partName
");
$parts = [];
while ($row = $partsResult->fetch_assoc()) { $parts[] = $row; }

$lowStockResult = $conn->query("
    SELECT partName, currentQuantity FROM SparePart
    WHERE currentQuantity <= minQuantity ORDER BY currentQuantity ASC
");
$lowStockParts = [];
while ($row = $lowStockResult->fetch_assoc()) { $lowStockParts[] = $row; }

$currentPage = 'sale';
$base = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale / POS — MotoStock26</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        .step-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; background: var(--amber); color: #fff;
            border-radius: 50%; font-size: .75rem; font-weight: 700;
            margin-right: 6px;
        }
        .add-part-row {
            display: flex; gap: 10px; align-items: flex-end;
            flex-wrap: wrap; margin-bottom: 16px;
        }
        .add-part-row .form-group { margin-bottom: 0; flex: 1; min-width: 140px; }
        #itemsBody td { padding: 8px 10px; }
        .empty-items { text-align: center; color: var(--muted); padding: 24px; }

        /* ── Order Summary box overrides for clarity ── */
        .summary-card {
            background: #1e3a5f;
            border-radius: 12px;
            padding: 22px 24px 20px;
            max-width: 480px;
            margin-bottom: 24px;
        }
        .summary-card .card-title {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: #fbbf24;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .summary-row:last-of-type { border-bottom: none; }
        .summary-row .s-label {
            font-size: .85rem;
            color: #cbd5e1;
            font-weight: 500;
        }
        .summary-row .s-value {
            font-size: .92rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        .summary-row.discount .s-value { color: #4ade80; }
        .summary-row.grand {
            margin-top: 10px;
            padding: 12px 0 0;
            border-top: 2px solid rgba(255,255,255,.2);
            border-bottom: none;
        }
        .summary-row.grand .s-label {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
        }
        .summary-row.grand .s-value {
            font-family: 'Syne', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: #fbbf24;
        }
        .summary-input-group {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,.15);
        }
        .summary-input-group label {
            display: block;
            font-size: .8rem;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .summary-input-group input {
            width: 100%;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 7px;
            padding: 9px 12px;
            color: #f1f5f9;
            font-size: .9rem;
            font-family: 'DM Sans', sans-serif;
            box-sizing: border-box;
        }
        .summary-input-group input::placeholder { color: #94a3b8; }
        .summary-input-group input:focus {
            outline: none;
            border-color: #fbbf24;
            background: rgba(255,255,255,.15);
        }
        .change-display {
            margin-top: 10px;
            background: rgba(74,222,128,.12);
            border: 1px solid rgba(74,222,128,.3);
            border-radius: 7px;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .change-display .c-label { font-size: .82rem; color: #86efac; font-weight: 600; }
        .change-display .c-value { font-size: 1rem; font-weight: 800; color: #4ade80; font-family: 'Syne', sans-serif; }

        @media print {
            .sidebar, .main-wrap > .topbar, .no-print { display: none !important; }
            .main-wrap { margin-left: 0; }
        }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
    <div class="topbar no-print">
        <div>
            <div class="topbar-title">Sales / POS Checkout</div>
            <div class="topbar-breadcrumb">Sales › New Sale</div>
        </div>
        <a href="sales_list.php" class="btn btn-secondary btn-sm">View All Sales</a>
    </div>

    <div class="main-content">

        <div class="page-header no-print">
            <div class="page-title">POS Checkout</div>
        </div>

        <?php if (!empty($lowStockParts)): ?>
        <div class="alert alert-warning mb-3">
            ⚠ <strong>Low Stock Alert:</strong>
            <?php
            $lowLabels = [];
            foreach ($lowStockParts as $p) {
                $lowLabels[] = htmlspecialchars($p['partName']) . ' (' . $p['currentQuantity'] . ' left)';
            }
            echo implode(', ', $lowLabels);
            ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-3">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="saleForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <!-- Step 1: Customer & Payment -->
            <div class="card mb-3">
                <div class="card-header"><span class="step-num">1</span> Customer &amp; Payment</div>
                <div class="card-body">
                    <div class="form-grid-2">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Customer Name <span style="color:var(--muted);font-size:.78rem">(optional)</span></label>
                            <input type="text" name="customerName" id="customerName" class="form-control"
                                   placeholder="Walk-in customer" value="<?= htmlspecialchars($_POST['customerName'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Payment Method</label>
                            <select name="paymentMethod" class="form-control">
                                <option value="Cash">💵 Cash</option>
                                <option value="Online Transfer">🏦 Online Transfer</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Items -->
            <div class="card mb-3">
                <div class="card-header"><span class="step-num">2</span> Add Items</div>
                <div class="card-body">
                    <div class="add-part-row">
                        <div class="form-group" style="flex:3">
                            <label class="form-label">Select Part</label>
                            <select id="partSelect" class="form-control">
                                <option value="">— Choose a part —</option>
                                <?php foreach ($parts as $p): ?>
                                    <option value="<?= $p['partID'] ?>"
                                        data-name="<?= htmlspecialchars($p['partName']) ?>"
                                        data-brand="<?= htmlspecialchars($p['brandName']) ?>"
                                        data-price="<?= $p['sellingPrice'] ?>"
                                        data-stock="<?= $p['currentQuantity'] ?>"
                                        data-category="<?= htmlspecialchars($p['category']) ?>">
                                        <?= htmlspecialchars($p['partName']) ?>
                                        (<?= htmlspecialchars($p['brandName']) ?>)
                                        — Rs.<?= number_format($p['sellingPrice'], 2) ?>
                                        [Stock: <?= $p['currentQuantity'] ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:100px">
                            <label class="form-label">Qty</label>
                            <input type="number" id="partQty" class="form-control" min="1" value="1">
                        </div>
                        <div class="form-group" style="max-width:160px">
                            <label class="form-label">Unit Price (Rs.)</label>
                            <input type="number" id="partPrice" class="form-control" step="0.01" placeholder="Auto-filled">
                        </div>
                        <button type="button" id="addItemBtn" class="btn btn-success" style="height:40px;margin-bottom:0">
                            + Add Item
                        </button>
                    </div>

                    <div class="table-wrap" style="border-radius:0;border:none;box-shadow:none;">
                        <table class="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th>#</th><th>Part Name</th><th>Brand</th>
                                    <th>Category</th><th>Qty</th>
                                    <th>Unit Price</th><th>Total</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr id="emptyRow"><td colspan="8" class="empty-items">No items added yet. Select a part above to add.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="hiddenInputs"></div>
                </div>
            </div>

            <!-- Step 3: Order Summary -->
            <div class="summary-card">
                <div class="card-title">
                    <span class="step-num" style="background:#fbbf24;color:#1e3a5f">3</span>
                    Order Summary
                </div>

                <div class="summary-row">
                    <span class="s-label">Sub Total</span>
                    <span class="s-value" id="subtotalDisplay">Rs. 0.00</span>
                </div>
                <div class="summary-row discount" id="discountRow" style="display:none">
                    <span class="s-label">Discount (5% on orders ≥ Rs. 50,000)</span>
                    <span class="s-value" id="discountDisplay">− Rs. 0.00</span>
                </div>
                <div class="summary-row grand">
                    <span class="s-label">Grand Total</span>
                    <span class="s-value" id="grandTotalDisplay">Rs. 0.00</span>
                </div>

                <div class="summary-input-group">
                    <label>Amount Received (Rs.) — optional</label>
                    <input type="number" name="amountReceived" id="amountReceivedInput"
                           step="0.01" placeholder="Enter cash / amount received">
                </div>

                <div class="change-display" id="changeBox" style="display:none">
                    <span class="c-label">💚 Change to Return</span>
                    <span class="c-value" id="changeDisplay">Rs. 0.00</span>
                </div>
            </div>

            <div class="no-print" style="margin-bottom:32px">
                <button type="submit" class="btn btn-amber" style="font-size:.95rem;padding:11px 28px">
                    🖨 Save Sale &amp; Generate Bill →
                </button>
                <a href="sales_list.php" class="btn btn-secondary" style="margin-left:8px">✕ Cancel</a>
            </div>

        </form>
    </div>
</div>

<script>
let saleItems = [];
let currentGrandTotal = 0;

const partSelect  = document.getElementById('partSelect');
const partQty     = document.getElementById('partQty');
const partPrice   = document.getElementById('partPrice');
const addItemBtn  = document.getElementById('addItemBtn');
const itemsBody   = document.getElementById('itemsBody');
const hiddenDiv   = document.getElementById('hiddenInputs');
const amtInput    = document.getElementById('amountReceivedInput');
const changeBox   = document.getElementById('changeBox');
const changeDisp  = document.getElementById('changeDisplay');

partSelect.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    partPrice.value = opt.dataset.price || '';
    partQty.value   = 1;
});

addItemBtn.addEventListener('click', function() {
    const opt      = partSelect.options[partSelect.selectedIndex];
    const name     = opt.dataset.name     || '';
    const brand    = opt.dataset.brand    || '';
    const category = opt.dataset.category || '';
    const stock    = parseInt(opt.dataset.stock) || 0;
    const qty      = parseInt(partQty.value)    || 0;
    const price    = parseFloat(partPrice.value) || 0;

    if (!name)    { alert('Please select a part.'); return; }
    if (qty <= 0) { alert('Quantity must be at least 1.'); return; }
    if (price <= 0) { alert('Please enter a valid price.'); return; }
    if (qty > stock) { alert('Not enough stock! Available: ' + stock); return; }

    const existing = saleItems.find(i => i.name === name);
    if (existing) {
        if (existing.qty + qty > stock) { alert('Total quantity exceeds stock.'); return; }
        existing.qty  += qty;
        existing.total = existing.qty * existing.price;
    } else {
        saleItems.push({ name, brand, category, qty, price, total: qty * price });
    }

    renderItems();
    partSelect.value = '';
    partPrice.value  = '';
    partQty.value    = 1;
});

amtInput.addEventListener('input', function() {
    const received = parseFloat(this.value) || 0;
    if (received > 0 && currentGrandTotal > 0) {
        const change = received - currentGrandTotal;
        changeDisp.textContent = 'Rs. ' + Math.max(0, change).toFixed(2);
        changeBox.style.display = change >= 0 ? 'flex' : 'none';
    } else {
        changeBox.style.display = 'none';
    }
});

function renderItems() {
    itemsBody.innerHTML = '';
    hiddenDiv.innerHTML = '';

    if (saleItems.length === 0) {
        itemsBody.innerHTML = '<tr id="emptyRow"><td colspan="8" class="empty-items">No items added yet. Select a part above to add.</td></tr>';
        updateSummary(0);
        return;
    }

    let subTotal = 0;
    saleItems.forEach((item, idx) => {
        subTotal += item.total;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td><strong>${escHtml(item.name)}</strong></td>
            <td>${escHtml(item.brand)}</td>
            <td>${escHtml(item.category)}</td>
            <td>${item.qty}</td>
            <td>Rs. ${item.price.toFixed(2)}</td>
            <td><strong>Rs. ${item.total.toFixed(2)}</strong></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${idx})">✕</button></td>
        `;
        itemsBody.appendChild(tr);

        hiddenDiv.innerHTML += `
            <input type="hidden" name="partName[]" value="${escHtml(item.name)}">
            <input type="hidden" name="quantity[]" value="${item.qty}">
            <input type="hidden" name="price[]"    value="${item.price}">
        `;
    });

    updateSummary(subTotal);
}

function removeItem(idx) {
    saleItems.splice(idx, 1);
    renderItems();
}

function updateSummary(sub) {
    const disc  = sub >= 50000 ? sub * 0.05 : 0;
    const grand = sub - disc;
    currentGrandTotal = grand;

    document.getElementById('subtotalDisplay').textContent    = 'Rs. ' + sub.toFixed(2);
    document.getElementById('grandTotalDisplay').textContent  = 'Rs. ' + grand.toFixed(2);

    const discRow = document.getElementById('discountRow');
    if (disc > 0) {
        document.getElementById('discountDisplay').textContent = '− Rs. ' + disc.toFixed(2);
        discRow.style.display = 'flex';
    } else {
        discRow.style.display = 'none';
    }

    // Recalculate change if amount entered
    amtInput.dispatchEvent(new Event('input'));
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>