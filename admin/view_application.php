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

<style>
/* ─────────────────────────────────────────────────────────────
   VIEW APPLICATION PAGE — Premium Edition (Dashboard Matching)
   ───────────────────────────────────────────────────────────── */

/* ── Entry animations ──────────────────────────────────────── */
@keyframes fadeUp   { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn   { from{opacity:0} to{opacity:1} }
@keyframes shimmer  {
  0%  { background-position: -200% center }
  100%{ background-position:  200% center }
}
@keyframes float {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-6px) }
}
@keyframes pulse-ring {
  0%  { box-shadow: 0 0 0 0 rgba(52,211,153,.5) }
  70% { box-shadow: 0 0 0 8px rgba(52,211,153,0) }
  100%{ box-shadow: 0 0 0 0 rgba(52,211,153,0) }
}
@keyframes stagePulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.2); background: #60A5FA; }
  100% { transform: scale(1); }
}

.view-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.view-animate-d1 { animation-delay:.06s }
.view-animate-d2 { animation-delay:.12s }
.view-animate-d3 { animation-delay:.18s }

/* ── Spotlight Card Effect ───────────────────────────────── */
.hover-card {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  overflow: hidden;
  transform-style: preserve-3d;
  will-change: transform;
}

.hover-card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.12) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  z-index: 1;
}

.hover-card:hover::before {
  opacity: 1;
}

.hover-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

/* ── Hero Section ────────────────────────────────────────── */
.view-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .view-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.view-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.view-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.view-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.view-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.view-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.view-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.view-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.view-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.view-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.view-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.view-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.view-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.view-hero-meta-chip i { font-size: .62rem; }

.view-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Application Grid ─────────────────────────────────────── */
.app-detail-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.2rem;
}
@media(max-width:700px){ .app-detail-grid { grid-template-columns: 1fr; } }

/* ── Premium Cards ────────────────────────────────────────── */
.view-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  margin-bottom: 1.2rem;
}
.view-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.08) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  border-radius: inherit;
  z-index: 1;
}
.view-card:hover::before { opacity: 1; }
.view-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.card-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.card-title i {
  color: var(--gold);
  font-size: .78rem;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  padding: 1.5rem;
}
@media(max-width:500px){ .info-grid { grid-template-columns: 1fr; } }
.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.info-label {
  font-size: .65rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
}
.info-value {
  font-size: .9rem;
  font-weight: 500;
  color: var(--text);
  word-break: break-word;
}
.info-value strong {
  font-weight: 700;
  color: var(--navy-light);
}
.info-value.monospace {
  font-family: monospace;
  font-size: .85rem;
}

/* Status Badge Large */
.status-badge-large {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 1rem;
  border-radius: 30px;
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.status-badge-large.pending {
  background: rgba(245,158,11,.15);
  color: #F59E0B;
  border: 1px solid rgba(245,158,11,.3);
}
.status-badge-large.in-progress {
  background: rgba(59,130,246,.15);
  color: #60A5FA;
  border: 1px solid rgba(59,130,246,.3);
  animation: pulse-ring 2s infinite;
}
.status-badge-large.completed {
  background: rgba(52,211,153,.15);
  color: #34D399;
  border: 1px solid rgba(52,211,153,.3);
}
.status-badge-large.rejected {
  background: rgba(239,68,68,.15);
  color: #F87171;
  border: 1px solid rgba(239,68,68,.3);
}

/* Stage Timeline */
.stage-timeline {
  padding: 1rem 1.5rem 1.5rem;
}
.stage-item {
  display: flex;
  gap: 1rem;
  padding: 0.8rem 0;
  position: relative;
  border-left: 2px solid var(--border);
  margin-left: 1rem;
  padding-left: 1.5rem;
}
.stage-item:last-child {
  border-left-color: transparent;
}
.stage-item::before {
  content: '';
  position: absolute;
  left: -8px;
  top: 1.2rem;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: var(--border-mid);
  transition: all 0.2s;
}
.stage-item.done::before {
  background: #34D399;
  box-shadow: 0 0 0 3px rgba(52,211,153,.2);
}
.stage-item.active::before {
  background: #60A5FA;
  box-shadow: 0 0 0 3px rgba(96,165,250,.3);
  animation: stagePulse 1.5s infinite;
}
.stage-item.rejected::before {
  background: #F87171;
}
.stage-dot-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--muted);
}
.stage-item.done .stage-dot-icon {
  background: #34D39920;
  border-color: #34D399;
  color: #34D399;
}
.stage-item.active .stage-dot-icon {
  background: #60A5FA20;
  border-color: #60A5FA;
  color: #60A5FA;
}
.stage-info {
  flex: 1;
}
.stage-info h4 {
  font-size: .85rem;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 0.2rem 0;
}
.stage-badge {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 20px;
  font-size: .65rem;
  font-weight: 600;
  margin: 0.3rem 0;
}
.stage-badge.completed {
  background: rgba(52,211,153,.12);
  color: #34D399;
}
.stage-badge.in-progress {
  background: rgba(59,130,246,.12);
  color: #60A5FA;
}
.stage-badge.pending {
  background: rgba(107,114,128,.12);
  color: #9CA3AF;
}
.stage-badge.rejected {
  background: rgba(239,68,68,.12);
  color: #F87171;
}
.stage-meta {
  font-size: .7rem;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 0.8rem;
  margin-top: 0.3rem;
  flex-wrap: wrap;
}
.stage-meta i {
  font-size: .6rem;
}
.stage-comment {
  font-size: .75rem;
  color: var(--text-soft);
  margin-top: 0.3rem;
  padding: 0.3rem 0.6rem;
  background: var(--surface);
  border-radius: 8px;
  display: inline-block;
}

