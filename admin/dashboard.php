<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

// ── Quick-create user from dashboard ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_create'])) {
    $name  = trim($_POST['full_name'] ?? '');
    $uname = trim($_POST['username']  ?? '');
    $email = trim($_POST['email']     ?? '');
    $phone = trim($_POST['phone']     ?? '');
    $role  = $_POST['role']            ?? '';
    $pass  = $_POST['password']        ?? '';

    if (!in_array($role, ['admin','officer'])) {
        flash('error', 'Invalid role.');
    } elseif (!$name || !$uname || !$email || !$pass) {
        flash('error', 'All fields are required.');
    } elseif (strlen($pass) < 8) {
        flash('error', 'Password must be at least 8 characters.');
    } else {
        try {
            $hashed = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare('INSERT INTO users (full_name,username,email,password,role,phone) VALUES (?,?,?,?,?,?)')
               ->execute([$name, $uname, $email, $hashed, $role, $phone]);
            logActivity($_SESSION['user_id'], 'ADD_USER', 'Created '.ucfirst($role).": $uname");
            flash('success', ucfirst($role)." account '$uname' created successfully.");
        } catch (PDOException) {
            flash('error', 'Username or email already exists.');
        }
    }
    redirect(APP_URL . '/admin/dashboard.php');
}

// ── Stats ────────────────────────────────────────────────────
$totalApps     = (int)$db->query('SELECT COUNT(*) FROM passport_applications')->fetchColumn();
$pending       = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='Pending'")->fetchColumn();
$inProgress    = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='In-Progress'")->fetchColumn();
$completed     = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='Completed'")->fetchColumn();
$rejected      = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='Rejected'")->fetchColumn();
$readyColl     = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE current_stage='Ready for Collection'")->fetchColumn();
$totalOfficers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='officer' AND is_active=1")->fetchColumn();
$totalAdmins   = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'  AND is_active=1")->fetchColumn();

