<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$app = $db->prepare('SELECT pa.*, u.full_name AS officer_name, u.email AS officer_email
    FROM passport_applications pa JOIN users u ON u.id=pa.officer_id WHERE pa.id=?');
$app->execute([$id]);
$app = $app->fetch();
if (!$app) { flash('error','Application not found.'); redirect(APP_URL.'/admin/applications.php'); }

$stagesQ = $db->prepare('SELECT ps.*, u.full_name AS officer_name FROM processing_stages ps
    LEFT JOIN users u ON u.id=ps.officer_id WHERE ps.application_id=?
    ORDER BY FIELD(ps.stage_name,"Application Submitted","Document Verification","Biometric Capture","Background Check","Passport Printing","Ready for Collection","Passport Released")');
$stagesQ->execute([$id]);
$stages   = $stagesQ->fetchAll();
$stageMap = array_column($stages, null, 'stage_name');

$allStages = ['Application Submitted','Document Verification','Biometric Capture','Background Check','Passport Printing','Ready for Collection','Passport Released'];

$pageTitle = 'View Application';
include __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-file-alt"></i></div>
      <div>
        <div class="hero-eyebrow">System Administrator</div>
        <div class="hero-name">Application Details</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> #<?= e($app['application_number']) ?>
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right no-print">
      <div class="action-buttons-group">
        <button class="btn btn-outline" onclick="window.print()">
          <i class="fa fa-print"></i> Print
        </button>
        <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline">
          <i class="fa fa-arrow-left"></i> Back
        </a>
      </div>
    </div>
  </div>
</div>

<div class="grid">
  <!-- Left Column: Applicant Info & Stages -->
  <div>
    <!-- Applicant Information Card -->
    <div class="card animate animate-d1 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-id-card"></i> Applicant Information</span>
        <span class="status-badge-large <?= strtolower(str_replace(' ', '-', $app['status'])) ?>">
          <i class="fa fa-<?= match($app['status']) { 'Pending' => 'clock', 'In-Progress' => 'sync-alt', 'Completed' => 'check-circle', 'Rejected' => 'times-circle', default => 'info-circle' } ?>"></i>
          <?= e($app['status']) ?>
        </span>
      </div>
      <div class="info-grid" style="padding: 20px;">
        <div class="info-item">
          <div class="info-label"><i class="fa fa-hashtag"></i> Application Number</div>
          <div class="info-value monospace"><strong><?= e($app['application_number']) ?></strong></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-passport"></i> Passport Type</div>
          <div class="info-value"><span class="app-type-badge"><?= e($app['passport_type']) ?></span></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-user"></i> Full Name</div>
          <div class="info-value"><?= e($app['full_name']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-id-card"></i> National ID</div>
          <div class="info-value monospace"><?= e($app['national_id']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-birthday-cake"></i> Date of Birth</div>
          <div class="info-value"><?= e($app['date_of_birth']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-venus-mars"></i> Gender</div>
          <div class="info-value"><?= e($app['gender']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-phone"></i> Phone</div>
          <div class="info-value"><?= e($app['phone']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-envelope"></i> Email</div>
          <div class="info-value"><?= e($app['email']) ?></div>
        </div>
        <div class="info-item" style="grid-column: 1/-1;">
          <div class="info-label"><i class="fa fa-map-marker-alt"></i> Residential Address</div>
          <div class="info-value"><?= e($app['address']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-calendar-alt"></i> Application Date</div>
          <div class="info-value"><?= e($app['application_date']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label"><i class="fa fa-user-shield"></i> Processing Officer</div>
          <div class="info-value"><?= e($app['officer_name']) ?></div>
        </div>
      </div>
    </div>

    <!-- Processing Stages Card -->
    <div class="card animate animate-d2 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-tasks"></i> Processing Stages</span>
        <span class="card-badge"><?= count(array_filter($stages, fn($s) => $s['status'] === 'Completed')) ?>/<?= count($allStages) ?> completed</span>
      </div>
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
          <div class="stage-dot-icon"><?= $icon ?></div>
          <div class="stage-info">
            <h4><?= e($stageName) ?></h4>
            <span class="stage-badge <?= strtolower($status) ?>"><?= e($status) ?></span>
            <?php if ($st && ($st['officer_name'] || $st['updated_at'])): ?>
              <div class="stage-meta">
                <?php if ($st['officer_name']): ?>
                  <span><i class="fa fa-user"></i> <?= e($st['officer_name']) ?></span>
                <?php endif; ?>
                <?php if ($st['updated_at']): ?>
                  <span><i class="fa fa-calendar"></i> <?= date('d M Y, H:i', strtotime($st['updated_at'])) ?></span>
                <?php endif; ?>
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

  <!-- Right Column: Photo & Quick Info -->
  <div>
    <!-- Applicant Photo Card -->
    <div class="card animate animate-d1 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-image"></i> Applicant Photo</span>
      </div>
      <div class="photo-container">
        <?php if ($app['photo_path'] && file_exists(UPLOAD_DIR . $app['photo_path'])): ?>
          <img src="<?= UPLOAD_URL . e($app['photo_path']) ?>" class="photo-preview" alt="Applicant Photo">
        <?php else: ?>
          <div class="photo-placeholder">
            <i class="fa fa-user"></i>
          </div>
          <p class="text-muted" style="font-size:.75rem; margin-top: 0.8rem;">No photo uploaded</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Info Card -->
    <div class="card animate animate-d2 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-info-circle"></i> Quick Information</span>
      </div>
      <div class="quick-info-item">
        <div class="quick-info-label"><i class="fa fa-chart-line"></i> Current Stage</div>
        <div class="quick-info-value"><?= e($app['current_stage']) ?></div>
      </div>
      <div class="divider"></div>
      <div class="quick-info-item">
        <div class="quick-info-label"><i class="fa fa-plus-circle"></i> Created</div>
        <div class="quick-info-value"><?= date('d F Y, H:i:s', strtotime($app['created_at'])) ?></div>
      </div>
      <div class="quick-info-item">
        <div class="quick-info-label"><i class="fa fa-edit"></i> Last Updated</div>
        <div class="quick-info-value"><?= date('d F Y, H:i:s', strtotime($app['updated_at'])) ?></div>
      </div>
      <div class="divider"></div>
      <div class="quick-info-item">
        <div class="quick-info-label"><i class="fa fa-clock"></i> Processing Time</div>
        <div class="quick-info-value">
          <?php
            $created = new DateTime($app['created_at']);
            $now = new DateTime();
            $diff = $created->diff($now);
            echo $diff->days . ' days, ' . $diff->h . ' hours';
          ?>
        </div>
      </div>
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

<style>
.app-type-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .7rem;
  background: rgba(200,145,26,.12);
  border: 1px solid rgba(200,145,26,.2);
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 500;
  color: var(--gold-light);
}
.card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}
.text-muted {
  color: var(--muted);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>