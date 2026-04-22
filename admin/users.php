<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

// ── Handle POST actions ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name     = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $role     = $_POST['role'] ?? 'applicant';
        $pass     = $_POST['password'] ?? '';

        if ($name && $username && $email && $pass) {
            $hashed = password_hash($pass, PASSWORD_BCRYPT);
            try {
                $stmt = $db->prepare('INSERT INTO users (full_name,username,email,password,role,phone) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$name,$username,$email,$hashed,$role,$phone]);
                logActivity($_SESSION['user_id'], 'ADD_USER', "Added user: $username ($role)");
                flash('success', "User '$username' created successfully.");
            } catch (PDOException $ex) {
                flash('error', 'Username or email already exists.');
            }
        } else {
            flash('error', 'Please fill all required fields.');
        }
    }

    if ($action === 'toggle_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid !== (int)$_SESSION['user_id']) {
            $db->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=?')->execute([$uid]);
            logActivity($_SESSION['user_id'], 'TOGGLE_USER', "Toggled user ID: $uid");
            flash('success', 'User status updated.');
        } else {
            flash('error', 'You cannot deactivate your own account.');
        }
    }

    if ($action === 'reset_password') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $pass = $_POST['new_password'] ?? '';
        if ($pass) {
            $hashed = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hashed,$uid]);
            logActivity($_SESSION['user_id'], 'RESET_PASSWORD', "Reset password for user ID: $uid");
            flash('success', 'Password reset successfully.');
        }
    }

    redirect(APP_URL . '/admin/users.php');
}

$users = $db->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   USERS MANAGEMENT PAGE — Premium Edition (Dashboard Matching)
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
@keyframes modalSlide {
  from { opacity: 0; transform: translateY(30px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.user-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.user-animate-d1 { animation-delay:.06s }
.user-animate-d2 { animation-delay:.12s }
.user-animate-d3 { animation-delay:.18s }
.user-animate-d4 { animation-delay:.24s }

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
.user-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .user-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.user-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.user-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.user-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.user-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.user-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.user-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.user-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.user-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.user-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.user-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.user-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.user-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.user-hero-meta-chip i { font-size: .62rem; }

.user-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Stats Row (mini stats) ───────────────────────────────── */
.user-stats-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.user-stat-mini {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0.75rem 1.25rem;
  flex: 1;
  min-width: 100px;
  transition: all 0.2s;
  cursor: pointer;
}
.user-stat-mini:hover {
  transform: translateY(-2px);
  border-color: rgba(59,130,246,0.3);
  background: rgba(59,130,246,.02);
}
.user-stat-mini .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--gold-light);
  line-height: 1;
}
.user-stat-mini .label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── User Card ───────────────────────────────────────────── */
.user-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.user-card::before {
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
.user-card:hover::before { opacity: 1; }
.user-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.user-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.user-card-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.user-card-title i {
  color: var(--gold);
  font-size: .78rem;
}
.user-card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}

/* Search Bar */
.user-search-bar {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 0.8rem;
  background: var(--surface);
}
.user-search-bar i {
  color: var(--muted);
  font-size: 0.9rem;
}
.user-search-bar input {
  flex: 1;
  background: transparent;
  border: none;
  padding: 0.5rem 0;
  color: var(--text);
  font-size: 0.85rem;
  outline: none;
}
.user-search-bar input::placeholder {
  color: var(--muted);
}

/* Premium Table */
.user-table-wrapper {
  overflow-x: auto;
}
.user-table {
  width: 100%;
  border-collapse: collapse;
}
.user-table thead {
  background: #07101E;
  border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .user-table thead { background: var(--surface); }
.user-table thead th {
  padding: 1rem 1.2rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  white-space: nowrap;
}
.user-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
  animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both;
}
.user-table tbody tr:hover {
  background: rgba(59,130,246,.04);
}
.user-table tbody td {
  padding: 1rem 1.2rem;
  vertical-align: middle;
  font-size: .85rem;
}

/* Role Badges */
.role-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .7rem;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 600;
}
.role-admin {
  background: rgba(239,68,68,.12);
  color: #F87171;
  border: 1px solid rgba(239,68,68,.2);
}
.role-officer {
  background: rgba(59,130,246,.12);
  color: #60A5FA;
  border: 1px solid rgba(59,130,246,.2);
}
.role-applicant {
  background: rgba(52,211,153,.12);
  color: #34D399;
  border: 1px solid rgba(52,211,153,.2);
}

