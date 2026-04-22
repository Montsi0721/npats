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

<style>
/* ─────────────────────────────────────────────────────────────
   TRACK APPLICATION PAGE — Premium Edition
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

.track-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.track-animate-d1 { animation-delay:.06s }
.track-animate-d2 { animation-delay:.12s }
.track-animate-d3 { animation-delay:.18s }
.track-animate-d4 { animation-delay:.24s }

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
.track-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .track-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.track-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.track-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.track-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.track-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.track-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.track-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.track-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.track-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.track-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.track-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.track-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.track-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.track-hero-meta-chip i { font-size: .62rem; }

.track-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Search Card ──────────────────────────────────────────── */
.search-card {
  max-width: 680px;
  margin: 0 auto 1.5rem;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.search-card::before {
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
.search-card:hover::before { opacity: 1; }
.search-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}
.search-form {
  padding: 1.25rem 1.5rem;
  display: flex;
  gap: 0.8rem;
  align-items: center;
}
.search-input {
  flex: 1;
  position: relative;
}
.search-input i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 0.85rem;
}
.search-input input {
  width: 100%;
  padding: .8rem 1rem .8rem 2.5rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .9rem;
  transition: all .2s;
}
.search-input input:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.search-input input::placeholder {
  color: var(--muted);
  font-size: .85rem;
}

/* ── Alert Box ───────────────────────────────────────────── */
.alert {
  padding: 1rem 1.25rem;
  border-radius: var(--radius);
  display: flex;
  gap: 0.75rem;
  align-items: center;
  max-width: 680px;
  margin: 0 auto;
}
.alert i {
  font-size: 1.1rem;
  flex-shrink: 0;
}
.alert-error {
  background: rgba(239,68,68,.1);
  border-left: 3px solid #F87171;
  color: #F87171;
}
.alert-error strong {
  color: #F87171;
}

/* ── Application Card ────────────────────────────────────── */
.app-card {
  max-width: 680px;
  margin: 0 auto;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.app-card::before {
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
.app-card:hover::before { opacity: 1; }
.app-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}
.app-card .card-header {
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
.card-body {
  padding: 1.5rem;
}

/* Summary Section */
.summary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.8rem;
  margin-bottom: 1rem;
  padding-bottom: 0.8rem;
  border-bottom: 1px solid var(--border);
}
.app-number-label {
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: 0.2rem;
}
.app-number-value {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--navy-light);
  font-family: monospace;
  letter-spacing: .5px;
}
.status-badge-large {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
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

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.8rem;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
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
}
.info-value strong {
  font-weight: 700;
  color: var(--navy-light);
}

/* Stage Timeline */
.stage-timeline {
  padding: 0.5rem 0 0.5rem 0;
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
.stage-dot {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--muted);
}
.stage-item.done .stage-dot {
  background: #34D39920;
  border-color: #34D399;
  color: #34D399;
}
.stage-item.active .stage-dot {
  background: #60A5FA20;
  border-color: #60A5FA;
  color: #60A5FA;
}
.stage-item.rejected .stage-dot {
  background: #F8717120;
  border-color: #F87171;
  color: #F87171;
}
.stage-info {
  flex: 1;
}
.stage-info h4 {
  font-size: .88rem;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 0.3rem 0;
}
.stage-date {
  font-size: .72rem;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 0.3rem;
  margin-top: 0.3rem;
}
.stage-date i {
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

/* Status Badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .7rem;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 600;
}
.status-pending {
  background: rgba(245,158,11,.12);
  color: #F59E0B;
  border: 1px solid rgba(245,158,11,.2);
}
.status-in-progress {
  background: rgba(59,130,246,.12);
  color: #60A5FA;
  border: 1px solid rgba(59,130,246,.2);
}
.status-completed {
  background: rgba(52,211,153,.12);
  color: #34D399;
  border: 1px solid rgba(52,211,153,.2);
}
.status-rejected {
  background: rgba(239,68,68,.12);
  color: #F87171;
  border: 1px solid rgba(239,68,68,.2);
}

/* Buttons */
.btn-primary {
  background: linear-gradient(135deg, #1D5A9E, #3B82F6);
  border: none;
  padding: 0.7rem 1.2rem;
  border-radius: var(--radius);
  color: #fff;
  font-weight: 600;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59,130,246,.3);
}
.btn-outline {
  background: transparent;
  border: 1.5px solid var(--border);
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: var(--text-soft);
  font-weight: 500;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.btn-outline:hover {
  border-color: var(--navy-light);
  color: var(--navy-light);
  transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 640px) {
  .track-hero-inner {
    flex-direction: column;
    text-align: center;
  }
  .search-form {
    flex-direction: column;
  }
  .search-form .btn-primary {
    width: 100%;
    justify-content: center;
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
  .summary-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

<!-- Hero Section -->
<div class="track-hero track-animate">
  <div class="track-hero-mesh"></div>
  <div class="track-hero-grid"></div>
  <div class="track-hero-inner">
    <div class="track-hero-left">
      <div class="track-hero-icon"><i class="fa fa-search"></i></div>
      <div>
        <div class="track-hero-eyebrow">Passport Applicant</div>
        <div class="track-hero-name">Track Application</div>
        <div class="track-hero-meta">
          <span class="track-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="track-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="track-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Real-time Status
          </span>
        </div>
      </div>
    </div>
    <div class="track-hero-right">
      <a href="<?= APP_URL ?>/applicant/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Search Card -->
<div class="search-card track-animate track-animate-d1 hover-card">
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
<div class="alert alert-error track-animate track-animate-d2">
  <i class="fa fa-exclamation-circle"></i>
  <div>No application found with number <strong><?= e($appNum) ?></strong>. Please check the number and try again.</div>
</div>

<?php elseif ($app): ?>
<div class="track-animate track-animate-d2">
  
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
  <div class="app-card hover-card track-animate track-animate-d3" style="margin-top: 1.2rem;">
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