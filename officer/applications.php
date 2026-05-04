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

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-list-alt"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Officer</div>
        <div class="hero-name">My Applications</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Manage & Track
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/officer/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= $total ?></div>
    <div class="label">Total Applications</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'In-Progress')) ?></div>
    <div class="label">In Progress</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #F59E0B;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'Pending')) ?></div>
    <div class="label">Pending</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'Completed')) ?></div>
    <div class="label">Completed</div>
  </div>
</div>

<!-- Search Card with Custom Select Dropdowns -->
<div class="search-card animate animate-d2" id="searchCard">
  <form method="GET" class="search-form hover-card" id="filterForm">
    <div class="search-group" style="flex:2;">
      <label><i class="fa fa-search"></i> Search</label>
      <div class="search-input">
        <i class="fa fa-search"></i>
        <input type="text" name="q" id="searchInput" value="<?= e($search) ?>" placeholder="Application number, applicant name, or national ID...">
      </div>
    </div>
    <div class="search-group">
      <label><i class="fa fa-filter"></i> Status</label>
      <div class="custom-select" id="statusSelect">
        <div class="custom-select-trigger">
          <span class="selected-text"><?= $status ?: 'All Statuses' ?></span>
          <i class="fa fa-chevron-down arrow"></i>
        </div>
        <div class="custom-select-dropdown">
          <div class="custom-select-option" data-value="">All Statuses</div>
          <div class="custom-select-option" data-value="Pending">Pending</div>
          <div class="custom-select-option" data-value="In-Progress">In-Progress</div>
          <div class="custom-select-option" data-value="Completed">Completed</div>
          <div class="custom-select-option" data-value="Rejected">Rejected</div>
        </div>
        <input type="hidden" name="status" id="statusInput" value="<?= e($status) ?>">
      </div>
    </div>
    <div class="actions">
      <button type="submit" class="btn btn-primary" style="padding: .7rem 1.2rem;">
        <i class="fa fa-search"></i> Filter
      </button>
      <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-outline" style="padding: .7rem 1rem;">
        <i class="fa fa-times"></i>
      </a>
    </div>
  </form>
</div>

<!-- Applications Table Card -->
<div class="table-card animate animate-d3 hover-card" id="tableCard">
  <div class="card-header">
    <div class="card-title">
      <i class="fa fa-table-list"></i> Application List
      <span class="card-badge"><?= $total ?> total</span>
    </div>
    <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary btn-sm">
      <i class="fa fa-plus"></i> New Application
    </a>
  </div>
  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th>App Number</th>
          <th>Applicant</th>
          <th>National ID</th>
          <th>Type</th>
          <th>Stage</th>
          <th>Status</th>
          <th>Date</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($apps)): ?>
        <tr>
          <td colspan="8">
            <div class="empty">
              <div class="empty-icon">
                <i class="fa fa-inbox"></i>
              </div>
              <h3>No applications found</h3>
              <p>Start by creating a new passport application.</p>
              <a href="<?= APP_URL ?>/officer/new_application.php" class="btn btn-primary">
                <i class="fa fa-plus"></i> New Application
              </a>
            </div>
           </td>
         </tr>
      <?php else: 
        $idx = 0;
        foreach ($apps as $a): 
          $stageDot = match($a['status']) {
            'Pending' => 'pending',
            'In-Progress' => 'progress',
            'Completed' => 'completed',
            'Rejected' => 'rejected',
            default => 'pending'
          };
      ?>
        <tr style="--i: <?= $idx++ ?>">
          <td>
            <a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="number">
              <?= e($a['application_number']) ?>
            </a>
          </td>
          <td>
            <span class="applicant-name"><?= e($a['full_name']) ?></span>
          </td>
          <td style="font-family: monospace; font-size: .8rem;"><?= e($a['national_id']) ?></td>
          <td>
            <span class="type-badge">
              <i class="fa fa-passport"></i> <?= e($a['passport_type']) ?>
            </span>
          </td>
          <td>
            <div class="stage">
              <?= e($a['current_stage']) ?>
            </div>
          </td>
          <td><?= statusBadge($a['status']) ?></td>
          <td style="font-size: .75rem; color: var(--muted); white-space: nowrap;">
            <i class="fa fa-calendar"></i> <?= e($a['application_date']) ?>
          </td>
          <td>
            <div class="action-buttons">
              <a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="action-btn" title="Manage Application">
                <i class="fa fa-edit"></i>
              </a>
              <a href="<?= APP_URL ?>/public_track.php?app_num=<?= urlencode($a['application_number']) ?>" class="action-btn view" title="Public View" target="_blank">
                <i class="fa fa-eye"></i>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination animate animate-d4">
  <?php if ($page > 1): ?>
    <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
      <i class="fa fa-chevron-left"></i>
    </a>
  <?php endif; ?>
  
  <?php 
  $start = max(1, $page - 2);
  $end = min($pages, $page + 2);
  if ($start > 1): ?>
    <a href="?page=1&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">1</a>
    <?php if ($start > 2): ?><span>...</span><?php endif; ?>
  <?php endif; ?>
  
  <?php for ($p = $start; $p <= $end; $p++): ?>
    <?php if ($p == $page): ?>
      <span class="current"><?= $p ?></span>
    <?php else: ?>
      <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  
  <?php if ($end < $pages): ?>
    <?php if ($end < $pages - 1): ?><span>...</span><?php endif; ?>
    <a href="?page=<?= $pages ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $pages ?></a>
  <?php endif; ?>
  
  <?php if ($page < $pages): ?>
    <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
      <i class="fa fa-chevron-right"></i>
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<script src="<?= APP_URL ?>/js/select.js"></script>
<script src="<?= APP_URL ?>/js/spotlight.js"></script>
<script>
// Auto-submit on search input debounce
(function() {
  const searchInput = document.getElementById('searchInput');
  let timeout = null;
  
  if (searchInput) {
    searchInput.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') {
        document.getElementById('filterForm').submit();
        return;
      }
      if (timeout) clearTimeout(timeout);
      timeout = setTimeout(() => {
        document.getElementById('filterForm').submit();
      }, 500);
    });
  }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>