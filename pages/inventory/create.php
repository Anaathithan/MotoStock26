<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$showBought = ($_SESSION['role'] === 'Owner');
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$part = ['partName'=>'','brandName'=>'','size'=>'','sellingPrice'=>'','boughtPrice'=>'','category'=>'Engine Items','currentQuantity'=>10,'minQuantity'=>1];

if ($id > 0) {
    $res = $conn->query("SELECT * FROM SparePart WHERE partID = $id");
    if ($res->num_rows === 1) $part = $res->fetch_assoc();
}

$errors = [];
$categories = ['Engine Items', 'Body Parts', 'Oil & Lubricants', 'Dat Today Items'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partName = trim($_POST['partName']         ?? '');
    $brand    = trim($_POST['brandName']        ?? '');
    $size     = trim($_POST['size']             ?? '');
    $selling  = trim($_POST['sellingPrice']     ?? '');
    $bought   = $showBought ? trim($_POST['boughtPrice'] ?? '') : 0;
    $category = trim($_POST['category']         ?? '');
    $qty      = trim($_POST['currentQuantity']  ?? '');

    // ── Server-side validation ────────────────────────────────────────────────
    if ($partName === '') {
        $errors['partName'] = 'Part name is required.';
    } elseif (strlen($partName) < 2 || strlen($partName) > 100) {
        $errors['partName'] = 'Part name must be 2–100 characters.';
    }

    if ($selling === '' || !is_numeric($selling)) {
        $errors['sellingPrice'] = 'Selling price is required and must be a number.';
    } elseif ((float)$selling <= 0) {
        $errors['sellingPrice'] = 'Selling price must be greater than 0.';
    } elseif ((float)$selling > 9999999) {
        $errors['sellingPrice'] = 'Selling price seems too high. Please check.';
    }

    if ($showBought && $bought !== '' && $bought != 0) {
        if (!is_numeric($bought) || (float)$bought < 0) {
            $errors['boughtPrice'] = 'Bought price must be a positive number.';
        } elseif (is_numeric($selling) && (float)$bought >= (float)$selling) {
            $errors['boughtPrice'] = 'Bought price should be less than selling price.';
        }
    }

    if ($qty === '' || !ctype_digit((string)(int)$qty) || (int)$qty < 0) {
        $errors['currentQuantity'] = 'Quantity must be a whole number (0 or more).';
    }

    if (!in_array($category, $categories)) {
        $errors['category'] = 'Please select a valid category.';
    }

    if (empty($errors)) {
        $sellingF = (float)$selling;
        $boughtF  = $showBought ? (float)$bought : 0;
        $qtyI     = (int)$qty;

        if ($id > 0) {
            $sql  = "UPDATE SparePart SET partName=?, brandName=?, size=?, sellingPrice=?, boughtPrice=?, category=?, currentQuantity=? WHERE partID=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssddsii", $partName, $brand, $size, $sellingF, $boughtF, $category, $qtyI, $id);
        } else {
            $sql  = "INSERT INTO SparePart (partName, brandName, size, sellingPrice, boughtPrice, category, currentQuantity) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssddsi", $partName, $brand, $size, $sellingF, $boughtF, $category, $qtyI);
        }
        $stmt->execute();

          // If coming from a PO, go to next part in the queue
          $fromPO = isset($_GET['from_po']) ? (int)$_GET['from_po'] : 0;
          $queue  = isset($_GET['queue']) ? trim($_GET['queue']) : '';

          if ($fromPO && $queue !== '') {
          $ids   = explode(',', $queue);
          $nextID = (int)array_shift($ids);
          $remaining = implode(',', $ids);
          header("Location: create.php?id=$nextID&from_po=$fromPO&queue=" . urlencode($remaining));
        } elseif ($fromPO) {
          // Queue exhausted — go back to the PO view
          header("Location: ../purchase/view.php?poID=$fromPO&updated=1&stocked=1");
        } else {
        header("Location: list.php?success=1");
        }
        exit;
    }

    $part = array_merge($part, [
        'partName' => $partName, 'brandName' => $brand, 'size' => $size,
        'sellingPrice' => $selling, 'boughtPrice' => $bought,
        'category' => $category, 'currentQuantity' => $qty
    ]);
}

