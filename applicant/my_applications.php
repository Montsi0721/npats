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

<style>
/* ─────────────────────────────────────────────────────────────
   MY APPLICATIONS PAGE — Premium Edition
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

.ma-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.ma-animate-d1 { animation-delay:.06s }
.ma-animate-d2 { animation-delay:.12s }
.ma-animate-d3 { animation-delay:.18s }
.ma-animate-d4 { animation-delay:.24s }

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
.ma-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .ma-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.ma-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.ma-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.ma-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.ma-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.ma-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.ma-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.ma-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.ma-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.ma-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.ma-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.ma-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.ma-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.ma-hero-meta-chip i { font-size: .62rem; }

.ma-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Stats Row (mini stats) ───────────────────────────────── */
.ma-stats-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.ma-stat-mini {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0.75rem 1.25rem;
  flex: 1;
  min-width: 100px;
  transition: all 0.2s;
  cursor: pointer;
}
.ma-stat-mini:hover {
  transform: translateY(-2px);
  border-color: rgba(59,130,246,0.3);
  background: rgba(59,130,246,.02);
}
.ma-stat-mini .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--gold-light);
  line-height: 1;
}
.ma-stat-mini .label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── Applications Card ────────────────────────────────────── */
.ma-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.ma-card::before {
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
.ma-card:hover::before { opacity: 1; }
.ma-card:hover {
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
.card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}

/* Premium Table */
.ma-table-wrapper {
  overflow-x: auto;
}
.ma-table {
  width: 100%;
  border-collapse: collapse;
}
.ma-table thead {
  background: #07101E;
  border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .ma-table thead { background: var(--surface); }
.ma-table thead th {
  padding: 1rem 1.2rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  white-space: nowrap;
}
.ma-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
  animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both;
}
.ma-table tbody tr:hover {
  background: rgba(59,130,246,.04);
}
.ma-table tbody td {
  padding: 1rem 1.2rem;
  vertical-align: middle;
  font-size: .85rem;
}

.app-number {
  font-family: 'DM Mono', monospace;
  font-weight: 700;
  font-size: .85rem;
  color: var(--navy-light);
  letter-spacing: -.02em;
  text-decoration: none;
  transition: color .12s;
}
.app-number:hover {
  color: var(--info-light, #93C5FD);
  text-decoration: none;
}

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
.stage-text {
  font-size: .78rem;
  color: var(--text-soft);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
}
.empty-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 1rem;
  background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(200,145,26,.05));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
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
  font-size: .8rem;
  margin-bottom: 0.2rem;
}

/* Action Buttons */
.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.9rem;
  background: linear-gradient(135deg, #1D5A9E, #3B82F6);
  border: none;
  border-radius: 8px;
  color: #fff;
  font-size: .75rem;
  font-weight: 600;
  transition: all .2s cubic-bezier(.34,1.56,.64,1);
  text-decoration: none;
}
.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59,130,246,.3);
  color: #fff;
}
.action-btn i {
  font-size: .7rem;
}

/* Responsive */
@media (max-width: 768px) {
  .ma-hero-inner { flex-direction: column; text-align: center; }
  .ma-stats-row { flex-wrap: wrap; }
  .ma-table thead th,
  .ma-table tbody td { padding: 0.75rem; }
  .action-btn { padding: 0.3rem 0.7rem; font-size: .7rem; }
}
</style>

<!-- Hero Section -->
<div class="ma-hero ma-animate">
  <div class="ma-hero-mesh"></div>
  <div class="ma-hero-grid"></div>
  <div class="ma-hero-inner">
    <div class="ma-hero-left">
      <div class="ma-hero-icon"><i class="fa fa-list-alt"></i></div>
      <div>
        <div class="ma-hero-eyebrow">Passport Applicant</div>
        <div class="ma-hero-name">My Applications</div>
        <div class="ma-hero-meta">
          <span class="ma-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="ma-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="ma-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Track your passport
          </span>
        </div>
      </div>
    </div>
    <div class="ma-hero-right">
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
<div class="ma-stats-row ma-animate ma-animate-d1">
  <div class="ma-stat-mini hover-card">
    <div class="value"><?= $totalApps ?></div>
    <div class="label">Total Applications</div>
  </div>
  <div class="ma-stat-mini hover-card">
    <div class="value" style="color: #F59E0B;"><?= $pendingApps ?></div>
    <div class="label">Pending</div>
  </div>
  <div class="ma-stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= $inProgressApps ?></div>
    <div class="label">In Progress</div>
  </div>
  <div class="ma-stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= $completedApps ?></div>
    <div class="label">Completed</div>
  </div>
</div>

<!-- Applications Card -->
<div class="ma-card ma-animate ma-animate-d2 hover-card">
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
    <div class="ma-table-wrapper">
      <table class="ma-table">
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
  const spotlightElements = document.querySelectorAll('.hover-card, .ma-card');
  
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