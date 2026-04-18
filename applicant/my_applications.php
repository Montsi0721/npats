<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('applicant');
$db  = getDB();
$uid = $_SESSION['user_id'];

$apps = $db->prepare('SELECT * FROM passport_applications WHERE applicant_user_id=? ORDER BY created_at DESC');
$apps->execute([$uid]);
$apps = $apps->fetchAll();

$pageTitle = 'My Applications';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-list-alt"></i> My Applications</h1>
</div>

<div class="card">
  <?php if (empty($apps)): ?>
  <div style="text-align:center;padding:3rem;">
    <i class="fa fa-folder-open" style="font-size:3rem;color:var(--muted);display:block;margin-bottom:1rem;"></i>
    <p style="color:var(--muted);">No applications linked to your account.</p>
    <p style="color:var(--muted);font-size:.85rem;margin-top:.5rem;">Applications are linked when an officer registers them using your email.</p>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>App Number</th><th>Passport Type</th><th>Stage</th><th>Status</th><th>Applied</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($apps as $a): ?>
      <tr>
        <td><strong><?= e($a['application_number']) ?></strong></td>
        <td><?= e($a['passport_type']) ?></td>
        <td style="font-size:.82rem;"><?= e($a['current_stage']) ?></td>
        <td><span class="status-badge status-<?= strtolower(str_replace(' ','-',$a['status'])) ?>"><?= e($a['status']) ?></span></td>
        <td><?= e($a['application_date']) ?></td>
        <td>
          <a href="<?= APP_URL ?>/applicant/track.php?app_num=<?= urlencode($a['application_number']) ?>" class="btn btn-sm btn-primary">
            <i class="fa fa-search"></i> Track
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
