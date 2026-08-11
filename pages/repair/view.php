<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/notify_events.php';
if (!isset($_SESSION['userID'])) { header("Location: ../../login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: list.php"); exit; }

// â”€â”€ Handle status update â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update') {
        $bikeNo   = $conn->real_escape_string(trim($_POST['bikeNo']   ?? ''));
        $problem  = $conn->real_escape_string(trim($_POST['problem']  ?? ''));
        $status   = $conn->real_escape_string(trim($_POST['status']   ?? ''));
        $warranty = isset($_POST['warranty']) ? 1 : 0;
        $customerID = !empty($_POST['customerID']) ? (int)$_POST['customerID'] : null;
        $customerVal = $customerID ? $customerID : 'NULL';

        $conn->query("UPDATE servicejob SET
            bikeNo = '$bikeNo',
            problemDescription = '$problem',
            status = '$status',
            isWarranty = $warranty,
            customerID = $customerVal
            WHERE jobID = $id");

        // Auto-notify if marked Finished
        if ($status === 'Finished') {
            $jRes = $conn->query("SELECT * FROM servicejob WHERE jobID = $id");
            if ($jRes) notify_repair_finished($conn, $jRes->fetch_assoc());
        }

        header("Location: view.php?id=$id&updated=1"); exit;
    }

    if ($_POST['action'] === 'delete' && $_SESSION['role'] === 'Owner') {
        $check = $conn->query("SELECT status FROM servicejob WHERE jobID = $id")->fetch_assoc();
        if ($check && $check['status'] === 'Finished') {
            $conn->query("DELETE FROM servicejob WHERE jobID = $id");
            header("Location: list.php?deleted=1"); exit;
        }
    }
}

// â”€â”€ Fetch job â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$job = $conn->query("
    SELECT sj.*, c.name as customerName, c.phone as customerPhone, c.vehicleNo as customerVehicle
    FROM servicejob sj
    LEFT JOIN customer c ON sj.customerID = c.customerID
    WHERE sj.jobID = $id
")->fetch_assoc();
if (!$job) { header("Location: list.php"); exit; }

// â”€â”€ Fetch customers for dropdown â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$customersRes = $conn->query("SELECT customerID, name, vehicleNo, phone FROM customer ORDER BY name ASC");
$customers = [];
if ($customersRes) { while ($r = $customersRes->fetch_assoc()) $customers[] = $r; }

$currentPage = 'repair';
$base = '../../';

