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

<style>
/* ─────────────────────────────────────────────────────────────
   ACTIVITY LOG PAGE — Premium Edition (Dashboard Matching)
   ───────────────────────────────────────────────────────────── */

/* ── Entry animations ──────────────────────────────────────── */
@keyframes fadeUp   { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn   { from{opacity:0} to{opacity:1} }
@keyframes shimmer  {
  0%  { background-position: -200% center }
  100%{ background-position:  200% center }
}
@keyframes float {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-6px) }
}
@keyframes pulse-ring {
  0%  { box-shadow: 0 0 0 0 rgba(59,130,246,.4) }
  70% { box-shadow: 0 0 0 6px rgba(59,130,246,0) }
  100%{ box-shadow: 0 0 0 0 rgba(59,130,246,0) }
}

.act-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.act-animate-d1 { animation-delay:.06s }
.act-animate-d2 { animation-delay:.12s }
.act-animate-d3 { animation-delay:.18s }
.act-animate-d4 { animation-delay:.24s }

/* ── Spotlight Card Effect ───────────────────────────────── */
.hover-card {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  overflow: hidden;
  transform-style: preserve-3d;
  will-change: transform;
}

.hover-card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.12) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  z-index: 1;
}

.hover-card:hover::before {
  opacity: 1;
}

.hover-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

/* ── Hero Section ────────────────────────────────────────── */
.act-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .act-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.act-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.act-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.act-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.act-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.act-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.act-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.act-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.act-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.act-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.act-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.act-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.act-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.act-hero-meta-chip i { font-size: .62rem; }

.act-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Stats Row (mini stats) ───────────────────────────────── */
.act-stats-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.act-stat-mini {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0.75rem 1.25rem;
  flex: 1;
  min-width: 100px;
  transition: all 0.2s;
  cursor: pointer;
}
.act-stat-mini:hover {
  transform: translateY(-2px);
  border-color: rgba(59,130,246,0.3);
  background: rgba(59,130,246,.02);
}
.act-stat-mini .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--gold-light);
  line-height: 1;
}
.act-stat-mini .label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── Activity Card ───────────────────────────────────────── */
.act-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.act-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.08) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  border-radius: inherit;
  z-index: 1;
}
.act-card:hover::before { opacity: 1; }
.act-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.act-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.act-card-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.act-card-title i {
  color: var(--gold);
  font-size: .78rem;
}
.act-card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}

/* Search Bar */
.act-search-bar {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 0.8rem;
  background: var(--surface);
}
.act-search-bar i {
  color: var(--muted);
  font-size: 0.9rem;
}
.act-search-bar input {
  flex: 1;
  background: transparent;
  border: none;
  padding: 0.5rem 0;
  color: var(--text);
  font-size: 0.85rem;
  outline: none;
}
.act-search-bar input::placeholder {
  color: var(--muted);
}

/* Premium Table */
.act-table-wrapper {
  overflow-x: auto;
}
.act-table {
  width: 100%;
  border-collapse: collapse;
}
.act-table thead {
  background: #07101E;
  border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .act-table thead { background: var(--surface); }
.act-table thead th {
  padding: 1rem 1.2rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  white-space: nowrap;
}
.act-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
  animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both;
}
.act-table tbody tr:hover {
  background: rgba(59,130,246,.04);
}
.act-table tbody td {
  padding: 1rem 1.2rem;
  vertical-align: middle;
  font-size: .85rem;
}

/* Action Badges */
.action-badge {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .25rem .75rem;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 600;
  font-family: monospace;
  white-space: nowrap;
}
.action-badge.login {
  background: rgba(52,211,153,.12);
  color: #34D399;
  border: 1px solid rgba(52,211,153,.2);
}
.action-badge.logout {
  background: rgba(239,68,68,.12);
  color: #F87171;
  border: 1px solid rgba(239,68,68,.2);
}
.action-badge.create {
  background: rgba(59,130,246,.12);
  color: #60A5FA;
  border: 1px solid rgba(59,130,246,.2);
}
.action-badge.update {
  background: rgba(245,158,11,.12);
  color: #F59E0B;
  border: 1px solid rgba(245,158,11,.2);
}
.action-badge.delete {
  background: rgba(239,68,68,.12);
  color: #F87171;
  border: 1px solid rgba(239,68,68,.2);
}
.action-badge.default {
  background: rgba(107,114,128,.12);
  color: #9CA3AF;
  border: 1px solid rgba(107,114,128,.2);
}

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.user-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(59,130,246,.2), rgba(200,145,26,.1));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  color: var(--gold-light);
}
.user-name {
  font-weight: 500;
  color: var(--text);
}

