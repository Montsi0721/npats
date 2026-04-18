<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];

$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';

$where  = ['pa.officer_id = ?'];
$params = [$uid];
if ($search) {
    $where[] = '(pa.application_number LIKE ? OR pa.full_name LIKE ? OR pa.national_id LIKE ?)';
    $w = "%$search%"; $params = array_merge($params, [$w,$w,$w]);
}
if ($status) { $where[] = 'pa.status = ?'; $params[] = $status; }
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM passport_applications pa WHERE $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT * FROM passport_applications pa WHERE $whereSQL ORDER BY pa.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$apps = $stmt->fetchAll();

$pageTitle = 'My Applications';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-list-alt"></i> My Applications</h1>
  <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Application</a>
</div>

<div class="card">
  <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, app#, national ID…" style="flex:1;min-width:180px;">
    <select name="status" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <?php foreach (['Pending','In-Progress','Completed','Rejected'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
    <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-outline btn-sm"><i class="fa fa-times"></i></a>
  </form>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>App Number</th><th>Applicant</th><th>National ID</th><th>Type</th><th>Stage</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if (empty($apps)): ?>
      <tr class="no-data"><td colspan="8">No applications found. <a href="<?= APP_URL ?>/officer/new_application.php">Create one.</a></td></tr>
      <?php else: foreach ($apps as $a): ?>
      <tr>
        <td><strong><?= e($a['application_number']) ?></strong></td>
        <td><?= e($a['full_name']) ?></td>
        <td style="font-size:.82rem;"><?= e($a['national_id']) ?></td>
        <td><?= e($a['passport_type']) ?></td>
        <td style="font-size:.8rem;"><?= e($a['current_stage']) ?></td>
        <td><span class="status-badge status-<?= strtolower(str_replace(' ','-',$a['status'])) ?>"><?= e($a['status']) ?></span></td>
        <td style="font-size:.8rem;"><?= e($a['application_date']) ?></td>
        <td>
          <a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i></a>
          <a href="<?= APP_URL ?>/public_track.php?app_num=<?= urlencode($a['application_number']) ?>" class="btn btn-sm btn-outline" target="_blank"><i class="fa fa-eye"></i></a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">&laquo;</a><?php endif; ?>
    <?php for ($p=1;$p<=$pages;$p++): ?>
      <?php if ($p===$page): ?><span class="current"><?= $p ?></span><?php else: ?><a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">&raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
