<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Shared private/admin layout header and navigation used after a page has loaded its data.
 * Flow: Included by public pages and modules to reuse common behavior across the system.
 * Security: Keep access checks in the calling page and escape user-controlled output before displaying it.
 */
/**
 * includes/header.php — Sidebar + Topbar layout
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
// Study security: this page requires an active login before any private data is shown.
requireLogin();
$flash = getFlash();
$cur   = $_SERVER['REQUEST_URI'] ?? '';
/**
 * Study function: Supports the nav active workflow in this feature area.
 */
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

if (!function_exists('topbarNotifications')) {
function topbarNotifications(): array {
    if (isGuestHouseStaff()) {
        return [
            'title' => 'Notifications',
            'viewAllUrl' => APP_URL . '/public/dashboard/guest_house.php',
            'items' => [],
        ];
    }

    $db = getDB();
    $items = [];

    if (isAdminOrGuard()) {
        $stmt = $db->prepare("
            SELECT gv.visit_id, gv.visit_reference, gv.visit_date, gv.expected_time_in,
                   g.full_name AS guest_name,
                   GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS offices
            FROM guest_visits gv
            JOIN guests g ON gv.guest_id = g.guest_id
            LEFT JOIN visit_destinations vd ON vd.visit_id = gv.visit_id
            LEFT JOIN offices o ON o.office_id = vd.office_id
            WHERE gv.registration_type = 'pre_registered'
              AND gv.overall_status = 'pending'
              AND gv.visit_date >= CURDATE()
            GROUP BY gv.visit_id
            ORDER BY gv.visit_date ASC, gv.expected_time_in ASC, gv.created_at DESC
            LIMIT 8
        ");
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'icon' => 'calendar-clock',
                'title' => $row['guest_name'] . ' pre-registered',
                'meta' => trim(formatDate($row['visit_date']) . ' ' . formatTime($row['expected_time_in'])),
                'body' => $row['offices'] ?: 'No office selected',
                'url' => APP_URL . '/public/visits/checkin.php?id=' . (int)$row['visit_id'],
            ];
        }

        return [
            'title' => isAdmin() ? 'Campus Notifications' : 'Guard Notifications',
            'viewAllUrl' => APP_URL . '/public/visits/checkin.php',
            'items' => $items,
        ];
    }

    if (isOfficeStaff()) {
        $stmt = $db->prepare("
            SELECT vd.destination_id, gv.visit_id, gv.visit_reference, gv.actual_check_in,
                   gv.purpose_of_visit, g.full_name AS guest_name, g.organization
            FROM visit_destinations vd
            JOIN guest_visits gv ON vd.visit_id = gv.visit_id
            JOIN guests g ON gv.guest_id = g.guest_id
            WHERE vd.office_id = :office_id
              AND vd.destination_status = 'pending'
              AND gv.overall_status = 'checked_in'
            ORDER BY gv.actual_check_in DESC
            LIMIT 8
        ");
        $stmt->execute([':office_id' => currentOfficeId()]);

        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'icon' => 'user-check',
                'title' => $row['guest_name'] . ' is heading to your office',
                'meta' => formatDateTime($row['actual_check_in']),
                'body' => $row['purpose_of_visit'] ?: ($row['organization'] ?: $row['visit_reference']),
                'url' => APP_URL . '/public/office/handle.php?dest_id=' . (int)$row['destination_id'],
            ];
        }

        return [
            'title' => 'Office Notifications',
            'viewAllUrl' => APP_URL . '/public/office/incoming.php',
            'items' => $items,
        ];
    }

    return [
        'title' => 'Notifications',
        'viewAllUrl' => getDashboardUrl(),
        'items' => [],
    ];
}
}

