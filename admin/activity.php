<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page-1)*$perPage;

$total = (int)$db->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
$pages = ceil($total/$perPage);

$logs = $db->prepare('SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT ? OFFSET ?');
$logs->bindValue(1, $perPage, PDO::PARAM_INT);
$logs->bindValue(2, $offset,  PDO::PARAM_INT);
$logs->execute();
$logs = $logs->fetchAll();

$pageTitle = 'Activity Log';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-history"></i> Activity Log</h1>
  <span class="text-muted"><?= $total ?> entries</span>
</div>

<div class="card">
  <div class="search-bar">
    <i class="fa fa-search" style="color:var(--muted);"></i>
    <input type="text" id="tableSearch" placeholder="Filter log…">
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
      <tbody>
      <?php if (empty($logs)): ?>
      <tr class="no-data"><td colspan="6">No activity yet.</td></tr>
      <?php else: foreach ($logs as $i => $l): ?>
      <tr>
        <td style="font-size:.8rem;"><?= $offset+$i+1 ?></td>
        <td style="font-size:.85rem;"><?= e($l['username'] ?? 'Guest') ?></td>
        <td><span style="font-size:.8rem;background:var(--info-bg);color:var(--info);padding:.15rem .5rem;border-radius:4px;font-family:monospace;"><?= e($l['action']) ?></span></td>
        <td style="font-size:.82rem;color:var(--muted);"><?= e($l['details']) ?></td>
        <td style="font-size:.8rem;color:var(--muted);"><?= e($l['ip_address'] ?? '') ?></td>
        <td style="font-size:.8rem;white-space:nowrap;"><?= e($l['created_at']) ?></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>">&laquo;</a><?php endif; ?>
    <?php for ($p=max(1,$page-3);$p<=min($pages,$page+3);$p++): ?>
      <?php if ($p===$page): ?><span class="current"><?= $p ?></span><?php else: ?><a href="?page=<?= $p ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>">&raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
