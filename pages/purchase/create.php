<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$errors = [];

// At the top, after $errors = [];
$prefillSupplier = isset($_GET['supplierID']) ? (int)$_GET['supplierID'] : 0;
$prefillPart     = isset($_GET['prefill']) ? htmlspecialchars($_GET['prefill']) : '';

if (!in_array($_SESSION['role'], ['Owner', 'Cashier'])) {
    header("Location: ../../pages/dashboard.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supplierID'])) {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid request token. Please refresh and try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supplierID']) && empty($errors['csrf'])) {
    $supplierID = (int)$_POST['supplierID'];
    $partNames  = $_POST['partName']  ?? [];
    $quantities = $_POST['quantity']  ?? [];
    $prices     = $_POST['price']     ?? [];

    // ── Server-side validation ────────────────────────────────────────────────
    if ($supplierID <= 0) {
        $errors['supplier'] = 'Please select a supplier.';
    }

    $validItems = [];
    $itemErrors = [];
    foreach ($partNames as $key => $name) {
        $name = trim($name);
        $qty  = (int)($quantities[$key] ?? 0);
        $price = (float)($prices[$key]  ?? 0);
        if ($name === '' && $qty == 0 && $price == 0) continue; // skip blank rows

        $rowErrors = [];
        if ($name === '')  $rowErrors[] = 'Part name required';
        elseif (strlen($name) > 100) $rowErrors[] = 'Part name too long (max 100 chars)';

        if ($qty <= 0) $rowErrors[] = 'Quantity must be at least 1';
        elseif ($qty > 99999) $rowErrors[] = 'Quantity seems too high';

        if ($price <= 0) $rowErrors[] = 'Price must be greater than 0';
        elseif ($price > 9999999) $rowErrors[] = 'Price seems too high';

        if (empty($rowErrors)) {
            $validItems[] = ['name' => $name, 'qty' => $qty, 'price' => $price];
        } else {
            $itemErrors[$key] = $rowErrors;
        }
    }

    if (empty($validItems) && empty($itemErrors)) {
        $errors['items'] = 'At least one item is required.';
    }
    if (!empty($itemErrors)) {
        $errors['itemRows'] = $itemErrors;
    }

    if (empty($errors)) {
        $total = 0;
        $stmt = $conn->prepare("INSERT INTO PurchaseOrder (supplierID, totalCost) VALUES (?, 0)");
        $stmt->bind_param("i", $supplierID);
        $stmt->execute();
        $newPOID = $conn->insert_id;

        foreach ($validItems as $item) {
            $total += $item['qty'] * $item['price'];
            $stmt2 = $conn->prepare("INSERT INTO PurchaseItem (poID, partName, quantity, boughtPrice) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isid", $newPOID, $item['name'], $item['qty'], $item['price']);
            $stmt2->execute();
        }

        $conn->query("UPDATE PurchaseOrder SET totalCost = $total WHERE poID = $newPOID");
        header("Location: list.php?success=1");
        exit;
    }
}

$currentPage = 'purchase';
$base = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Purchase Order — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <style>#itemsTable td { padding: 8px 10px; }</style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">New Purchase Order</div>
      <div class="topbar-breadcrumb">Purchase Orders → Create</div>
    </div>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div class="page-title">New Purchase Order</div>
      <a href="list.php" class="btn btn-secondary">← Back to List</a>
    </div>

    <div class="card">
      <div class="card-header">Order Details</div>
      <div class="card-body">

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            ⚠ Please fix the errors below:
            <?php if (isset($errors['supplier'])): ?><br>• <?= htmlspecialchars($errors['supplier']) ?><?php endif; ?>
            <?php if (isset($errors['items'])): ?><br>• <?= htmlspecialchars($errors['items']) ?><?php endif; ?>
            <?php if (isset($errors['csrf'])): ?><br>• <?= htmlspecialchars($errors['csrf']) ?><?php endif; ?>
            <?php if (isset($errors['itemRows'])): ?><br>• One or more items have errors. Check the table below.<?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" id="purchaseForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="form-group" style="max-width:360px">
            <label class="form-label">Supplier *</label>
            <select name="supplierID" id="supplierSelect"
                    class="form-control <?= isset($errors['supplier']) ? 'is-invalid' : '' ?>">
              <option value="">— Select Supplier —</option>
              <?php
              $sup = $conn->query("SELECT * FROM Supplier");
              while ($s = $sup->fetch_assoc()):
              ?>
              <option value="<?= $s['supplierID'] ?>" <?= ((isset($_POST['supplierID']) && (int)$_POST['supplierID'] === (int)$s['supplierID']) || $prefillSupplier === (int)$s['supplierID']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['supplierName']) ?>
              </option>
              <?php endwhile; ?>
            </select>
            <span class="field-error" id="supplierErr"><?= htmlspecialchars($errors['supplier'] ?? '') ?></span>
          </div>

          <div class="d-flex align-center gap-2 mb-2" style="margin-top:24px">
            <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;">Items</span>
            <button type="button" class="btn btn-sm btn-success" onclick="addItemRow()">+ Add Item</button>
          </div>
          <span class="field-error" id="itemsErr" style="<?= isset($errors['items']) ? '' : 'display:none' ?>"><?= htmlspecialchars($errors['items'] ?? '') ?></span>

          <div class="table-wrap mb-3">
            <table class="table" id="itemsTable">
              <thead>
                <tr>
                  <th>Part Name / Part No. *</th>
                  <th>Quantity *</th>
                  <th>Price per Unit (Rs.) *</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                <?php
                // Repopulate rows on error
                if (!empty($_POST['partName'])) {
                    foreach ($_POST['partName'] as $k => $pn) {
                        $rowErrMsg = isset($errors['itemRows'][$k]) ? implode(', ', $errors['itemRows'][$k]) : '';
                        echo '<tr data-row="'.$k.'">
                          <td><input type="text" name="partName[]" class="form-control '.($rowErrMsg?'is-invalid':'').'" value="'.htmlspecialchars($pn).'" placeholder="Part name" required maxlength="100"></td>
                          <td style="width:110px"><input type="number" name="quantity[]" class="form-control '.($rowErrMsg?'is-invalid':'').'" value="'.htmlspecialchars($_POST['quantity'][$k]??1).'" min="1" required></td>
                          <td style="width:150px"><input type="number" step="0.01" name="price[]" class="form-control '.($rowErrMsg?'is-invalid':'').'" value="'.htmlspecialchars($_POST['price'][$k]??'').'" placeholder="0.00" required></td>
                          <td style="width:80px"><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">✕</button></td>
                        </tr>';
                        if ($rowErrMsg) echo '<tr><td colspan="4"><span class="field-error">'.htmlspecialchars($rowErrMsg).'</span></td></tr>';
                    }
                }
                ?>
              </tbody>
            </table>
          </div>

          <div class="form-group">
            <div class="form-check">
              <input type="checkbox" name="isDamage" value="1" id="isDamage" <?= isset($_POST['isDamage']) ? 'checked' : '' ?>>
              <label for="isDamage" class="form-label" style="margin-bottom:0;cursor:pointer;color:var(--red)">
                Mark as Damaged / Returned
              </label>
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-amber">Save Purchase Order</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
let rowIndex = <?= !empty($_POST['partName']) ? count($_POST['partName']) : 0 ?>;

function addItemRow() {
  const tbody = document.getElementById('itemsBody');
  const row   = document.createElement('tr');
  row.innerHTML = `
    <td><input type="text" name="partName[]" class="form-control" placeholder="Part name / part no." maxlength="100"></td>
    <td style="width:110px"><input type="number" name="quantity[]" class="form-control" value="1" min="1"></td>
    <td style="width:150px"><input type="number" step="0.01" name="price[]" class="form-control" placeholder="0.00" min="0.01"></td>
    <td style="width:80px"><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">✕</button></td>
  `;
  tbody.appendChild(row);
  attachRowValidation(row);
  rowIndex++;
  document.getElementById('itemsErr').style.display = 'none';
}

// Pre-fill first item row from inventory view
<?php if ($prefillPart): ?>
window.addEventListener('DOMContentLoaded', function() {
    addItemRow();
    const firstRow = document.querySelector('#itemsBody tr');
    if (firstRow) {
        firstRow.querySelector('input[name="partName[]"]').value = <?= json_encode($prefillPart) ?>;
    }
});
<?php endif; ?>

function removeRow(btn) {
  btn.closest('tr').remove();
  // remove error row if it follows
  const next = btn.closest('tr')?.nextElementSibling;
  if (next && next.querySelector('.field-error')) next.remove();
}

function attachRowValidation(row) {
  const inputs = row.querySelectorAll('input');
  inputs.forEach(inp => {
    inp.addEventListener('blur', function() {
      if (inp.value.trim() === '' || (inp.type === 'number' && parseFloat(inp.value) <= 0)) {
        inp.classList.add('is-invalid');
      } else {
        inp.classList.remove('is-invalid');
        inp.classList.add('is-valid');
      }
    });
  });
}

// Attach to any pre-filled rows
document.querySelectorAll('#itemsBody tr').forEach(attachRowValidation);

function validateSupplier() {
  const sel = document.getElementById('supplierSelect');
  const err = document.getElementById('supplierErr');
  if (sel.value === '') {
    sel.classList.add('is-invalid');
    err.textContent = 'Please select a supplier.';
    err.style.display = 'block';
    return false;
  }
  sel.classList.remove('is-invalid'); sel.classList.add('is-valid');
  err.textContent = ''; err.style.display = 'none';
  return true;
}

document.getElementById('supplierSelect').addEventListener('change', validateSupplier);

document.getElementById('purchaseForm').addEventListener('submit', function(e) {
  let valid = true;
  if (!validateSupplier()) valid = false;

  // Check at least one valid item row
  const rows = document.querySelectorAll('#itemsBody tr');
  let hasItem = false;
  rows.forEach(row => {
    const nameInp = row.querySelector('input[name="partName[]"]');
    const qtyInp  = row.querySelector('input[name="quantity[]"]');
    const priceInp= row.querySelector('input[name="price[]"]');
    if (!nameInp) return;

    const name  = nameInp.value.trim();
    const qty   = parseFloat(qtyInp?.value  ?? 0);
    const price = parseFloat(priceInp?.value ?? 0);

    let rowOk = true;
    if (name === '')  { nameInp.classList.add('is-invalid');  rowOk = false; }
    else               nameInp.classList.remove('is-invalid');
    if (qty <= 0)     { qtyInp.classList.add('is-invalid');   rowOk = false; }
    else               qtyInp.classList.remove('is-invalid');
    if (price <= 0)   { priceInp.classList.add('is-invalid'); rowOk = false; }
    else               priceInp.classList.remove('is-invalid');

    if (rowOk) hasItem = true;
    else valid = false;
  });

  if (!hasItem) {
    const errEl = document.getElementById('itemsErr');
    errEl.textContent = 'At least one valid item is required.';
    errEl.style.display = 'block';
    valid = false;
  }

  if (!valid) e.preventDefault();
});
</script>
</body>
</html>