/* Status Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .7rem;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 600;
}
.status-active {
  background: rgba(52,211,153,.12);
  color: #34D399;
  border: 1px solid rgba(52,211,153,.2);
}
.status-inactive {
  background: rgba(239,68,68,.12);
  color: #F87171;
  border: 1px solid rgba(239,68,68,.2);
}

/* Action Buttons */
.action-btn-group {
  display: flex;
  gap: 0.4rem;
}
.action-icon-btn {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--muted);
  transition: all .2s cubic-bezier(.34,1.56,.64,1);
  cursor: pointer;
  text-decoration: none;
}
.action-icon-btn:hover {
  transform: scale(1.1);
}
.action-icon-btn.deactivate:hover {
  border-color: #F87171;
  color: #F87171;
}
.action-icon-btn.activate:hover {
  border-color: #34D399;
  color: #34D399;
}
.action-icon-btn.reset:hover {
  border-color: #60A5FA;
  color: #60A5FA;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
}
.empty-icon {
  width: 70px;
  height: 70px;
  margin: 0 auto 1rem;
  background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(200,145,26,.05));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
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
}

/* Premium Modal */
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
  max-width: 500px;
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

/* Form Elements */
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
.form-group input,
.form-group select {
  width: 100%;
  padding: .7rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .85rem;
  transition: all .2s;
}
.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}

/* Responsive */
@media (max-width: 768px) {
  .user-hero-inner { flex-direction: column; text-align: center; }
  .user-stats-row { flex-wrap: wrap; }
  .user-table thead th,
  .user-table tbody td { padding: 0.75rem; }
  .form-grid { grid-template-columns: 1fr; }
  .modal { width: 95%; margin: 1rem; }
}

/* Print Styles */
@media print {
  .no-print { display: none; }
  .user-card { break-inside: avoid; }
}
</style>

<!-- Hero Section -->
<div class="user-hero user-animate">
  <div class="user-hero-mesh"></div>
  <div class="user-hero-grid"></div>
  <div class="user-hero-inner">
    <div class="user-hero-left">
      <div class="user-hero-icon"><i class="fa fa-users"></i></div>
      <div>
        <div class="user-hero-eyebrow">System Administrator</div>
        <div class="user-hero-name">Manage Users</div>
        <div class="user-hero-meta">
          <span class="user-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="user-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="user-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-user-gear"></i> User Management
          </span>
        </div>
      </div>
    </div>
    <div class="user-hero-right">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
      <button class="btn btn-primary" data-modal-open="addUserModal">
        <i class="fa fa-user-plus"></i> Add User
      </button>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<?php
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['is_active'] == 1));
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$officerCount = count(array_filter($users, fn($u) => $u['role'] === 'officer'));
$applicantCount = count(array_filter($users, fn($u) => $u['role'] === 'applicant'));
?>
<div class="user-stats-row user-animate user-animate-d1">
  <div class="user-stat-mini hover-card">
    <div class="value"><?= $totalUsers ?></div>
    <div class="label">Total Users</div>
  </div>
  <div class="user-stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= $activeUsers ?></div>
    <div class="label">Active</div>
  </div>
  <div class="user-stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= $officerCount ?></div>
    <div class="label">Officers</div>
  </div>
  <div class="user-stat-mini hover-card">
    <div class="value" style="color: #F87171;"><?= $adminCount ?></div>
    <div class="label">Admins</div>
  </div>
</div>

