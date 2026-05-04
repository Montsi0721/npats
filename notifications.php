<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

// ── Role-aware dashboard URL ─────────────────────────────────
$dashUrl = match($_SESSION['user_role'] ?? '') {
    'admin'     => APP_URL . '/admin/dashboard.php',
    'officer'   => APP_URL . '/officer/dashboard.php',
    default     => APP_URL . '/applicant/dashboard.php',
};

// Mark all as read
$db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);

$notifs = $db->prepare('SELECT n.*, pa.application_number FROM notifications n
    LEFT JOIN passport_applications pa ON pa.id = n.application_id
    WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT 100');
$notifs->execute([$uid]);
$rows = $notifs->fetchAll();

$unreadCount = count(array_filter($rows, fn($n) => !$n['is_read']));

$pageTitle = 'Notifications';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-bell"></i></div>
      <div>
        <div class="hero-eyebrow">Stay Updated</div>
        <div class="hero-name">Notifications</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-bell"></i> All updates
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= $dashUrl ?>" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= count($rows) ?></div>
    <div class="label">Total Notifications</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color:#60A5FA;"><?= $unreadCount ?></div>
    <div class="label">Unread</div>
  </div>
</div>

<!-- Notifications Card -->
<div class="card animate animate-d2 hover-card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa fa-bell"></i> Notification Center
      <span class="card-badge <?= $unreadCount > 0 ? 'unread' : '' ?>"><?= count($rows) ?> messages</span>
    </div>
    <?php if (!empty($rows)): ?>
    <button class="mark-read-btn" id="markReadBtn">
      <i class="fa fa-check-double"></i> Mark all as read
    </button>
    <?php endif; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fa fa-bell-slash"></i></div>
      <h3>No notifications yet</h3>
      <p>When you receive updates about your applications, they'll appear here.</p>
    </div>
  <?php else: ?>
    <ul class="notif-list">
    <?php foreach ($rows as $idx => $n):
      // Determine icon type from message content
      $msg = strtolower($n['message'] ?? '');
      if (str_contains($msg, 'approved') || str_contains($msg, 'released') || str_contains($msg, 'completed')) {
        $iconType = 'success';
        $icon     = 'fa-check-circle';
      } elseif (str_contains($msg, 'rejected') || str_contains($msg, 'failed')) {
        $iconType = 'danger';
        $icon     = 'fa-exclamation-circle';
      } elseif (str_contains($msg, 'ready') || str_contains($msg, 'waiting')) {
        $iconType = 'warning';
        $icon     = 'fa-clock';
      } else {
        $iconType = 'info';
        $icon     = 'fa-info-circle';
      }
    ?>
      <li class="notif-item" style="animation-delay:<?= $idx * 0.03 ?>s">
        <?php if (!$n['is_read']): ?>
          <div class="notif-dot"></div>
        <?php endif; ?>

        <div class="notif-icon <?= $iconType ?>">
          <i class="fa <?= $icon ?>"></i>
        </div>

        <div class="notif-content">
          <div class="notif-message"><?= e($n['message']) ?></div>

          <?php if ($n['application_number']): ?>
            <a href="<?= APP_URL ?>/applicant/track.php?app_num=<?= urlencode($n['application_number']) ?>"
               class="notif-app-link">
              <i class="fa fa-passport"></i>
              Track Application #<?= e($n['application_number']) ?>
              <i class="fa fa-external-link-alt"></i>
            </a>
          <?php endif; ?>

          <div class="notif-time">
            <i class="fa fa-clock"></i>
            <?= date('M d, Y · h:i A', strtotime($n['created_at'])) ?>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<script>
// Spotlight
(function() {
  document.querySelectorAll('.hover-card').forEach(el => {
    const s = document.createElement('div');
    s.className = 'sc-spotlight';
    el.appendChild(s);
    el.addEventListener('mousemove', function(e) {
      const r = this.getBoundingClientRect();
      const x = ((e.clientX - r.left) / r.width) * 100;
      const y = ((e.clientY - r.top)  / r.height) * 100;
      this.style.setProperty('--x', x + '%');
      this.style.setProperty('--y', y + '%');
      s.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(59,130,246,.12) 0%, transparent 60%)`;
      s.style.opacity = '1';
    });
    el.addEventListener('mouseleave', () => s.style.opacity = '0');
  });
})();

// Mark all as read
const markBtn = document.getElementById('markReadBtn');
if (markBtn) {
  markBtn.addEventListener('click', () => {
    fetch('<?= APP_URL ?>/ajax/mark_notifications_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.querySelectorAll('.notif-dot').forEach(d => d.remove());
        markBtn.innerHTML = '<i class="fa fa-check"></i> All marked!';
        markBtn.style.opacity = '.7';
        setTimeout(() => {
          markBtn.innerHTML = '<i class="fa fa-check-double"></i> Mark all as read';
          markBtn.style.opacity = '1';
        }, 2000);
      }
    })
    .catch(err => console.error('Error:', err));
  });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>