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
$claimed = $_GET['claimed'] ?? '';   // '' | 'yes' | 'no'

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[] = '(pa.application_number LIKE ? OR pa.full_name LIKE ? OR pa.national_id LIKE ?)';
    $w = "%$search%"; $params = array_merge($params, [$w,$w,$w]);
}
if ($status)       { $where[] = 'pa.status = ?';        $params[] = $status; }
if ($type)         { $where[] = 'pa.passport_type = ?'; $params[] = $type; }
if ($claimed === 'no')  $where[] = 'pa.officer_id IS NULL';
if ($claimed === 'yes') $where[] = 'pa.officer_id IS NOT NULL';
$whereSQL = implode(' AND ', $where);

$totalStmt = $db->prepare("SELECT COUNT(*) FROM passport_applications pa WHERE $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = (int)ceil($total / $perPage);

$stmt = $db->prepare("SELECT pa.*, u.full_name AS officer_name
    FROM passport_applications pa
    LEFT JOIN users u ON u.id = pa.officer_id
    WHERE $whereSQL ORDER BY pa.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$apps = $stmt->fetchAll();

$unclaimedCount = (int)$db->query('SELECT COUNT(*) FROM passport_applications WHERE officer_id IS NULL')->fetchColumn();

$pageTitle = 'All Applications';
include __DIR__ . '/../includes/header.php';
?>

<!-- Hero -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-list-alt"></i></div>
      <div>
        <div class="hero-eyebrow">System Administrator</div>
        <div class="hero-name">All Applications</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <?php if ($unclaimedCount > 0): ?>
          <span class="hero-meta-chip" style="color:#F59E0B;border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.08);">
            <i class="fa fa-inbox"></i> <?= $unclaimedCount ?> unclaimed
          </span>
          <?php else: ?>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Full System View
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats -->
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= $total ?></div>
    <div class="label">Total (filtered)</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color:#F59E0B;"><?= $unclaimedCount ?></div>
    <div class="label">Unclaimed</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color:#60A5FA;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'In-Progress')) ?></div>
    <div class="label">In Progress</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color:#34D399;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'Completed')) ?></div>
    <div class="label">Completed</div>
  </div>
</div>

<!-- Search / Filter -->
<div class="search-card animate animate-d2" id="searchCard">
  <form method="GET" class="search-form hover-card" id="filterForm">
    <div class="search-group" style="flex:2;">
      <label><i class="fa fa-search"></i> Search</label>
      <div class="search-input">
        <i class="fa fa-search"></i>
        <input type="text" name="q" id="searchInput" value="<?= e($search) ?>"
               placeholder="Name, application number, or national ID...">
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
    <div class="search-group">
      <label><i class="fa fa-passport"></i> Type</label>
      <div class="custom-select" id="typeSelect">
        <div class="custom-select-trigger">
          <span class="selected-text"><?= $type ?: 'All Types' ?></span>
          <i class="fa fa-chevron-down arrow"></i>
        </div>
        <div class="custom-select-dropdown">
          <div class="custom-select-option" data-value="">All Types</div>
          <div class="custom-select-option" data-value="Normal">Normal</div>
          <div class="custom-select-option" data-value="Express">Express</div>
        </div>
        <input type="hidden" name="type" id="typeInput" value="<?= e($type) ?>">
      </div>
    </div>
    <div class="search-group">
      <label><i class="fa fa-user-check"></i> Claimed</label>
      <div class="custom-select" id="claimedSelect">
        <div class="custom-select-trigger">
          <span class="selected-text"><?= $claimed === 'no' ? 'Unclaimed' : ($claimed === 'yes' ? 'Claimed' : 'All') ?></span>
          <i class="fa fa-chevron-down arrow"></i>
        </div>
        <div class="custom-select-dropdown">
          <div class="custom-select-option" data-value="">All</div>
          <div class="custom-select-option" data-value="no">Unclaimed only</div>
          <div class="custom-select-option" data-value="yes">Claimed only</div>
        </div>
        <input type="hidden" name="claimed" id="claimedInput" value="<?= e($claimed) ?>">
      </div>
    </div>
    <div class="actions">
      <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
      <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline"><i class="fa fa-times"></i></a>
    </div>
  </form>
</div>

<!-- Table -->
<div class="table-card animate animate-d3 hover-card">
  <div class="card-header">
    <div class="card-title">
      <i class="fa fa-table-list"></i> Application List
      <span class="card-badge"><?= $total ?> total</span>
    </div>
    <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline btn-sm">
      <i class="fa fa-chart-bar"></i> Reports
    </a>
  </div>
  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th>App Number</th>
          <th>Applicant</th>
          <th>Type</th>
          <th>Officer</th>
          <th>Stage</th>
          <th>Status</th>
          <th>Date</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($apps)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <div class="empty-icon"><i class="fa fa-inbox"></i></div>
            <h3>No applications found</h3>
            <p>Try adjusting your search or filter criteria.</p>
          </div>
        </td></tr>
      <?php else:
        $idx = 0;
        foreach ($apps as $a):
          $isUnclaimed = $a['officer_id'] === null;
      ?>
        <tr style="--i:<?= $idx++ ?>">
          <td>
            <a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $a['id'] ?>" class="app-number">
              <?= e($a['application_number']) ?>
            </a>
          </td>
          <td><span class="applicant-name"><?= e($a['full_name']) ?></span></td>
          <td><span class="app-type-badge"><i class="fa fa-passport"></i> <?= e($a['passport_type']) ?></span></td>
          <td>
            <?php if ($isUnclaimed): ?>
              <span style="font-size:.75rem;font-weight:600;color:#F59E0B;display:inline-flex;align-items:center;gap:.3rem;">
                <i class="fa fa-inbox"></i> Unclaimed
              </span>
            <?php else: ?>
              <span class="officer-name"><i class="fa fa-user-circle"></i> <?= e($a['officer_name'] ?? '—') ?></span>
            <?php endif; ?>
          </td>
          <td><span class="stage-text"><?= e($a['current_stage']) ?></span></td>
          <td><?= statusBadge($a['status']) ?></td>
          <td style="font-size:.75rem;color:var(--muted);white-space:nowrap;">
            <i class="fa fa-calendar"></i> <?= e($a['application_date']) ?>
          </td>
          <td style="text-align:center;">
            <a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $a['id'] ?>" class="action-btn" title="View">
              <i class="fa fa-eye"></i>
            </a>
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
    <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>&claimed=<?= urlencode($claimed) ?>"><i class="fa fa-chevron-left"></i></a>
  <?php endif; ?>
  <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
    <?php if ($p == $page): ?>
      <span class="current"><?= $p ?></span>
    <?php else: ?>
      <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>&claimed=<?= urlencode($claimed) ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>&claimed=<?= urlencode($claimed) ?>"><i class="fa fa-chevron-right"></i></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function() {
  document.querySelectorAll('.hover-card').forEach(el => {
    const s = document.createElement('div'); s.className = 'sc-spotlight'; el.appendChild(s);
    el.addEventListener('mousemove', function(e) {
      const r = this.getBoundingClientRect();
      const x = ((e.clientX-r.left)/r.width)*100, y = ((e.clientY-r.top)/r.height)*100;
      this.style.setProperty('--x',x+'%'); this.style.setProperty('--y',y+'%');
      s.style.background=`radial-gradient(circle at ${x}% ${y}%, rgba(59,130,246,.12) 0%, transparent 60%)`; s.style.opacity='1';
    });
    el.addEventListener('mouseleave', ()=>s.style.opacity='0');
  });
})();

