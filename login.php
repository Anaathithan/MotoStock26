<?php
session_start();
require_once 'includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    // ── Server-side validation ────────────────────────────────────────────────
    if (!csrf_validate($token)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please fill in both fields.';
    } elseif (strlen($username) > 60) {
        $error = 'Username is too long.';
    } elseif (strlen($password) > 100) {
        $error = 'Password is too long.';
    } else {
        $stmt = $conn->prepare("SELECT userID, username, password, role FROM user WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $storedPassword = (string)($user['password'] ?? '');
            $isValid = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

            if ($isValid) {
                // Transparent migration from legacy plaintext passwords.
                if (!password_get_info($storedPassword)['algo']) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE user SET password = ? WHERE userID = ?");
                    if ($up) {
                        $up->bind_param("si", $newHash, $user['userID']);
                        $up->execute();
                        $up->close();
                    }
                }
                $_SESSION['userID']   = $user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                header("Location: index.php");
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — MotoStock26</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="login-body">
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">Moto<span>Stock</span></div>
    <div class="login-tagline">Bike Repair &amp; Parts Management System</div>
    <div class="login-divider"></div>

    <?php if ($error): ?>
      <div class="login-error">⚠ &nbsp;<?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label class="login-label">Username</label>
      <input type="text" name="username" id="loginUsername" class="login-input"
             placeholder="Enter your username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             maxlength="60" autocomplete="username" autofocus>
      <span style="font-size:.75rem;color:#f87171;margin-top:-10px;margin-bottom:8px;display:none" id="usernameErr"></span>

      <label class="login-label">Password</label>
      <input type="password" name="password" id="loginPassword" class="login-input"
             placeholder="••••••••" maxlength="100" autocomplete="current-password">
      <span style="font-size:.75rem;color:#f87171;margin-top:-10px;margin-bottom:8px;display:none" id="passwordErr"></span>

      <button type="submit" class="btn-login">Sign In →</button>
    </form>
  </div>
</div>

<script>
function validateLoginUsername() {
  const val = document.getElementById('loginUsername').value.trim();
  const err = document.getElementById('usernameErr');
  const inp = document.getElementById('loginUsername');
  if (val === '') {
    inp.style.borderColor = 'var(--red)';
    err.textContent = 'Username is required.'; err.style.display = 'block';
    return false;
  }
  if (val.length > 60) {
    inp.style.borderColor = 'var(--red)';
    err.textContent = 'Username is too long.'; err.style.display = 'block';
    return false;
  }
  inp.style.borderColor = '';
  err.style.display = 'none';
  return true;
}

function validateLoginPassword() {
  const val = document.getElementById('loginPassword').value;
  const err = document.getElementById('passwordErr');
  const inp = document.getElementById('loginPassword');
  if (val === '') {
    inp.style.borderColor = 'var(--red)';
    err.textContent = 'Password is required.'; err.style.display = 'block';
    return false;
  }
  if (val.length < 3) {
    inp.style.borderColor = 'var(--red)';
    err.textContent = 'Password is too short.'; err.style.display = 'block';
    return false;
  }
  inp.style.borderColor = '';
  err.style.display = 'none';
  return true;
}

document.getElementById('loginUsername').addEventListener('blur', validateLoginUsername);
document.getElementById('loginPassword').addEventListener('blur', validateLoginPassword);

document.getElementById('loginForm').addEventListener('submit', function(e) {
  const v1 = validateLoginUsername();
  const v2 = validateLoginPassword();
  if (!v1 || !v2) e.preventDefault();
});
</script>
</body>
</html>
