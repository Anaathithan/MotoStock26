<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$errors = [];

$customersRes = $conn->query("SELECT customerID, name, vehicleNo, phone FROM customer ORDER BY name ASC");
$customers = [];
if ($customersRes) {
while ($row = $customersRes->fetch_assoc()) { $customers[] = $row; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bikeNo     = trim($_POST['bikeNo'] ?? '');
    $problem    = trim($_POST['problem'] ?? '');
    $isWarranty = isset($_POST['warranty']) ? 1 : 0;
    $customerID = !empty($_POST['customerID']) ? (int)$_POST['customerID'] : null;

    // â”€â”€ Server-side validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($bikeNo === '') {
        $errors['bikeNo'] = 'Vehicle number is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-\s]{2,20}$/', $bikeNo)) {
        $errors['bikeNo'] = 'Enter a valid vehicle number (letters, numbers, hyphens only, 2â€“20 chars).';
    }

    if ($problem === '') {
        $errors['problem'] = 'Problem description is required.';
    } elseif (strlen($problem) < 5) {
        $errors['problem'] = 'Description must be at least 5 characters.';
    } elseif (strlen($problem) > 500) {
        $errors['problem'] = 'Description cannot exceed 500 characters.';
    }

    if (empty($errors)) {
        $sql  = "INSERT INTO servicejob (customerID, bikeNo, problemDescription, isWarranty) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issi", $customerID, $bikeNo, $problem, $isWarranty);
        $stmt->execute();
        header("Location: list.php?success=1");
        exit;
    }
}

$currentPage = 'repair';
$base = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Repair Job â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</head>
<style>
.ts-wrapper.is-invalid .ts-control {
    border-color: var(--danger, #ef4444) !important;
}
</style>

<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">New Repair Job</div>
      <div class="topbar-breadcrumb">Repair Jobs â†’ Create</div>
    </div>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div class="page-title">New Repair Job</div>
      <a href="list.php" class="btn btn-secondary">â† Back to List</a>
    </div>

    <div class="card" style="max-width:1000px">
      <div class="card-header">Job Details</div>
      <div class="card-body">

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">âš  Please fix the errors below before submitting.</div>
        <?php endif; ?>

        <form method="post" id="repairForm" novalidate>
          <div class="form-group">
            <div class="form-group">
              <label class="form-label">Customer <span style="color:var(--muted);font-size:.78rem">(optional)</span></label>
              <select name="customerID" id="customerID" class="form-control">
                <option value="">â€” Walk-in / No customer linked â€”</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= $c['customerID'] ?>"
                    data-vehicle="<?= htmlspecialchars($c['vehicleNo'] ?? '') ?>"
                    <?= (isset($_POST['customerID']) && $_POST['customerID'] == $c['customerID']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                    <?php if ($c['vehicleNo']): ?>(<?= htmlspecialchars($c['vehicleNo']) ?>)<?php endif; ?>
                    <?php if ($c['phone']): ?>â€” <?= htmlspecialchars($c['phone']) ?><?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
          </div>

            <label class="form-label">Bike / Vehicle Number *</label>
            <input type="text" name="bikeNo" id="bikeNo"
                   class="form-control <?= isset($errors['bikeNo']) ? 'is-invalid' : '' ?>"
                   placeholder="e.g. WP-1234 or ABC-5678"
                   value="<?= htmlspecialchars($_POST['bikeNo'] ?? '') ?>"
                   maxlength="20">
            <?php if (isset($errors['bikeNo'])): ?>
              <span class="field-error"><?= htmlspecialchars($errors['bikeNo']) ?></span>
            <?php else: ?>
              <span class="field-error" id="bikeNoErr" style="display:none"></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Problem Description *</label>
            <textarea name="problem" id="problem" rows="4"
                      class="form-control <?= isset($errors['problem']) ? 'is-invalid' : '' ?>"
                      placeholder="Describe the issue in detailâ€¦ (5â€“500 characters)"
                      maxlength="500"><?= htmlspecialchars($_POST['problem'] ?? '') ?></textarea>
            <div class="char-counter" id="problemCounter">0 / 500</div>
            <?php if (isset($errors['problem'])): ?>
              <span class="field-error"><?= htmlspecialchars($errors['problem']) ?></span>
            <?php else: ?>
              <span class="field-error" id="problemErr" style="display:none"></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <div class="form-check">
              <input type="checkbox" name="warranty" id="warranty" <?= isset($_POST['warranty']) ? 'checked' : '' ?>>
              <label for="warranty" class="form-label" style="margin-bottom:0;cursor:pointer">Warranty Service</label>
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-amber">Save Repair Job</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const tomCustomer = new TomSelect('#customerID', {
    placeholder: 'Search by name, vehicle or phoneâ€¦',
    allowEmptyOption: true,
    searchField: ['text'],
    render: {
        option: function(data, escape) {
            return '<div>' + escape(data.text) + '</div>';
        }
    }
});

