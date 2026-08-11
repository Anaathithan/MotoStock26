<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }
if (!in_array($_SESSION['role'], ['Owner', 'Cashier'])) { header("Location: list.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = ['name'=>'','phone'=>'','vehicleNo'=>'','email'=>'','lastServiceDate'=>'','nextServiceDue'=>''];

if ($id > 0) {
    $res = $conn->query("SELECT * FROM customer WHERE customerID = $id");
    if ($res->num_rows === 1) $customer = $res->fetch_assoc();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']            ?? '');
    $phone     = trim($_POST['phone']           ?? '');
    $vehicleNo = trim($_POST['vehicleNo']       ?? '');
    $email     = trim($_POST['email']           ?? '');
    $last      = trim($_POST['lastServiceDate'] ?? '');
    $next      = trim($_POST['nextServiceDue']  ?? '');
    $last      = $last ?: null;
    $next      = $next ?: null;

    // â”€â”€ Server-side validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($name === '') {
        $errors['name'] = 'Full name is required.';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.]{2,80}$/u', $name)) {
        $errors['name'] = 'Name must be 2â€“80 characters (letters and spaces only).';
    }

    if ($phone !== '' && !preg_match('/^(?:0|\+94)[0-9]{9}$/', preg_replace('/[\s\-]/','',$phone))) {
        $errors['phone'] = 'Enter a valid Sri Lanka phone number (e.g. 0771234567).';
    }

    if ($vehicleNo !== '' && !preg_match('/^[A-Za-z0-9\-\s]{2,15}$/', $vehicleNo)) {
        $errors['vehicleNo'] = 'Vehicle number: letters, numbers, hyphens only (2â€“15 chars).';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($last && $next && $next < $last) {
        $errors['nextServiceDue'] = 'Next service due date must be after the last service date.';
    }

    if (empty($errors)) {
        if ($id > 0) {
            $sql  = "UPDATE customer SET name=?, phone=?, vehicleNo=?, email=?, lastServiceDate=?, nextServiceDue=? WHERE customerID=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $name, $phone, $vehicleNo, $email, $last, $next, $id);
        } else {
            $sql  = "INSERT INTO customer (name, phone, vehicleNo, email, lastServiceDate, nextServiceDue) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $name, $phone, $vehicleNo, $email, $last, $next);
        }
        $stmt->execute();
        header("Location: list.php?success=1");
        exit;
    }

    // Repopulate from POST on error
    $customer = array_merge($customer, [
        'name' => $name, 'phone' => $phone, 'vehicleNo' => $vehicleNo, 'email' => $email,
        'lastServiceDate' => $last ?? '', 'nextServiceDue' => $next ?? ''
    ]);
}

$currentPage = 'customer';
$base = '../../';
$pageTitle = $id > 0 ? 'Edit Customer' : 'Add New Customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title"><?= $pageTitle ?></div>
      <div class="topbar-breadcrumb">Customers â†’ <?= $id > 0 ? 'Edit' : 'Create' ?></div>
    </div>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div class="page-title"><?= $pageTitle ?></div>
      <a href="list.php" class="btn btn-secondary">â† Back to List</a>
    </div>

    <div class="card" style="max-width:640px">
      <div class="card-header">Customer Details</div>
      <div class="card-body">

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">âš  Please fix the errors below before submitting.</div>
        <?php endif; ?>

        <form method="post" id="customerForm" novalidate>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" id="custName"
                     class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($customer['name']) ?>"
                     placeholder="e.g. Kamal Perera" maxlength="80">
              <span class="field-error" id="nameErr"><?= htmlspecialchars($errors['name'] ?? '') ?></span>
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="text" name="phone" id="custPhone"
                     class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($customer['phone']) ?>"
                     placeholder="e.g. 0771234567" maxlength="15">
              <span class="field-error" id="phoneErr"><?= htmlspecialchars($errors['phone'] ?? '') ?></span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" id="custEmail"
                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($customer['email'] ?? '') ?>"
                   placeholder="e.g. customer@email.com" maxlength="100">
            <span class="field-error" id="emailErr"><?= htmlspecialchars($errors['email'] ?? '') ?></span>
          </div>
          <div class="form-group">
            <label class="form-label">Vehicle Number</label>
            <input type="text" name="vehicleNo" id="custVehicle"
                   class="form-control <?= isset($errors['vehicleNo']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($customer['vehicleNo']) ?>"
                   placeholder="e.g. WP-CAR-1234" maxlength="15">
            <span class="field-error" id="vehicleErr"><?= htmlspecialchars($errors['vehicleNo'] ?? '') ?></span>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Last Service Date</label>
              <input type="date" name="lastServiceDate" id="lastDate"
                     class="form-control"
                     value="<?= htmlspecialchars($customer['lastServiceDate'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Next Service Due</label>
              <input type="date" name="nextServiceDue" id="nextDate"
                     class="form-control <?= isset($errors['nextServiceDue']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($customer['nextServiceDue'] ?? '') ?>">
              <span class="field-error" id="nextDateErr"><?= htmlspecialchars($errors['nextServiceDue'] ?? '') ?></span>
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-amber">Save Customer</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function showErr(elId, inputEl, msg) {
  const err = document.getElementById(elId);
  if (err) { err.textContent = msg; err.style.display = msg ? 'block' : 'none'; }
  if (msg) { inputEl.classList.add('is-invalid'); inputEl.classList.remove('is-valid'); }
  else     { inputEl.classList.remove('is-invalid'); inputEl.classList.add('is-valid'); }
  return !msg;
}