if (!function_exists('flashMessageHtml')) {
function flashMessageHtml(string $message): string {
    $placeholders = [];
    $i = 0;

    $message = preg_replace_callback(
        '/<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>/is',
        function (array $match) use (&$placeholders, &$i): string {
            $href = $match[1];
            $label = trim(strip_tags($match[2]));
            if (!str_starts_with($href, APP_URL . '/') || $label === '') {
                return $match[0];
            }
            $token = "__FLASH_HTML_{$i}__";
            $i++;
            $placeholders[$token] = '<a href="' . e($href) . '" style="font-weight:800;color:inherit;text-decoration:underline;">' . e($label) . '</a>';
            return $token;
        },
        $message
    );

    $message = preg_replace_callback(
        '/<strong>(.*?)<\/strong>/is',
        function (array $match) use (&$placeholders, &$i): string {
            $label = trim(strip_tags($match[1]));
            if ($label === '') {
                return '';
            }
            $token = "__FLASH_HTML_{$i}__";
            $i++;
            $placeholders[$token] = '<strong>' . e($label) . '</strong>';
            return $token;
        },
        $message
    );

    $escaped = e($message);
    return strtr($escaped, $placeholders);
}
}

$topbarNotifications = topbarNotifications();
$topbarNotificationItems = $topbarNotifications['items'];
$topbarNotificationCount = count($topbarNotificationItems);
$appSoundCue = '';

if (!empty($_SESSION['play_login_sound'])) {
    $appSoundCue = 'login';
    unset($_SESSION['play_login_sound']);
} elseif (!empty($flash['type']) && in_array($flash['type'], ['success', 'error'], true)) {
    $appSoundCue = $flash['type'];
}

if ($appSoundCue === '' && $topbarNotificationCount > 0) {
    $notificationSignature = currentUserRole() . '|' . implode('|', array_map(
        static fn(array $item): string => $item['url'],
        $topbarNotificationItems
    ));

    if (($_SESSION['notification_sound_signature'] ?? '') !== $notificationSignature) {
        $appSoundCue = 'notification';
        $_SESSION['notification_sound_signature'] = $notificationSignature;
    }
} elseif ($topbarNotificationCount === 0) {
    unset($_SESSION['notification_sound_signature']);
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
<body data-app-sound="<?= e($appSoundCue) ?>"
      data-sound-base="<?= APP_URL ?>/assets/sound"
      data-notification-count="<?= $topbarNotificationCount ?>">
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
      <button class="notif-btn" id="notifBtn" title="Notifications" aria-haspopup="true" aria-expanded="false">
        <i data-lucide="bell" style="width:20px;height:20px;"></i>
        <?php if ($topbarNotificationCount > 0): ?>
        <span class="notif-badge"><?= $topbarNotificationCount > 9 ? '9+' : $topbarNotificationCount ?></span>
        <?php endif; ?>
      </button>

      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-head">
          <div>
            <div class="notif-title"><?= e($topbarNotifications['title']) ?></div>
            <div class="notif-subtitle"><?= $topbarNotificationCount ?> active alert<?= $topbarNotificationCount === 1 ? '' : 's' ?></div>
          </div>
          <a href="<?= e($topbarNotifications['viewAllUrl']) ?>" class="notif-view-all">View all</a>
        </div>
        <div class="notif-list">
          <?php if (empty($topbarNotificationItems)): ?>
          <div class="notif-empty">
            <div class="notif-empty-title">No new notifications</div>
            <div class="notif-empty-copy">You are all caught up for now.</div>
          </div>
          <?php else: ?>
          <?php foreach ($topbarNotificationItems as $item): ?>
          <a href="<?= e($item['url']) ?>" class="notif-item">
            <span class="notif-item-icon"><i data-lucide="<?= e($item['icon']) ?>"></i></span>
            <span class="notif-item-main">
              <span class="notif-item-title"><?= e($item['title']) ?></span>
              <span class="notif-item-body"><?= e($item['body']) ?></span>
              <span class="notif-item-meta"><?= e($item['meta']) ?></span>
            </span>
          </a>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

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
    <span><?= flashMessageHtml($flash['message']) ?></span>
    <button class="flash-close" onclick="document.getElementById('flashMsg').remove()">
      <i data-lucide="x" style="width:14px;height:14px;"></i>
    </button>
  </div>
  <?php endif; ?>
