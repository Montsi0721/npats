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

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-gauge"></i> Admin Dashboard</h1>
    <p class="page-subtitle"><?= date('l, d F Y') ?></p>
  </div>
  <div class="btn-group">
    <a href="<?= APP_URL ?>/admin/reports.php"      class="btn btn-outline btn-sm"><i class="fa fa-chart-bar"></i> Reports</a>
    <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline btn-sm"><i class="fa fa-list"></i> Applications</a>
    <a href="<?= APP_URL ?>/admin/users.php"        class="btn btn-ghost   btn-sm"><i class="fa fa-users"></i> All Users</a>
  </div>
</div>

<!-- ── Quick-action cards ─────────────────────────────────── -->
<div class="quick-actions-grid">

  <button class="quick-action-card" data-modal-open="modalAddOfficer">
    <div class="qa-icon" style="background:var(--info-bg);color:var(--info);">
      <i class="fa fa-id-badge"></i>
    </div>
    <div class="qa-body">
      <div class="qa-title">Add Passport Officer</div>
      <div class="qa-sub">Create an officer account with application management access</div>
    </div>
    <i class="fa fa-plus qa-arrow"></i>
  </button>

  <button class="quick-action-card" data-modal-open="modalAddAdmin">
    <div class="qa-icon" style="background:var(--warning-bg);color:var(--gold);">
      <i class="fa fa-user-shield"></i>
    </div>
    <div class="qa-body">
      <div class="qa-title">Add Administrator</div>
      <div class="qa-sub">Create an admin account with full system privileges</div>
    </div>
    <i class="fa fa-plus qa-arrow"></i>
  </button>

</div>

<style>
.quick-actions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1.75rem;
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
  transition: box-shadow .15s, transform .15s, border-color .15s;
}
.quick-action-card:hover {
  box-shadow: var(--shadow);
  transform: translateY(-2px);
  border-color: var(--border-mid);
}
.qa-icon {
  width: 46px; height: 46px;
  border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.qa-body   { flex: 1; }
.qa-title  { font-size: .95rem; font-weight: 600; color: var(--text); }
.qa-sub    { font-size: .76rem; color: var(--muted); margin-top: .15rem; }
.qa-arrow  { color: var(--muted); font-size: .82rem; flex-shrink: 0; }
</style>

<!-- ── Stats ──────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-icon"><i class="fa fa-file-lines"></i></div>
    <div><div class="stat-num"><?= $totalApps ?></div><div class="stat-label">Total Applications</div></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-icon"><i class="fa fa-clock"></i></div>
    <div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
  </div>
  <div class="stat-card teal">
    <div class="stat-icon"><i class="fa fa-rotate"></i></div>
    <div><div class="stat-num"><?= $inProgress ?></div><div class="stat-label">In Progress</div></div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="fa fa-circle-check"></i></div>
    <div><div class="stat-num"><?= $completed ?></div><div class="stat-label">Completed</div></div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon"><i class="fa fa-circle-xmark"></i></div>
    <div><div class="stat-num"><?= $rejected ?></div><div class="stat-label">Rejected</div></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-icon"><i class="fa fa-box-open"></i></div>
    <div><div class="stat-num"><?= $readyColl ?></div><div class="stat-label">Ready for Collection</div></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon"><i class="fa fa-id-badge"></i></div>
    <div><div class="stat-num"><?= $totalOfficers ?></div><div class="stat-label">Active Officers</div></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-icon"><i class="fa fa-user-shield"></i></div>
    <div><div class="stat-num"><?= $totalAdmins ?></div><div class="stat-label">Administrators</div></div>
  </div>
</div>

<!-- ── Main content grid ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.2rem;" class="dash-main">

  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-clock-rotate-left"></i> Recent Applications</span>
      <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-ghost btn-sm">
        View all <i class="fa fa-arrow-right"></i>
      </a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>App Number</th><th>Applicant</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($recent)): ?>
        <tr class="no-data"><td colspan="5"><i class="fa fa-inbox"></i> No applications yet.</td></tr>
        <?php else: foreach ($recent as $r): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $r['id'] ?>"
                 style="font-weight:500;color:var(--navy-light);"><?= e($r['application_number']) ?></a></td>
          <td><strong><?= e($r['full_name']) ?></strong></td>
          <td><span style="font-size:.78rem;background:var(--surface);padding:.14rem .48rem;border-radius:4px;">
              <?= e($r['passport_type']) ?></span></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td style="font-size:.78rem;color:var(--muted);"><?= e($r['application_date']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:1rem;">
      <div class="card-header"><span class="card-title"><i class="fa fa-chart-pie"></i> By Type</span></div>
      <?php if (empty($typeStats)): ?>
        <p class="text-muted" style="font-size:.85rem;">No data yet.</p>
      <?php else: foreach ($typeStats as $ts): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:.5rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--text-soft);"><?= e($ts['passport_type']) ?></span>
          <strong><?= $ts['cnt'] ?></strong>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa fa-layer-group"></i> By Stage</span></div>
      <?php if (empty($stageStats)): ?>
        <p class="text-muted" style="font-size:.85rem;">No data yet.</p>
      <?php else: foreach ($stageStats as $st): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:.45rem 0;border-bottom:1px solid var(--border);font-size:.78rem;">
          <span style="color:var(--text-soft);flex:1;padding-right:.5rem;"><?= e($st['current_stage']) ?></span>
          <strong><?= $st['cnt'] ?></strong>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<style>.dash-main{grid-template-columns:2fr 1fr;}@media(max-width:768px){.dash-main{grid-template-columns:1fr;}}</style>

<!-- ══ MODAL: Add Officer ════════════════════════════════════ -->
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

<!-- ══ MODAL: Add Administrator ══════════════════════════════ -->
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
