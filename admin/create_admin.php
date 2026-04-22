<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => trim($_POST['email']     ?? ''),
        'phone'     => trim($_POST['phone']     ?? ''),
        'password'  => $_POST['password']         ?? '',
        'confirm'   => $_POST['confirm_password'] ?? '',
    ];

    if (!$data['full_name'])
        $errors['full_name'] = 'Full name is required.';
    if (!$data['email'])
        $errors['email'] = 'Email address is required.';
    elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Enter a valid email address.';
    if (strlen($data['password']) < 8)
        $errors['password'] = 'Password must be at least 8 characters.';
    if ($data['password'] !== $data['confirm'])
        $errors['confirm'] = 'Passwords do not match.';

    if (empty($errors)) {
        try {
            $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
            $db->prepare('INSERT INTO users (full_name, email, password, role, phone) VALUES (?,?,?,?,?)')
               ->execute([$data['full_name'], $data['email'], $hashed, 'admin', $data['phone']]);
            logActivity($_SESSION['user_id'], 'CREATE_ADMIN', "Created admin: {$data['email']}");
            flash('success', "Administrator '{$data['full_name']}' created with full system access.");
            redirect(APP_URL . '/admin/users.php');
        } catch (PDOException) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }
}

$pageTitle = 'Create Administrator';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   CREATE ADMIN PAGE — Premium Edition (Dashboard Matching)
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

.cadmin-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.cadmin-animate-d1 { animation-delay:.06s }
.cadmin-animate-d2 { animation-delay:.12s }
.cadmin-animate-d3 { animation-delay:.18s }
.cadmin-animate-d4 { animation-delay:.24s }

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
.cadmin-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .cadmin-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.cadmin-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.cadmin-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.cadmin-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.cadmin-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.cadmin-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.cadmin-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.cadmin-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.cadmin-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.cadmin-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.cadmin-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.cadmin-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.cadmin-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.cadmin-hero-meta-chip i { font-size: .62rem; }

.cadmin-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Main Grid ────────────────────────────────────────────── */
.cadmin-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
}
@media(max-width: 768px) { .cadmin-grid { grid-template-columns: 1fr; } }

/* ── Form Card ────────────────────────────────────────────── */
.cadmin-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.cadmin-card::before {
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
.cadmin-card:hover::before { opacity: 1; }
.cadmin-card:hover {
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

/* Form Elements */
.form-section-title {
  font-size: .78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--gold-light);
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.form-section-title i {
  font-size: .7rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}
.form-group.full { grid-column: 1 / -1; }
.form-group label {
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: .4rem;
  display: block;
}
.form-group label i {
  margin-right: 0.25rem;
}

.input-wrap {
  position: relative;
}
.input-wrap.has-prefix {
  position: relative;
}
.input-wrap.has-prefix .prefix {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 0.85rem;
  z-index: 1;
}
.input-wrap.has-prefix input {
  padding-left: 2.2rem;
}
.input-wrap input,
.input-wrap select {
  width: 100%;
  padding: .75rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .9rem;
  transition: all .2s;
}
.input-wrap input:focus,
.input-wrap select:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.input-wrap input.error {
  border-color: var(--danger);
}
.eye-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 0;
  font-size: 1rem;
}
.eye-btn:hover {
  color: var(--text);
}

.field-error {
  font-size: .7rem;
  color: var(--danger);
  display: none;
  margin-top: 0.3rem;
}
.field-error.show {
  display: block;
}

/* Password Strength */
.strength-bar {
  height: 4px;
  border-radius: 4px;
  background: var(--border);
  overflow: hidden;
  margin: 0.5rem 0 0.3rem;
}
.strength-fill {
  height: 100%;
  width: 0%;
  border-radius: 4px;
  transition: width .3s, background .3s;
}
.strength-text {
  font-size: .7rem;
  margin-top: 0.2rem;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
}

/* Alert Boxes */
.alert {
  padding: 1rem 1.25rem;
  border-radius: var(--radius);
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.alert i {
  font-size: 1.1rem;
  flex-shrink: 0;
}
.alert-warning {
  background: rgba(245,158,11,.1);
  color: #F59E0B;
}
.alert-warning div {
  color: var(--text-soft);
}
.alert-error {
  background: rgba(239,68,68,.1);
  color: #F87171;
}
.alert-success {
  background: rgba(52,211,153,.1);
  color: #34D399;
}

/* Sidebar Cards */
.sidebar-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  margin-bottom: 1rem;
}
.sidebar-card:last-child {
  margin-bottom: 0;
}
.sidebar-card .card-header {
  padding: 1rem 1.25rem;
}
.sidebar-card .card-body {
  padding: 1rem 1.25rem;
}
.permission-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}
.permission-list li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: .82rem;
  color: var(--text-soft);
}
.permission-list li i.fa-check {
  color: #34D399;
  width: 18px;
}
.permission-list li i.fa-circle-info {
  color: var(--info);
}

