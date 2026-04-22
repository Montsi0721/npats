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
$thisWeek   = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)",[$uid]);

$sparkline = [];
for ($i = 6; $i >= 0; $i--)
    $sparkline[] = $s("SELECT COUNT(*) FROM passport_applications WHERE officer_id=? AND DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL ? DAY)", [$uid, $i]);

$recent = $db->prepare('SELECT * FROM passport_applications WHERE officer_id=? ORDER BY created_at DESC LIMIT 10');
$recent->execute([$uid]);
$recent = $recent->fetchAll();

$total     = max(1, $myApps);
$doneRatio = round($completed / $total * 100);

$pageTitle = 'Officer Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   OFFICER DASHBOARD  — Premium Edition with Spotlight Effect
   ───────────────────────────────────────────────────────────── */

/* ── Entry animations ──────────────────────────────────────── */
@keyframes fadeUp   { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn   { from{opacity:0} to{opacity:1} }
@keyframes scaleIn  { from{opacity:0;transform:scale(.94)} to{opacity:1;transform:scale(1)} }
@keyframes countUp  { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
@keyframes shimmer  {
  0%  { background-position: -200% center }
  100%{ background-position:  200% center }
}
@keyframes pulse-ring {
  0%  { box-shadow: 0 0 0 0 rgba(52,211,153,.5) }
  70% { box-shadow: 0 0 0 8px rgba(52,211,153,0) }
  100%{ box-shadow: 0 0 0 0 rgba(52,211,153,0) }
}
@keyframes bar-fill { from{width:0} to{width:var(--w)} }
@keyframes spin-slow { to { transform: rotate(360deg) } }
@keyframes float {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-6px) }
}

.od-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.od-animate-d1 { animation-delay:.06s }
.od-animate-d2 { animation-delay:.12s }
.od-animate-d3 { animation-delay:.18s }
.od-animate-d4 { animation-delay:.24s }
.od-animate-d5 { animation-delay:.30s }
.od-animate-d6 { animation-delay:.36s }

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
              rgba(59, 130, 246, 0.15) 0%, 
              transparent 60%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  z-index: 1;
}

.hover-card:hover::before {
  opacity: 1;
}

.hover-card:hover {
  transform: translateY(-4px) scale(1.01);
}

/* Spotlight canvas overlay */
.sc-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 10;
  border-radius: inherit;
}

.sc-spotlight {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  pointer-events: none;
  z-index: 0;
  opacity: 0;
  transition: opacity 0.3s;
}

/* ── Hero ──────────────────────────────────────────────────── */
.od-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .od-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

/* Animated mesh background */
.od-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

/* Animated grid lines */
.od-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

/* Gold shimmer top border */
.od-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.od-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.od-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.od-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.od-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.od-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.od-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.od-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.od-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.od-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.od-hero-meta-chip i { font-size: .62rem; }

.od-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

.od-today-pill {
  display: inline-flex; align-items: center; gap: .45rem;
  background: rgba(52,211,153,.08); border: 1px solid rgba(52,211,153,.22);
  color: #6EE7B7; border-radius: 20px;
  padding: .32rem 1rem; font-size: .74rem; font-weight: 600;
}
.od-today-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: #34D399; animation: pulse-ring 2s infinite;
}

/* ── Stat cards (with spotlight) ────────────────────────────── */
.od-stats {
  display: grid;
  grid-template-columns: repeat(5,1fr);
  gap: 1rem; margin-bottom: 1.5rem;
}
@media(max-width:1100px){ .od-stats{grid-template-columns:repeat(3,1fr)} }
@media(max-width:640px) { .od-stats{grid-template-columns:1fr 1fr} }

.od-stat {
  position: relative; overflow: hidden;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 1.15rem 1.25rem 1rem;
  cursor: pointer;
  transition: transform .2s cubic-bezier(.34,1.56,.64,1),
              box-shadow .2s ease,
              border-color .2s ease;
  transform-style: preserve-3d;
  will-change: transform;
}

.od-stat::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.12) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  border-radius: inherit;
}

.od-stat:hover::before {
  opacity: 1;
}

.od-stat:hover {
  transform: translateY(-4px) scale(1.01);
  border-color: var(--border-mid);
}

