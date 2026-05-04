<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('applicant');
$db  = getDB();
$uid = $_SESSION['user_id'];

$count = fn($q,$p=[]) => (function() use($db,$q,$p){
    $st = $db->prepare($q); $st->execute($p); return (int)$st->fetchColumn();
})();

$myApps   = $count('SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=?', [$uid]);
$pending  = $count("SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=? AND status='Pending'", [$uid]);
$inProg   = $count("SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=? AND status='In-Progress'", [$uid]);
$complete = $count("SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=? AND status='Completed'", [$uid]);

$latest = $db->prepare('SELECT * FROM passport_applications WHERE applicant_user_id=? ORDER BY created_at DESC LIMIT 1');
$latest->execute([$uid]);
$latest = $latest->fetch();

$allStages = [
    'Application Submitted', 'Document Verification', 'Biometric Capture',
    'Background Check', 'Passport Printing', 'Ready for Collection', 'Passport Released',
];
$stageMap = [];
if ($latest) {
    $sq = $db->prepare('SELECT * FROM processing_stages WHERE application_id=?
        ORDER BY FIELD(stage_name,
            "Application Submitted","Document Verification","Biometric Capture",
            "Background Check","Passport Printing","Ready for Collection","Passport Released")');
    $sq->execute([$latest['id']]);
    foreach ($sq->fetchAll() as $s) $stageMap[$s['stage_name']] = $s;
}

$pageTitle = 'My Dashboard';
include __DIR__ . '/../includes/header.php';
?>


<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-gauge"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Applicant</div>
        <div class="hero-name">Welcome, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?></div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Track Your Application
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/applicant/create_application.php" class="btn btn-primary">
        <i class="fa fa-file-circle-plus"></i> Create Application
      </a>
    </div>
  </div>
</div>

<!-- Make Application Banner -->
<div class="make-app-banner animate animate-d1">
  <div class="make-app-text">
    <div class="make-app-heading">
      <i class="fa fa-passport"></i> Ready to apply for a passport?
    </div>
    <p class="make-app-sub">
      Visit any passport office with your documents. The officer will create your application
      and link it to your account — you can then track every step here.
    </p>
  </div>
  <div style="display:flex; gap:.65rem; flex-wrap:wrap;">
    <a href="<?= APP_URL ?>/applicant/create_application.php" class="btn btn-primary">
      <i class="fa fa-file-circle-plus"></i> Create Application
    </a>
    <a href="<?= APP_URL ?>/applicant/track.php" class="btn btn-gold">
      <i class="fa fa-magnifying-glass"></i> Track Application
    </a>
  </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid animate animate-d2">
  <div class="stat-card blue hover-card">
    <div class="stat-icon"><i class="fa fa-file-lines"></i></div>
    <div><div class="stat-num"><?= $myApps ?></div><div class="stat-label">My Applications</div></div>
  </div>
  <div class="stat-card gold hover-card">
    <div class="stat-icon"><i class="fa fa-clock"></i></div>
    <div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
  </div>
  <div class="stat-card teal hover-card">
    <div class="stat-icon"><i class="fa fa-rotate"></i></div>
    <div><div class="stat-num"><?= $inProg ?></div><div class="stat-label">In Progress</div></div>
  </div>
  <div class="stat-card green hover-card">
    <div class="stat-icon"><i class="fa fa-circle-check"></i></div>
    <div><div class="stat-num"><?= $complete ?></div><div class="stat-label">Completed</div></div>
  </div>
</div>

<?php if ($latest): ?>
<!-- Latest Application + Progress -->
<div class="app-dash-grid animate animate-d3">
  
  <!-- Latest Application Card -->
  <div class="card hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-passport"></i> Latest Application</span>
      <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $latest['status'])) ?>">
        <i class="fa fa-<?= match($latest['status']) { 'Pending' => 'clock', 'In-Progress' => 'sync-alt', 'Completed' => 'check-circle', 'Rejected' => 'times-circle', default => 'info-circle' } ?>"></i>
        <?= e($latest['status']) ?>
      </span>
    </div>
    <div class="card-body">
      <div style="margin-bottom: 1rem;">
        <div class="info-label">Application Number</div>
        <div class="info-value monospace" style="font-size:1rem; font-weight:700; color:var(--navy-light);">
          <?= e($latest['application_number']) ?>
        </div>
      </div>
      <div class="info-grid">
        <div>
          <div class="info-label"><i class="fa fa-passport"></i> Passport Type</div>
          <div class="info-value"><?= e($latest['passport_type']) ?></div>
        </div>
        <div>
          <div class="info-label"><i class="fa fa-calendar-alt"></i> Applied On</div>
          <div class="info-value"><?= e($latest['application_date']) ?></div>
        </div>
        <div style="grid-column:1/-1;">
          <div class="info-label"><i class="fa fa-chart-line"></i> Current Stage</div>
          <div class="info-value" style="font-weight:600; color: var(--info);"><?= e($latest['current_stage']) ?></div>
        </div>
      </div>
      <div class="btn-group">
        <a href="<?= APP_URL ?>/applicant/track.php?app_num=<?= urlencode($latest['application_number']) ?>"
           class="btn btn-primary btn-sm"><i class="fa fa-magnifying-glass"></i> Track Progress</a>
        <a href="<?= APP_URL ?>/applicant/my_applications.php"
           class="btn btn-outline btn-sm">View all</a>
      </div>
    </div>
  </div>

  <!-- Processing Progress Card -->
  <div class="card hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-list-check"></i> Processing Progress</span>
    </div>
    <div class="stage-list">
      <?php foreach ($allStages as $i => $stageName):
        $st     = $stageMap[$stageName] ?? null;
        $status = $st['status'] ?? 'Pending';
        $cls    = match($status) {
            'Completed'   => 'done',
            'In-Progress' => 'active',
            'Rejected'    => 'rejected',
            default       => 'pending',
        };
        $icon = match($status) {
            'Completed'   => '<i class="fa fa-check" style="font-size:.65rem;"></i>',
            'Rejected'    => '<i class="fa fa-times" style="font-size:.65rem;"></i>',
            'In-Progress' => '<i class="fa fa-circle-dot" style="font-size:.65rem;"></i>',
            default       => $i + 1,
        };
      ?>
      <div class="stage-item <?= $cls ?>">
        <div class="stage-dot"><?= $icon ?></div>
        <div class="stage-info">
          <h4><?= e($stageName) ?></h4>
          <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $status)) ?>">
            <?= e($status) ?>
          </span>
          <?php if ($st && $st['updated_at']): ?>
          <div class="stage-meta">
            <i class="fa fa-calendar-days"></i>
            <?= e(date('d M Y', strtotime($st['updated_at']))) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php else: ?>
<!-- Empty State -->
<div class="card animate animate-d3 hover-card" style="text-align:center;">
  <div class="empty-state">
    <div class="empty-icon">
      <i class="fa fa-passport"></i>
    </div>
    <h3>No applications yet</h3>
    <p>Visit a passport office with your documents and ask an officer to register your application.<br>
    Once created, you can track it here or by application number.</p>
    <div class="btn-group" style="justify-content:center; margin-top: 1rem;">
      <a href="<?= APP_URL ?>/applicant/create_application.php" class="btn btn-primary">
        <i class="fa fa-file-circle-plus"></i> Create Application
      </a>
      <a href="<?= APP_URL ?>/applicant/track.php" class="btn btn-outline">
        <i class="fa fa-magnifying-glass"></i> Track by Number
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>