function initCustomSelect(id, hiddenId) {
  const c = document.getElementById(id); if(!c) return;
  const trigger = c.querySelector('.custom-select-trigger');
  const dropdown = c.querySelector('.custom-select-dropdown');
  const hidden = document.getElementById(hiddenId);
  const span = trigger.querySelector('.selected-text');
  const opts = c.querySelectorAll('.custom-select-option');
  const cur = hidden.value;
  opts.forEach(o => { if(o.dataset.value===cur){ o.classList.add('selected'); span.textContent=o.textContent.trim(); } });
  trigger.addEventListener('click', e => { e.stopPropagation(); document.querySelectorAll('.custom-select-dropdown.show').forEach(d=>{ if(d!==dropdown)d.classList.remove('show'); }); dropdown.classList.toggle('show'); trigger.classList.toggle('open'); });
  opts.forEach(o => { o.addEventListener('click', e => { e.stopPropagation(); hidden.value=o.dataset.value; span.textContent=o.textContent.trim(); opts.forEach(x=>x.classList.remove('selected')); o.classList.add('selected'); dropdown.classList.remove('show'); trigger.classList.remove('open'); document.getElementById('filterForm').submit(); }); });
  document.addEventListener('click', e => { if(!c.contains(e.target)){ dropdown.classList.remove('show'); trigger.classList.remove('open'); } });
}
initCustomSelect('statusSelect','statusInput');
initCustomSelect('typeSelect','typeInput');
initCustomSelect('claimedSelect','claimedInput');

(function() {
  const input = document.getElementById('searchInput'); if(!input) return;
  let t = null;
  input.addEventListener('keyup', e => {
    if(e.key==='Enter'){ document.getElementById('filterForm').submit(); return; }
    clearTimeout(t); t = setTimeout(()=>document.getElementById('filterForm').submit(), 500);
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
