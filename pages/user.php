<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['userID'])) {
    header("Location: ../login.php");
    exit;
}

$currentPage = 'user';
$base = '../';
$username = $_SESSION['username'] ?? 'Unknown';
$role = $_SESSION['role'] ?? 'Employee';
$userId = (int)($_SESSION['userID'] ?? 0);
$isOwner = ($role === 'Owner');
$error = '';
$success = '';

$roleOptions = ['Owner', 'Cashier', 'Employee'];
$dbRoleResult = $conn->query("SELECT DISTINCT role FROM user WHERE role IS NOT NULL AND role != '' ORDER BY role");
if ($dbRoleResult) {
    while ($r = $dbRoleResult->fetch_assoc()) {
        $dbRole = trim((string)$r['role']);
        if ($dbRole !== '' && !in_array($dbRole, $roleOptions, true)) {
            $roleOptions[] = $dbRole;
        }
    }
}
sort($roleOptions);

if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please refresh and try again.';
        $action = '';
    }

    if ($action === 'create_user') {
        $createUsername = trim($_POST['create_username'] ?? '');
        $createPassword = trim($_POST['create_password'] ?? '');
        $createRole = trim($_POST['create_role'] ?? 'Employee');

        if ($createUsername === '' || $createPassword === '' || $createRole === '') {
            $error = 'Username, password, and role are required to create an account.';
        } elseif (strlen($createUsername) < 3 || strlen($createUsername) > 40) {
            $error = 'Username must be 3â€“40 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9_\.\-]+$/', $createUsername)) {
            $error = 'Username can only contain letters, numbers, underscores, dots and hyphens.';
        } elseif (strlen($createPassword) < 4) {
            $error = 'Password must be at least 4 characters.';
        } elseif (strlen($createPassword) > 100) {
            $error = 'Password is too long (max 100 chars).';
        } elseif (!in_array($createRole, $roleOptions, true)) {
            $error = 'Invalid role selected.';
        } else {
            $dupStmt = $conn->prepare("SELECT userID FROM user WHERE username = ?");
            $dupStmt->bind_param("s", $createUsername);
            $dupStmt->execute();
            $dupResult = $dupStmt->get_result();

            if ($dupResult->num_rows > 0) {
                $error = 'That username is already taken.';
            } else {
                $hashedPassword = password_hash($createPassword, PASSWORD_DEFAULT);
                $createStmt = $conn->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
                $createStmt->bind_param("sss", $createUsername, $hashedPassword, $createRole);
                if ($createStmt->execute()) {
                    $success = 'New account created successfully.';
                } else {
                    $error = 'Could not create account. Please try again.';
                }
                $createStmt->close();
            }
            $dupStmt->close();
        }
    } elseif ($action === 'update_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $newRole = trim($_POST['new_role'] ?? '');

        if ($targetUserId <= 0 || $newUsername === '' || $newRole === '') {
            $error = 'Username and role are required.';
        } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 40) {
            $error = 'Username must be 3â€“40 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9_\.\-]+$/', $newUsername)) {
            $error = 'Username can only contain letters, numbers, underscores, dots and hyphens.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 4) {
            $error = 'Password must be at least 4 characters when provided.';
        } elseif (strlen($newPassword) > 100) {
            $error = 'Password is too long (max 100 chars).';
        } elseif (!in_array($newRole, $roleOptions, true)) {
            $error = 'Invalid role selected.';
        } else {
            $dupStmt = $conn->prepare("SELECT userID FROM user WHERE username = ? AND userID != ?");
            $dupStmt->bind_param("si", $newUsername, $targetUserId);
            $dupStmt->execute();
            $dupResult = $dupStmt->get_result();

            if ($dupResult->num_rows > 0) {
                $error = 'That username is already taken.';
            } else {
                if ($newPassword !== '') {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updStmt = $conn->prepare("UPDATE user SET username = ?, password = ?, role = ? WHERE userID = ?");
                    $updStmt->bind_param("sssi", $newUsername, $hashedPassword, $newRole, $targetUserId);
                } else {
                    $updStmt = $conn->prepare("UPDATE user SET username = ?, role = ? WHERE userID = ?");
                    $updStmt->bind_param("ssi", $newUsername, $newRole, $targetUserId);
                }

                if ($updStmt->execute()) {
                    if ($targetUserId === $userId) {
                        $_SESSION['username'] = $newUsername;
                        $_SESSION['role'] = $newRole;
                        $username = $newUsername;
                        $role = $newRole;
                        $isOwner = ($newRole === 'Owner');
                    }
                    $success = 'Account updated successfully.';
                } else {
                    $error = 'Could not update account. Please try again.';
                }
                $updStmt->close();
            }
            $dupStmt->close();
        }
    } elseif ($action === 'delete_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId <= 0) {
            $error = 'Invalid account selected.';
        } else {
            $roleStmt = $conn->prepare("SELECT role FROM user WHERE userID = ?");
            $roleStmt->bind_param("i", $targetUserId);
            $roleStmt->execute();
            $roleResult = $roleStmt->get_result();
            $targetUser = $roleResult ? $roleResult->fetch_assoc() : null;
            $roleStmt->close();

            if (!$targetUser) {
                $error = 'Account not found.';
            } elseif (($targetUser['role'] ?? '') === 'Owner') {
                $error = 'Owner accounts cannot be deleted.';
            } elseif ($targetUserId === $userId) {
                $error = 'You cannot delete your own currently logged-in account.';
            } else {
                $delStmt = $conn->prepare("DELETE FROM user WHERE userID = ?");
                $delStmt->bind_param("i", $targetUserId);
                if ($delStmt->execute()) {
                    $success = 'Account deleted successfully.';
                } else {
                    $error = 'Could not delete account. Please try again.';
                }
                $delStmt->close();
            }
        }
    }
}