/* Photo Section */
.photo-container {
  padding: 1.5rem;
  text-align: center;
}
.photo-preview {
  width: 100%;
  max-width: 220px;
  height: auto;
  border-radius: var(--radius-lg);
  border: 2px solid var(--border);
  box-shadow: 0 8px 20px rgba(0,0,0,.2);
}
.photo-placeholder {
  width: 100%;
  max-width: 200px;
  height: 200px;
  margin: 0 auto;
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  color: var(--muted);
  border: 2px dashed var(--border);
}
.quick-info-item {
  padding: 0.8rem 1.5rem;
  border-bottom: 1px solid var(--border);
}
.quick-info-item:last-child {
  border-bottom: none;
}
.quick-info-label {
  font-size: .65rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: 0.2rem;
}
.quick-info-value {
  font-size: .9rem;
  font-weight: 600;
  color: var(--text);
}
.divider {
  margin: 0.5rem 0;
  border: none;
  border-top: 1px solid var(--border);
}

/* Action Buttons */
.action-buttons-group {
  display: flex;
  gap: 0.5rem;
}

/* Responsive */
@media print {
  .no-print { display: none; }
  .view-card { break-inside: avoid; page-break-inside: avoid; }
  body { background: white; }
  .view-hero { background: #fff; color: #000; border: 1px solid #ddd; }
  .view-hero::before { display: none; }
}
</style>

<!-- Hero Section -->
<div class="view-hero view-animate">
  <div class="view-hero-mesh"></div>
  <div class="view-hero-grid"></div>
  <div class="view-hero-inner">
    <div class="view-hero-left">
      <div class="view-hero-icon"><i class="fa fa-file-alt"></i></div>
      <div>
        <div class="view-hero-eyebrow">System Administrator</div>
        <div class="view-hero-name">Application Details</div>
        <div class="view-hero-meta">
          <span class="view-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="view-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="view-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> #<?= e($app['application_number']) ?>
          </span>
        </div>
      </div>
    </div>
    <div class="view-hero-right no-print">
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

<div class="app-detail-grid">
  <!-- Left Column: Applicant Info & Stages -->
  <div>
    <!-- Applicant Information Card -->
    <div class="view-card view-animate view-animate-d1 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-id-card"></i> Applicant Information</span>
        <span class="status-badge-large <?= strtolower(str_replace(' ', '-', $app['status'])) ?>">
          <i class="fa fa-<?= match($app['status']) { 'Pending' => 'clock', 'In-Progress' => 'sync-alt', 'Completed' => 'check-circle', 'Rejected' => 'times-circle', default => 'info-circle' } ?>"></i>
          <?= e($app['status']) ?>
        </span>
      </div>
      <div class="info-grid">
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
    <div class="view-card view-animate view-animate-d2 hover-card">
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
    <div class="view-card view-animate view-animate-d1 hover-card">
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
    <div class="view-card view-animate view-animate-d2 hover-card">
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

    <!-- Tracking Link Card -->
    <div class="view-card view-animate view-animate-d3 hover-card no-print">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-link"></i> Public Tracking</span>
      </div>
      <div class="quick-info-item">
        <div class="quick-info-label"><i class="fa fa-qrcode"></i> Tracking URL</div>
        <div class="quick-info-value" style="word-break: break-all;">
          <a href="<?= APP_URL ?>/public_track.php?app_num=<?= urlencode($app['application_number']) ?>" target="_blank" style="color: var(--navy-light);">
            <?= APP_URL ?>/public_track.php?app_num=<?= urlencode($app['application_number']) ?>
          </a>
        </div>
      </div>
      <div class="quick-info-item">
        <button class="btn btn-outline btn-sm" onclick="window.open('<?= APP_URL ?>/public_track.php?app_num=<?= urlencode($app['application_number']) ?>', '_blank')" style="width: 100%;">
          <i class="fa fa-external-link-alt"></i> Open Public Tracking Page
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .view-card');
  
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