<?php
ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];

// Helper function for counting records
$s = fn($q, $p = []) => (
    function() use ($db, $q, $p) {
        $st = $db->prepare($q);
        $st->execute($p);
        return (int)$st->fetchColumn();
    }
)();

$myApps     = $s('SELECT COUNT(*) FROM passport_applications WHERE officer_id=?', [$uid]);
$myPending  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND status='Pending'", [$uid]);
$myProgress = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND status='In-Progress'", [$uid]);
$readyColl  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND current_stage='Ready for Collection'", [$uid]);
$todayApps  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND DATE(created_at)=CURDATE()", [$uid]);
$completed  = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND status='Completed'", [$uid]);
$thisWeek   = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)", [$uid]);

// Sparkline data for last 7 days
$sparkline = [];
for ($i = 6; $i >= 0; $i--) {
    $sparkline[] = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL ? DAY)", [$uid, $i]);
}

// Recent applications
$recent = $db->prepare('SELECT * FROM passport_applications WHERE officer_id=? ORDER BY created_at DESC LIMIT 10');
$recent->execute([$uid]);
$recent = $recent->fetchAll();

$total     = max(1, $myApps);
$doneRatio = $total > 0 ? round($completed / $total * 100) : 0;

$pageTitle = 'Officer Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* Dashboard specific styles */
.od-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.od-stat {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 1.2rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 1px solid var(--border);
}

.od-stat:hover {
    transform: translateY(-4px);
    border-color: var(--navy-light);
}

.od-stat-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.od-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}