<!-- Users Card -->
<div class="user-card user-animate user-animate-d2 hover-card">
  <div class="user-card-header">
    <div class="user-card-title">
      <i class="fa fa-id-card"></i> System Users
      <span class="user-card-badge"><?= $totalUsers ?> registered</span>
    </div>
  </div>
  
  <div class="user-search-bar">
    <i class="fa fa-search"></i>
    <input type="text" id="tableSearch" placeholder="Search by name, username, email, or role...">
  </div>
  
  <div class="user-table-wrapper">
    <table class="user-table" id="usersTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($users)): ?>
        <tr class="no-data">
          <td colspan="9">
            <div class="empty-state">
              <div class="empty-icon">
                <i class="fa fa-users-slash"></i>
              </div>
              <h3>No users found</h3>
              <p>Click "Add User" to create your first user account.</p>
            </div>
          </td>
        </tr>
      <?php else: foreach ($users as $i => $u): ?>
        <tr data-searchable="<?= strtolower(e($u['full_name'] . ' ' . $u['username'] . ' ' . $u['email'] . ' ' . $u['role'])) ?>">
          <td><?= $i+1 ?></td>
          <td><strong><?= e($u['full_name']) ?></strong></td>
          <td style="font-family: monospace;"><?= e($u['username']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone'] ?? '—') ?></td>
          <td>
            <span class="role-badge role-<?= e($u['role']) ?>">
              <i class="fa fa-<?= $u['role'] === 'admin' ? 'user-shield' : ($u['role'] === 'officer' ? 'id-badge' : 'user') ?>"></i>
              <?= ucfirst(e($u['role'])) ?>
            </span>
          </td>
          <td>
            <span class="status-badge status-<?= $u['is_active'] ? 'active' : 'inactive' ?>">
              <i class="fa fa-<?= $u['is_active'] ? 'check-circle' : 'ban' ?>"></i>
              <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td style="font-size: .75rem; color: var(--muted); white-space: nowrap;">
            <i class="fa fa-calendar"></i> <?= date('d M Y', strtotime($u['created_at'])) ?>
          </td>
          <td>
            <div class="action-btn-group">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="action-icon-btn <?= $u['is_active'] ? 'deactivate' : 'activate' ?>"
                  data-confirm="<?= $u['is_active'] ? 'Deactivate this user?' : 'Activate this user?' ?>"
                  title="<?= $u['is_active'] ? 'Deactivate User' : 'Activate User' ?>">
                  <i class="fa fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
                </button>
              </form>
              <button class="action-icon-btn reset" onclick='openResetModal(<?= $u['id'] ?>, "<?= addslashes(e($u['username'])) ?>")' title="Reset Password">
                <i class="fa fa-key"></i>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-user-plus"></i> Add New User</span>
      <button class="modal-close" data-modal-close="addUserModal">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="alert alert-info" style="background: rgba(59,130,246,.1); border-left: 3px solid #60A5FA; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.8rem;">
          <i class="fa fa-info-circle"></i> Create a new system user with specific role permissions.
        </div>
        <div class="form-grid">
          <div class="form-group full">
            <label><i class="fa fa-user"></i> Full Name *</label>
            <input type="text" name="full_name" required placeholder="e.g., John Doe">
          </div>
          <div class="form-group">
            <label><i class="fa fa-at"></i> Username *</label>
            <input type="text" name="username" required placeholder="Unique username">
          </div>
          <div class="form-group">
            <label><i class="fa fa-envelope"></i> Email *</label>
            <input type="email" name="email" required placeholder="user@example.com">
          </div>
          <div class="form-group">
            <label><i class="fa fa-phone"></i> Phone</label>
            <input type="text" name="phone" placeholder="+266 ...">
          </div>
          <div class="form-group">
            <label><i class="fa fa-tag"></i> Role *</label>
            <select name="role">
              <option value="applicant">Applicant</option>
              <option value="officer">Officer</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
          <div class="form-group full">
            <label><i class="fa fa-lock"></i> Password * <span style="color:var(--muted);font-weight:400;">(min. 6 chars)</span></label>
            <div class="input-wrap" style="position: relative;">
              <input type="password" name="password" required minlength="6" placeholder="Enter strong password" style="padding-right: 2.5rem;">
              <button type="button" class="eye-btn" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer;">
                <i class="fa fa-eye"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="addUserModal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPwdModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-key"></i> Reset Password</span>
      <button class="modal-close" data-modal-close="resetPwdModal">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="resetUserId">
      <div class="modal-body">
        <div class="alert alert-warning" style="background: rgba(245,158,11,.1); border-left: 3px solid #F59E0B; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.8rem;">
          <i class="fa fa-triangle-exclamation"></i> Resetting password for: <strong id="resetUserName"></strong>
        </div>
        <div class="form-group">
          <label><i class="fa fa-lock"></i> New Password *</label>
          <div class="input-wrap" style="position: relative;">
            <input type="password" name="new_password" required minlength="6" placeholder="Min. 6 characters" style="padding-right: 2.5rem;">
            <button type="button" class="eye-btn" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer;">
              <i class="fa fa-eye"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="resetPwdModal">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa fa-key"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .user-card');
  
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

// Table search functionality
function initTableSearch() {
  const searchInput = document.getElementById('tableSearch');
  if (!searchInput) return;
  
  searchInput.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('usersTable');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
      const searchableText = row.getAttribute('data-searchable') || '';
      if (searchTerm === '' || searchableText.includes(searchTerm)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });
}

// Confirm actions
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', (e) => {
    if (!confirm(btn.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });
});

function openResetModal(id, name) {
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetUserName').textContent = name;
  openModal('resetPwdModal');
}

// Initialize search on page load
document.addEventListener('DOMContentLoaded', initTableSearch);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>