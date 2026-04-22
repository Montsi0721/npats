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

<style>
/* ─────────────────────────────────────────────────────────────
   APPLICANT DASHBOARD — Premium Edition (Matching System)
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

.applicant-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.applicant-animate-d1 { animation-delay:.06s }
.applicant-animate-d2 { animation-delay:.12s }
.applicant-animate-d3 { animation-delay:.18s }
.applicant-animate-d4 { animation-delay:.24s }

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
.applicant-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .applicant-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.applicant-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.applicant-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.applicant-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.applicant-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.applicant-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.applicant-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.applicant-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.applicant-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.applicant-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.applicant-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.applicant-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.applicant-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.applicant-hero-meta-chip i { font-size: .62rem; }

.applicant-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Make Application Banner (Premium) ────────────────────── */
.make-app-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  background: linear-gradient(135deg, #0F1D35 0%, #142545 100%);
  border: 1px solid rgba(59,130,246,.2);
  border-radius: var(--radius-lg);
  padding: 1.4rem 1.75rem;
  margin-bottom: 1.75rem;
  position: relative;
  overflow: hidden;
}
.make-app-banner::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--gold-light), #3B82F6, var(--gold-light), transparent);
  animation: shimmer 3s linear infinite;
  background-size: 200% auto;
}
html[data-theme="light"] .make-app-banner {
  background: linear-gradient(135deg, #EEF4FF 0%, #E6EDF8 100%);
  border-color: #C8D8F0;
}
.make-app-heading {
  font-size: 1.05rem;
  font-weight: 700;
  color: #E2E8F4;
  margin-bottom: .3rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
html[data-theme="light"] .make-app-heading { color: var(--navy); }
.make-app-heading i { color: var(--gold-light); }
html[data-theme="light"] .make-app-heading i { color: var(--gold); }
.make-app-sub {
  font-size: .82rem;
  color: rgba(255,255,255,.6);
  max-width: 520px;
  line-height: 1.55;
}
html[data-theme="light"] .make-app-sub { color: var(--text-soft); }

/* ── Stats Grid ───────────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}
@media(max-width:768px){ .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width:480px){ .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 1rem 1.2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  cursor: pointer;
  position: relative;
  overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.12) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}
.stat-card:hover::before { opacity: 1; }
.stat-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}
.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}
.stat-card.blue .stat-icon { background: rgba(59,130,246,.12); color: #60A5FA; }
.stat-card.gold .stat-icon { background: rgba(245,158,11,.12); color: #F59E0B; }
.stat-card.teal .stat-icon { background: rgba(45,212,191,.12); color: #2DD4BF; }
.stat-card.green .stat-icon { background: rgba(52,211,153,.12); color: #34D399; }
.stat-num {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--text);
  line-height: 1.2;
  letter-spacing: -.03em;
}
.stat-label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── Main Grid ───────────────────────────────────────────── */
.app-dash-grid {
  display: grid;
  grid-template-columns: 1fr 1.6fr;
  gap: 1.2rem;
}
@media(max-width:700px) { .app-dash-grid { grid-template-columns: 1fr; } }

/* ── Premium Cards ────────────────────────────────────────── */
.applicant-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.applicant-card::before {
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
.applicant-card:hover::before { opacity: 1; }
.applicant-card:hover {
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

.card-body {
  padding: 1.5rem;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.8rem;
}
.info-label {
  font-size: .65rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: 0.2rem;
}
.info-value {
  font-size: .9rem;
  font-weight: 500;
  color: var(--text);
  word-break: break-word;
}
.info-value.monospace {
  font-family: monospace;
  font-size: .85rem;
}

/* Stage List */
.stage-list {
  padding: 0.5rem 1.5rem 1.5rem;
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
.stage-info {
  flex: 1;
}
.stage-info h4 {
  font-size: .85rem;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 0.3rem 0;
}
.stage-meta {
  font-size: .7rem;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.3rem;
}
.stage-meta i {
  font-size: .6rem;
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
.btn-group {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}
.btn-primary {
  background: linear-gradient(135deg, #1D5A9E, #3B82F6);
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: #fff;
  font-weight: 600;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59,130,246,.3);
}
.btn-gold {
  background: linear-gradient(135deg, #B45309, #F59E0B);
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: #fff;
  font-weight: 600;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.btn-gold:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245,158,11,.3);
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
.btn-ghost {
  background: transparent;
  border: 1.5px solid transparent;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: var(--text-soft);
  font-weight: 500;
  transition: all .2s;
}
.btn-ghost:hover {
  background: rgba(59,130,246,.08);
  color: var(--navy-light);
}
.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: .75rem;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
}
.empty-icon {
  width: 72px;
  height: 72px;
  margin: 0 auto 1.2rem;
  background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(200,145,26,.05));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  color: var(--muted);
}
.empty-state h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: .3rem;
}
.empty-state p {
  color: var(--muted);
  font-size: .85rem;
  max-width: 380px;
  margin: 0 auto;
  line-height: 1.6;
}

/* Utilities */
.mt-2 { margin-top: 1rem; }

/* Responsive */
@media (max-width: 768px) {
  .applicant-hero-inner {
    flex-direction: column;
    text-align: center;
  }
  .make-app-banner {
    flex-direction: column;
    text-align: center;
  }
  .make-app-sub {
    max-width: 100%;
  }
  .btn-group {
    justify-content: center;
  }
}
</style>

<!-- Hero Section -->
<div class="applicant-hero applicant-animate">
  <div class="applicant-hero-mesh"></div>
  <div class="applicant-hero-grid"></div>
  <div class="applicant-hero-inner">
    <div class="applicant-hero-left">
      <div class="applicant-hero-icon"><i class="fa fa-gauge"></i></div>
      <div>
        <div class="applicant-hero-eyebrow">Passport Applicant</div>
        <div class="applicant-hero-name">Welcome, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?></div>
        <div class="applicant-hero-meta">
          <span class="applicant-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="applicant-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="applicant-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Track Your Application
          </span>
        </div>
      </div>
    </div>
    <div class="applicant-hero-right">
      <a href="<?= APP_URL ?>/applicant/create_application.php" class="btn btn-primary">
        <i class="fa fa-file-circle-plus"></i> Create Application
      </a>
    </div>
  </div>
</div>

<!-- Make Application Banner -->
<div class="make-app-banner applicant-animate applicant-animate-d1">
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
<div class="stats-grid applicant-animate applicant-animate-d2">
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
<div class="app-dash-grid applicant-animate applicant-animate-d3">
  
  <!-- Latest Application Card -->
  <div class="applicant-card hover-card">
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
  <div class="applicant-card hover-card">
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
<div class="applicant-card applicant-animate applicant-animate-d3 hover-card" style="text-align:center;">
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

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .applicant-card, .stat-card');
  
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