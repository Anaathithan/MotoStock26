<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/mailer.php';
require_once '../includes/notify_events.php';

if (!isset($_SESSION['userID'])) { header("Location: ../login.php"); exit; }

$currentPage = 'notifications';
$base = '../';
$success = '';
$error   = '';

if (!in_array($_SESSION['role'], ['Owner', 'Cashier'])) {
    header("Location: ../../pages/dashboard.php"); exit;
}

$hasNotificationTable = ms_notification_table_exists($conn);
$hasCustomerEmailCol = false;
$chkCustomerEmail = $conn->query("SHOW COLUMNS FROM customer LIKE 'email'");
if ($chkCustomerEmail && $chkCustomerEmail->num_rows > 0) {
    $hasCustomerEmailCol = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'send_reminders') {
        $today = date('Y-m-d');
        $reminderWhere = $hasCustomerEmailCol ? "AND email IS NOT NULL AND email != ''" : '';
        $res = $conn->query(
            "SELECT * FROM customer
             WHERE nextServiceDue IS NOT NULL
               AND nextServiceDue <= '$today'
               {$reminderWhere}"
        );
        $sent = 0;
        if ($res) {
            while ($c = $res->fetch_assoc()) {
                notify_service_due($conn, $c);
                $sent++;
            }
        }
        if ($hasCustomerEmailCol) {
            $success = $sent > 0
                ? "Sent {$sent} service reminder(s) to overdue customers."
                : "No overdue customers with an email address found.";
        } else {
            $success = $sent > 0
                ? "Logged {$sent} service reminder(s). Customer email column is missing, so emails were not sent."
                : "No overdue customers found.";
        }

    } elseif ($_POST['action'] === 'send_low_stock') {
        $res = $conn->query("SELECT partName, currentQuantity, minQuantity FROM sparepart WHERE currentQuantity < minQuantity");
        $parts = [];
        if ($res) { while ($p = $res->fetch_assoc()) $parts[] = $p; }
        if (count($parts) > 0) {
            notify_low_stock_summary($conn, $parts);
            $ownerEmail = defined('OWNER_EMAIL') ? OWNER_EMAIL : 'the owner email';
            $success = "Low stock alert logged and emailed to {$ownerEmail} for " . count($parts) . " part(s).";
        } else {
            $success = "All parts are above minimum stock level — no alert needed.";
        }

    } elseif ($_POST['action'] === 'send_custom') {
        $toEmail = trim($_POST['to_email'] ?? '');
        $subject = trim($_POST['subject']  ?? '');
        $msg     = trim($_POST['message']  ?? '');
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($subject) < 10) {
            $error = 'Subject must be at least 10 characters.';
        } elseif (strlen($msg) < 20) {
            $error = 'Message must be at least 20 characters.';
        } else {
            ms_notify($conn, 'custom', $subject, $msg, $toEmail);
            $success = "Notification sent to {$toEmail}.";
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$logs = $hasNotificationTable ? $conn->query("SELECT * FROM notification ORDER BY sentAt DESC LIMIT 100") : false;

$today = date('Y-m-d');
$overdueSelectEmail = $hasCustomerEmailCol ? "email" : "NULL AS email";
$overdueRes = $conn->query(
    "SELECT name, vehicleNo, {$overdueSelectEmail}, nextServiceDue FROM customer
     WHERE nextServiceDue IS NOT NULL AND nextServiceDue <= '$today'
     ORDER BY nextServiceDue ASC"
);
$overdueCustomers = [];
if ($overdueRes) { while ($r = $overdueRes->fetch_assoc()) $overdueCustomers[] = $r; }

$lowStockRes = $conn->query("SELECT partName, currentQuantity, minQuantity FROM sparepart WHERE currentQuantity < minQuantity ORDER BY currentQuantity ASC");
$lowStockParts = [];
if ($lowStockRes) { while ($r = $lowStockRes->fetch_assoc()) $lowStockParts[] = $r; }

$totalLogged = 0;
if ($hasNotificationTable) {
    $totalLogged = (int)($conn->query("SELECT COUNT(*) as c FROM notification")->fetch_assoc()['c'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications — MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">Notifications</div>
      <div class="topbar-breadcrumb">Email alerts &amp; notification log</div>
    </div>
  </div>

  <div class="main-content">

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!$hasNotificationTable): ?>
      <div class="alert alert-warning">⚠ Notification history table is missing. Emails can still be sent, but logs are disabled until the `notification` table is created.</div>
    <?php endif; ?>

    <!-- Service Reminders + Low Stock -->
    <div class="grid-2 mb-3">

      <!-- Service Reminders -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
          <span>🔧 Service Reminders</span>
          <?php if (count($overdueCustomers) > 0): ?>
            <span class="badge badge-warning"><?= count($overdueCustomers) ?> overdue</span>
          <?php else: ?>
            <span class="badge badge-success">All clear</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (count($overdueCustomers) > 0): ?>
            <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;margin:0 -20px;">
              <table class="table" style="margin:0;">
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Vehicle</th>
                    <th>Due Date</th>
                    <th>Email</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($overdueCustomers as $c): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td style="color:var(--muted);font-size:.85rem;"><?= htmlspecialchars($c['vehicleNo']) ?></td>
                    <td><span class="badge badge-danger"><?= date('d M Y', strtotime($c['nextServiceDue'])) ?></span></td>
                    <td style="font-size:.82rem;">
                      <?php if ($c['email']): ?>
                        <span style="color:#2dd5a0;">✓ <?= htmlspecialchars($c['email']) ?></span>
                      <?php else: ?>
                        <span style="color:var(--muted);">No email</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div style="padding-top:16px;">
              <form method="post">
                <input type="hidden" name="action" value="send_reminders">
                <button class="btn btn-amber" type="submit">📨 Send Reminders</button>
              </form>
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:32px 0;color:var(--muted);">
              <div style="font-size:2rem;margin-bottom:8px;">✅</div>
              <div style="font-weight:600;">No overdue customers</div>
              <div style="font-size:.82rem;margin-top:4px;">All service schedules are up to date.</div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Low Stock Alerts -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
          <span>⚠ Low Stock Alerts</span>
          <?php if (count($lowStockParts) > 0): ?>
            <span class="badge badge-danger"><?= count($lowStockParts) ?> low</span>
          <?php else: ?>
            <span class="badge badge-success">All stocked</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (count($lowStockParts) > 0): ?>
            <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;margin:0 -20px;">
              <table class="table" style="margin:0;">
                <thead>
                  <tr>
                    <th>Part Name</th>
                    <th style="text-align:center;">Current</th>
                    <th style="text-align:center;">Minimum</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($lowStockParts as $p): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($p['partName']) ?></strong></td>
                    <td style="text-align:center;"><span class="badge badge-danger"><?= (int)$p['currentQuantity'] ?></span></td>
                    <td style="text-align:center;color:var(--muted);"><?= (int)$p['minQuantity'] ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div style="padding-top:16px;">
              <form method="post">
                <input type="hidden" name="action" value="send_low_stock">
                <button class="btn btn-amber" type="submit">📨 Email Report to Owner</button>
              </form>
              <p style="font-size:.78rem;color:var(--muted);margin-top:8px;">
                📬 Sending to: <strong><?= htmlspecialchars(defined('OWNER_EMAIL') ? OWNER_EMAIL : 'Not configured') ?></strong>
              </p>
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:32px 0;color:var(--muted);">
              <div style="font-size:2rem;margin-bottom:8px;">✅</div>
              <div style="font-weight:600;">All parts stocked</div>
              <div style="font-size:.82rem;margin-top:4px;">No parts are below minimum level.</div>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Custom Notification -->
    <div class="card mb-3">
      <div class="card-header">✉ Send Custom Notification</div>
      <div class="card-body">
        <form method="post" id="customForm" novalidate>
          <input type="hidden" name="action" value="send_custom">
          <div class="form-grid-3">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">To Email <span style="color:var(--danger)">*</span></label>
              <input type="email" id="customEmail" name="to_email" class="form-control" placeholder="customer@email.com">
              <span class="field-error" id="emailErr" style="display:none;"></span>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Subject <span style="color:var(--danger)">*</span> <span id="subjectCount" style="color:var(--muted);font-size:.75rem;font-weight:400;">(0/10)</span></label>
              <input type="text" id="customSubject" name="subject" class="form-control" placeholder="Subject…" maxlength="100">
              <span class="field-error" id="subjectErr" style="display:none;"></span>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Message <span style="color:var(--danger)">*</span> <span id="messageCount" style="color:var(--muted);font-size:.75rem;font-weight:400;">(0/20)</span></label>
              <input type="text" id="customMessage" name="message" class="form-control" placeholder="Your message…" maxlength="500">
              <span class="field-error" id="messageErr" style="display:none;"></span>
            </div>
          </div>
          <div style="margin-top:16px;">
            <button class="btn btn-amber" type="submit">Send Notification</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Notification Log -->
    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span>📋 Notification Log</span>
        <span style="font-size:.78rem;color:var(--muted);">Last 100 entries</span>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0;">
        <table class="table">
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Type</th>
              <th>Title</th>
              <th>Message</th>
              <th>Sent To</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($logs && $logs->num_rows > 0): while ($n = $logs->fetch_assoc()):
            $typeBadge = 'badge-dark';
            if ($n['type'] === 'low_stock') {
              $typeBadge = 'badge-danger';
            } elseif ($n['type'] === 'repair_finished') {
              $typeBadge = 'badge-success';
            } elseif ($n['type'] === 'sale') {
              $typeBadge = 'badge-info';
            } elseif ($n['type'] === 'service_due') {
              $typeBadge = 'badge-warning';
            }

            $typeLabel = $n['type'];
            if ($n['type'] === 'low_stock') {
              $typeLabel = '📦 Low Stock';
            } elseif ($n['type'] === 'repair_finished') {
              $typeLabel = '🔧 Repair Done';
            } elseif ($n['type'] === 'sale') {
              $typeLabel = '🛒 Sale';
            } elseif ($n['type'] === 'service_due') {
              $typeLabel = '🗓 Service Due';
            } elseif ($n['type'] === 'custom') {
              $typeLabel = '✉ Custom';
            }
          ?>
          <tr>
            <td style="white-space:nowrap;font-size:.78rem;color:var(--muted);">
              <?= date('d M Y', strtotime($n['sentAt'])) ?><br>
              <span style="font-size:.72rem;"><?= date('H:i', strtotime($n['sentAt'])) ?></span>
            </td>
            <td><span class="badge <?= $typeBadge ?>"><?= $typeLabel ?></span></td>
            <td><strong style="font-size:.88rem;"><?= htmlspecialchars($n['title']) ?></strong></td>
            <td style="font-size:.82rem;color:var(--muted);max-width:240px;"><?= htmlspecialchars($n['message']) ?></td>
            <td style="font-size:.82rem;"><?= $n['toEmail'] ? htmlspecialchars($n['toEmail']) : '<span style="color:var(--muted)">—</span>' ?></td>
            <td>
              <?php if ($n['emailSent']): ?>
                <span class="badge badge-success">✓ Sent</span>
              <?php else: ?>
                <span class="badge badge-dark">Logged</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr>
            <td colspan="6" style="text-align:center;color:var(--muted);padding:32px;">No notifications logged yet.</td>
          </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
const emailInput   = document.getElementById('customEmail');
const subjectInput = document.getElementById('customSubject');
const messageInput = document.getElementById('customMessage');
const subjectCount = document.getElementById('subjectCount');
const messageCount = document.getElementById('messageCount');

function showError(input, errId, msg) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    const err = document.getElementById(errId);
    err.textContent = msg;
    err.style.display = 'block';
}

function showValid(input, errId) {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    document.getElementById(errId).style.display = 'none';
}

function validateEmail() {
    const val = emailInput.value.trim();
    if (!val) { showError(emailInput, 'emailErr', 'Email is required.'); return false; }
    if (!val.includes('@') || !val.includes('.')) { showError(emailInput, 'emailErr', 'Enter a valid email address with @ and domain.'); return false; }
    showValid(emailInput, 'emailErr'); return true;
}

function validateSubject() {
    const val = subjectInput.value.trim();
    const len = val.length;
    subjectCount.textContent = `(${len}/10)`;
    subjectCount.style.color = len >= 10 ? '#2dd5a0' : 'var(--muted)';
    if (!val) { showError(subjectInput, 'subjectErr', 'Subject is required.'); return false; }
    if (len < 10) { showError(subjectInput, 'subjectErr', `Subject must be at least 10 characters (${len}/10).`); return false; }
    showValid(subjectInput, 'subjectErr'); return true;
}

function validateMessage() {
    const val = messageInput.value.trim();
    const len = val.length;
    messageCount.textContent = `(${len}/20)`;
    messageCount.style.color = len >= 20 ? '#2dd5a0' : 'var(--muted)';
    if (!val) { showError(messageInput, 'messageErr', 'Message is required.'); return false; }
    if (len < 20) { showError(messageInput, 'messageErr', `Message must be at least 20 characters (${len}/20).`); return false; }
    showValid(messageInput, 'messageErr'); return true;
}

emailInput.addEventListener('blur', validateEmail);
emailInput.addEventListener('input', validateEmail);
subjectInput.addEventListener('input', validateSubject);
subjectInput.addEventListener('blur', validateSubject);
messageInput.addEventListener('input', validateMessage);
messageInput.addEventListener('blur', validateMessage);

document.getElementById('customForm').addEventListener('submit', function(e) {
    const v1 = validateEmail();
    const v2 = validateSubject();
    const v3 = validateMessage();
    if (!v1 || !v2 || !v3) e.preventDefault();
});
</script>

</body>
</html>