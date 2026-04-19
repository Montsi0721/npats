<?php
// includes/header.php
$user         = currentUser();
$notifCount   = unreadCount();
$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
$userInitials = initials($user['name'] ?: 'U');

$navLinks = match($user['role']) {
    'admin'  => [
        ['icon'=>'fa-gauge',       'label'=>'Dashboard',    'href'=>'/admin/dashboard.php'],
        ['icon'=>'fa-users',       'label'=>'Users',        'href'=>'/admin/users.php'],
        ['icon'=>'fa-list-alt',    'label'=>'Applications', 'href'=>'/admin/applications.php'],
        ['icon'=>'fa-chart-bar',   'label'=>'Reports',      'href'=>'/admin/reports.php'],
        ['icon'=>'fa-history',     'label'=>'Activity Log', 'href'=>'/admin/activity.php'],
    ],
    'officer' => [
        ['icon'=>'fa-gauge',       'label'=>'Dashboard',       'href'=>'/officer/dashboard.php'],
        ['icon'=>'fa-plus-circle', 'label'=>'New Application', 'href'=>'/officer/new_application.php'],
        ['icon'=>'fa-list-alt',    'label'=>'Applications',    'href'=>'/officer/applications.php'],
        ['icon'=>'fa-box-open',    'label'=>'Releases',        'href'=>'/officer/releases.php'],
    ],
    'applicant' => [
        ['icon'=>'fa-gauge',       'label'=>'Dashboard',         'href'=>'/applicant/dashboard.php'],
        ['icon'=>'fa-search',      'label'=>'Track Application', 'href'=>'/applicant/track.php'],
        ['icon'=>'fa-folder-open', 'label'=>'My Applications',   'href'=>'/applicant/my_applications.php'],
    ],
    default => [],
};

$currentPath = $_SERVER['PHP_SELF'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'NPATS') ?> — National Passport Application Tracking System</title>

<!-- Anti-flash: apply saved theme before first paint -->
<script>
  (function() {
    var t = localStorage.getItem('npats_theme');
    if (t === 'light') { document.documentElement.setAttribute('data-theme', 'light'); }
    else { document.documentElement.removeAttribute('data-theme'); }
  })();
</script>

<link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/headerIcon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<nav class="navbar">
  <a href="<?= APP_URL ?>/<?= e($user['role']) ?>/dashboard.php" class="nav-brand">
    <div class="nav-brand-icon"><i class="fa fa-passport"></i></div>
    <div>
      <span class="nav-brand-title">NPATS</span>
      <span class="nav-brand-sub">Ministry of Home Affairs</span>
    </div>
  </a>

  <button class="hamburger" id="hamburger" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>

  <ul class="nav-links" id="navLinks">
    <?php foreach ($navLinks as $link):
      $isActive = str_ends_with($currentPath, basename($link['href']));
    ?>
    <li>
      <a href="<?= APP_URL . e($link['href']) ?>" <?= $isActive ? 'class="active"' : '' ?>>
        <i class="fa <?= e($link['icon']) ?>"></i> <?= e($link['label']) ?>
      </a>
    </li>
    <?php endforeach; ?>

    <li class="nav-sep"></li>

    <!-- Theme toggle -->
    <li>
      <button class="theme-toggle" id="themeToggle" title="Toggle light / dark theme" aria-label="Toggle theme">
        <i class="fa fa-sun icon-sun"></i>
        <i class="fa fa-moon icon-moon"></i>
      </button>
    </li>

    <!-- Notifications -->
    <li class="nav-notif">
      <a href="<?= APP_URL ?>/notifications.php" title="Notifications">
        <i class="fa fa-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span class="nav-notif-badge"><?= $notifCount ?></span>
        <?php endif; ?>
      </a>
    </li>

    <!-- User chip -->
    <li>
      <div class="nav-user">
        <div class="nav-avatar"><?= e($userInitials) ?></div>
        <span class="nav-user-name"><?= e($user['name']) ?></span>
        <span class="role-badge role-<?= e($user['role']) ?>"><?= ucfirst(e($user['role'])) ?></span>
      </div>
    </li>

    <!-- Logout -->
    <li>
      <a href="<?= APP_URL ?>/logout.php" class="nav-logout">
        <i class="fa fa-arrow-right-from-bracket"></i> Logout
      </a>
    </li>
  </ul>
</nav>

<main class="main-content">
<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i> <?= e($flashError) ?></div>
<?php endif; ?>