/* Divider */
.divider-light {
  border: none;
  border-top: 1px solid var(--border);
  margin: 1rem 0;
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

/* Responsive */
@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .cadmin-hero-inner {
    flex-direction: column;
    text-align: center;
  }
  .form-actions {
    flex-direction: column;
  }
  .form-actions .btn {
    width: 100%;
    text-align: center;
  }
}
</style>

<!-- Hero Section -->
<div class="cadmin-hero cadmin-animate">
  <div class="cadmin-hero-mesh"></div>
  <div class="cadmin-hero-grid"></div>
  <div class="cadmin-hero-inner">
    <div class="cadmin-hero-left">
      <div class="cadmin-hero-icon"><i class="fa fa-user-shield"></i></div>
      <div>
        <div class="cadmin-hero-eyebrow">System Administrator</div>
        <div class="cadmin-hero-name">Create Administrator</div>
        <div class="cadmin-hero-meta">
          <span class="cadmin-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="cadmin-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="cadmin-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-user-plus"></i> Grant Full Access
          </span>
        </div>
      </div>
    </div>
    <div class="cadmin-hero-right no-print">
      <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Back to Users
      </a>
    </div>
  </div>
</div>

<!-- Warning Banner -->
<div class="alert alert-warning cadmin-animate cadmin-animate-d1">
  <i class="fa fa-triangle-exclamation"></i>
  <div>
    <strong>Full Privilege Account</strong> — This account will have the same access as your own:
    user management, all applications system-wide, reports, and the activity audit log.
    Only create this for trusted staff members.
  </div>
</div>

