<?php
/**
 * includes/header.php — Sidebar + Topbar layout
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
requireLogin();
$flash = getFlash();
$cur   = $_SERVER['REQUEST_URI'] ?? '';
function navActive(string $path): string {
    global $cur;
    return (strpos($cur, $path) !== false) ? ' active' : '';
}

$quickActionUrl = APP_URL . '/public/visits/lookup.php';
$quickActionIcon = 'search';
$quickActionLabel = 'Lookup';
$quickDropdownLabel = 'Visit Lookup';
if (isOfficeStaff()) {
    $quickActionUrl = APP_URL . '/public/office/lookup.php';
    $quickActionIcon = 'scan-search';
    $quickActionLabel = 'Receive';
    $quickDropdownLabel = 'Receive Visitor';
} elseif (isGuestHouseStaff()) {
    $quickActionUrl = APP_URL . '/public/guest_house/bookings.php';
    $quickActionIcon = 'calendar-days';
    $quickActionLabel = 'Expected';
    $quickDropdownLabel = 'Expected Guests';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">

<!-- ═══ SIDEBAR ═══════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <div class="sidebar-brand spud-brand">
    <img src="<?= APP_URL ?>/assets/images/SPUD-LOGO1.png" alt="St. Paul University Dumaguete" class="spud-brand-logo">
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

  <?php if (isAdmin()): ?>
    <!-- ADMIN NAV -->
    <a href="<?= APP_URL ?>/public/dashboard/admin.php" class="nav-item<?= navActive('/dashboard/admin') ?>">
      <i data-lucide="layout-dashboard" class="nav-icon"></i><span class="nav-label">Dashboard</span>
    </a>
    <div class="nav-section-label">Registration</div>
    <a href="<?= APP_URL ?>/public/visits/walkin.php" class="nav-item<?= navActive('/visits/walkin') ?>">
      <i data-lucide="user-plus" class="nav-icon"></i><span class="nav-label">Walk-in Registration</span>
    </a>

    <div class="nav-section-label">Visit Management</div>
    <a href="<?= APP_URL ?>/public/visits/checkin.php" class="nav-item<?= navActive('/visits/checkin') ?>">
      <i data-lucide="log-in" class="nav-icon"></i><span class="nav-label">Check-in</span>
    </a>
    <a href="<?= APP_URL ?>/public/visits/checkout.php" class="nav-item<?= navActive('/visits/checkout') ?>">
      <i data-lucide="log-out" class="nav-icon"></i><span class="nav-label">Check-out</span>
    </a>
    <a href="<?= APP_URL ?>/public/visits/active.php" class="nav-item<?= navActive('/visits/active') ?>">
      <i data-lucide="users" class="nav-icon"></i><span class="nav-label">Active Visitors</span>
    </a>
    <a href="<?= APP_URL ?>/public/visits/lookup.php" class="nav-item<?= navActive('/visits/lookup') ?>">
      <i data-lucide="search" class="nav-icon"></i><span class="nav-label">Visit Lookup</span>
    </a>

    <div class="nav-section-label">Guests</div>
    <a href="<?= APP_URL ?>/public/guests/list.php" class="nav-item<?= navActive('/guests/list') ?>">
      <i data-lucide="contact" class="nav-icon"></i><span class="nav-label">Guest Directory</span>
    </a>
    <a href="<?= APP_URL ?>/public/guests/restricted.php" class="nav-item<?= navActive('/guests/restricted') ?>">
      <i data-lucide="ban" class="nav-icon"></i><span class="nav-label">Restricted Guests</span>
    </a>

    <div class="nav-section-label">Reports</div>
    <a href="<?= APP_URL ?>/public/reports/index.php" class="nav-item<?= navActive('/reports') ?>">
      <i data-lucide="bar-chart-2" class="nav-icon"></i><span class="nav-label">Reports &amp; Analytics</span>
    </a>
    <a href="<?= APP_URL ?>/public/admin/activity_logs.php" class="nav-item<?= navActive('/activity_logs') ?>">
      <i data-lucide="scroll-text" class="nav-icon"></i><span class="nav-label">Audit Logs</span>
    </a>

    <div class="nav-section-label">System</div>
    <a href="<?= APP_URL ?>/public/admin/offices.php" class="nav-item<?= navActive('/admin/offices') ?>">
      <i data-lucide="building-2" class="nav-icon"></i><span class="nav-label">Offices</span>
    </a>
    <a href="<?= APP_URL ?>/public/admin/users.php" class="nav-item<?= navActive('/admin/users') ?>">
      <i data-lucide="user-cog" class="nav-icon"></i><span class="nav-label">Users</span>
    </a>

    <div class="nav-section-label">Guest House</div>
    <a href="<?= APP_URL ?>/public/dashboard/guest_house.php" class="nav-item<?= navActive('/dashboard/guest_house') ?>">
      <i data-lucide="layout-dashboard" class="nav-icon"></i><span class="nav-label">GH Dashboard</span>
    </a>
    <a href="<?= APP_URL ?>/public/guest_house/bookings.php" class="nav-item<?= navActive('/guest_house/bookings') ?>">
      <i data-lucide="calendar-days" class="nav-icon"></i><span class="nav-label">Expected Guests</span>
    </a>
    <a href="<?= APP_URL ?>/public/guest_house/booking_create.php" class="nav-item<?= navActive('/guest_house/booking_create') ?>">
      <i data-lucide="calendar-plus" class="nav-icon"></i><span class="nav-label">Add Expected Guest</span>
    </a>
    <a href="<?= APP_URL ?>/public/guest_house/rooms.php" class="nav-item<?= navActive('/guest_house/rooms') ?>">
      <i data-lucide="door-open" class="nav-icon"></i><span class="nav-label">Rooms</span>
    </a>
    <a href="<?= APP_URL ?>/public/guest_house/room_types.php" class="nav-item<?= navActive('/guest_house/room_types') ?>">
      <i data-lucide="layers" class="nav-icon"></i><span class="nav-label">Room Types</span>
    </a>

  <?php elseif (isGuestHouseStaff()): ?>
    <!-- GUEST HOUSE STAFF NAV -->
    <a href="<?= APP_URL ?>/public/dashboard/guest_house.php" class="nav-item<?= navActive('/dashboard/guest_house') ?>">
      <i data-lucide="layout-dashboard" class="nav-icon"></i><span class="nav-label">Dashboard</span>
    </a>

    <div class="nav-section-label">Guest House</div>
    <a href="<?= APP_URL ?>/public/guest_house/bookings.php" class="nav-item<?= navActive('/guest_house/bookings') ?>">
      <i data-lucide="calendar-days" class="nav-icon"></i><span class="nav-label">Expected Guests</span>
    </a>
    <a href="<?= APP_URL ?>/public/guest_house/booking_create.php" class="nav-item<?= navActive('/guest_house/booking_create') ?>">
      <i data-lucide="calendar-plus" class="nav-icon"></i><span class="nav-label">Add Expected Guest</span>
    </a>

    <div class="nav-section-label">Rooms</div>
    <a href="<?= APP_URL ?>/public/guest_house/rooms.php" class="nav-item<?= navActive('/guest_house/rooms') ?>">
      <i data-lucide="door-open" class="nav-icon"></i><span class="nav-label">Rooms</span>
    </a>

  <?php elseif (isGuard()): ?>
    <!-- GUARD NAV -->
    <a href="<?= APP_URL ?>/public/dashboard/guard.php" class="nav-item<?= navActive('/dashboard/guard') ?>">
      <i data-lucide="layout-dashboard" class="nav-icon"></i><span class="nav-label">Dashboard</span>
    </a>
    <div class="nav-section-label">Registration</div>
    <a href="<?= APP_URL ?>/public/visits/walkin.php" class="nav-item<?= navActive('/visits/walkin') ?>">
      <i data-lucide="user-plus" class="nav-icon"></i><span class="nav-label">Walk-in Registration</span>
    </a>

    <div class="nav-section-label">Visit Management</div>
    <a href="<?= APP_URL ?>/public/visits/checkin.php" class="nav-item<?= navActive('/visits/checkin') ?>">
      <i data-lucide="log-in" class="nav-icon"></i><span class="nav-label">Check-in</span>
    </a>
    <a href="<?= APP_URL ?>/public/visits/checkout.php" class="nav-item<?= navActive('/visits/checkout') ?>">
      <i data-lucide="log-out" class="nav-icon"></i><span class="nav-label">Check-out</span>
    </a>
    <a href="<?= APP_URL ?>/public/visits/active.php" class="nav-item<?= navActive('/visits/active') ?>">
      <i data-lucide="users" class="nav-icon"></i><span class="nav-label">Active Visitors</span>
    </a>
    <a href="<?= APP_URL ?>/public/visits/lookup.php" class="nav-item<?= navActive('/visits/lookup') ?>">
      <i data-lucide="search" class="nav-icon"></i><span class="nav-label">Visit Lookup</span>
    </a>
    <a href="<?= APP_URL ?>/public/guests/list.php" class="nav-item<?= navActive('/guests/list') ?>">
      <i data-lucide="contact" class="nav-icon"></i><span class="nav-label">Guest Directory</span>
    </a>

  <?php else: ?>
    <!-- OFFICE STAFF NAV -->
    <a href="<?= APP_URL ?>/public/dashboard/office.php" class="nav-item<?= navActive('/dashboard/office') ?>">
      <i data-lucide="layout-dashboard" class="nav-icon"></i><span class="nav-label">Dashboard</span>
    </a>
    <div class="nav-section-label">My Guests</div>
    <a href="<?= APP_URL ?>/public/office/incoming.php" class="nav-item<?= navActive('/office/incoming') ?>">
      <i data-lucide="arrow-down-circle" class="nav-icon"></i><span class="nav-label">Incoming</span>
    </a>
    <a href="<?= APP_URL ?>/public/office/current.php" class="nav-item<?= navActive('/office/current') ?>">
      <i data-lucide="user-check" class="nav-icon"></i><span class="nav-label">Arrived Guests</span>
    </a>
    <a href="<?= APP_URL ?>/public/office/history.php" class="nav-item<?= navActive('/office/history') ?>">
      <i data-lucide="history" class="nav-icon"></i><span class="nav-label">History</span>
    </a>

    <div class="nav-section-label">Visit Management</div>
    <a href="<?= APP_URL ?>/public/office/lookup.php" class="nav-item<?= navActive('/office/lookup') ?>">
      <i data-lucide="scan-search" class="nav-icon"></i><span class="nav-label">Receive Visitor</span>
    </a>
  <?php endif; ?>

  </nav>

  <!-- Building image at bottom -->
  <div class="sidebar-footer">
    <img src="<?= APP_URL ?>/assets/images/home1.jpg" alt="St. Paul University Dumaguete campus">
  </div>
</aside>

<!-- ═══ MAIN AREA ══════════════════════════════════════════ -->
<div class="main-area" id="mainArea">

  <!-- TOPBAR -->
  <header class="topbar">
    <!-- Hamburger -->
    <button class="topbar-toggle" id="sidebarToggle" title="Toggle sidebar">
      <i data-lucide="menu" style="width:20px;height:20px;"></i>
    </button>

    <!-- Date & Time -->
    <div class="topbar-date">
      <i data-lucide="calendar"></i>
      <span id="topbarDate"></span>
      <span class="topbar-divider">|</span>
      <span id="topbarTime"></span>
    </div>

    <div class="topbar-right">
      <!-- Notifications -->
      <button class="notif-btn" title="Notifications">
        <i data-lucide="bell" style="width:20px;height:20px;"></i>
        <span class="notif-badge">3</span>
      </button>

      <!-- User menu -->
      <div class="user-menu" id="userMenuBtn">
        <div class="user-avatar">
          <?= strtoupper(substr(currentUserName(), 0, 1)) ?>
        </div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars(currentUserName()) ?></div>
          <div class="user-role"><?= htmlspecialchars(statusLabel(currentUserRole())) ?></div>
        </div>
        <i data-lucide="chevron-down" class="user-chevron"></i>
      </div>

      <a href="<?= $quickActionUrl ?>" class="topbar-action-btn topbar-action-lookup">
        <i data-lucide="<?= $quickActionIcon ?>"></i>
        <span><?= $quickActionLabel ?></span>
      </a>

      <a href="<?= APP_URL ?>/public/auth/logout.php" class="topbar-action-btn topbar-action-logout">
        <i data-lucide="log-out"></i>
        <span>Logout</span>
      </a>

      <!-- Dropdown (simple) -->
      <div id="userDropdown" style="display:none;position:absolute;top:60px;right:16px;background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-lg);min-width:180px;z-index:200;padding:8px 0;">
        <div style="padding:10px 14px;border-bottom:1px solid var(--border);font-size:.8rem;color:var(--text-m);">
          <?= htmlspecialchars($_SESSION['user_username'] ?? '') ?>
          <?php if (!empty($_SESSION['office_name'])): ?>
          <div><?= htmlspecialchars($_SESSION['office_name']) ?></div>
          <?php endif; ?>
        </div>
        <a href="<?= $quickActionUrl ?>" style="display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:.85rem;color:var(--text);transition:background .15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='none'">
          <i data-lucide="<?= $quickActionIcon ?>" style="width:14px;height:14px;"></i> <?= $quickDropdownLabel ?>
        </a>
        <hr style="margin:4px 0;border:none;border-top:1px solid var(--border);">
        <a href="<?= APP_URL ?>/public/auth/logout.php" style="display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:.85rem;color:var(--danger);transition:background .15s;" onmouseover="this.style.background='#fff5f5'" onmouseout="this.style.background='none'">
          <i data-lucide="log-out" style="width:14px;height:14px;"></i> Sign Out
        </a>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">

  <?php if ($flash): ?>
  <div class="flash-msg flash-<?= $flash['type'] === 'error' ? 'error' : htmlspecialchars($flash['type']) ?>" id="flashMsg">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>"></i>
    <span><?= $flash['message'] ?></span>
    <button class="flash-close" onclick="document.getElementById('flashMsg').remove()">
      <i data-lucide="x" style="width:14px;height:14px;"></i>
    </button>
  </div>
  <?php endif; ?>
