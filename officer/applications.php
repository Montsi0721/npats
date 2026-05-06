<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];

// ── Handle Claim Assignment ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_application') {
    $appId = (int)($_POST['application_id'] ?? 0);
    if ($appId) {
        // Get the ID of the 'unassigned' user
        $unassignedStmt = $db->prepare("SELECT id FROM users WHERE username = 'unassigned' LIMIT 1");
        $unassignedStmt->execute();
        $unassignedOfficerId = $unassignedStmt->fetchColumn();
        
        // Only assign if the application currently has the 'unassigned' user as officer
        $stmt = $db->prepare('UPDATE passport_applications SET officer_id = ? WHERE id = ? AND officer_id = ?');
        $stmt->execute([$uid, $appId, $unassignedOfficerId]);
        if ($stmt->rowCount() > 0) {
            logActivity($uid, 'ASSIGN_APPLICATION', "Assigned application ID: $appId to officer ID: $uid");
            flash('success', 'Application assigned to you successfully.');
        } else {
            flash('error', 'Application could not be assigned (already assigned or invalid).');
        }
    }
    redirect(APP_URL . '/officer/applications.php');
}

// Get or create the 'unassigned' user
$unassignedStmt = $db->prepare("SELECT id FROM users WHERE username = 'unassigned' LIMIT 1");
$unassignedStmt->execute();
$unassignedOfficerId = $unassignedStmt->fetchColumn();

// If no 'unassigned' user exists, create one
if (!$unassignedOfficerId) {
    $hashedPassword = password_hash('unassigned_default', PASSWORD_BCRYPT);
    $insertStmt = $db->prepare("INSERT INTO users (username, full_name, email, password, role, is_active) VALUES ('unassigned', 'Unassigned Officer', 'unassigned@system.local', ?, 'officer', 1)");
    $insertStmt->execute([$hashedPassword]);
    $unassignedOfficerId = $db->lastInsertId();
}

$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';

// Modified WHERE clause: officer_id = current user OR officer_id = unassigned user
$where  = ['(pa.officer_id = ? OR pa.officer_id = ?)'];
$params = [$uid, $unassignedOfficerId];

if ($search) {
    $where[] = '(pa.application_number LIKE ? OR pa.full_name LIKE ? OR pa.national_id LIKE ?)';
    $w = "%$search%"; 
    $params = array_merge($params, [$w, $w, $w]);
}
if ($status) { 
    $where[] = 'pa.status = ?'; 
    $params[] = $status; 
}
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM passport_applications pa WHERE $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pages = ceil($total / $perPage);

// Select all relevant fields, including a flag to check if assigned to current user
$stmt = $db->prepare("
    SELECT 
        pa.*, 
        CASE 
            WHEN pa.officer_id = ? THEN 1 
            ELSE 0 
        END as is_assigned_to_me
    FROM passport_applications pa 
    WHERE $whereSQL 
    ORDER BY 
        CASE WHEN pa.officer_id = ? THEN 1 ELSE 0 END, 
        pa.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute(array_merge([$uid, $unassignedOfficerId], $params));
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
<?php
// Calculate stats for the visible data (all matching records)
$totalApps = $total;
$assignedToMe = count(array_filter($apps, fn($a) => $a['officer_id'] == $uid));
$unassigned = count(array_filter($apps, fn($a) => $a['officer_id'] == $unassignedOfficerId));
$totalInProgress = count(array_filter($apps, fn($a) => $a['status'] === 'In-Progress'));
$totalPending = count(array_filter($apps, fn($a) => $a['status'] === 'Pending'));
$totalCompleted = count(array_filter($apps, fn($a) => $a['status'] === 'Completed'));
?>
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= $totalApps ?></div>
    <div class="label">Total Accessible</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #3B82F6;"><?= $assignedToMe ?></div>
    <div class="label">Assigned to Me</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #F59E0B;"><?= $unassigned ?></div>
    <div class="label">Unassigned</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= $totalCompleted ?></div>
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
          <th>Assigned To</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($apps)): ?>
        <tr>
          <td colspan="9">
            <div class="empty">
              <div class="empty-icon">
                <i class="fa fa-inbox"></i>
              </div>
              <h3>No applications found</h3>
              <p>There are no applications available for you to process.</p>
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
            <?php if ($a['officer_id'] == $unassignedOfficerId): ?>
              <span class="badge unassigned" style="background: rgba(245,158,11,.15); color: #F59E0B; padding: 4px 8px; border-radius: 20px; font-size: 0.7rem; white-space: nowrap;">
                <i class="fa fa-user-plus"></i> Unassigned
              </span>
            <?php else: ?>
              <span class="badge assigned" style="background: rgba(59,130,246,.15); color: #3B82F6; padding: 4px 8px; border-radius: 20px; font-size: 0.7rem; white-space: nowrap;">
                <i class="fa fa-user-check"></i> Assigned to Me
              </span>
            <?php endif; ?>
           </td>
          <td>
            <div class="action-buttons">
              <?php if ($a['officer_id'] == $unassignedOfficerId): ?>
                <!-- Assign Button for Unassigned Applications -->
                <form method="POST" style="display:inline;" onsubmit="return confirm('Assign this application to yourself? You will become responsible for processing it.');">
                  <input type="hidden" name="action" value="assign_application">
                  <input type="hidden" name="application_id" value="<?= $a['id'] ?>">
                  <button type="submit" class="action-btn assign" title="Assign to Me" style="color: #10B981;">
                    <i class="fa fa-hand-peace"></i>
                  </button>
                </form>
              <?php else: ?>
                <!-- Manage Button for Assigned Applications -->
                <a href="<?= APP_URL ?>/officer/manage_application.php?id=<?= $a['id'] ?>" class="action-btn" title="Manage Application">
                  <i class="fa fa-edit"></i>
                </a>
              <?php endif; ?>
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