<!-- Main Grid -->
<div class="cadmin-grid">

  <!-- Form Card -->
  <div class="cadmin-card cadmin-animate cadmin-animate-d2 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-user-shield"></i> Administrator Account Details</span>
    </div>
    <div class="card-body">
      <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <i class="fa fa-circle-exclamation"></i>
        <div>Please fix the errors highlighted below.</div>
      </div>
      <?php endif; ?>

      <form method="POST" id="createAdminForm">
        <div class="form-section-title">
          <i class="fa fa-id-card"></i> Personal Information
        </div>
        <div class="form-grid">
          <div class="form-group full">
            <label><i class="fa fa-user"></i> Full Name *</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-user"></i>
              <input type="text" id="full_name" name="full_name" required
                     value="<?= e($data['full_name'] ?? '') ?>"
                     placeholder="e.g., Lesedi Thamae"
                     class="<?= isset($errors['full_name']) ? 'error' : '' ?>">
            </div>
            <?php if (isset($errors['full_name'])): ?>
              <span class="field-error show"><?= e($errors['full_name']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label><i class="fa fa-envelope"></i> Email Address *</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-envelope"></i>
              <input type="email" id="email" name="email" required
                     value="<?= e($data['email'] ?? '') ?>"
                     placeholder="admin@npats.gov.ls"
                     class="<?= isset($errors['email']) ? 'error' : '' ?>">
            </div>
            <div class="field-error <?= isset($errors['email']) ? 'show' : '' ?>"><?= e($errors['email'] ?? '') ?></div>
          </div>

          <div class="form-group">
            <label><i class="fa fa-phone"></i> Phone Number</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-phone"></i>
              <input type="tel" id="phone" name="phone"
                     value="<?= e($data['phone'] ?? '') ?>"
                     placeholder="+266 ...">
            </div>
          </div>
        </div>

        <div class="divider-light"></div>

        <div class="form-section-title">
          <i class="fa fa-lock"></i> Security Credentials
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label><i class="fa fa-key"></i> Password * <span style="color:var(--muted);font-weight:400;">(min. 8 chars)</span></label>
            <div class="input-wrap">
              <input type="password" id="password" name="password" required
                     placeholder="Choose a strong password"
                     style="padding-right: 2.5rem;"
                     class="<?= isset($errors['password']) ? 'error' : '' ?>">
              <button type="button" class="eye-btn" data-target="password">
                <i class="fa fa-eye"></i>
              </button>
            </div>
            <div class="field-error <?= isset($errors['password']) ? 'show' : '' ?>"><?= e($errors['password'] ?? '') ?></div>
            <div class="strength-bar">
              <div class="strength-fill" id="strengthFill"></div>
            </div>
            <div class="strength-text" id="strengthText"></div>
          </div>

          <div class="form-group">
            <label><i class="fa fa-check-circle"></i> Confirm Password *</label>
            <div class="input-wrap">
              <input type="password" id="confirm_password" name="confirm_password" required
                     placeholder="Repeat password"
                     style="padding-right: 2.5rem;"
                     class="<?= isset($errors['confirm']) ? 'error' : '' ?>">
              <button type="button" class="eye-btn" data-target="confirm_password">
                <i class="fa fa-eye"></i>
              </button>
            </div>
            <div class="field-error <?= isset($errors['confirm']) ? 'show' : '' ?>"><?= e($errors['confirm'] ?? '') ?></div>
          </div>
        </div>

        <div class="form-actions">
          <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">
            <i class="fa fa-times"></i> Cancel
          </a>
          <button type="submit" class="btn btn-gold"
            onclick="return confirm('Grant full administrator access to this person? This action cannot be undone.')">
            <i class="fa fa-user-shield"></i> Create Administrator Account
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Sidebar -->
  <div>
    <!-- Admin Permissions Card -->
    <div class="sidebar-card accent cadmin-animate cadmin-animate-d3 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-shield-alt"></i> Admin Permissions</span>
      </div>
      <div class="card-body">
        <ul class="permission-list">
          <li><i class="fa fa-check"></i> Manage all users (create, deactivate, reset)</li>
          <li><i class="fa fa-check"></i> View all passport applications</li>
          <li><i class="fa fa-check"></i> Generate system-wide reports</li>
          <li><i class="fa fa-check"></i> Access the full activity audit log</li>
          <li><i class="fa fa-check"></i> Create other administrators and officers</li>
          <li><i class="fa fa-check"></i> Modify system settings and configurations</li>
        </ul>
      </div>
    </div>

    <!-- Login Credentials Info Card -->
    <div class="sidebar-card cadmin-animate cadmin-animate-d4 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-circle-info"></i> Login Credentials</span>
      </div>
      <div class="card-body">
        <p style="font-size: .82rem; color: var(--text-soft); line-height: 1.6; margin-bottom: 0.8rem;">
          The new administrator will log in using their <strong>email address</strong> or
          <strong>phone number</strong> and the password you set here.
        </p>
        <div class="alert" style="background: rgba(59,130,246,.08); margin: 0; padding: 0.6rem 0.8rem;">
          <i class="fa fa-lock" style="color: #60A5FA;"></i>
          <div style="font-size: .75rem; color: var(--text-soft);">
            Share credentials securely. The admin can reset their own password from the Users page after login.
          </div>
        </div>
      </div>
    </div>

    <!-- Security Tip Card -->
    <div class="sidebar-card cadmin-animate cadmin-animate-d4 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-shield-virus"></i> Security Tip</span>
      </div>
      <div class="card-body">
        <p style="font-size: .78rem; color: var(--text-soft); line-height: 1.5;">
          <i class="fa fa-check-circle" style="color: #34D399; margin-right: 0.4rem;"></i>
          Use a strong, unique password<br>
          <i class="fa fa-check-circle" style="color: #34D399; margin-right: 0.4rem;"></i>
          Enable two-factor authentication if available<br>
          <i class="fa fa-check-circle" style="color: #34D399; margin-right: 0.4rem;"></i>
          Review admin access regularly
        </p>
      </div>
    </div>
  </div>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card');
  
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

// Password strength meter
const pwdInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const strengthFill = document.getElementById('strengthFill');
const strengthText = document.getElementById('strengthText');

function checkPasswordStrength(password) {
  let score = 0;
  if (password.length >= 8) score += 25;
  if (password.length >= 12) score += 15;
  if (/[A-Z]/.test(password)) score += 20;
  if (/[0-9]/.test(password)) score += 20;
  if (/[^A-Za-z0-9]/.test(password)) score += 20;
  return Math.min(score, 100);
}

function updateStrengthMeter() {
  if (!pwdInput) return;
  const strength = checkPasswordStrength(pwdInput.value);
  const colors = ['#F87171', '#FBBF24', '#60A5FA', '#34D399'];
  const labels = ['Too short', 'Weak', 'Fair', 'Strong'];
  const index = Math.min(Math.floor(strength / 25), 3);
  
  strengthFill.style.width = strength + '%';
  strengthFill.style.background = colors[index];
  strengthText.textContent = pwdInput.value ? labels[index] : '';
  strengthText.style.color = colors[index];
}

if (pwdInput) {
  pwdInput.addEventListener('input', updateStrengthMeter);
}

// Password confirmation validation
function validateConfirmPassword() {
  if (!confirmInput || !pwdInput) return;
  if (confirmInput.value && confirmInput.value !== pwdInput.value) {
    confirmInput.classList.add('error');
    const errorSpan = confirmInput.closest('.form-group')?.querySelector('.field-error');
    if (errorSpan) {
      errorSpan.textContent = 'Passwords do not match';
      errorSpan.classList.add('show');
    }
  } else {
    confirmInput.classList.remove('error');
    const errorSpan = confirmInput.closest('.form-group')?.querySelector('.field-error');
    if (errorSpan) {
      errorSpan.classList.remove('show');
    }
  }
}

if (confirmInput) {
  confirmInput.addEventListener('input', validateConfirmPassword);
}

// Toggle password visibility
document.querySelectorAll('.eye-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const targetId = btn.getAttribute('data-target');
    const input = targetId ? document.getElementById(targetId) : btn.closest('.input-wrap')?.querySelector('input');
    if (input) {
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
    }
  });
});

// Initial validation on page load
if (pwdInput && pwdInput.value) updateStrengthMeter();
if (confirmInput && confirmInput.value) validateConfirmPassword();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>