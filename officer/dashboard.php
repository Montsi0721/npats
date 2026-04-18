<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];

$s = fn($q,$p=[]) => (function() use($db,$q,$p){ $st=$db->prepare($q);$st->execute($p);return(int)$st->fetchColumn(); })();
$myApps     = $s('SELECT COUNT(*) FROM passport_applications WHERE officer_id=?',[$uid]);
$myPending  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND status='Pending'",[$uid]);
$myProgress = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND status='In-Progress'",[$uid]);
$readyColl  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND current_stage='Ready for Collection'",[$uid]);
$todayApps  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND DATE(created_at)=CURDATE()",[$uid]);
$completed  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND status='Completed'",[$uid]);

$recent = $db->prepare('SELECT * FROM passport_applications WHERE officer_id=? ORDER BY created_at DESC LIMIT 8');
$recent->execute([$uid]);
$recent = $recent->fetchAll();

$pageTitle = 'Officer Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Officer dashboard enhancements ───────────────────────── */
.dash-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  background: linear-gradient(135deg, #0B1829 0%, #0F2040 60%, #0B1829 100%);
  border: 1px solid #1E3050;
  border-radius: var(--radius-lg);
  padding: 1.6rem 2rem;
  margin-bottom: 1.75rem;
  position: relative;
  overflow: hidden;
}
.dash-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold));
}
.dash-hero::after {
  content: '';
  position: absolute; right: -40px; top: -40px;
  width: 200px; height: 200px;
  background: radial-gradient(circle, rgba(59,130,246,.08) 0%, transparent 70%);
  pointer-events: none;
}
html[data-theme="light"] .dash-hero {
  background: linear-gradient(135deg, #EEF4FF 0%, #E2ECF8 100%);
  border-color: #C8D8F0;
}
.dash-hero-left { display: flex; align-items: center; gap: 1rem; }
.dash-hero-avatar {
  width: 52px; height: 52px; border-radius: 14px;
  background: linear-gradient(135deg, rgba(59,130,246,.3), rgba(59,130,246,.1));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; color: var(--info); flex-shrink: 0;
}
.dash-hero-greeting { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: .2rem; }
.dash-hero-name { font-size: 1.25rem; font-weight: 600; color: var(--text); letter-spacing: -.02em; }
.dash-hero-date { font-size: .78rem; color: var(--muted); margin-top: .15rem; }
html[data-theme="light"] .dash-hero-name { color: var(--navy); }
.dash-hero-right { display: flex; gap: .6rem; flex-wrap: wrap; }

/* ── Stats grid v2 ──────────────────────────────────────────── */
.stats-grid-v2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 1rem;
  margin-bottom: 1.75rem;
}
.stat-card-v2 {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 1.25rem 1.4rem;
  position: relative;
  overflow: hidden;
  transition: transform .15s, box-shadow .15s, border-color .15s;
  cursor: default;
}
.stat-card-v2:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
  border-color: var(--border-mid);
}
.stat-card-v2-top {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: .9rem;
}
.stat-card-v2-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: .92rem;
}
.stat-card-v2-trend {
  font-size: .7rem; font-weight: 500;
  padding: .18rem .5rem; border-radius: 20px;
  display: flex; align-items: center; gap: .2rem;
}
.trend-up   { background: rgba(52,211,153,.12); color: var(--success); }
.trend-warn { background: rgba(251,191,36,.12);  color: var(--warning); }
.trend-neu  { background: rgba(96,165,250,.12);  color: var(--info); }

