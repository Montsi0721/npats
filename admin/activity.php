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

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-history"></i></div>
      <div>
        <div class="hero-eyebrow">System Administrator</div>
        <div class="hero-name">Activity Log</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-shield-alt"></i> Audit Trail
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
      <button class="btn btn-outline" onclick="window.print()" class="no-print">
        <i class="fa fa-print"></i> Print
      </button>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= $total ?></div>
    <div class="label">Total Events</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= count(array_filter($logs, fn($l) => str_contains($l['action'] ?? '', 'LOGIN')))?></div>
    <div class="label">Logins</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #F59E0B;"><?= count(array_filter($logs, fn($l) => str_contains($l['action'] ?? '', 'CREATE') || str_contains($l['action'] ?? '', 'ADD')))?></div>
    <div class="label">Creations</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= $pages ?></div>
    <div class="label">Pages</div>
  </div>
</div>

<!-- Activity Log Card -->
<div class="card animate animate-d2 hover-card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa fa-list-alt"></i> System Audit Trail
      <span class="card-badge"><?= $total ?> records</span>
    </div>
  </div>
  
  <div class="search-wrap no-print" style="padding: 20px;">
    <i class="fa fa-search" style="margin-left: 20px;"></i>
    <input type="text" id="tableSearch" placeholder="Filter by user, action, details, or IP address...">
  </div>
  
  <div class="table-wrapper">
    <table class="table" id="activityTable">
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Action</th>
          <th>Details</th>
          <th>IP Address</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr class="no-data">
          <td colspan="6">
            <div class="empty-state">
              <div class="empty-icon">
                <i class="fa fa-history"></i>
              </div>
              <h3>No activity recorded yet</h3>
              <p>System events and user actions will appear here.</p>
            </div>
          </td>
        </tr>
      <?php else: foreach ($logs as $i => $l):
        $actionClass = 'default';
        $actionUpper = strtoupper($l['action'] ?? '');
        if (str_contains($actionUpper, 'LOGIN')) $actionClass = 'login';
        elseif (str_contains($actionUpper, 'LOGOUT')) $actionClass = 'logout';
        elseif (str_contains($actionUpper, 'CREATE') || str_contains($actionUpper, 'ADD')) $actionClass = 'create';
        elseif (str_contains($actionUpper, 'UPDATE') || str_contains($actionUpper, 'EDIT')) $actionClass = 'update';
        elseif (str_contains($actionUpper, 'DELETE') || str_contains($actionUpper, 'REMOVE')) $actionClass = 'delete';
        
        $actionIcon = match($actionClass) {
          'login' => 'fa-sign-in-alt',
          'logout' => 'fa-sign-out-alt',
          'create' => 'fa-plus-circle',
          'update' => 'fa-edit',
          'delete' => 'fa-trash-alt',
          default => 'fa-circle-info'
        };
      ?>
        <tr data-searchable="<?= strtolower(e(($l['username'] ?? 'Guest') . ' ' . ($l['action'] ?? '') . ' ' . ($l['details'] ?? '') . ' ' . ($l['ip_address'] ?? ''))) ?>">
          <td style="font-size:.8rem; color: var(--muted);"><?= $offset+$i+1 ?></td>
          <td>
            <div class="user-cell">
              <div class="user-avatar">
                <i class="fa fa-user"></i>
              </div>
              <span class="user-name"><?= e($l['username'] ?? 'Guest') ?></span>
            </div>
          </td>
          <td>
            <span class="action-badge <?= $actionClass ?>">
              <i class="fa <?= $actionIcon ?>"></i>
              <?= e($l['action']) ?>
            </span>
          </td>
          <td>
            <div class="details-text" title="<?= e($l['details']) ?>">
              <?= e(strlen($l['details'] ?? '') > 60 ? substr($l['details'], 0, 60) . '...' : ($l['details'] ?? '—')) ?>
            </div>
          </td>
          <td>
            <span class="ip-address">
              <i class="fa fa-network-wired"></i> <?= e($l['ip_address'] ?? '—') ?>
            </span>
          </td>
          <td>
            <div class="timestamp">
              <i class="fa fa-clock"></i>
              <?= date('d M Y, H:i:s', strtotime($l['created_at'])) ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination no-print" style="padding: 20px;">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page-1 ?>">
        <i class="fa fa-chevron-left"></i>
      </a>
    <?php endif; ?>
    
    <?php 
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    if ($start > 1): ?>
      <a href="?page=1">1</a>
      <?php if ($start > 2): ?><span>...</span><?php endif; ?>
    <?php endif; ?>
    
    <?php for ($p = $start; $p <= $end; $p++): ?>
      <?php if ($p == $page): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="?page=<?= $p ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($end < $pages): ?>
      <?php if ($end < $pages - 1): ?><span>...</span><?php endif; ?>
      <a href="?page=<?= $pages ?>"><?= $pages ?></a>
    <?php endif; ?>
    
    <?php if ($page < $pages): ?>
      <a href="?page=<?= $page+1 ?>">
        <i class="fa fa-chevron-right"></i>
      </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .card');
  
  spotlightElements.forEach(el => {
    let spotlight = el.querySelector('.sc-spotlight');
    if (!spotlight) {
      spotlight = document.createElement('div');
      spotlight.className = 'sc-spotlight';
      el.style.position = 'relative';
      el.style.overflow = 'hidden';
      el.appendChild(spotlight);
    }
    
    el.addEventListener('mousemove', function(e) {
      const rect = this.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;
      
      this.style.setProperty('--x', x + '%');
      this.style.setProperty('--y', y + '%');
      
      spotlight.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(59, 130, 246, 0.12) 0%, transparent 60%)`;
      spotlight.style.opacity = '1';
    });
    
    el.addEventListener('mouseleave', function() {
      spotlight.style.opacity = '0';
    });
  });
})();

// Table search functionality
function initTableSearch() {
  const searchInput = document.getElementById('tableSearch');
  if (!searchInput) return;
  
  searchInput.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('activityTable');
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
      // Skip if it's the "no data" row
      if (row.classList.contains('no-data')) {
        if (searchTerm === '') {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
        return;
      }
      
      const searchableText = row.getAttribute('data-searchable') || '';
      if (searchTerm === '' || searchableText.includes(searchTerm)) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });
    
    // Show/hide no results message if needed
    const tbody = table.querySelector('tbody');
    const existingNoData = tbody.querySelector('.no-data-row');
    
    if (visibleCount === 0 && !tbody.querySelector('.no-data')) {
      if (!existingNoData) {
        const noDataRow = document.createElement('tr');
        noDataRow.className = 'no-data-row';
        noDataRow.innerHTML = '<td colspan="6"><div class="empty-state" style="padding: 2rem;"><div class="empty-icon"><i class="fa fa-search"></i></div><h3>No matching records</h3><p>Try adjusting your search terms.</p></div></td>';
        tbody.appendChild(noDataRow);
      }
    } else if (existingNoData) {
      existingNoData.remove();
    }
  });
}

// Initialize search on page load
document.addEventListener('DOMContentLoaded', initTableSearch);

// Auto-refresh activity log every 60 seconds (optional)
let autoRefreshInterval = null;

function startAutoRefresh() {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);
  autoRefreshInterval = setInterval(() => {
    location.reload();
  }, 60000);
}

// Uncomment to enable auto-refresh
// startAutoRefresh();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>