.od-stat.blue .od-stat-icon { color: #60A5FA; }
.od-stat.gold .od-stat-icon { color: #F59E0B; }
.od-stat.teal .od-stat-icon { color: #2DD4BF; }
.od-stat.green .od-stat-icon { color: #34D399; }

.od-spark {
    opacity: 0.7;
}

.od-stat-num {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-light);
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.od-stat-label {
    font-size: 0.75rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.od-stat-footer {
    margin-top: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.od-stat-tag {
    font-size: 0.65rem;
    padding: 0.2rem 0.5rem;
    border-radius: 20px;
    background: var(--surface);
    color: var(--muted);
}

.od-stat-stripe {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--navy-light), var(--gold-light));
    opacity: 0;
    transition: opacity 0.3s;
}

.od-stat:hover .od-stat-stripe {
    opacity: 1;
}

.corner-orb {
    position: absolute;
    top: -20px;
    right: -20px;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%);
    pointer-events: none;
}

.od-main {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
}

@media (max-width: 1000px) {
    .od-main {
        grid-template-columns: 1fr;
    }
}

.table-wrap {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    text-align: left;
    padding: 0.85rem 1rem;
    background: var(--surface);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
}

.table td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.app-num {
    font-family: monospace;
    font-weight: 600;
    color: var(--navy-light);
    text-decoration: none;
}

.app-num:hover {
    text-decoration: underline;
}

.fullname {
    font-weight: 500;
    color: var(--text-light);
}

.chip {
    background: var(--surface);
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
}

.stage {
    font-size: 0.75rem;
    color: var(--text-soft);
}

.date {
    font-size: 0.7rem;
    color: var(--muted);
    white-space: nowrap;
}

.edit-btn {
    color: var(--navy-light);
    text-decoration: none;
    padding: 0.3rem 0.6rem;
    border-radius: var(--radius);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.edit-btn:hover {
    background: var(--surface);
}

.empty {
    text-align: center;
    padding: 3rem;
}

.empty-icon {
    font-size: 3rem;
    color: var(--muted);
    margin-bottom: 1rem;
}

.actions {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.qa {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--surface);
    border-radius: var(--radius-lg);
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid var(--border);
}

.qa:hover {
    transform: translateX(4px);
    border-color: var(--navy-light);
}

.qa-ico {
    width: 32px;
    height: 32px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.qa.p .qa-ico { background: rgba(59,130,246,0.1); color: #60A5FA; }
.qa.g .qa-ico { background: rgba(16,185,129,0.1); color: #34D399; }
.qa.m .qa-ico { background: rgba(245,158,11,0.1); color: #F59E0B; }

.qa span {
    flex: 1;
    font-weight: 500;
    font-size: 0.85rem;
    color: var(--text-light);
}

.qa-arr {
    color: var(--muted);
    font-size: 0.7rem;
    transition: transform 0.2s;
}

.qa:hover .qa-arr {
    transform: translateX(4px);
}

.donut-body {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 1rem;
}

.legend {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.leg-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
}

.leg-l {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.leg-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.leg-lbl {
    color: var(--text-soft);
}

.leg-val {
    font-weight: 600;
    color: var(--text-light);
}

.bars-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.bar-row {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.bar-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: var(--muted);
}

.bar-track {
    height: 6px;
    background: var(--surface);
    border-radius: 3px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    width: 0%;
    border-radius: 3px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fillBar 0.8s ease-out forwards;
}

@keyframes fillBar {
    to {
        width: var(--target-w);
    }
}

.today-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(16, 185, 129, 0.1);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    color: #34D399;
    margin-right: 1rem;
}

.od-today-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #34D399;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.btn-ghost {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-soft);
}

.btn-ghost:hover {
    background: var(--surface);
    border-color: var(--navy-light);
    color: var(--navy-light);
}

/* Spotlight effect */
.sc-spotlight {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
    z-index: 1;
}
</style>

<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-user-shield"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Officer</div>
        <div class="hero-name"><?= e($_SESSION['user_name']) ?></div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <?php if ($thisWeek > 0): ?>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-fire" style="color:#F59E0B;"></i> <?= $thisWeek ?> this week
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <?php if ($todayApps > 0): ?>
      <div class="today-pill">
        <div class="od-today-dot"></div>
        <?= $todayApps ?> captured today
      </div>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary">
        <i class="fa fa-plus"></i> New Application
      </a>
    </div>
  </div>
</div>

<div class="od-stats">
  <div class="od-stat blue od-animate od-animate-d1 hover-card" data-spotlight>
    <div class="corner-orb"></div>
    <div class="od-stat-top">
      <div class="od-stat-icon"><i class="fa fa-file-lines"></i></div>
      <canvas class="od-spark" id="sp0" width="56" height="28"></canvas>
    </div>
    <div class="od-stat-num" data-target="<?= $myApps ?>"><?= $myApps ?></div>
    <div class="od-stat-label">Total Applications</div>
    <div class="od-stat-footer">
      <span class="od-stat-tag">All time</span>
      <span style="font-size:.68rem;color:var(--muted);">7-day trend ↑</span>
    </div>
    <div class="od-stat-stripe"></div>
  </div>

  <div class="od-stat gold od-animate od-animate-d2 hover-card" data-spotlight>
    <div class="corner-orb"></div>
    <div class="od-stat-top">
      <div class="od-stat-icon"><i class="fa fa-clock"></i></div>
      <canvas class="od-spark" id="sp1" width="56" height="28"></canvas>
    </div>
    <div class="od-stat-num"><?= $myPending ?></div>
    <div class="od-stat-label">Pending</div>
    <div class="od-stat-footer">
      <span class="od-stat-tag">Needs action</span>
    </div>
    <div class="od-stat-stripe"></div>
  </div>

  <div class="od-stat teal od-animate od-animate-d3 hover-card" data-spotlight>
    <div class="corner-orb"></div>
    <div class="od-stat-top">
      <div class="od-stat-icon"><i class="fa fa-rotate"></i></div>
      <canvas class="od-spark" id="sp2" width="56" height="28"></canvas>
    </div>
    <div class="od-stat-num"><?= $myProgress ?></div>
    <div class="od-stat-label">In Progress</div>
    <div class="od-stat-footer">
      <span class="od-stat-tag">Active</span>
    </div>
    <div class="od-stat-stripe"></div>
  </div>

  <div class="od-stat green od-animate od-animate-d4 hover-card" data-spotlight>
    <div class="corner-orb"></div>
    <div class="od-stat-top">
      <div class="od-stat-icon"><i class="fa fa-box-open"></i></div>
      <canvas class="od-spark" id="sp3" width="56" height="28"></canvas>
    </div>
    <div class="od-stat-num"><?= $readyColl ?></div>
    <div class="od-stat-label">Ready for Collection</div>
    <div class="od-stat-footer">
      <span class="od-stat-tag">Awaiting pickup</span>
    </div>
    <div class="od-stat-stripe"></div>
  </div>

  <div class="od-stat green od-animate od-animate-d5 hover-card" data-spotlight>
    <div class="corner-orb"></div>
    <div class="od-stat-top">
      <div class="od-stat-icon"><i class="fa fa-circle-check"></i></div>
      <canvas class="od-spark" id="sp4" width="56" height="28"></canvas>
    </div>
    <div class="od-stat-num"><?= $completed ?></div>
    <div class="od-stat-label">Completed</div>
    <div class="od-stat-footer">
      <span class="od-stat-tag"><?= $doneRatio ?>% rate</span>
    </div>
    <div class="od-stat-stripe"></div>
  </div>
</div>

<div class="od-main">

  <!-- Table -->
  <div class="card animate animate-d3 hover-card" data-spotlight>
    <div class="card-header">
      <div class="card-title">
        <i class="fa fa-clock-rotate-left"></i> Recent Applications
        <span class="card-badge"><?= count($recent) ?></span>
      </div>
      <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-ghost btn-sm">
        View all <i class="fa fa-arrow-right"></i>
      </a>
    </div>
    <div class="table-wrap">
      <table class="table">
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
          <tr><td colspan="7">
            <div class="empty">
              <div class="empty-icon"><i class="fa fa-inbox"></i></div>
              <h4>No applications yet</h4>
              <p>Captured applications will appear here for quick access and management.</p>
              <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> Capture First Application
              </a>
            </div>
            </td></tr>
        <?php else: foreach ($recent as $idx => $a): ?>
          <tr style="animation:fadeUp .4s cubic-bezier(.22,1,.36,1) <?= $idx * 0.04 ?>s both">
            <td><a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="app-num"><?= e($a['application_number']) ?></a></td>
            <td><span class="fullname"><?= e($a['full_name']) ?></span></td>
            <td><span class="chip"><?= e($a['passport_type']) ?></span></td>
            <td><span class="stage"><?= e($a['current_stage']) ?></span></td>
            <td><?= statusBadge($a['status']) ?></td>
            <td><span class="date"><?= e($a['application_date']) ?></span></td>
            <td><a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="edit-btn" title="Manage"><i class="fa fa-pen-to-square"></i></a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="od-sidebar">

    <!-- Quick actions -->
    <div class="card animate animate-d4 hover-card" data-spotlight>
      <div class="card-header">
        <div class="card-title"><i class="fa fa-bolt"></i> Quick Actions</div>
      </div>
      <div class="actions">
        <a href="<?= APP_URL ?>/officer/new_application.php" class="qa p hover-card" data-spotlight>
          <div class="qa-ico"><i class="fa fa-file-circle-plus"></i></div>
          <span>Capture Application</span>
          <i class="fa fa-chevron-right qa-arr"></i>
        </a>
        <a href="<?= APP_URL ?>/officer/releases.php" class="qa g hover-card" data-spotlight>
          <div class="qa-ico"><i class="fa fa-box-open"></i></div>
          <span>Release Passport</span>
          <i class="fa fa-chevron-right qa-arr"></i>
        </a>
        <a href="<?= APP_URL ?>/officer/applications.php" class="qa m hover-card" data-spotlight>
          <div class="qa-ico"><i class="fa fa-list"></i></div>
          <span>All Applications</span>
          <i class="fa fa-chevron-right qa-arr"></i>
        </a>
      </div>
    </div>

    <!-- Donut -->
    <div class="card animate animate-d5 hover-card" data-spotlight>
      <div class="card-header">
        <div class="card-title"><i class="fa fa-chart-pie"></i> Status Breakdown</div>
      </div>
      <div class="donut-body">
        <canvas id="donut" width="84" height="84" style="flex-shrink:0;"></canvas>
        <div class="legend">
          <?php 
          $segs = [
            ['Pending', $myPending, '#F59E0B'],
            ['In Progress', $myProgress, '#60A5FA'],
            ['Ready', $readyColl, '#2DD4BF'],
            ['Completed', $completed, '#34D399']
          ];
          foreach ($segs as [$l, $v, $c]): 
          ?>
          <div class="leg-row">
            <div class="leg-l">
              <div class="leg-dot" style="background:<?= $c ?>;"></div>
              <span class="leg-lbl"><?= $l ?></span>
            </div>
            <span class="leg-val"><?= $v ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Completion bars -->
    <div class="card animate animate-d6 hover-card" data-spotlight>
      <div class="card-header">
        <div class="card-title"><i class="fa fa-chart-simple"></i> Workload</div>
        <span style="font-size:.8rem;font-weight:800;color:var(--success);"><?= $doneRatio ?>%</span>
      </div>
      <div class="bars-body">
        <?php
        $bd = [
          ['Pending', $myPending, 'linear-gradient(90deg,#92400E,#F59E0B)'],
          ['In Progress', $myProgress, 'linear-gradient(90deg,#1E3A8A,#60A5FA)'],
          ['Ready', $readyColl, 'linear-gradient(90deg,#0D9488,#2DD4BF)'],
          ['Completed', $completed, 'linear-gradient(90deg,#065F46,#34D399)'],
        ];
        foreach($bd as [$l, $v, $g]):
          $pct = $total > 0 ? round($v / $total * 100) : 0;
        ?>
        <div class="bar-row">
          <div class="bar-meta"><span><?= $l ?></span><span><?= $v ?></span></div>
          <div class="bar-track">
            <div class="bar-fill" style="--target-w:<?= $pct ?>%;background:<?= $g ?>;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<script>
(function() {
  // Spotlight effect for all hover-card elements
  const spotlightElements = document.querySelectorAll('.hover-card, [data-spotlight]');
  
  spotlightElements.forEach(el => {
    // Create spotlight overlay if not exists
    let spotlight = el.querySelector('.sc-spotlight');
    if (!spotlight) {
      spotlight = document.createElement('div');
      spotlight.className = 'sc-spotlight';
      el.style.position = 'relative';
      el.style.overflow = 'hidden';
      el.appendChild(spotlight);
    }
    
    // Mouse move handler for spotlight
    el.addEventListener('mousemove', function(e) {
      const rect = this.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;
      
      // Update CSS variables for the gradient
      this.style.setProperty('--x', x + '%');
      this.style.setProperty('--y', y + '%');
      
      // Update spotlight position
      spotlight.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(59, 130, 246, 0.12) 0%, transparent 60%)`;
      spotlight.style.opacity = '1';
    });
    
    el.addEventListener('mouseleave', function() {
      spotlight.style.opacity = '0';
    });
  });
  
  // Also add tilt effect to stat cards
  const tiltCards = document.querySelectorAll('.od-stat');
  tiltCards.forEach(card => {
    let tiltX = 0, tiltY = 0;
    let targetX = 0, targetY = 0;
    let animFrame = null;
    
    card.addEventListener('mousemove', function(e) {
      const rect = this.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;
      const deltaX = (e.clientX - centerX) / (rect.width / 2);
      const deltaY = (e.clientY - centerY) / (rect.height / 2);
      
      targetX = -deltaY * 4;
      targetY = deltaX * 4;
      
      if (!animFrame) {
        const update = () => {
          tiltX += (targetX - tiltX) * 0.1;
          tiltY += (targetY - tiltY) * 0.1;
          card.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateY(-4px)`;
          animFrame = requestAnimationFrame(update);
        };
        animFrame = requestAnimationFrame(update);
      }
    });
    
    card.addEventListener('mouseleave', function() {
      targetX = 0;
      targetY = 0;
      if (animFrame) {
        cancelAnimationFrame(animFrame);
        animFrame = null;
      }
      card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px)';
      tiltX = 0;
      tiltY = 0;
    });
  });
})();
</script>

<script>
(function(){
  const raw = <?= json_encode($sparkline) ?>;
  const isDark = document.documentElement.dataset.theme !== 'light';

  /* ── Sparklines ── */
  function spark(id, data, color) {
    const c = document.getElementById(id); 
    if (!c) return;
    const ctx = c.getContext('2d');
    const W = c.width, H = c.height;
    const max = Math.max(...data, 1);
    const pts = data.map((v,i)=>({
      x: 1 + (i/(data.length-1))*(W-2),
      y: H - 3 - (v/max)*(H-7)
    }));
    ctx.clearRect(0,0,W,H);

    function smooth(p) {
      ctx.beginPath(); 
      ctx.moveTo(p[0].x, p[0].y);
      for (let i=1; i<p.length-1; i++) {
        const mx = (p[i].x + p[i+1].x) / 2;
        const my = (p[i].y + p[i+1].y) / 2;
        ctx.quadraticCurveTo(p[i].x, p[i].y, mx, my);
      }
      ctx.lineTo(p[p.length-1].x, p[p.length-1].y);
    }

    const g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, color + '60');
    g.addColorStop(1, color + '00');
    
    ctx.save();
    smooth(pts);
    ctx.lineTo(pts[pts.length-1].x, H);
    ctx.lineTo(pts[0].x, H);
    ctx.closePath();
    ctx.fillStyle = g;
    ctx.fill();
    ctx.restore();

    smooth(pts);
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.6;
    ctx.lineJoin = 'round';
    ctx.stroke();

    const lp = pts[pts.length-1];
    ctx.beginPath();
    ctx.arc(lp.x, lp.y, 2.5, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
    
    ctx.beginPath();
    ctx.arc(lp.x, lp.y, 4.5, 0, Math.PI * 2);
    ctx.fillStyle = color + '33';
    ctx.fill();
  }

  spark('sp0', raw, '#60A5FA');
  spark('sp1', raw.map(v => Math.max(0, v - Math.round(v * 0.6))), '#F59E0B');
  spark('sp2', raw.map(v => Math.round(v * 0.55)), '#2DD4BF');
  spark('sp3', raw.map(v => Math.round(v * 0.2)), '#34D399');
  spark('sp4', raw.map((v, i) => i < 3 ? Math.round(v * 0.1) : Math.round(v * 0.5)), '#34D399');

  /* ── Donut ── */
  const dc = document.getElementById('donut');
  if (dc) {
    const ctx = dc.getContext('2d');
    const segs = [
      {v: <?= $myPending ?>, c: '#F59E0B'},
      {v: <?= $myProgress ?>, c: '#60A5FA'},
      {v: <?= $readyColl ?>, c: '#2DD4BF'},
      {v: <?= $completed ?>, c: '#34D399'},
    ];
    const tot = segs.reduce((s, x) => s + x.v, 0) || 1;
    const cx = 42, cy = 42, OR = 37, IR = 24;
    let a = -Math.PI / 2;

    if (segs.every(s => s.v === 0)) {
      ctx.beginPath();
      ctx.arc(cx, cy, OR, 0, Math.PI * 2);
      ctx.arc(cx, cy, IR, Math.PI * 2, 0, true);
      ctx.closePath();
      ctx.fillStyle = isDark ? '#1C2333' : '#EEF1F8';
      ctx.fill();
    } else {
      segs.forEach(seg => {
        if (seg.v === 0) return;
        const sw = (seg.v / tot) * Math.PI * 2;
        ctx.beginPath();
        ctx.arc(cx, cy, OR, a, a + sw);
        ctx.arc(cx, cy, IR, a + sw, a, true);
        ctx.closePath();
        ctx.fillStyle = seg.c;
        ctx.fill();
        a += sw + 0.03;
      });
    }
    
    ctx.fillStyle = isDark ? '#E2E8F4' : '#1A2238';
    ctx.font = '800 14px system-ui';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(<?= $myApps ?>, cx, cy - 1);
    
    ctx.font = '500 9px system-ui';
    ctx.fillStyle = isDark ? '#667085' : '#6B7898';
    ctx.fillText('total', cx, cy + 9);
  }

  /* ── Animated number counters ── */
  document.querySelectorAll('.od-stat-num[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target);
    if (!target || target === 0) return;
    let current = 0;
    const step = Math.ceil(target / 30);
    const interval = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = current;
      if (current >= target) clearInterval(interval);
    }, 30);
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>