$currentPage = 'inventory';
$base = '../../';
$pageTitle = $id > 0 ? 'Edit Part' : 'Add New Spare Part';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title"><?= $pageTitle ?></div>
      <div class="topbar-breadcrumb">Inventory → <?= $id > 0 ? 'Edit' : 'Create' ?></div>
    </div>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div class="page-title"><?= $pageTitle ?></div>
      <a href="list.php" class="btn btn-secondary">← Back to List</a>
    </div>


    <?php 
      $fromPO = isset($_GET['from_po']) ? (int)$_GET['from_po'] : 0;
      $queue  = isset($_GET['queue']) ? trim($_GET['queue']) : '';
      $queueCount = ($queue !== '') ? count(explode(',', $queue)) : 0;
    ?>

    <?php if ($fromPO): ?>
      <div class="alert alert-warning" style="margin-bottom:16px;">
        📦 This part was added from <strong>Purchase Order #<?= $fromPO ?></strong>. 
        Fill in the missing details and save.
        <?php if ($queueCount > 0): ?>
          <br><span style="font-size:.85rem;color:var(--muted);">
            <?= $queueCount ?> more part<?= $queueCount > 1 ? 's' : '' ?> to complete after this.
          </span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card" style="max-width:700px">
      <div class="card-header">Part Details</div>
      <div class="card-body">

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">⚠ Please fix the errors below before submitting.</div>
        <?php endif; ?>

        <form method="post" id="inventoryForm" novalidate>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Part Name *</label>
              <input type="text" name="partName" id="partName"
                     class="form-control <?= isset($errors['partName']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($part['partName']) ?>"
                     placeholder="e.g. Brake Pad" maxlength="100">
              <span class="field-error" id="partNameErr"><?= htmlspecialchars($errors['partName'] ?? '') ?></span>
            </div>
            <div class="form-group">
              <label class="form-label">Brand Name</label>
              <input type="text" name="brandName" id="brandName"
                     class="form-control"
                     value="<?= htmlspecialchars($part['brandName']) ?>"
                     placeholder="e.g. Honda" maxlength="60">
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Category</label>
              <select name="category" id="category" class="form-control <?= isset($errors['category']) ? 'is-invalid' : '' ?>">
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>" <?= $part['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
              </select>
              <span class="field-error" id="categoryErr"><?= htmlspecialchars($errors['category'] ?? '') ?></span>
            </div>
            <div class="form-group">
              <label class="form-label">Size / Spec</label>
              <input type="text" name="size" class="form-control"
                     value="<?= htmlspecialchars($part['size']) ?>"
                     placeholder="e.g. 120/70-17" maxlength="40">
            </div>
          </div>

          <div class="form-grid-<?= $showBought ? '3' : '2' ?>">
            <div class="form-group">
              <label class="form-label">Selling Price (Rs.) *</label>
              <input type="number" step="0.01" name="sellingPrice" id="sellingPrice"
                     class="form-control <?= isset($errors['sellingPrice']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($part['sellingPrice']) ?>"
                     placeholder="0.00" min="0.01">
              <span class="field-error" id="sellingErr"><?= htmlspecialchars($errors['sellingPrice'] ?? '') ?></span>
            </div>
            <?php if ($showBought): ?>
            <div class="form-group">
              <label class="form-label">Bought Price (Rs.) <span style="font-size:.75rem;color:var(--amber-dk)">Owner only</span></label>
              <input type="number" step="0.01" name="boughtPrice" id="boughtPrice"
                     class="form-control <?= isset($errors['boughtPrice']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($part['boughtPrice']) ?>"
                     placeholder="0.00" min="0">
              <span class="field-error" id="boughtErr"><?= htmlspecialchars($errors['boughtPrice'] ?? '') ?></span>
            </div>
            <?php endif; ?>
            <div class="form-group">
              <label class="form-label">Current Quantity *</label>
              <input type="number" name="currentQuantity" id="currentQty"
                     class="form-control <?= isset($errors['currentQuantity']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($part['currentQuantity']) ?>"
                     min="0" step="1">
              <span class="field-error" id="qtyErr"><?= htmlspecialchars($errors['currentQuantity'] ?? '') ?></span>
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-amber">Save Part</button>
            <a href="<?= $fromPO ? '../purchase/view.php?poID='.$fromPO : 'list.php' ?>" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const showBought = <?= $showBought ? 'true' : 'false' ?>;