function validateName() {
  const val = document.getElementById('custName').value.trim();
  if (val === '') return showErr('nameErr', document.getElementById('custName'), 'Full name is required.');
  if (val.length < 2 || val.length > 80) return showErr('nameErr', document.getElementById('custName'), 'Name must be 2â€“80 characters.');
  if (!/^[\p{L}\s'\-\.]+$/u.test(val)) return showErr('nameErr', document.getElementById('custName'), 'Name should only contain letters and spaces.');
  return showErr('nameErr', document.getElementById('custName'), '');
}

function validatePhone() {
  const raw = document.getElementById('custPhone').value.trim();
  const val = raw.replace(/[\s\-]/g, '');
  if (raw === '') return showErr('phoneErr', document.getElementById('custPhone'), ''); // optional
  if (!/^(?:0|\+94)[0-9]{9}$/.test(val)) return showErr('phoneErr', document.getElementById('custPhone'), 'Enter a valid phone number (e.g. 0771234567).');
  return showErr('phoneErr', document.getElementById('custPhone'), '');
}

function validateVehicle() {
  const val = document.getElementById('custVehicle').value.trim();
  if (val === '') return showErr('vehicleErr', document.getElementById('custVehicle'), ''); // optional
  if (!/^[A-Za-z0-9\-\s]{2,15}$/.test(val)) return showErr('vehicleErr', document.getElementById('custVehicle'), 'Letters, numbers and hyphens only (2â€“15 chars).');
  return showErr('vehicleErr', document.getElementById('custVehicle'), '');
}

function validateDates() {
  const last = document.getElementById('lastDate').value;
  const next = document.getElementById('nextDate').value;
  const nextEl = document.getElementById('nextDate');
  if (last && next && next < last) {
    return showErr('nextDateErr', nextEl, 'Next service due must be after last service date.');
  }
  return showErr('nextDateErr', nextEl, '');
}

document.getElementById('custName').addEventListener('input', validateName);
document.getElementById('custName').addEventListener('blur',  validateName);
document.getElementById('custPhone').addEventListener('input', validatePhone);
document.getElementById('custPhone').addEventListener('blur',  validatePhone);
document.getElementById('custVehicle').addEventListener('input', validateVehicle);
document.getElementById('custVehicle').addEventListener('blur',  validateVehicle);
document.getElementById('lastDate').addEventListener('change', validateDates);
document.getElementById('nextDate').addEventListener('change', validateDates);

document.getElementById('customerForm').addEventListener('submit', function(e) {
  const v1 = validateName();
  const v2 = validatePhone();
  const v3 = validateVehicle();
  const v4 = validateDates();
  if (!v1 || !v2 || !v3 || !v4) e.preventDefault();
});
</script>
</body>
</html>