$users = [];
if ($isOwner) {
    $usersResult = $conn->query("SELECT userID, username, role FROM user ORDER BY username ASC");
    if ($usersResult) {
        while ($u = $usersResult->fetch_assoc()) {
            $users[] = $u;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Info â€” MotoStock26</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <div class="topbar-title">User Info</div>
      <div class="topbar-breadcrumb">Your account details</div>
    </div>
  </div>

  <div class="main-content">
    <div class="card" style="max-width:520px;">
      <div class="card-header">Profile</div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="form-control"><?= htmlspecialchars($username) ?></div>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Role</label>
          <div class="form-control"><?= htmlspecialchars($role) ?></div>
        </div>
      </div>
    </div>

    <?php if ($isOwner): ?>
      <div class="card mt-3">
        <div class="card-header">Manage User Accounts (Owner)</div>
        <div class="card-body">
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
          <?php endif; ?>

          <div class="card mb-2" style="box-shadow:none;">
            <div class="card-header">Create New Account</div>
            <div class="card-body">
              <form method="post" class="form-grid-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Username</label>
                  <input type="text" name="create_username" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Password</label>
                  <input type="text" name="create_password" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Role</label>
                  <select name="create_role" class="form-control">
                    <?php foreach ($roleOptions as $option): ?>
                      <option value="<?= htmlspecialchars($option) ?>" <?= ($option === 'Employee') ? 'selected' : '' ?>>
                        <?= htmlspecialchars($option) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <input type="hidden" name="action" value="create_user">
                <div style="grid-column:1 / -1;">
                  <button type="submit" class="btn btn-success">Create Account</button>
                </div>
              </form>
            </div>
          </div>

          <div class="table-wrap" style="box-shadow:none;">
            <table class="table">
              <thead>
                <tr>
                  <th>User ID</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int)$u['userID'] ?></td>
                  <td>
                    <input type="text" name="new_username" class="form-control" style="min-width:180px;" required
                           form="upd-<?= (int)$u['userID'] ?>"
                           value="<?= htmlspecialchars($u['username']) ?>">
                  </td>
                  <td>
                    <select name="new_role" class="form-control" style="min-width:150px;"
                            form="upd-<?= (int)$u['userID'] ?>">
                      <?php foreach ($roleOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= ($u['role'] === $option) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($option) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <form id="upd-<?= (int)$u['userID'] ?>" method="post" style="display:inline-block;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="update_user">
                      <input type="hidden" name="target_user_id" value="<?= (int)$u['userID'] ?>">
                      <input type="password" name="new_password" class="form-control" style="min-width:180px;margin-bottom:6px;"
                             form="upd-<?= (int)$u['userID'] ?>" placeholder="Leave blank to keep current password">
                      <button type="submit" class="btn btn-sm btn-primary">Update</button>
                    </form>
                    <?php if (($u['role'] ?? '') !== 'Owner'): ?>
                      <form method="post" style="display:inline-block;margin-top:6px;"
                            onsubmit="return confirm('Delete this account?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="target_user_id" value="<?= (int)$u['userID'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
// â”€â”€ User form validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function validateUserField(inp, errId, rules) {
  const val = inp.value.trim();
  let msg = '';
  for (const rule of rules) {
    if (!rule.test(val)) { msg = rule.msg; break; }
  }
  let errEl = document.getElementById(errId);
  if (!errEl) {
    errEl = document.createElement('span');
    errEl.id = errId;
    errEl.className = 'field-error';
    inp.parentNode.appendChild(errEl);
  }
  if (msg) { inp.classList.add('is-invalid'); errEl.textContent = msg; errEl.style.display = 'block'; return false; }
  inp.classList.remove('is-invalid'); inp.classList.add('is-valid');
  errEl.style.display = 'none';
  return true;
}

const usernameRules = [
  { test: v => v.length >= 3,                       msg: 'Username must be at least 3 characters.' },
  { test: v => v.length <= 40,                      msg: 'Username cannot exceed 40 characters.' },
  { test: v => /^[A-Za-z0-9_\.\-]+$/.test(v),   msg: 'Only letters, numbers, underscores, dots and hyphens allowed.' }
];
const passwordRules = [
  { test: v => v === '' || v.length >= 4,   msg: 'Password must be at least 4 characters.' },
  { test: v => v.length <= 100, msg: 'Password cannot exceed 100 characters.' }
];

// Create form
const createForm = document.querySelector('form input[name="action"][value="create_user"]')?.closest('form');
if (createForm) {
  const uInp = createForm.querySelector('input[name="create_username"]');
  const pInp = createForm.querySelector('input[name="create_password"]');
  if (uInp) { uInp.addEventListener('blur', () => validateUserField(uInp, 'createUsernameErr', usernameRules)); }
  if (pInp) { pInp.addEventListener('blur', () => validateUserField(pInp, 'createPasswordErr', passwordRules)); }
  createForm.addEventListener('submit', function(e) {
    const v1 = uInp ? validateUserField(uInp, 'createUsernameErr', usernameRules) : true;
    const v2 = pInp ? validateUserField(pInp, 'createPasswordErr', passwordRules) : true;
    if (!v1 || !v2) e.preventDefault();
  });
}

// Update forms (inline table)
document.querySelectorAll('form[id^="upd-"]').forEach(function(form) {
  const uid  = form.id.replace('upd-', '');
  const uInp = document.querySelector('input[name="new_username"][form="upd-' + uid + '"]');
  const pInp = document.querySelector('input[name="new_password"][form="upd-' + uid + '"]');
  if (uInp) { uInp.addEventListener('blur', () => validateUserField(uInp, 'updUsernameErr' + uid, usernameRules)); }
  if (pInp) { pInp.addEventListener('blur', () => validateUserField(pInp, 'updPasswordErr' + uid, passwordRules)); }
  form.addEventListener('submit', function(e) {
    const v1 = uInp ? validateUserField(uInp, 'updUsernameErr' + uid, usernameRules) : true;
    const v2 = pInp ? validateUserField(pInp, 'updPasswordErr' + uid, passwordRules) : true;
    if (!v1 || !v2) e.preventDefault();
  });
});

</script>
</body>
</html>