/* IP Address */
.ip-address {
  font-family: monospace;
  font-size: .75rem;
  background: var(--surface);
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
  display: inline-block;
  color: var(--muted);
}

/* Timestamp */
.timestamp {
  font-size: .75rem;
  color: var(--muted);
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.timestamp i {
  font-size: .65rem;
}

/* Details text */
.details-text {
  font-size: .8rem;
  color: var(--text-soft);
  max-width: 300px;
  white-space: normal;
  word-break: break-word;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
}
.empty-icon {
  width: 70px;
  height: 70px;
  margin: 0 auto 1rem;
  background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(200,145,26,.05));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  color: var(--muted);
}
.empty-state h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: .3rem;
}
.empty-state p {
  color: var(--muted);
  font-size: .8rem;
}

/* Premium Pagination */
.act-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.5rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border);
  gap: 0.3rem;
}
.act-pagination a,
.act-pagination span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 0.75rem;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text-soft);
  font-size: .85rem;
  transition: all .2s;
  text-decoration: none;
}
.act-pagination a:hover {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: #fff;
  transform: translateY(-2px);
}
.act-pagination .current {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: #fff;
}

/* Responsive */
@media (max-width: 768px) {
  .act-hero-inner { flex-direction: column; text-align: center; }
  .act-stats-row { flex-wrap: wrap; }
  .act-table thead th,
  .act-table tbody td { padding: 0.75rem; }
  .details-text { max-width: 200px; }
}

/* Print Styles */
@media print {
  .no-print { display: none; }
  .act-card { break-inside: avoid; }
  body { background: white; }
}
</style>

<!-- Hero Section -->
<div class="act-hero act-animate">
  <div class="act-hero-mesh"></div>
  <div class="act-hero-grid"></div>
  <div class="act-hero-inner">
    <div class="act-hero-left">
      <div class="act-hero-icon"><i class="fa fa-history"></i></div>
      <div>
        <div class="act-hero-eyebrow">System Administrator</div>
        <div class="act-hero-name">Activity Log</div>
        <div class="act-hero-meta">
          <span class="act-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="act-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="act-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-shield-alt"></i> Audit Trail
          </span>
        </div>
      </div>
    </div>
    <div class="act-hero-right">
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
<div class="act-stats-row act-animate act-animate-d1">
  <div class="act-stat-mini hover-card">
    <div class="value"><?= $total ?></div>
    <div class="label">Total Events</div>
  </div>
  <div class="act-stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= count(array_filter($logs, fn($l) => str_contains($l['action'] ?? '', 'LOGIN')))?></div>
    <div class="label">Logins</div>
  </div>
  <div class="act-stat-mini hover-card">
    <div class="value" style="color: #F59E0B;"><?= count(array_filter($logs, fn($l) => str_contains($l['action'] ?? '', 'CREATE') || str_contains($l['action'] ?? '', 'ADD')))?></div>
    <div class="label">Creations</div>
  </div>
  <div class="act-stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= $pages ?></div>
    <div class="label">Pages</div>
  </div>
</div>

<!-- Activity Log Card -->
<div class="act-card act-animate act-animate-d2 hover-card">
  <div class="act-card-header">
    <div class="act-card-title">
      <i class="fa fa-list-alt"></i> System Audit Trail
      <span class="act-card-badge"><?= $total ?> records</span>
    </div>
  </div>
  
  <div class="act-search-bar no-print">
    <i class="fa fa-search"></i>
    <input type="text" id="tableSearch" placeholder="Filter by user, action, details, or IP address...">
  </div>
  
  <div class="act-table-wrapper">
    <table class="act-table" id="activityTable">
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
  <div class="act-pagination no-print">
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
  const spotlightElements = document.querySelectorAll('.hover-card, .act-card');
  
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