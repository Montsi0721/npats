<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$db   = getDB();
$uid  = $_SESSION['user_id'];

// Mark all as read
$db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);

$notifs = $db->prepare('SELECT n.*, pa.application_number FROM notifications n
    LEFT JOIN passport_applications pa ON pa.id = n.application_id
    WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT 100');
$notifs->execute([$uid]);
$rows = $notifs->fetchAll();

$pageTitle = 'Notifications';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-bell"></i> Notifications</h1>
</div>
<div class="card">
  <?php if (empty($rows)): ?>
  <p class="text-muted text-center" style="padding:2rem;"><i class="fa fa-bell-slash" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i> No notifications yet.</p>
  <?php else: ?>
  <ul style="list-style:none;">
  <?php foreach ($rows as $n): ?>
    <li style="display:flex;align-items:flex-start;gap:1rem;padding:.85rem 0;border-bottom:1px solid var(--border);">
      <i class="fa fa-info-circle" style="color:var(--info);margin-top:3px;flex-shrink:0;"></i>
      <div>
        <p style="font-size:.9rem;"><?= e($n['message']) ?></p>
        <?php if ($n['application_number']): ?>
        <p style="font-size:.78rem;color:var(--muted);">App #: <a href="<?= APP_URL ?>/public_track.php?app_num=<?= urlencode($n['application_number']) ?>"><?= e($n['application_number']) ?></a></p>
        <?php endif; ?>
        <p style="font-size:.75rem;color:var(--muted);"><?= e($n['created_at']) ?></p>
      </div>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
