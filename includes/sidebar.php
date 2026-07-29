<?php
// includes/sidebar.php
// Usage: require_once ROOT . '/includes/sidebar.php';
// Caller must set $currentPage before including (e.g. $currentPage = 'dashboard')
$pages = [
    'dashboard' => ['label' => 'Dashboard',       'icon' => '📊', 'href' => $base . 'pages/dashboard.php'],
];
$modules = [
    'repair'    => ['label' => 'Repair Jobs',      'icon' => '🔧', 'href' => $base . 'pages/repair/list.php', 'roles' => ['Owner', 'Cashier', 'Employee']],
    'customer'  => ['label' => 'Customers',        'icon' => '👥', 'href' => $base . 'pages/customer/list.php', 'roles' => ['Owner', 'Cashier', 'Employee']],
    'inventory' => ['label' => 'Inventory',        'icon' => '📦', 'href' => $base . 'pages/inventory/list.php', 'roles' => ['Owner', 'Cashier', 'Employee']],
    'purchase'  => ['label' => 'Purchase Orders',  'icon' => '🛒', 'href' => $base . 'pages/purchase/list.php', 'roles' => ['Owner', 'Cashier']],
    'sale'      => ['label' => 'Sales / POS',      'icon' => '🧾', 'href' => $base . 'pages/sale/sales_list.php', 'roles' => ['Owner', 'Cashier']],
    'notifications' => ['label' => 'Notifications',   'icon' => '✉️', 'href' => $base . 'pages/notifications.php', 'roles' => ['Owner', 'Cashier']],
    'analytics'     => ['label' => 'Owner Analytics', 'icon' => '📊', 'href' => $base . 'pages/reports/analytics.php', 'roles' => ['Owner']],
];
$initials = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));
$username = $_SESSION['username'] ?? '';
$role     = $_SESSION['role'] ?? '';

// ── Build notifications ─────────────────────────────────────────────────────
$notifications = [];

// 1. Low stock items
$lowStockRes = $conn->query("SELECT partName, currentQuantity FROM SparePart WHERE currentQuantity < minQuantity ORDER BY currentQuantity ASC LIMIT 10");
if ($lowStockRes) {
    while ($ls = $lowStockRes->fetch_assoc()) {
        $notifications[] = [
            'icon' => '⚠️',
            'title' => 'Low Stock: ' . htmlspecialchars($ls['partName']),
            'sub'   => 'Only ' . $ls['currentQuantity'] . ' unit(s) remaining',
            'type'  => 'danger',
            'link'  => $base . 'pages/inventory/list.php',
        ];
    }
}

// 2. Overdue service reminders (nextServiceDue < today)
$today = date('Y-m-d');
$dueRes = $conn->query("SELECT name, vehicleNo, nextServiceDue FROM Customer WHERE nextServiceDue IS NOT NULL AND nextServiceDue < '$today' ORDER BY nextServiceDue ASC LIMIT 10");
if ($dueRes) {
    while ($dr = $dueRes->fetch_assoc()) {
        $notifications[] = [
            'icon' => '🔧',
            'title' => 'Service Due: ' . htmlspecialchars($dr['name']),
            'sub'   => htmlspecialchars($dr['vehicleNo']) . ' — due ' . date('d M Y', strtotime($dr['nextServiceDue'])),
            'type'  => 'warning',
            'link'  => $base . 'pages/customer/list.php',
        ];
    }
}

// 3. Repair jobs pending (status = 'Pending')
$pendingRes = $conn->query("SELECT COUNT(*) as cnt FROM ServiceJob WHERE status = 'Pending'");
if ($pendingRes) {
    $pendingRow = $pendingRes->fetch_assoc();
    if ($pendingRow['cnt'] > 0) {
        $notifications[] = [
            'icon' => '🕐',
            'title' => $pendingRow['cnt'] . ' Repair Job(s) Pending',
            'sub'   => 'Awaiting action',
            'type'  => 'info',
            'link'  => $base . 'pages/repair/list.php',
        ];
    }
}

$notifCount = count($notifications);
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <span class="logo-text">MotoStock</span>
    <span class="logo-sub">Bike Repair &amp; Parts Management System</span>
  </div>

  <ul class="sidebar-nav">
    <?php foreach ($pages as $key => $p): ?>
    <li>
      <a href="<?= $p['href'] ?>" class="<?= ($currentPage === $key) ? 'active' : '' ?>">
        <span class="nav-icon"><?= $p['icon'] ?></span><?= $p['label'] ?>
      </a>
    </li>
    <?php endforeach; ?>

    <li><div class="nav-section">Modules</div></li>

    <?php foreach ($modules as $key => $p): ?>
      <?php if (in_array($role, $p['roles'])): ?>
      <li>
        <a href="<?= $p['href'] ?>" class="<?= ($currentPage === $key) ? 'active' : '' ?>">
          <span class="nav-icon"><?= $p['icon'] ?></span><?= $p['label'] ?>
        </a>
      </li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-footer">
    <!-- Notification Bell -->
    <div class="notif-bell-wrap" style="margin-bottom:10px;">
      <button class="notif-bell" id="notifBell" onclick="toggleNotif(event)" title="Notifications">
        🔔
        <?php if ($notifCount > 0): ?>
        <span class="notif-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
        <?php endif; ?>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
          <span>Notifications <?php if ($notifCount > 0): ?><span style="color:var(--muted);font-weight:400;">(<?= $notifCount ?>)</span><?php endif; ?></span>
        </div>
        <div class="notif-list">
          <?php if (empty($notifications)): ?>
          <div class="notif-empty">✅ No new notifications</div>
          <?php else: ?>
            <?php foreach ($notifications as $n): ?>
            <a href="<?= $n['link'] ?>" class="notif-item unread" style="text-decoration:none;">
              <span class="notif-icon"><?= $n['icon'] ?></span>
              <div class="notif-body">
                <strong><?= $n['title'] ?></strong>
                <span><?= $n['sub'] ?></span>
              </div>
            </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <a href="<?= $base ?>pages/user.php" class="sidebar-user-link">
      <div class="sidebar-user <?= ($currentPage === 'user') ? 'active' : '' ?>">
      <div class="sidebar-avatar"><?= $initials ?></div>
      <div class="sidebar-user-info">
        <div class="s-name"><?= htmlspecialchars($username) ?></div>
        <div class="s-role"><?= htmlspecialchars($role) ?></div>
      </div>
      </div>
    </a>
    <a href="<?= $base ?>logout.php" class="btn-logout">Sign Out</a>
  </div>
</aside>
<script>
function toggleNotif(e) {
  e.stopPropagation();
  document.getElementById('notifDropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  var dd = document.getElementById('notifDropdown');
  if (dd && !dd.contains(e.target) && e.target.id !== 'notifBell') {
    dd.classList.remove('open');
  }
});
</script>