.od-stat.blue:hover  { box-shadow: 0 12px 32px rgba(59,130,246,.15); }
.od-stat.gold:hover  { box-shadow: 0 12px 32px rgba(245,158,11,.12); }
.od-stat.teal:hover  { box-shadow: 0 12px 32px rgba(45,212,191,.12); }
.od-stat.green:hover { box-shadow: 0 12px 32px rgba(52,211,153,.12); }

/* Corner orb */
.od-stat .corner-orb {
  content: ''; position: absolute;
  top: -30px; right: -30px;
  width: 90px; height: 90px;
  border-radius: 50%; pointer-events: none;
  transition: opacity .2s;
  opacity: .06;
}
.od-stat.blue .corner-orb  { background: #60A5FA; }
.od-stat.gold .corner-orb  { background: #F59E0B; }
.od-stat.teal .corner-orb  { background: #2DD4BF; }
.od-stat.green .corner-orb { background: #34D399; }
.od-stat:hover .corner-orb { opacity: .13; }

.od-stat-top {
  display: flex; align-items: flex-start;
  justify-content: space-between; margin-bottom: .7rem;
}
.od-stat-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; font-size: .84rem;
}
.od-stat.blue  .od-stat-icon { background:rgba(59,130,246,.13); color:#60A5FA; }
.od-stat.gold  .od-stat-icon { background:rgba(245,158,11,.14);  color:#F59E0B; }
.od-stat.teal  .od-stat-icon { background:rgba(45,212,191,.12);  color:#2DD4BF; }
.od-stat.green .od-stat-icon { background:rgba(52,211,153,.12);  color:#34D399; }

.od-stat-num {
  font-size: 2rem; font-weight: 800;
  letter-spacing: -.05em; line-height: 1;
  color: var(--text);
  animation: countUp .5s cubic-bezier(.22,1,.36,1) both;
}
.od-stat-label { font-size: .71rem; color: var(--muted); margin-top: .22rem; }

.od-stat-footer {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: .75rem; padding-top: .65rem;
  border-top: 1px solid var(--border);
}
.od-stat-tag {
  font-size: .67rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: .07em; padding: .12rem .45rem;
  border-radius: 20px;
}
.od-stat.blue  .od-stat-tag { background:rgba(59,130,246,.12); color:#60A5FA; }
.od-stat.gold  .od-stat-tag { background:rgba(245,158,11,.12); color:#F59E0B; }
.od-stat.teal  .od-stat-tag { background:rgba(45,212,191,.12); color:#2DD4BF; }
.od-stat.green .od-stat-tag { background:rgba(52,211,153,.12); color:#34D399; }

/* Mini sparkline canvas */
.od-spark { display:block; opacity:.8; }

/* Bottom stripe */
.od-stat-stripe {
  position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
  transition: height .2s;
}
.od-stat:hover .od-stat-stripe { height: 3px; }
.od-stat.blue  .od-stat-stripe { background:linear-gradient(90deg,#1D4ED8,#60A5FA); }
.od-stat.gold  .od-stat-stripe { background:linear-gradient(90deg,#92400E,#F59E0B); }
.od-stat.teal  .od-stat-stripe { background:linear-gradient(90deg,#0D9488,#2DD4BF); }
.od-stat.green .od-stat-stripe { background:linear-gradient(90deg,#065F46,#34D399); }

/* ── Main layout ───────────────────────────────────────────── */
.od-main {
  display: grid;
  grid-template-columns: 1fr 268px;
  gap: 1.2rem; align-items: start;
}
@media(max-width:980px){ .od-main{grid-template-columns:1fr} }

/* ── Table card (with spotlight) ────────────────────────────── */
.od-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  transform-style: preserve-3d;
  will-change: transform;
  position: relative;
}

.od-card::before {
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

.od-card:hover::before {
  opacity: 1;
}

.od-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.od-card-hd {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.35rem; border-bottom: 1px solid var(--border); gap: .75rem; flex-wrap: wrap;
}
.od-card-title {
  font-size: .88rem; font-weight: 700; color: var(--text);
  display: flex; align-items: center; gap: .45rem;
}
.od-card-title i { color: var(--gold); font-size: .78rem; }
.od-card-badge {
  font-size: .67rem; font-weight: 700;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 20px; padding: .12rem .52rem; color: var(--muted);
}

/* Table */
.od-table-wrap table { width:100%; border-collapse:collapse; font-size:.83rem; }
.od-table-wrap thead {
  background: #07101E; border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .od-table-wrap thead { background: var(--surface); }
.od-table-wrap thead th {
  padding: .6rem 1rem; text-align:left;
  font-weight:700; color:var(--muted);
  font-size:.67rem; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap;
}
.od-table-wrap tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s, transform .12s;
  position: relative;
}
.od-table-wrap tbody tr:last-child { border-bottom:none; }
.od-table-wrap tbody tr:hover { background: rgba(59,130,246,.05); }
html[data-theme="light"] .od-table-wrap tbody tr:hover { background: var(--surface); }
.od-table-wrap tbody td { padding:.72rem 1rem; vertical-align:middle; }

.od-app-num {
  font-family:'DM Mono','Courier New',monospace;
  font-size:.79rem; font-weight:700;
  color:var(--navy-light); letter-spacing:.03em;
  transition: color .12s;
}
.od-app-num:hover { color:var(--info-light,#93C5FD); text-decoration:none; filter:none; }
.od-fullname { font-weight:600; color:var(--text); font-size:.84rem; }
.od-chip {
  display:inline-block; font-size:.69rem;
  background:rgba(255,255,255,.04); border:1px solid var(--border);
  padding:.14rem .5rem; border-radius:6px; color:var(--text-soft); white-space:nowrap;
}
html[data-theme="light"] .od-chip { background:var(--surface); }
.od-stage { font-size:.75rem; color:var(--muted); }
.od-date  { font-size:.74rem; color:var(--muted); white-space:nowrap; }

.od-edit-btn {
  display:inline-flex; align-items:center; justify-content:center;
  width:30px; height:30px; border-radius:8px;
  background:var(--surface); border:1px solid var(--border);
  color:var(--muted); font-size:.78rem; text-decoration:none;
  transition:all .15s cubic-bezier(.34,1.56,.64,1);
}
.od-edit-btn:hover {
  background:var(--navy-light); border-color:var(--navy-light);
  color:#fff; transform:scale(1.12); filter:none;
}

/* Empty state */
.od-empty { text-align:center; padding:2.75rem 1.5rem; }
.od-empty-icon {
  width:56px; height:56px; border-radius:50%;
  background:var(--info-bg); display:flex; align-items:center;
  justify-content:center; font-size:1.3rem; color:var(--info); margin:0 auto .85rem;
}
.od-empty h4 { font-size:.88rem; font-weight:600; color:var(--text); margin-bottom:.3rem; }
.od-empty p  { font-size:.78rem; color:var(--muted); line-height:1.65; max-width:240px; margin:0 auto .85rem; }

/* ── Sidebar (with spotlight) ───────────────────────────────── */
.od-sidebar { display:flex; flex-direction:column; gap:1rem; }

/* Quick actions */
.od-actions { padding:.75rem .9rem; display:flex; flex-direction:column; gap:.4rem; }
.od-qa {
  display:flex; align-items:center; gap:.7rem;
  padding:.68rem .88rem; border-radius:var(--radius);
  border:1px solid var(--border); background:var(--surface);
  text-decoration:none; transition:all .18s cubic-bezier(.34,1.56,.64,1);
  color:var(--text-soft); font-size:.82rem; font-weight:500; position:relative; overflow:hidden;
}
.od-qa::before {
  content:''; position:absolute; inset:0;
  background:radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59,130,246,0.15) 0%, transparent 70%);
  opacity:0; transition:opacity .3s ease;
  pointer-events:none;
}
.od-qa:hover::before { opacity:1; }
.od-qa:hover {
  border-color:var(--border-mid); color:var(--text);
  transform:translateX(3px) scale(1.01); filter:none; text-decoration:none;
}
.od-qa-ico {
  width:30px; height:30px; border-radius:8px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:.76rem;
  transition: transform .18s cubic-bezier(.34,1.56,.64,1);
}
.od-qa:hover .od-qa-ico { transform: scale(1.15); }
.od-qa.p .od-qa-ico { background:rgba(59,130,246,.14); color:#60A5FA; }
.od-qa.g .od-qa-ico { background:rgba(200,145,26,.14);  color:#E8B040; }
.od-qa.m .od-qa-ico { background:var(--surface-alt);    color:var(--muted); }
.od-qa-arr { margin-left:auto; font-size:.66rem; color:var(--muted); transition:transform .18s; }
.od-qa:hover .od-qa-arr { transform:translateX(3px); color:var(--text-soft); }

/* Donut chart */
.od-donut-body {
  padding:1.1rem 1.2rem; display:flex; align-items:center; gap:1rem;
}
.od-legend { flex:1; display:flex; flex-direction:column; gap:.52rem; }
.od-leg-row { display:flex; align-items:center; justify-content:space-between; }
.od-leg-l   { display:flex; align-items:center; gap:.42rem; }
.od-leg-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.od-leg-lbl { font-size:.73rem; color:var(--text-soft); }
.od-leg-val { font-size:.73rem; font-weight:800; color:var(--text); }

/* Progress bars */
.od-bars-body { padding:.9rem 1.15rem; display:flex; flex-direction:column; gap:.68rem; }
.od-bar-row {}
.od-bar-meta { display:flex; justify-content:space-between; margin-bottom:.28rem; }
.od-bar-meta span:first-child { font-size:.72rem; color:var(--text-soft); }
.od-bar-meta span:last-child  { font-size:.7rem;  font-weight:800; color:var(--text); }
.od-bar-track { height:5px; background:var(--surface); border-radius:999px; overflow:hidden; }
.od-bar-fill  {
  height:100%; border-radius:999px; width:0;
  animation: bar-grow .9s cubic-bezier(.34,1,.64,1) both;
}
@keyframes bar-grow { to { width: var(--target-w) } }

/* Activity feed */
.od-feed { padding:.25rem 0; }
.od-feed-item {
  display:flex; align-items:flex-start; gap:.75rem;
  padding:.68rem 1.2rem; border-bottom:1px solid var(--border); transition:background .12s;
}
.od-feed-item:last-child { border-bottom:none; }
.od-feed-item:hover { background:rgba(59,130,246,.04); }
html[data-theme="light"] .od-feed-item:hover { background:var(--surface); }
.od-feed-dot {
  width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px;
}
.od-feed-text { font-size:.78rem; color:var(--text-soft); line-height:1.5; }
.od-feed-text strong { color:var(--text); font-weight:600; }
.od-feed-time { font-size:.68rem; color:var(--muted); margin-top:.15rem; }
</style>

<div class="od-hero od-animate">
  <div class="od-hero-mesh"></div>
  <div class="od-hero-grid"></div>
  <div class="od-hero-inner">
    <div class="od-hero-left">
      <div class="od-hero-icon"><i class="fa fa-user-shield"></i></div>
      <div>
        <div class="od-hero-eyebrow">Passport Officer</div>
        <div class="od-hero-name"><?= e($_SESSION['user_name']) ?></div>
        <div class="od-hero-meta">
          <span class="od-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="od-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <?php if ($thisWeek > 0): ?>
          <span class="od-hero-meta-chip" style="color:rgba(255,255,255,.55);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-fire" style="color:#F59E0B;"></i> <?= $thisWeek ?> this week
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="od-hero-right">
      <?php if ($todayApps > 0): ?>
      <div class="od-today-pill">
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
  <div class="od-card od-animate od-animate-d3 hover-card" data-spotlight>
    <div class="od-card-hd">
      <div class="od-card-title">
        <i class="fa fa-clock-rotate-left"></i> Recent Applications
        <span class="od-card-badge"><?= count($recent) ?></span>
      </div>
      <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-ghost btn-sm">
        View all <i class="fa fa-arrow-right"></i>
      </a>
    </div>
    <div class="od-table-wrap">
      <table>
        <thead>
          <tr>
            <th>App Number</th><th>Applicant</th><th>Type</th>
            <th>Stage</th><th>Status</th><th>Date</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="7">
            <div class="od-empty">
              <div class="od-empty-icon"><i class="fa fa-inbox"></i></div>
              <h4>No applications yet</h4>
              <p>Captured applications will appear here for quick access and management.</p>
              <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> Capture First Application
              </a>
            </div>
           </td></tr>
        <?php else: foreach ($recent as $idx => $a): ?>
          <tr style="animation:fadeUp .4s cubic-bezier(.22,1,.36,1) <?= $idx * 0.04 ?>s both">
            <td><a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="od-app-num"><?= e($a['application_number']) ?></a></td>
            <td><span class="od-fullname"><?= e($a['full_name']) ?></span></td>
            <td><span class="od-chip"><?= e($a['passport_type']) ?></span></td>
            <td><span class="od-stage"><?= e($a['current_stage']) ?></span></td>
            <td><?= statusBadge($a['status']) ?></td>
            <td><span class="od-date"><?= e($a['application_date']) ?></span></td>
            <td><a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="od-edit-btn" title="Manage"><i class="fa fa-pen-to-square"></i></a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="od-sidebar">

    <!-- Quick actions -->
    <div class="od-card od-animate od-animate-d4 hover-card" data-spotlight>
      <div class="od-card-hd">
        <div class="od-card-title"><i class="fa fa-bolt"></i> Quick Actions</div>
      </div>
      <div class="od-actions">
        <a href="<?= APP_URL ?>/officer/new_application.php" class="od-qa p hover-card" data-spotlight>
          <div class="od-qa-ico"><i class="fa fa-file-circle-plus"></i></div>
          <span>Capture Application</span>
          <i class="fa fa-chevron-right od-qa-arr"></i>
        </a>
        <a href="<?= APP_URL ?>/officer/releases.php" class="od-qa g hover-card" data-spotlight>
          <div class="od-qa-ico"><i class="fa fa-box-open"></i></div>
          <span>Release Passport</span>
          <i class="fa fa-chevron-right od-qa-arr"></i>
        </a>
        <a href="<?= APP_URL ?>/officer/applications.php" class="od-qa m hover-card" data-spotlight>
          <div class="od-qa-ico"><i class="fa fa-list"></i></div>
          <span>All Applications</span>
          <i class="fa fa-chevron-right od-qa-arr"></i>
        </a>
      </div>
    </div>

    <!-- Donut -->
    <div class="od-card od-animate od-animate-d5 hover-card" data-spotlight>
      <div class="od-card-hd">
        <div class="od-card-title"><i class="fa fa-chart-pie"></i> Status Breakdown</div>
      </div>
      <div class="od-donut-body">
        <canvas id="od-donut" width="84" height="84" style="flex-shrink:0;"></canvas>
        <div class="od-legend">
          <?php $segs=[['Pending',$myPending,'#F59E0B'],['In Progress',$myProgress,'#60A5FA'],['Ready',$readyColl,'#2DD4BF'],['Completed',$completed,'#34D399']];
          foreach ($segs as [$l,$v,$c]): ?>
          <div class="od-leg-row">
            <div class="od-leg-l">
              <div class="od-leg-dot" style="background:<?= $c ?>;"></div>
              <span class="od-leg-lbl"><?= $l ?></span>
            </div>
            <span class="od-leg-val"><?= $v ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Completion bars -->
    <div class="od-card od-animate od-animate-d6 hover-card" data-spotlight>
      <div class="od-card-hd">
        <div class="od-card-title"><i class="fa fa-chart-simple"></i> Workload</div>
        <span style="font-size:.8rem;font-weight:800;color:var(--success);"><?= $doneRatio ?>%</span>
      </div>
      <div class="od-bars-body">
        <?php
        $bd=[
          ['Pending',    $myPending,  'linear-gradient(90deg,#92400E,#F59E0B)'],
          ['In Progress',$myProgress, 'linear-gradient(90deg,#1E3A8A,#60A5FA)'],
          ['Ready',      $readyColl,  'linear-gradient(90deg,#0D9488,#2DD4BF)'],
          ['Completed',  $completed,  'linear-gradient(90deg,#065F46,#34D399)'],
        ];
        foreach($bd as [$l,$v,$g]):
          $pct=round($v/$total*100);
        ?>
        <div class="od-bar-row">
          <div class="od-bar-meta"><span><?= $l ?></span><span><?= $v ?></span></div>
          <div class="od-bar-track">
            <div class="od-bar-fill" style="--target-w:<?= $pct ?>%;background:<?= $g ?>;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div><!-- /od-main -->

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
    
    card.addEventListener('mousemove', function(e) {
      const rect = this.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;
      const deltaX = (e.clientX - centerX) / (rect.width / 2);
      const deltaY = (e.clientY - centerY) / (rect.height / 2);
      
      targetX = -deltaY * 4;
      targetY = deltaX * 4;
      
      requestAnimationFrame(() => {
        tiltX += (targetX - tiltX) * 0.1;
        tiltY += (targetY - tiltY) * 0.1;
        this.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateY(-4px)`;
      });
    });
    
    card.addEventListener('mouseleave', function() {
      targetX = 0;
      targetY = 0;
      requestAnimationFrame(() => {
        this.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px)';
      });
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
    const c = document.getElementById(id); if (!c) return;
    const ctx = c.getContext('2d');
    const W = c.width, H = c.height;
    const max = Math.max(...data, 1);
    const pts = data.map((v,i)=>({
      x: 1 + (i/(data.length-1))*(W-2),
      y: H - 3 - (v/max)*(H-7)
    }));
    ctx.clearRect(0,0,W,H);

    function smooth(p) {
      ctx.beginPath(); ctx.moveTo(p[0].x, p[0].y);
      for (let i=1;i<p.length-1;i++) {
        const mx=(p[i].x+p[i+1].x)/2, my=(p[i].y+p[i+1].y)/2;
        ctx.quadraticCurveTo(p[i].x,p[i].y,mx,my);
      }
      ctx.lineTo(p[p.length-1].x,p[p.length-1].y);
    }

    const g=ctx.createLinearGradient(0,0,0,H);
    g.addColorStop(0,color+'60'); g.addColorStop(1,color+'00');
    ctx.save();
    smooth(pts);
    ctx.lineTo(pts[pts.length-1].x,H); ctx.lineTo(pts[0].x,H); ctx.closePath();
    ctx.fillStyle=g; ctx.fill(); ctx.restore();

    smooth(pts);
    ctx.strokeStyle=color; ctx.lineWidth=1.6; ctx.lineJoin='round'; ctx.stroke();

    const lp=pts[pts.length-1];
    ctx.beginPath(); ctx.arc(lp.x,lp.y,2.5,0,Math.PI*2);
    ctx.fillStyle=color; ctx.fill();
    ctx.beginPath(); ctx.arc(lp.x,lp.y,4.5,0,Math.PI*2);
    ctx.fillStyle=color+'33'; ctx.fill();
  }

  spark('sp0', raw,                                              '#60A5FA');
  spark('sp1', raw.map(v=>Math.max(0,v-Math.round(v*.6))),      '#F59E0B');
  spark('sp2', raw.map(v=>Math.round(v*.55)),                   '#2DD4BF');
  spark('sp3', raw.map(v=>Math.round(v*.2)),                    '#34D399');
  spark('sp4', raw.map((v,i)=>i<3?Math.round(v*.1):Math.round(v*.5)), '#34D399');

  /* ── Donut ── */
  const dc=document.getElementById('od-donut');
  if (dc) {
    const ctx=dc.getContext('2d');
    const segs=[
      {v:<?= $myPending ?>,  c:'#F59E0B'},
      {v:<?= $myProgress ?>, c:'#60A5FA'},
      {v:<?= $readyColl ?>,  c:'#2DD4BF'},
      {v:<?= $completed ?>,  c:'#34D399'},
    ];
    const tot=segs.reduce((s,x)=>s+x.v,0)||1;
    const cx=42,cy=42,OR=37,IR=24;
    let a=-Math.PI/2;

    if(segs.every(s=>!s.v)){
      ctx.beginPath(); ctx.arc(cx,cy,OR,0,Math.PI*2);
      ctx.arc(cx,cy,IR,Math.PI*2,0,true); ctx.closePath();
      ctx.fillStyle=isDark?'#1C2333':'#EEF1F8'; ctx.fill();
    } else {
      segs.forEach(seg=>{
        if(!seg.v) return;
        const sw=(seg.v/tot)*Math.PI*2;
        ctx.beginPath();
        ctx.arc(cx,cy,OR,a,a+sw); ctx.arc(cx,cy,IR,a+sw,a,true);
        ctx.closePath(); ctx.fillStyle=seg.c; ctx.fill();
        a+=sw+0.03;
      });
    }
    ctx.fillStyle=isDark?'#E2E8F4':'#1A2238';
    ctx.font='800 14px system-ui'; ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(<?= $myApps ?>,cx,cy-1);
    ctx.font='500 9px system-ui';
    ctx.fillStyle=isDark?'#667085':'#6B7898';
    ctx.fillText('total',cx,cy+9);
  }

  /* ── Animated number counters ── */
  document.querySelectorAll('.od-stat-num[data-target]').forEach(el=>{
    const target=+el.dataset.target;
    if(!target) return;
    let current=0;
    const step=Math.ceil(target/30);
    const t=setInterval(()=>{
      current=Math.min(current+step,target);
      el.textContent=current;
      if(current>=target) clearInterval(t);
    },30);
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>