<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) redirect(APP_URL.'/'.$_SESSION['user_role'].'/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        $error = 'Please enter both username and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            logActivity($user['id'], 'LOGIN', 'Login from '.($_SERVER['REMOTE_ADDR']??''));
            redirect(APP_URL.'/'.$user['role'].'/dashboard.php');
        } else {
            $error = 'Invalid credentials. Please try again.';
            logActivity(null, 'FAILED_LOGIN', 'Username: '.htmlspecialchars($username));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — NPATS</title>
<script>(function(){var t=localStorage.getItem('npats_theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}else{document.documentElement.removeAttribute('data-theme');}})()</script>
<link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/headerIcon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body style="display:block;">
<div class="auth-page">

  <!-- Left decorative panel --> 
  <div class="auth-panel">
    <div class="auth-panel-circles">
      <div class="auth-circle auth-circle-1"></div>
      <div class="auth-circle auth-circle-2"></div>
      <div class="auth-circle auth-circle-3"></div>
    </div>
    <div class="auth-panel-content">
      <div class="auth-crest"><i class="fa fa-passport"></i></div>
      <h1>National Passport Application Tracking System</h1>
      <p class="auth-panel-desc">Secure, transparent and efficient passport processing for the Ministry of Home Affairs.</p>
      <div class="auth-panel-tag"><i class="fa fa-shield-halved"></i> Official Government Portal</div>

      <div class="auth-features">
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="fa fa-lock"></i></div>
          <div class="auth-feat-text">
            <h4>Role-Based Security</h4>
            <p>Admins, officers and applicants each see only what they need.</p>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="fa fa-magnifying-glass"></i></div>
          <div class="auth-feat-text">
            <h4>Real-Time Tracking</h4>
            <p>Follow your application through every processing stage.</p>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="fa fa-bell"></i></div>
          <div class="auth-feat-text">
            <h4>Instant Notifications</h4>
            <p>Get notified the moment your passport status changes.</p>
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

      <h2>Welcome back</h2>
      <p class="auth-subtitle">Sign in to your account to continue.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group" style="margin-bottom:.9rem;">
          <label for="username">Username or Email</label>
          <div class="input-wrap has-prefix">
            <i class="prefix fa fa-user"></i>
            <input type="text" id="username" name="username" required autocomplete="username"
                   value="<?= e($_POST['username'] ?? '') ?>" placeholder="Enter username or email">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:1.1rem;">
          <label for="password">Password</label>
          <div class="input-wrap has-prefix">
            <i class="prefix fa fa-lock"></i>
            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password" style="padding-right:2.8rem;">
            <button type="button" class="eye-btn"><i class="fa fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg">
          <i class="fa fa-arrow-right-to-bracket"></i> Sign In
        </button>
      </form>

      <div class="auth-divider">or</div>

      <div class="auth-footer-link">
        Don't have an account? <a href="<?= APP_URL ?>/signup.php">Create one</a>
      </div>
    </div>
  </div>

</div>
<script src="<?= APP_URL ?>/js/main.js"></script>
</body>
</html>