// â”€â”€ Live validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const bikeNoInput = document.getElementById('bikeNo');
const problemInput = document.getElementById('problem');
const problemCounter = document.getElementById('problemCounter');

function updateCounter() {
  const len = problemInput.value.length;
  problemCounter.textContent = len + ' / 500';
  problemCounter.className = 'char-counter' + (len > 450 ? (len >= 500 ? ' over' : ' warn') : '');
}

function validateCustomer() {
    const errEl = document.getElementById('customerErr');
    if (!tomCustomer.getValue()) {
        tomCustomer.control.classList.add('is-invalid');
        errEl.textContent = 'Please choose a customer first.';
        errEl.style.display = 'block';
        return false;
    }
    tomCustomer.control.classList.remove('is-invalid');
    errEl.style.display = 'none';
    return true;
}

tomCustomer.on('change', validateCustomer);

function validateBikeNo() {
  const val = bikeNoInput.value.trim();
  const errEl = document.getElementById('bikeNoErr');
  const pattern = /^[A-Za-z0-9\-\s]{2,20}$/;
  if (val === '') {
    bikeNoInput.classList.add('is-invalid'); bikeNoInput.classList.remove('is-valid');
    if (errEl) { errEl.textContent = 'Vehicle number is required.'; errEl.style.display = 'block'; }
    return false;
  } else if (!pattern.test(val)) {
    bikeNoInput.classList.add('is-invalid'); bikeNoInput.classList.remove('is-valid');
    if (errEl) { errEl.textContent = 'Letters, numbers and hyphens only (2â€“20 chars).'; errEl.style.display = 'block'; }
    return false;
  }
  bikeNoInput.classList.remove('is-invalid'); bikeNoInput.classList.add('is-valid');
  if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
  return true;
}

function validateProblem() {
  const val = problemInput.value.trim();
  const errEl = document.getElementById('problemErr');
  if (val === '') {
    problemInput.classList.add('is-invalid'); problemInput.classList.remove('is-valid');
    if (errEl) { errEl.textContent = 'Problem description is required.'; errEl.style.display = 'block'; }
    return false;
  } else if (val.length < 15) {
    problemInput.classList.add('is-invalid'); problemInput.classList.remove('is-valid');
    if (errEl) { errEl.textContent = 'Must be at least 15 characters.'; errEl.style.display = 'block'; }
    return false;
  }
  problemInput.classList.remove('is-invalid'); problemInput.classList.add('is-valid');
  if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
  return true;
}

bikeNoInput.addEventListener('input', validateBikeNo);
bikeNoInput.addEventListener('blur',  validateBikeNo);
problemInput.addEventListener('input', function() { updateCounter(); validateProblem(); });
problemInput.addEventListener('blur',  validateProblem);
updateCounter();

document.getElementById('repairForm').addEventListener('submit', function(e) {
  const v1 = validateBikeNo();
  const v2 = validateProblem();
  if (!v1 || !v2) e.preventDefault();
});

// Auto-fill bike number when a customer is selected
document.getElementById('customerID').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const vehicle = opt.dataset.vehicle || '';
    if (vehicle) {
        bikeNoInput.value = vehicle;
        validateBikeNo();
    }
});
</script>
</body>
</html>
