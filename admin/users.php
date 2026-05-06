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
        $role     = $_POST['role'] ?? 'officer';
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

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-users"></i></div>
      <div>
        <div class="hero-eyebrow">System Administrator</div>
        <div class="hero-name">Manage Users</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-gear"></i> User Management
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
      <button class="btn btn-primary" data-modal-open="addUserModal">
        <i class="fa fa-plus"></i> Add User
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
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= $totalUsers ?></div>
    <div class="label">Total Users</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= $activeUsers ?></div>
    <div class="label">Active</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= $officerCount ?></div>
    <div class="label">Officers</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #F87171;"><?= $adminCount ?></div>
    <div class="label">Admins</div>
  </div>
</div>

<!-- Users Card -->
<div class="card animate animate-d2 hover-card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa fa-id-card"></i> System Users
      <span class="card-badge"><?= $totalUsers ?> registered</span>
    </div>
  </div>
  
  <div class="search-wrap" style="padding: 20px;">
    <i class="fa fa-search" style="margin-left: 20px"></i>
    <input type="text" id="tableSearch" placeholder="Search by name, username, email, or role...">
  </div>
  
  <div class="table-wrapper">
    <table class="table" id="usersTable">
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
              <i class="fa fa-<?= $u['role'] === 'admin' ? 'shield' : ($u['role'] === 'officer' ? 'id-badge' : 'user') ?>"></i>
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
      <span class="modal-title"><i class="fa fa-plus"></i> Add New User</span>
      <button class="modal-close" data-modal-close="addUserModal">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="alert alert-info" style="background: rgba(59,130,246,.1); border-left: 3px solid #60A5FA; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.8rem;">
          <i class="fa fa-info-circle"></i> Create a new system user with specific role permissions.
        </div>
        <div class="form-grid">
          <div class="input-wrap form-group full">
            <label><i class="fa fa-user"></i> Full Name *</label>
            <input type="text" name="full_name" required placeholder="e.g., Rets'elisitsoe Mohale">
          </div>
          <div class="input-wrap form-group">
            <label><i class="fa fa-at"></i> Username *</label>
            <input type="text" name="username" required placeholder="Unique username">
          </div>
          <div class="input-wrap form-group">
            <label><i class="fa fa-envelope"></i> Email *</label>
            <input type="email" name="email" required placeholder="user@example.com">
          </div>
          <div class="input-wrap form-group">
            <label><i class="fa fa-phone"></i> Phone</label>
            <input type="text" name="phone" placeholder="+266 ...">
          </div>
          <div class="input-wrap form-group">
            <label><i class="fa fa-tag"></i> Role *</label>
            <!-- Custom Select for Role -->
            <div class="custom-select" id="roleSelect">
              <div class="custom-select-trigger">
                <span class="selected-text">Officer</span>
                <i class="fa fa-chevron-down arrow"></i>
              </div>
              <div class="custom-select-dropdown">
                <div class="custom-select-option" data-value="officer">
                  <i class="fa fa-id-badge"></i> Officer
                </div>
                <div class="custom-select-option" data-value="admin">
                  <i class="fa fa-shield"></i> Administrator
                </div>
              </div>
              <input type="hidden" name="role" id="roleInput" value="applicant">
            </div>
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
  const spotlightElements = document.querySelectorAll('.hover-card, .card');
  
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

// Consolidated Custom Select Initialization
function initCustomSelect(selectId, hiddenId) {
  const container = document.getElementById(selectId);
  if (!container) return;
  
  const trigger = container.querySelector('.custom-select-trigger');
  const dropdown = container.querySelector('.custom-select-dropdown');
  const hiddenInput = document.getElementById(hiddenId);
  const selectedSpan = trigger.querySelector('.selected-text');
  const options = container.querySelectorAll('.custom-select-option');
  const currentValue = hiddenInput.value;
  
  // Set initial selected text and class
  options.forEach(opt => {
    if (opt.dataset.value === currentValue) {
      opt.classList.add('selected');
      // Extract text content without the icon
      const optText = opt.childNodes[opt.childNodes.length - 1].textContent.trim();
      selectedSpan.textContent = optText;
    }
  });
  
  // Toggle dropdown
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    // Close other dropdowns
    document.querySelectorAll('.custom-select-dropdown.show').forEach(d => {
      if (d !== dropdown) d.classList.remove('show');
    });
    document.querySelectorAll('.custom-select-trigger.open').forEach(t => {
      if (t !== trigger) t.classList.remove('open');
    });
    dropdown.classList.toggle('show');
    trigger.classList.toggle('open');
  });
  
  // Option selection
  options.forEach(opt => {
    opt.addEventListener('click', (e) => {
      e.stopPropagation();
      const value = opt.dataset.value;
      hiddenInput.value = value;
      // Extract text without icon
      const optText = opt.childNodes[opt.childNodes.length - 1].textContent.trim();
      selectedSpan.textContent = optText;
      
      options.forEach(o => o.classList.remove('selected'));
      opt.classList.add('selected');
      
      dropdown.classList.remove('show');
      trigger.classList.remove('open');
    });
  });
  
  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!container.contains(e.target)) {
      dropdown.classList.remove('show');
      trigger.classList.remove('open');
    }
  });
}

// Initialize Role custom select
initCustomSelect('roleSelect', 'roleInput');

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