$recent    = $db->query('SELECT pa.*, u.full_name AS officer_name FROM passport_applications pa
    JOIN users u ON u.id=pa.officer_id ORDER BY pa.created_at DESC LIMIT 7')->fetchAll();
$typeStats = $db->query('SELECT passport_type, COUNT(*) cnt FROM passport_applications GROUP BY passport_type')->fetchAll();
$stageStats= $db->query('SELECT current_stage, COUNT(*) cnt FROM passport_applications GROUP BY current_stage ORDER BY cnt DESC')->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   ADMIN DASHBOARD — Premium Edition (Matches Officer Dashboard)
   ───────────────────────────────────────────────────────────── */

/* ── Entry animations ──────────────────────────────────────── */
@keyframes fadeUp   { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn   { from{opacity:0} to{opacity:1} }
@keyframes scaleIn  { from{opacity:0;transform:scale(.94)} to{opacity:1;transform:scale(1)} }
@keyframes shimmer  {
  0%  { background-position: -200% center }
  100%{ background-position:  200% center }
}
@keyframes pulse-ring {
  0%  { box-shadow: 0 0 0 0 rgba(52,211,153,.5) }
  70% { box-shadow: 0 0 0 8px rgba(52,211,153,0) }
  100%{ box-shadow: 0 0 0 0 rgba(52,211,153,0) }
}
@keyframes float {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-6px) }
}
@keyframes modalSlide {
  from { opacity: 0; transform: translateY(30px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.admin-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.admin-animate-d1 { animation-delay:.06s }
.admin-animate-d2 { animation-delay:.12s }
.admin-animate-d3 { animation-delay:.18s }
.admin-animate-d4 { animation-delay:.24s }
.admin-animate-d5 { animation-delay:.30s }

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
.admin-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .admin-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.admin-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.admin-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.admin-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.admin-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.admin-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.admin-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.admin-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.admin-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.admin-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.admin-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.admin-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.admin-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.admin-hero-meta-chip i { font-size: .62rem; }

/* ── Quick Actions Grid ──────────────────────────────────── */
.quick-actions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
@media(max-width:640px){ .quick-actions-grid { grid-template-columns: 1fr; } }

.quick-action-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.2rem 1.4rem;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  overflow: hidden;
}
.quick-action-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.1) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}
.quick-action-card:hover::before { opacity: 1; }
.quick-action-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}
.qa-icon {
  width: 48px; height: 48px;
  border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.qa-body { flex: 1; }
.qa-title { font-size: .95rem; font-weight: 700; color: var(--text); }
.qa-sub { font-size: .72rem; color: var(--muted); margin-top: .2rem; }
.qa-arrow { color: var(--muted); font-size: .85rem; flex-shrink: 0; transition: transform .2s; }
.quick-action-card:hover .qa-arrow { transform: translateX(3px); color: var(--gold-light); }

/* ── Stats Grid (Premium) ────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}
@media(max-width:1100px){ .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width:640px) { .stats-grid { grid-template-columns: 1fr; } }

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
.stat-card.red .stat-icon { background: rgba(239,68,68,.12); color: #F87171; }

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

/* ── Main Content Grid ───────────────────────────────────── */
.dash-main {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.2rem;
}
@media(max-width:768px){ .dash-main { grid-template-columns: 1fr; } }

/* ── Cards (Premium) ─────────────────────────────────────── */
.admin-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.admin-card::before {
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
.admin-card:hover::before { opacity: 1; }
.admin-card:hover {
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

/* Tables */
.table-wrapper {
  overflow-x: auto;
}
.table-wrapper table {
  width: 100%;
  border-collapse: collapse;
}
.table-wrapper thead {
  background: #07101E;
  border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .table-wrapper thead { background: var(--surface); }
.table-wrapper thead th {
  padding: 0.9rem 1.2rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
}
.table-wrapper tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.table-wrapper tbody tr:hover {
  background: rgba(59,130,246,.04);
}
.table-wrapper tbody td {
  padding: 0.9rem 1.2rem;
  vertical-align: middle;
  font-size: .85rem;
}
.table-wrapper .no-data td {
  text-align: center;
  padding: 2rem;
  color: var(--muted);
}
.table-wrapper .no-data i {
  font-size: 1.2rem;
  margin-right: 0.5rem;
}

/* List items for stats */
.stat-list-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.7rem 1.2rem;
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.stat-list-item:hover {
  background: rgba(59,130,246,.04);
}
.stat-list-item:last-child {
  border-bottom: none;
}
.stat-list-label {
  font-size: .8rem;
  color: var(--text-soft);
}
.stat-list-value {
  font-weight: 700;
  color: var(--text);
  font-size: .9rem;
}

/* ── Premium Modal ───────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  visibility: hidden;
  opacity: 0;
  transition: all 0.25s ease;
}
.modal-overlay.active {
  visibility: visible;
  opacity: 1;
}
.modal {
  background: var(--bg-alt);
  border-radius: var(--radius-lg);
  max-width: 550px;
  width: 90%;
  border: 1px solid var(--border);
  box-shadow: 0 25px 50px rgba(0,0,0,.5);
  animation: modalSlide 0.3s cubic-bezier(.34,1.56,.64,1);
}
.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.modal-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.modal-title i {
  color: var(--gold);
}
.modal-close {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  cursor: pointer;
  color: var(--muted);
  transition: all .2s;
}
.modal-close:hover {
  background: var(--danger);
  border-color: var(--danger);
  color: #fff;
  transform: scale(1.05);
}
.modal-body {
  padding: 1.5rem;
  max-height: 60vh;
  overflow-y: auto;
}
.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 0.8rem;
  background: var(--surface);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}

/* Form elements in modal */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.form-group.full { grid-column: 1 / -1; }
.form-group label {
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: .3rem;
  display: block;
}
.form-group input {
  width: 100%;
  padding: .7rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .85rem;
  transition: all .2s;
}
.form-group input:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.input-wrap {
  position: relative;
}
.input-wrap .eye-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 0 5px;
}
.alert {
  padding: 0.8rem 1rem;
  border-radius: var(--radius);
  font-size: .8rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.alert-info {
  background: rgba(59,130,246,.1);
  border: 1px solid rgba(59,130,246,.2);
  color: #60A5FA;
}
.alert-warning {
  background: rgba(245,158,11,.1);
  border: 1px solid rgba(245,158,11,.2);
  color: #F59E0B;
}

/* Buttons */
.btn-gold {
  background: linear-gradient(135deg, #B45309, #F59E0B);
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: #fff;
  font-weight: 600;
  transition: all .2s;
}
.btn-gold:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245,158,11,.3);
}
</style>

<!-- Hero Section (matching officer dashboard) -->
<div class="admin-hero admin-animate">
  <div class="admin-hero-mesh"></div>
  <div class="admin-hero-grid"></div>
  <div class="admin-hero-inner">
    <div class="admin-hero-left">
      <div class="admin-hero-icon"><i class="fa fa-gauge"></i></div>
      <div>
        <div class="admin-hero-eyebrow">System Administrator</div>
        <div class="admin-hero-name">Admin Dashboard</div>
        <div class="admin-hero-meta">
          <span class="admin-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="admin-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="admin-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-chart-line"></i> System Overview
          </span>
        </div>
      </div>
    </div>
    <div class="admin-hero-right">
      <div class="btn-group">
        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline btn-sm">
          <i class="fa fa-chart-bar"></i> Reports
        </a>
        <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline btn-sm">
          <i class="fa fa-list"></i> Applications
        </a>
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline btn-sm">
          <i class="fa fa-users"></i> Users
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Quick Action Cards -->
<div class="quick-actions-grid admin-animate admin-animate-d1">
  <button class="quick-action-card" data-modal-open="modalAddOfficer" onclick="window.location.href = './create_officer.php'">
    <div class="qa-icon" style="background:rgba(59,130,246,.12);color:#60A5FA;">
      <i class="fa fa-id-badge"></i>
    </div>
    <div class="qa-body">
      <div class="qa-title">Add Passport Officer</div>
      <div class="qa-sub">Create an officer account with application management access</div>
    </div>
    <i class="fa fa-plus qa-arrow"></i>
  </button>

  <button class="quick-action-card" data-modal-open="modalAddAdmin" onclick="window.location.href = './create_admin.php'">
    <div class="qa-icon" style="background:rgba(245,158,11,.12);color:#F59E0B;">
      <i class="fa fa-user-shield"></i>
    </div>
    <div class="qa-body">
      <div class="qa-title">Add Administrator</div>
      <div class="qa-sub">Create an admin account with full system privileges</div>
    </div>
    <i class="fa fa-plus qa-arrow"></i>
  </button>
</div>

<!-- Stats Grid -->
<div class="stats-grid admin-animate admin-animate-d2">
  <div class="stat-card blue hover-card">
    <div class="stat-icon"><i class="fa fa-file-lines"></i></div>
    <div><div class="stat-num"><?= $totalApps ?></div><div class="stat-label">Total Applications</div></div>
  </div>
  <div class="stat-card gold hover-card">
    <div class="stat-icon"><i class="fa fa-clock"></i></div>
    <div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
  </div>
  <div class="stat-card teal hover-card">
    <div class="stat-icon"><i class="fa fa-rotate"></i></div>
    <div><div class="stat-num"><?= $inProgress ?></div><div class="stat-label">In Progress</div></div>
  </div>
  <div class="stat-card green hover-card">
    <div class="stat-icon"><i class="fa fa-circle-check"></i></div>
    <div><div class="stat-num"><?= $completed ?></div><div class="stat-label">Completed</div></div>
  </div>
  <div class="stat-card red hover-card">
    <div class="stat-icon"><i class="fa fa-circle-xmark"></i></div>
    <div><div class="stat-num"><?= $rejected ?></div><div class="stat-label">Rejected</div></div>
  </div>
  <div class="stat-card gold hover-card">
    <div class="stat-icon"><i class="fa fa-box-open"></i></div>
    <div><div class="stat-num"><?= $readyColl ?></div><div class="stat-label">Ready for Collection</div></div>
  </div>
  <div class="stat-card blue hover-card">
    <div class="stat-icon"><i class="fa fa-id-badge"></i></div>
    <div><div class="stat-num"><?= $totalOfficers ?></div><div class="stat-label">Active Officers</div></div>
  </div>
  <div class="stat-card gold hover-card">
    <div class="stat-icon"><i class="fa fa-user-shield"></i></div>
    <div><div class="stat-num"><?= $totalAdmins ?></div><div class="stat-label">Administrators</div></div>
  </div>
</div>

<!-- Main Content Grid -->
<div class="dash-main">
  <!-- Recent Applications Card -->
  <div class="admin-card admin-animate admin-animate-d3 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-clock-rotate-left"></i> Recent Applications</span>
      <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-ghost btn-sm">
        View all <i class="fa fa-arrow-right"></i>
      </a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>App Number</th><th>Applicant</th><th>Type</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr class="no-data"><td colspan="5"><i class="fa fa-inbox"></i> No applications yet.</td></tr>
        <?php else: foreach ($recent as $r): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $r['id'] ?>"
                   style="font-weight:500;color:var(--navy-light);"><?= e($r['application_number']) ?></a></td>
            <td><strong><?= e($r['full_name']) ?></strong></td>
            <td><span class="app-type-badge"><?= e($r['passport_type']) ?></span></td>
            <td><?= statusBadge($r['status']) ?></td>
            <td style="font-size:.78rem;color:var(--muted);"><?= e($r['application_date']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Column Stats -->
  <div>
    <!-- By Type Card -->
    <div class="admin-card admin-animate admin-animate-d4 hover-card" style="margin-bottom:1rem;">
      <div class="card-header"><span class="card-title"><i class="fa fa-chart-pie"></i> By Passport Type</span></div>
      <?php if (empty($typeStats)): ?>
        <div class="stat-list-item"><span class="stat-list-label">No data yet</span></div>
      <?php else: foreach ($typeStats as $ts): ?>
        <div class="stat-list-item">
          <span class="stat-list-label"><?= e($ts['passport_type']) ?></span>
          <span class="stat-list-value"><?= $ts['cnt'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- By Stage Card -->
    <div class="admin-card admin-animate admin-animate-d5 hover-card">
      <div class="card-header"><span class="card-title"><i class="fa fa-layer-group"></i> By Processing Stage</span></div>
      <?php if (empty($stageStats)): ?>
        <div class="stat-list-item"><span class="stat-list-label">No data yet</span></div>
      <?php else: foreach ($stageStats as $st): ?>
        <div class="stat-list-item">
          <span class="stat-list-label"><?= e($st['current_stage']) ?></span>
          <span class="stat-list-value"><?= $st['cnt'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- MODAL: Add Officer -->
<div class="modal-overlay" id="modalAddOfficer">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-id-badge"></i> Add Passport Officer</span>
      <button class="modal-close" data-modal-close="modalAddOfficer">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="quick_create" value="1">
      <input type="hidden" name="role" value="officer">
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="fa fa-info-circle"></i>
          Officers can capture applications, update processing stages, and release passports.
        </div>
        <div class="form-grid" style="margin-top:1rem;">
          <div class="form-group full">
            <label>Full Name *</label>
            <input type="text" name="full_name" required placeholder="e.g. Jane Mokoena">
          </div>
          <div class="form-group">
            <label>Username *</label>
            <input type="text" name="username" required placeholder="Unique username">
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" required placeholder="officer@npats.gov.ls">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" placeholder="+266 …">
          </div>
          <div class="form-group full">
            <label>Password * <span style="color:var(--muted);font-weight:400;">(min. 8 chars)</span></label>
            <div class="input-wrap">
              <input type="password" name="password" required placeholder="Strong password" style="padding-right:2.8rem;">
              <button type="button" class="eye-btn"><i class="fa fa-eye"></i></button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modalAddOfficer">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-user-plus"></i> Create Officer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Add Administrator -->
<div class="modal-overlay" id="modalAddAdmin">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-user-shield"></i> Add Administrator</span>
      <button class="modal-close" data-modal-close="modalAddAdmin">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="quick_create" value="1">
      <input type="hidden" name="role" value="admin">
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="fa fa-triangle-exclamation"></i>
          Admins have <strong>full system access</strong> — user management, all applications, reports and audit logs.
        </div>
        <div class="form-grid" style="margin-top:1rem;">
          <div class="form-group full">
            <label>Full Name *</label>
            <input type="text" name="full_name" required placeholder="e.g. Lesedi Thamae">
          </div>
          <div class="form-group">
            <label>Username *</label>
            <input type="text" name="username" required placeholder="Unique username">
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" required placeholder="admin@npats.gov.ls">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" placeholder="+266 …">
          </div>
          <div class="form-group full">
            <label>Password * <span style="color:var(--muted);font-weight:400;">(min. 8 chars)</span></label>
            <div class="input-wrap">
              <input type="password" name="password" required placeholder="Strong password" style="padding-right:2.8rem;">
              <button type="button" class="eye-btn"><i class="fa fa-eye"></i></button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modalAddAdmin">Cancel</button>
        <button type="submit" class="btn btn-gold"
          onclick="return confirm('Grant full administrator privileges to this user?')">
          <i class="fa fa-user-shield"></i> Create Administrator
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .quick-action-card, .stat-card');
  
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

// Modal functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Open modals from buttons
document.querySelectorAll('[data-modal-open]').forEach(btn => {
  btn.addEventListener('click', () => {
    const modalId = btn.getAttribute('data-modal-open');
    openModal(modalId);
  });
});

// Close modals with close buttons
document.querySelectorAll('[data-modal-close]').forEach(btn => {
  btn.addEventListener('click', () => {
    const modalId = btn.getAttribute('data-modal-close');
    closeModal(modalId);
  });
});

// Close modal when clicking overlay
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  });
});

// Escape key to close modal
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(modal => {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    });
  }
});

// Toggle password visibility
document.querySelectorAll('.eye-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.closest('.input-wrap').querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  });
});
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
.btn-group {
  display: flex;
  gap: 0.5rem;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>