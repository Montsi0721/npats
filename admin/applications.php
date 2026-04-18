<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$type    = $_GET['type'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(pa.application_number LIKE ? OR pa.full_name LIKE ? OR pa.national_id LIKE ?)'; $w = "%$search%"; $params = array_merge($params, [$w,$w,$w]); }
if ($status) { $where[] = 'pa.status = ?'; $params[] = $status; }
if ($type)   { $where[] = 'pa.passport_type = ?'; $params[] = $type; }
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM passport_applications pa WHERE $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT pa.*, u.full_name AS officer_name FROM passport_applications pa
    JOIN users u ON u.id = pa.officer_id WHERE $whereSQL ORDER BY pa.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$apps = $stmt->fetchAll();

$pageTitle = 'All Applications';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-list-alt"></i> All Applications</h1>
  <span class="text-muted"><?= $total ?> total</span>
</div>

<div class="card">
  <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;" id="filterForm">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, app#, ID…" style="flex:1;min-width:180px;">
    <select name="status" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <?php foreach (['Pending','In-Progress','Completed','Rejected'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" onchange="this.form.submit()">
      <option value="">All Types</option>
      <option value="Normal" <?= $type==='Normal'?'selected':'' ?>>Normal</option>
      <option value="Express" <?= $type==='Express'?'selected':'' ?>>Express</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
    <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline btn-sm"><i class="fa fa-times"></i></a>
  </form>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>App Number</th><th>Applicant</th><th>Type</th><th>Officer</th><th>Stage</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if (empty($apps)): ?>
      <tr class="no-data"><td colspan="8">No applications found.</td></tr>
      <?php else: foreach ($apps as $a): ?>
      <tr>
        <td><strong><?= e($a['application_number']) ?></strong></td>
        <td><?= e($a['full_name']) ?></td>
        <td><span style="font-size:.8rem;"><?= e($a['passport_type']) ?></span></td>
        <td style="font-size:.82rem;"><?= e($a['officer_name']) ?></td>
        <td style="font-size:.8rem;"><?= e($a['current_stage']) ?></td>
        <td><span class="status-badge status-<?= strtolower(str_replace(' ','-',$a['status'])) ?>"><?= e($a['status']) ?></span></td>
        <td style="font-size:.8rem;"><?= e($a['application_date']) ?></td>
        <td>
          <a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline"><i class="fa fa-eye"></i></a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>">&laquo;</a><?php endif; ?>
    <?php for ($p=1;$p<=$pages;$p++): ?>
      <?php if ($p===$page): ?><span class="current"><?= $p ?></span><?php else: ?><a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>">&raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
