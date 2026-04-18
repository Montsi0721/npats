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
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-users"></i> Manage Users</h1>
  <button class="btn btn-primary" data-modal-open="addUserModal"><i class="fa fa-user-plus"></i> Add User</button>
</div>

<div class="card">
  <div class="search-bar">
    <i class="fa fa-search" style="color:var(--muted);"></i>
    <input type="text" id="tableSearch" placeholder="Search users…">
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Full Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if (empty($users)): ?>
      <tr class="no-data"><td colspan="9">No users found.</td></tr>
      <?php else: foreach ($users as $i => $u): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><?= e($u['full_name']) ?></td>
        <td><?= e($u['username']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['phone'] ?? '—') ?></td>
        <td><span class="role-badge role-<?= e($u['role']) ?>"><?= ucfirst(e($u['role'])) ?></span></td>
        <td><?= $u['is_active'] ? '<span class="status-badge status-completed">Active</span>' : '<span class="status-badge status-rejected">Inactive</span>' ?></td>
        <td style="font-size:.8rem;"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
        <td>
          <div class="btn-group">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle_user">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                data-confirm="<?= $u['is_active'] ? 'Deactivate this user?' : 'Activate this user?' ?>">
                <i class="fa fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
              </button>
            </form>
            <button class="btn btn-sm btn-outline" onclick='openResetModal(<?= $u['id'] ?>, "<?= addslashes(e($u['username'])) ?>")'>
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
        <div class="form-grid">
          <div class="form-group full">
            <label>Full Name *</label>
            <input type="text" name="full_name" required>
          </div>
          <div class="form-group">
            <label>Username *</label>
            <input type="text" name="username" required>
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone">
          </div>
          <div class="form-group">
            <label>Role *</label>
            <select name="role">
              <option value="applicant">Applicant</option>
              <option value="officer">Officer</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group full">
            <label>Password *</label>
            <input type="password" name="password" required minlength="6">
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
        <p style="margin-bottom:.8rem;">Resetting password for: <strong id="resetUserName"></strong></p>
        <div class="form-group">
          <label>New Password *</label>
          <input type="password" name="new_password" required minlength="6" placeholder="Min. 6 characters">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="resetPwdModal">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa fa-key"></i> Reset</button>
      </div>
    </form>
  </div>
</div>

<script>
function openResetModal(id, name) {
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetUserName').textContent = name;
  openModal('resetPwdModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
