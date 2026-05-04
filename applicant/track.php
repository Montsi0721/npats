<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('applicant');
$db  = getDB();
$uid = $_SESSION['user_id'];

$appNum   = trim($_GET['app_num'] ?? '');
$app      = null;
$stages   = [];
$stageMap = [];
$notFound = false;

$allStages = ['Application Submitted','Document Verification','Biometric Capture','Background Check','Passport Printing','Ready for Collection','Passport Released'];

if ($appNum !== '') {
    $stmt = $db->prepare('SELECT * FROM passport_applications WHERE application_number=?');
    $stmt->execute([$appNum]);
    $app = $stmt->fetch();
    if ($app) {
        $sq = $db->prepare('SELECT ps.*, u.full_name AS officer_name FROM processing_stages ps
            LEFT JOIN users u ON u.id=ps.officer_id WHERE ps.application_id=?
            ORDER BY FIELD(ps.stage_name,"Application Submitted","Document Verification","Biometric Capture","Background Check","Passport Printing","Ready for Collection","Passport Released")');
        $sq->execute([$app['id']]);
        $stages = $sq->fetchAll();
        foreach ($stages as $s) $stageMap[$s['stage_name']] = $s;
    } else {
        $notFound = true;
    }
}

$pageTitle = 'Track Application';
include __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-search"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Applicant</div>
        <div class="hero-name">Track Application</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Real-time Status
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/applicant/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Search Card -->
<div class="search-card animate animate-d1 hover-card">
  <form method="GET" class="search-form">
    <div class="search-input">
      <i class="fa fa-search"></i>
      <input type="text" name="app_num" value="<?= e($appNum) ?>" 
             placeholder="Enter application number (e.g. NPATS-2026-AB1C2D)" required>
    </div>
    <button type="submit" class="btn-primary">
      <i class="fa fa-search"></i> Track Application
    </button>
  </form>
</div>

<?php if ($notFound): ?>
<div class="alert alert-error animate animate-d2">
  <i class="fa fa-exclamation-circle"></i>
  <div>No application found with number <strong><?= e($appNum) ?></strong>. Please check the number and try again.</div>
</div>

<?php elseif ($app): ?>
<div class="animate animate-d2">
  
  <!-- Summary Card -->
  <div class="app-card hover-card" style="margin-bottom: 1.2rem;">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-passport"></i> Application Summary</span>
    </div>
    <div class="card-body">
      <div class="summary-header">
        <div>
          <div class="app-number-label"><i class="fa fa-hashtag"></i> APPLICATION NUMBER</div>
          <div class="app-number-value"><?= e($app['application_number']) ?></div>
        </div>
        <span class="status-badge-large <?= strtolower(str_replace(' ', '-', $app['status'])) ?>">
          <i class="fa fa-<?= match($app['status']) { 'Pending' => 'clock', 'In-Progress' => 'sync-alt', 'Completed' => 'check-circle', 'Rejected' => 'times-circle', default => 'info-circle' } ?>"></i>
          <?= e($app['status']) ?>
        </span>
      </div>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label"><i class="fa fa-user"></i> Applicant Name</div>
          <div class="info-value"><strong><?= e($app['full_name']) ?></strong></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-passport"></i> Passport Type</div>
          <div class="info-value"><?= e($app['passport_type']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-calendar-alt"></i> Application Date</div>
          <div class="info-value"><?= e($app['application_date']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-chart-line"></i> Current Stage</div>
          <div class="info-value" style="color: var(--info); font-weight: 600;"><?= e($app['current_stage']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Processing Progress Card -->
  <div class="app-card hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-tasks"></i> Processing Progress</span>
      <span class="stage-count-badge" style="font-size: .7rem; background: var(--surface); padding: 0.2rem 0.6rem; border-radius: 20px;">
        <?= count(array_filter($stages, fn($s) => $s['status'] === 'Completed')) ?>/<?= count($allStages) ?> completed
      </span>
    </div>
    <div class="card-body">
      <div class="stage-timeline">
        <?php foreach ($allStages as $i => $stageName):
          $st     = $stageMap[$stageName] ?? null;
          $status = $st['status'] ?? 'Pending';
          $cls    = match($status) { 
            'Completed' => 'done',
            'In-Progress' => 'active',
            'Rejected' => 'rejected',
            default => 'pending' 
          };
          $icon = match($status) {
            'Completed' => '<i class="fa fa-check"></i>',
            'Rejected' => '<i class="fa fa-times"></i>',
            'In-Progress' => '<i class="fa fa-spinner fa-pulse"></i>',
            default => ($i + 1)
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
              <div class="stage-date">
                <i class="fa fa-calendar-alt"></i>
                <?= date('d M Y, H:i', strtotime($st['updated_at'])) ?>
              </div>
            <?php endif; ?>
            <?php if ($st && $st['comments']): ?>
              <div class="stage-comment">
                <i class="fa fa-comment"></i> <?= e($st['comments']) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Quick Actions Card -->
  <div class="app-card hover-card animate animate-d3" style="margin-top: 1.2rem;">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-bolt"></i> Quick Actions</span>
    </div>
    <div class="card-body">
      <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <button class="btn-outline" onclick="window.print()">
          <i class="fa fa-print"></i> Print Status
        </button>
        <a href="<?= APP_URL ?>/applicant/my_applications.php" class="btn-outline">
          <i class="fa fa-list"></i> View All Applications
        </a>
        <a href="<?= APP_URL ?>/applicant/create_application.php" class="btn-primary">
          <i class="fa fa-plus"></i> New Application
        </a>
      </div>
    </div>
  </div>

</div>
<?php endif; ?>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card');
  
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

// Auto-focus search input if no application is being tracked
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.querySelector('.search-input input');
  if (searchInput && !<?= $appNum ? 'true' : 'false' ?>) {
    searchInput.focus();
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>