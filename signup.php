<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) redirect(APP_URL.'/'.$_SESSION['user_role'].'/dashboard.php');

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'username'  => trim($_POST['username']  ?? ''),
        'email'     => trim($_POST['email']     ?? ''),
        'phone'     => trim($_POST['phone']     ?? ''),
        'password'  => $_POST['password']        ?? '',
        'confirm'   => $_POST['confirm_password'] ?? '',
    ];

    if (!$data['full_name']) $errors['full_name'] = 'Full name is required.';
    if (!$data['username'])  $errors['username']  = 'Username is required.';
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $data['username']))
        $errors['username'] = 'Username must be 3–30 alphanumeric characters.';
    if (!$data['email'])
        $errors['email'] = 'Email is required.';
    elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Enter a valid email address.';
    if (strlen($data['password']) < 8)
        $errors['password'] = 'Password must be at least 8 characters.';
    if ($data['password'] !== $data['confirm'])
        $errors['confirm'] = 'Passwords do not match.';

    if (empty($errors)) {
        $db = getDB();
        $chk = $db->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $chk->execute([$data['username'], $data['email']]);
        if ($chk->fetch()) {
            $errors['username'] = 'Username or email is already registered.';
        } else {
            $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
            $ins = $db->prepare('INSERT INTO users (full_name,username,email,password,role,phone) VALUES (?,?,?,?,?,?)');
            $ins->execute([$data['full_name'], $data['username'], $data['email'], $hashed, 'applicant', $data['phone']]);
            $newId = (int)$db->lastInsertId();
            logActivity($newId, 'REGISTER', 'New applicant registration: '.$data['username']);

            session_regenerate_id(true);
            $_SESSION['user_id']    = $newId;
            $_SESSION['user_name']  = $data['full_name'];
            $_SESSION['user_role']  = 'applicant';
            $_SESSION['user_email'] = $data['email'];
            flash('success', 'Account created! Welcome to NPATS, '.$data['full_name'].'.');
            redirect(APP_URL.'/applicant/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — NPATS</title>
<script>(function(){var t=localStorage.getItem('npats_theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}else{document.documentElement.removeAttribute('data-theme');}})()</script>
<link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
<link rel="icon" type="image/svg+xml" href="assets/headerIcon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body style="display:block;">
<div class="auth-page">

  <!-- Left panel -->
  <div class="auth-panel">
    <div class="auth-panel-circles">
      <div class="auth-circle auth-circle-1"></div>
      <div class="auth-circle auth-circle-2"></div>
      <div class="auth-circle auth-circle-3"></div>
    </div>
    <div class="auth-panel-content">
      <div class="auth-crest"><i class="fa fa-passport"></i></div>
      <h1>Create Your NPATS Account</h1>
      <p class="auth-panel-desc">Join the National Passport Application Tracking System to apply for and track your passport applications online.</p>
      <div class="auth-panel-tag"><i class="fa fa-shield-halved"></i> Applicants only</div>

      <div class="auth-features">
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="fa fa-file-circle-plus"></i></div>
          <div class="auth-feat-text">
            <h4>Submit Applications</h4>
            <p>Visit any passport office — officers will link your application to your account.</p>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="fa fa-clock-rotate-left"></i></div>
          <div class="auth-feat-text">
            <h4>Track Every Step</h4>
            <p>Follow your passport from submission through to collection in real time.</p>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="fa fa-bell"></i></div>
          <div class="auth-feat-text">
            <h4>Stay Informed</h4>
            <p>Receive in-app notifications whenever your application moves forward.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right form panel -->
  <div class="auth-form-panel">
    <div class="auth-form-box">
      <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa fa-passport"></i></div>
        <span class="auth-brand-name">NPATS</span>
      </div>

      <h2>Create account</h2>
      <p class="auth-subtitle">Register as an applicant to track your passport.</p>

      <?php if (!empty($errors)): ?>
      <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i> Please correct the errors below.</div>
      <?php endif; ?>

      <form method="POST" action="">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:.8rem;">
          <div class="form-group">
            <label for="full_name">Full Name *</label>
            <input type="text" id="full_name" name="full_name" required
                   value="<?= e($data['full_name']??'') ?>" placeholder="Your full name"
                   class="<?= isset($errors['full_name'])?'error':'' ?>">
            <?php if (isset($errors['full_name'])): ?>
            <span class="field-error show"><?= e($errors['full_name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label for="username">Username *</label>
            <input type="text" id="username" name="username" required
                   value="<?= e($data['username']??'') ?>" placeholder="Letters, numbers, _"
                   class="<?= isset($errors['username'])?'error':'' ?>">
            <?php if (isset($errors['username'])): ?>
            <span class="field-error show"><?= e($errors['username']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:.8rem;">
          <label for="email">Email Address *</label>
          <div class="input-wrap has-prefix">
            <i class="prefix fa fa-envelope"></i>
            <input type="email" id="email" name="email" required
                   value="<?= e($data['email']??'') ?>" placeholder="you@example.com"
                   class="<?= isset($errors['email'])?'error':'' ?>">
          </div>
          <?php if (isset($errors['email'])): ?>
          <span class="field-error show"><?= e($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group" style="margin-bottom:.8rem;">
          <label for="phone">Phone <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
          <div class="input-wrap has-prefix">
            <i class="prefix fa fa-phone"></i>
            <input type="tel" id="phone" name="phone" value="<?= e($data['phone']??'') ?>" placeholder="+266 …">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1.1rem;">
          <div class="form-group">
            <label for="password">Password *</label>
            <div class="input-wrap">
              <input type="password" id="password" name="password" required
                     placeholder="Min. 8 characters"
                     class="<?= isset($errors['password'])?'error':'' ?>"
                     style="padding-right:2.8rem;">
              <button type="button" class="eye-btn"><i class="fa fa-eye"></i></button>
            </div>
            <?php if (isset($errors['password'])): ?>
            <span class="field-error show"><?= e($errors['password']) ?></span>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm Password *</label>
            <div class="input-wrap">
              <input type="password" id="confirm_password" name="confirm_password" required
                     placeholder="Repeat password"
                     class="<?= isset($errors['confirm'])?'error':'' ?>"
                     style="padding-right:2.8rem;">
              <button type="button" class="eye-btn"><i class="fa fa-eye"></i></button>
            </div>
            <?php if (isset($errors['confirm'])): ?>
            <span class="field-error show"><?= e($errors['confirm']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Password strength bar -->
        <div style="margin-bottom:1.1rem;">
          <div style="height:4px;border-radius:2px;background:var(--border);overflow:hidden;">
            <div id="strengthFill" style="height:100%;width:0%;border-radius:2px;transition:width .3s,background .3s;"></div>
          </div>
          <p id="strengthText" style="font-size:.72rem;color:var(--muted);margin-top:.3rem;"></p>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg">
          <i class="fa fa-user-plus"></i> Create Account
        </button>
      </form>

      <div class="auth-footer-link">
        Already have an account? <a href="<?= APP_URL ?>/index.php">Sign in</a>
      </div>
    </div>
  </div>

</div>
<script src="<?= APP_URL ?>/js/main.js"></script>
<script>
const pwdInput     = document.getElementById('password');
const strengthFill = document.getElementById('strengthFill');
const strengthText = document.getElementById('strengthText');
function calcStrength(pwd) {
  let s = 0;
  if (pwd.length >= 8)  s += 25;
  if (pwd.length >= 12) s += 15;
  if (/[A-Z]/.test(pwd)) s += 20;
  if (/[0-9]/.test(pwd)) s += 20;
  if (/[^A-Za-z0-9]/.test(pwd)) s += 20;
  return Math.min(s, 100);
}
if (pwdInput) {
  pwdInput.addEventListener('input', () => {
    const score = calcStrength(pwdInput.value);
    const cols  = ['#F87171','#FBBF24','#60A5FA','#34D399'];
    const labs  = ['Too short','Weak','Fair','Strong'];
    const idx   = Math.min(Math.floor(score / 25), 3);
    strengthFill.style.width      = score + '%';
    strengthFill.style.background = cols[idx];
    strengthText.textContent      = pwdInput.value ? labs[idx] : '';
    strengthText.style.color      = cols[idx];
  });
}
const confirmInput = document.getElementById('confirm_password');
if (confirmInput && pwdInput) {
  confirmInput.addEventListener('input', () => {
    confirmInput.classList.toggle('error', !!(confirmInput.value && confirmInput.value !== pwdInput.value));
  });
}
</script>
</body>
</html>