function showErr(elId, inputEl, msg) {
  const err = document.getElementById(elId);
  if (err) { err.textContent = msg; err.style.display = msg ? 'block' : 'none'; }
  if (msg) { inputEl.classList.add('is-invalid'); inputEl.classList.remove('is-valid'); }
  else     { inputEl.classList.remove('is-invalid'); inputEl.classList.add('is-valid'); }
  return !msg;
}

function validatePartName() {
  const val = document.getElementById('partName').value.trim();
  if (val === '') return showErr('partNameErr', document.getElementById('partName'), 'Part name is required.');
  if (val.length < 2 || val.length > 100) return showErr('partNameErr', document.getElementById('partName'), 'Must be 2–100 characters.');
  return showErr('partNameErr', document.getElementById('partName'), '');
}

function validateSelling() {
  const val = parseFloat(document.getElementById('sellingPrice').value);
  const el  = document.getElementById('sellingPrice');
  if (isNaN(val) || val <= 0) return showErr('sellingErr', el, 'Selling price must be greater than 0.');
  if (val > 9999999) return showErr('sellingErr', el, 'Value seems too high. Please check.');
  const ok = showErr('sellingErr', el, '');
  if (showBought) validateBought(); // re-check bought vs selling
  return ok;
}

function validateBought() {
  if (!showBought) return true;
  const boughtEl  = document.getElementById('boughtPrice');
  const sellingEl = document.getElementById('sellingPrice');
  if (!boughtEl) return true;
  const b = parseFloat(boughtEl.value);
  const s = parseFloat(sellingEl.value);
  if (boughtEl.value === '' || isNaN(b)) return showErr('boughtErr', boughtEl, '');
  if (b < 0) return showErr('boughtErr', boughtEl, 'Bought price cannot be negative.');
  if (!isNaN(s) && b >= s) return showErr('boughtErr', boughtEl, 'Bought price must be less than selling price.');
  return showErr('boughtErr', boughtEl, '');
}

function validateQty() {
  const val = document.getElementById('currentQty').value;
  const el  = document.getElementById('currentQty');
  if (val === '') return showErr('qtyErr', el, 'Quantity is required.');
  if (!Number.isInteger(Number(val)) || Number(val) < 0) return showErr('qtyErr', el, 'Must be a whole number (0 or more).');
  return showErr('qtyErr', el, '');
}

document.getElementById('partName').addEventListener('input', validatePartName);
document.getElementById('partName').addEventListener('blur',  validatePartName);
document.getElementById('sellingPrice').addEventListener('input', validateSelling);
document.getElementById('sellingPrice').addEventListener('blur',  validateSelling);
document.getElementById('currentQty').addEventListener('input', validateQty);
document.getElementById('currentQty').addEventListener('blur',  validateQty);
if (showBought && document.getElementById('boughtPrice')) {
  document.getElementById('boughtPrice').addEventListener('input', validateBought);
  document.getElementById('boughtPrice').addEventListener('blur',  validateBought);
}

document.getElementById('inventoryForm').addEventListener('submit', function(e) {
  const v1 = validatePartName();
  const v2 = validateSelling();
  const v3 = validateBought();
  const v4 = validateQty();
  if (!v1 || !v2 || !v3 || !v4) e.preventDefault();
});
</script>
</body>
</html>