$statusOptions = ['Investigating', 'InformingCustomer', 'Repairing', 'Finished'];
$badgeClass = match($job['status']) {
    'Finished'          => 'badge-success',
    'Repairing'         => 'badge-warning',
    'InformingCustomer' => 'badge-info',
    default             => 'badge-dark'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Repair Job #<?= $id ?> â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  <style>
    .detail-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; }
    .detail-item label { display:block; font-size:.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
    .detail-item span  { font-weight:600; font-size:1rem; }
    .section-divider   { border:none; border-top:1px solid var(--border); margin:24px 0; }
    .ts-wrapper.is-invalid .ts-control { border-color:var(--danger,#ef4444) !important; }
  </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Repair Job #<?= $id ?></div>
      <div class="topbar-breadcrumb">Repair Jobs â†’ Job #<?= $id ?></div>
    </div>
  </div>

  <div class="main-content">

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success">âœ… Repair job updated successfully.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-title">Repair Job #<?= $id ?></div>
      <div style="display:flex;gap:8px;">
        <a href="list.php" class="btn btn-secondary">â† Back to List</a>
        <?php if ($job['status'] === 'Finished' && $_SESSION['role'] === 'Owner'): ?>
        <form method="post" onsubmit="return confirm('Delete this finished repair job? This cannot be undone.')">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn btn-danger">ðŸ—‘ Delete</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Job Info Card -->
    <div class="card mb-3">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span>Job Details</span>
        <span class="badge <?= $badgeClass ?>"><?= $job['status'] ?></span>
      </div>
      <div class="card-body">
        <div class="detail-grid mb-3">
          <div class="detail-item">
            <label>Job ID</label>
            <span>#<?= $job['jobID'] ?></span>
          </div>
          <div class="detail-item">
            <label>Bike / Vehicle No.</label>
            <span><?= htmlspecialchars($job['bikeNo'] ?? 'â€”') ?></span>
          </div>
          <div class="detail-item">
            <label>Warranty</label>
            <span><?= $job['isWarranty'] ? '<span class="badge badge-info">Yes</span>' : '<span class="badge badge-dark">No</span>' ?></span>
          </div>
          <div class="detail-item">
            <label>Date Created</label>
            <span><?= date('d M Y, H:i', strtotime($job['created_at'])) ?></span>
          </div>
        </div>

        <!-- Linked Customer -->
        <?php if ($job['customerName']): ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
          <div>
            <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Linked Customer</div>
            <div style="font-weight:700;"><?= htmlspecialchars($job['customerName']) ?></div>
            <div style="font-size:.82rem;color:var(--muted);">
              <?= $job['customerPhone'] ? 'ðŸ“ž ' . htmlspecialchars($job['customerPhone']) : '' ?>
              <?= $job['customerVehicle'] ? ' &nbsp;ðŸš— ' . htmlspecialchars($job['customerVehicle']) : '' ?>
            </div>
          </div>
          <a href="../customer/view.php?id=<?= $job['customerID'] ?>" class="btn btn-sm btn-outline">View Profile</a>
        </div>
        <?php else: ?>
        <div style="color:var(--muted);font-size:.88rem;">No customer linked to this job.</div>
        <?php endif; ?>

        <hr class="section-divider">

        <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Problem Description</div>
        <div style="font-size:.95rem;line-height:1.6;"><?= nl2br(htmlspecialchars($job['problemDescription'])) ?></div>
      </div>
    </div>

    <!-- Edit Form -->
    <div class="card">
      <div class="card-header">âœ Edit Job</div>
      <div class="card-body">
        <form method="post" id="editForm">
          <input type="hidden" name="action" value="update">

          <!-- Customer -->
          <div class="form-group">
            <label class="form-label">Customer <span style="color:var(--muted);font-size:.78rem;">(optional)</span></label>
            <select name="customerID" id="customerID" class="form-control">
              <option value="">â€” Walk-in / No customer linked â€”</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= $c['customerID'] ?>"
                  data-vehicle="<?= htmlspecialchars($c['vehicleNo'] ?? '') ?>"
                  <?= $job['customerID'] == $c['customerID'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                  <?= $c['vehicleNo'] ? '(' . htmlspecialchars($c['vehicleNo']) . ')' : '' ?>
                  <?= $c['phone'] ? 'â€” ' . htmlspecialchars($c['phone']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Bike No -->
          <div class="form-group">
            <label class="form-label">Bike / Vehicle Number <span style="color:var(--danger)">*</span></label>
            <input type="text" name="bikeNo" id="bikeNo" class="form-control"
                   value="<?= htmlspecialchars($job['bikeNo']) ?>" maxlength="20" required>
            <span class="field-error" id="bikeNoErr" style="display:none;"></span>
          </div>

          <!-- Problem -->
          <div class="form-group">
            <label class="form-label">Problem Description <span style="color:var(--danger)">*</span></label>
            <textarea name="problem" id="problem" class="form-control" rows="4"
                      maxlength="1000" required><?= htmlspecialchars($job['problemDescription']) ?></textarea>
            <span class="field-error" id="problemErr" style="display:none;"></span>
          </div>

          <!-- Status -->
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <?php foreach ($statusOptions as $s): ?>
                <option value="<?= $s ?>" <?= $job['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Warranty -->
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
              <input type="checkbox" name="warranty" <?= $job['isWarranty'] ? 'checked' : '' ?> style="width:16px;height:16px;">
              <span class="form-label" style="margin:0;">Under Warranty</span>
            </label>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="submit" class="btn btn-amber">Save Changes</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
// Tom Select for customer dropdown
const tomCustomer = new TomSelect('#customerID', {
    placeholder: 'Search by name, vehicle or phoneâ€¦',
    allowEmptyOption: true,
});

// Auto-fill bike number when customer selected
tomCustomer.on('change', function(val) {
    const opt = document.querySelector(`#customerID option[value="${val}"]`);
    if (opt && opt.dataset.vehicle) {
        document.getElementById('bikeNo').value = opt.dataset.vehicle;
    }
});

// Validation
const bikeNoInput = document.getElementById('bikeNo');
const problemInput = document.getElementById('problem');

function showError(input, errId, msg) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    document.getElementById(errId).textContent = msg;
    document.getElementById(errId).style.display = 'block';
}
function showValid(input, errId) {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    document.getElementById(errId).style.display = 'none';
}

function validateBikeNo() {
    const val = bikeNoInput.value.trim();
    if (!val) { showError(bikeNoInput, 'bikeNoErr', 'Vehicle number is required.'); return false; }
    if (val.length < 2) { showError(bikeNoInput, 'bikeNoErr', 'Must be at least 2 characters.'); return false; }
    showValid(bikeNoInput, 'bikeNoErr'); return true;
}

function validateProblem() {
    const val = problemInput.value.trim();
    if (!val) { showError(problemInput, 'problemErr', 'Problem description is required.'); return false; }
    if (val.length < 10) { showError(problemInput, 'problemErr', `Too short â€” at least 10 characters (${val.length}/10).`); return false; }
    showValid(problemInput, 'problemErr'); return true;
}

bikeNoInput.addEventListener('input', validateBikeNo);
problemInput.addEventListener('input', validateProblem);

document.getElementById('editForm').addEventListener('submit', function(e) {
    const v1 = validateBikeNo();
    const v2 = validateProblem();
    if (!v1 || !v2) e.preventDefault();
});
</script>
</body>
</html>
