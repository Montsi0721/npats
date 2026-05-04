<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('applicant');
$db  = getDB();
$uid = $_SESSION['user_id'];

$apps = $db->prepare('SELECT * FROM passport_applications WHERE applicant_user_id=? ORDER BY created_at DESC');
$apps->execute([$uid]);
$apps = $apps->fetchAll();

$pageTitle = 'My Applications';
include __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-list-alt"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Applicant</div>
        <div class="hero-name">My Applications</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Track your passport
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/applicant/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
      <a href="<?= APP_URL ?>/applicant/create_application.php" class="btn btn-primary">
        <i class="fa fa-plus"></i> New Application
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<?php
$totalApps = count($apps);
$pendingApps = count(array_filter($apps, fn($a) => $a['status'] === 'Pending'));
$inProgressApps = count(array_filter($apps, fn($a) => $a['status'] === 'In-Progress'));
$completedApps = count(array_filter($apps, fn($a) => $a['status'] === 'Completed'));
?>
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= $totalApps ?></div>
    <div class="label">Total Applications</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #F59E0B;"><?= $pendingApps ?></div>
    <div class="label">Pending</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= $inProgressApps ?></div>
    <div class="label">In Progress</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= $completedApps ?></div>
    <div class="label">Completed</div>
  </div>
</div>

<!-- Applications Card -->
<div class="card animate animate-d2 hover-card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa fa-table-list"></i> Application List
      <span class="card-badge"><?= $totalApps ?> total</span>
    </div>
  </div>
  
  <?php if (empty($apps)): ?>
    <div class="empty-state">
      <div class="empty-icon">
        <i class="fa fa-folder-open"></i>
      </div>
      <h3>No applications found</h3>
      <p>You don't have any passport applications linked to your account yet.</p>
      <p style="font-size: .75rem; margin-top: 0.5rem;">Applications are linked when an officer registers them using your email address.</p>
      <div style="margin-top: 1.5rem;">
        <a href="<?= APP_URL ?>/applicant/create_application.php" class="action-btn">
          <i class="fa fa-plus"></i> Create New Application
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>App Number</th>
            <th>Passport Type</th>
            <th>Stage</th>
            <th>Status</th>
            <th>Applied Date</th>
            <th style="text-align:center;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $idx => $a): ?>
          <tr style="--i: <?= $idx ?>">
            <td>
              <a href="<?= APP_URL ?>/applicant/track.php?app_num=<?= urlencode($a['application_number']) ?>" class="app-number">
                <?= e($a['application_number']) ?>
              </a>
            </td>
            <td>
              <span class="app-type-badge">
                <i class="fa fa-passport"></i> <?= e($a['passport_type']) ?>
              </span>
            </td>
            <td>
              <span class="stage-text"><?= e($a['current_stage']) ?></span>
            </td>
            <td><?= statusBadge($a['status']) ?></td>
            <td style="font-size: .75rem; color: var(--muted); white-space: nowrap;">
              <i class="fa fa-calendar"></i> <?= e($a['application_date']) ?>
            </td>
            <td style="text-align:center;">
              <a href="<?= APP_URL ?>/applicant/track.php?app_num=<?= urlencode($a['application_number']) ?>" class="action-btn">
                <i class="fa fa-search"></i> Track
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .card');
  
  spotlightElements.forEach(el => {
    let spotlight = el.querySelector('.sc-spotlight');
    if (!spotlight) {
      spotlight = document.createElement('div');
      spotlight.className = 'sc-spotlight';
      el.style.position = 'relative';
      el.style.overflow = 'hidden';
      el.appendChild(spotlight);
    }
    
    el.addEventListener('mousemove', function(e) {
      const rect = this.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;
      
      this.style.setProperty('--x', x + '%');
      this.style.setProperty('--y', y + '%');
      
      spotlight.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(59, 130, 246, 0.12) 0%, transparent 60%)`;
      spotlight.style.opacity = '1';
    });
    
    el.addEventListener('mouseleave', function() {
      spotlight.style.opacity = '0';
    });
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>