.stat-card-v2-num   { font-size: 2rem; font-weight: 700; letter-spacing: -.04em; color: var(--text); line-height: 1; }
.stat-card-v2-label { font-size: .74rem; color: var(--muted); margin-top: .3rem; }
.stat-card-v2-bar {
  position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
}
.stat-card-v2.blue  .stat-card-v2-icon { background: var(--info-bg);               color: var(--info); }
.stat-card-v2.gold  .stat-card-v2-icon { background: var(--warning-bg);             color: var(--gold); }
.stat-card-v2.teal  .stat-card-v2-icon { background: rgba(45,212,191,.12);          color: #2DD4BF; }
.stat-card-v2.green .stat-card-v2-icon { background: var(--success-bg);             color: var(--success); }
.stat-card-v2.red   .stat-card-v2-icon { background: var(--danger-bg);              color: var(--danger); }

.stat-card-v2.blue  .stat-card-v2-bar { background: var(--navy-light); }
.stat-card-v2.gold  .stat-card-v2-bar { background: var(--gold); }
.stat-card-v2.teal  .stat-card-v2-bar { background: #2DD4BF; }
.stat-card-v2.green .stat-card-v2-bar { background: var(--success); }
.stat-card-v2.red   .stat-card-v2-bar { background: var(--danger); }

/* ── Two-col layout ─────────────────────────────────────────── */
.dash-grid {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 1.2rem;
  align-items: start;
}
@media(max-width:900px){ .dash-grid { grid-template-columns: 1fr; } }

/* ── Quick actions sidebar card ─────────────────────────────── */
.quick-actions { display: flex; flex-direction: column; gap: .5rem; }
.quick-action-btn {
  display: flex; align-items: center; gap: .75rem;
  padding: .8rem 1rem;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  text-decoration: none;
  color: var(--text-soft);
  font-size: .85rem;
  font-weight: 500;
  transition: background .15s, border-color .15s, color .15s, transform .12s;
}
.quick-action-btn:hover {
  background: var(--bg-alt);
  border-color: var(--border-mid);
  color: var(--text);
  transform: translateX(3px);
  filter: none;
  text-decoration: none;
}
.quick-action-btn i:first-child {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: .82rem; flex-shrink: 0;
}
.qa-primary i:first-child { background: var(--info-bg);    color: var(--info); }
.qa-gold    i:first-child { background: var(--warning-bg); color: var(--gold); }
.qa-ghost   i:first-child { background: var(--surface-alt); color: var(--muted); }
.quick-action-btn .qa-arrow { margin-left: auto; font-size: .72rem; color: var(--muted); transition: transform .15s; }
.quick-action-btn:hover .qa-arrow { transform: translateX(3px); }

/* ── Today badge ────────────────────────────────────────────── */
.today-pill {
  display: inline-flex; align-items: center; gap: .35rem;
  background: rgba(52,211,153,.1); border: 1px solid rgba(52,211,153,.25);
  color: var(--success); border-radius: 20px;
  padding: .22rem .65rem; font-size: .72rem; font-weight: 600;
}
.today-pill::before { content: ''; width: 6px; height: 6px; background: var(--success); border-radius: 50%; animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── Table improvements ─────────────────────────────────────── */
.app-num-link {
  color: var(--navy-light) !important;
  font-weight: 600;
  font-size: .84rem;
  letter-spacing: .01em;
}
.app-num-link:hover { text-decoration: underline; }
.type-chip {
  display: inline-block;
  font-size: .72rem;
  background: var(--surface);
  border: 1px solid var(--border);
  padding: .16rem .52rem;
  border-radius: 6px;
  color: var(--text-soft);
  white-space: nowrap;
}
.stage-text { font-size: .78rem; color: var(--muted); }

/* ── Empty state ────────────────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
}
.empty-state-icon {
  width: 60px; height: 60px;
  background: var(--info-bg);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: var(--info);
  margin: 0 auto 1rem;
}
.empty-state h3 { font-size: .95rem; font-weight: 500; color: var(--text); margin-bottom: .4rem; }
.empty-state p  { font-size: .82rem; color: var(--muted); max-width: 300px; margin: 0 auto 1.25rem; line-height: 1.6; }

/* ── Section label ──────────────────────────────────────────── */
.section-label {
  font-size: .68rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: .1em; color: var(--muted);
  margin-bottom: .6rem; padding: 0 .1rem;
}
</style>

<!-- ── Hero greeting ──────────────────────────────────────────── -->
<div class="dash-hero">
  <div class="dash-hero-left">
    <div class="dash-hero-avatar"><i class="fa fa-user-shield"></i></div>
    <div>
      <div class="dash-hero-greeting">Officer Dashboard</div>
      <div class="dash-hero-name"><?= e($_SESSION['user_name']) ?></div>
      <div class="dash-hero-date"><i class="fa fa-calendar-days" style="font-size:.72rem;margin-right:.3rem;"></i><?= date('l, d F Y') ?></div>
    </div>
  </div>
  <div class="dash-hero-right">
    <?php if ($todayApps > 0): ?>
    <div class="today-pill"><i class="fa fa-bolt" style="font-size:.65rem;"></i> <?= $todayApps ?> application<?= $todayApps > 1 ? 's' : '' ?> today</div>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary">
      <i class="fa fa-plus"></i> New Application
    </a>
  </div>
</div>

<!-- ── Stat cards ─────────────────────────────────────────────── -->
<div class="stats-grid-v2">
  <div class="stat-card-v2 blue">
    <div class="stat-card-v2-top">
      <div class="stat-card-v2-icon"><i class="fa fa-file-lines"></i></div>
      <span class="stat-card-v2-trend trend-neu"><i class="fa fa-layer-group" style="font-size:.6rem;"></i> Total</span>
    </div>
    <div class="stat-card-v2-num"><?= $myApps ?></div>
    <div class="stat-card-v2-label">My Applications</div>
    <div class="stat-card-v2-bar"></div>
  </div>
  <div class="stat-card-v2 gold">
    <div class="stat-card-v2-top">
      <div class="stat-card-v2-icon"><i class="fa fa-clock"></i></div>
      <span class="stat-card-v2-trend trend-warn"><i class="fa fa-circle" style="font-size:.5rem;"></i> Needs action</span>
    </div>
    <div class="stat-card-v2-num"><?= $myPending ?></div>
    <div class="stat-card-v2-label">Pending</div>
    <div class="stat-card-v2-bar"></div>
  </div>
  <div class="stat-card-v2 teal">
    <div class="stat-card-v2-top">
      <div class="stat-card-v2-icon"><i class="fa fa-rotate"></i></div>
      <span class="stat-card-v2-trend trend-neu"><i class="fa fa-circle" style="font-size:.5rem;"></i> Active</span>
    </div>
    <div class="stat-card-v2-num"><?= $myProgress ?></div>
    <div class="stat-card-v2-label">In Progress</div>
    <div class="stat-card-v2-bar"></div>
  </div>
  <div class="stat-card-v2 green">
    <div class="stat-card-v2-top">
      <div class="stat-card-v2-icon"><i class="fa fa-box-open"></i></div>
      <span class="stat-card-v2-trend trend-up"><i class="fa fa-arrow-up" style="font-size:.6rem;"></i> Ready</span>
    </div>
    <div class="stat-card-v2-num"><?= $readyColl ?></div>
    <div class="stat-card-v2-label">Ready for Collection</div>
    <div class="stat-card-v2-bar"></div>
  </div>
  <div class="stat-card-v2 green">
    <div class="stat-card-v2-top">
      <div class="stat-card-v2-icon"><i class="fa fa-circle-check"></i></div>
      <span class="stat-card-v2-trend trend-up"><i class="fa fa-check" style="font-size:.6rem;"></i> Done</span>
    </div>
    <div class="stat-card-v2-num"><?= $completed ?></div>
    <div class="stat-card-v2-label">Completed</div>
    <div class="stat-card-v2-bar"></div>
  </div>
</div>

<!-- ── Main grid: table + sidebar ─────────────────────────────── -->
<div class="dash-grid">

  <!-- Recent applications table -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-clock-rotate-left"></i> Recent Applications</span>
      <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-ghost btn-sm">View all <i class="fa fa-arrow-right"></i></a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>App Number</th>
            <th>Applicant</th>
            <th>Type</th>
            <th>Stage</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <div class="empty-state-icon"><i class="fa fa-inbox"></i></div>
                <h3>No applications yet</h3>
                <p>Once you capture applications they'll appear here for quick access.</p>
                <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary btn-sm">
                  <i class="fa fa-plus"></i> Capture First Application
                </a>
              </div>
            </td>
          </tr>
        <?php else: foreach ($recent as $a): ?>
          <tr>
            <td>
              <a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="app-num-link">
                <?= e($a['application_number']) ?>
              </a>
            </td>
            <td><strong><?= e($a['full_name']) ?></strong></td>
            <td><span class="type-chip"><?= e($a['passport_type']) ?></span></td>
            <td><span class="stage-text"><?= e($a['current_stage']) ?></span></td>
            <td><?= statusBadge($a['status']) ?></td>
            <td style="font-size:.78rem;color:var(--muted);white-space:nowrap;"><?= e($a['application_date']) ?></td>
            <td>
              <a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline" title="Manage">
                <i class="fa fa-pen-to-square"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sidebar -->
  <div>
    <div class="card" style="margin-bottom:1.2rem;">
      <div class="card-header" style="margin-bottom:.75rem;padding-bottom:.75rem;">
        <span class="card-title"><i class="fa fa-bolt"></i> Quick Actions</span>
      </div>
      <div class="quick-actions">
        <a href="<?= APP_URL ?>/officer/new_application.php" class="quick-action-btn qa-primary">
          <i class="fa fa-file-circle-plus"></i>
          <span>Capture Application</span>
          <i class="fa fa-arrow-right qa-arrow"></i>
        </a>
        <a href="<?= APP_URL ?>/officer/releases.php" class="quick-action-btn qa-gold">
          <i class="fa fa-box-open"></i>
          <span>Release Passport</span>
          <i class="fa fa-arrow-right qa-arrow"></i>
        </a>
        <a href="<?= APP_URL ?>/officer/applications.php" class="quick-action-btn qa-ghost">
          <i class="fa fa-list"></i>
          <span>All Applications</span>
          <i class="fa fa-arrow-right qa-arrow"></i>
        </a>
      </div>
    </div>

    <!-- Workload summary -->
    <div class="card" style="margin-bottom:0;">
      <div class="card-header" style="margin-bottom:.75rem;padding-bottom:.75rem;">
        <span class="card-title"><i class="fa fa-chart-simple"></i> Workload</span>
      </div>
      <?php
        $total = max(1, $myApps);
        $bars = [
          ['label'=>'Pending',    'val'=>$myPending,  'color'=>'var(--gold)',       'pct'=> round($myPending/$total*100)],
          ['label'=>'In Progress','val'=>$myProgress, 'color'=>'var(--info)',        'pct'=> round($myProgress/$total*100)],
          ['label'=>'Ready',      'val'=>$readyColl,  'color'=>'#2DD4BF',           'pct'=> round($readyColl/$total*100)],
          ['label'=>'Completed',  'val'=>$completed,  'color'=>'var(--success)',     'pct'=> round($completed/$total*100)],
        ];
      ?>
      <div style="display:flex;flex-direction:column;gap:.8rem;">
        <?php foreach($bars as $b): ?>
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem;">
            <span style="font-size:.78rem;color:var(--text-soft);"><?= $b['label'] ?></span>
            <span style="font-size:.78rem;font-weight:600;color:var(--text);"><?= $b['val'] ?></span>
          </div>
          <div style="height:5px;background:var(--surface);border-radius:999px;overflow:hidden;">
            <div style="height:100%;width:<?= $b['pct'] ?>%;background:<?= $b['color'] ?>;border-radius:999px;transition:width .6s ease;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>