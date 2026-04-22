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

<style>
/* ─────────────────────────────────────────────────────────────
   ADMIN APPLICATIONS PAGE — Premium Edition
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
@keyframes dropdownSlide {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

.app-admin-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.app-admin-animate-d1 { animation-delay:.06s }
.app-admin-animate-d2 { animation-delay:.12s }
.app-admin-animate-d3 { animation-delay:.18s }
.app-admin-animate-d4 { animation-delay:.24s }

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
.app-admin-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .app-admin-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.app-admin-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.app-admin-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.app-admin-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.app-admin-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.app-admin-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.app-admin-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.app-admin-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.app-admin-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.app-admin-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.app-admin-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.app-admin-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.app-admin-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.app-admin-hero-meta-chip i { font-size: .62rem; }

.app-admin-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Stats Row (mini stats) ───────────────────────────────── */
.app-admin-stats-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.app-admin-stat-mini {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0.75rem 1.25rem;
  flex: 1;
  min-width: 100px;
  transition: all 0.2s;
  cursor: pointer;
}
.app-admin-stat-mini:hover {
  transform: translateY(-2px);
  border-color: rgba(59,130,246,0.3);
  background: rgba(59,130,246,.02);
}
.app-admin-stat-mini .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--gold-light);
  line-height: 1;
}
.app-admin-stat-mini .label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── Search Card (matches dashboard) ───────────────────────── */
.app-admin-search-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: visible;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  margin-bottom: 1.5rem;
  position: relative;
  z-index: 10;
}
.app-admin-search-card::before {
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
.app-admin-search-card:hover::before { opacity: 1; }
.app-admin-search-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}
.app-admin-search-form {
  padding: 1.25rem 1.5rem;
  display: flex;
  gap: 0.8rem;
  flex-wrap: wrap;
  align-items: flex-end;
  position: relative;
  z-index: 10;
}
.app-admin-search-group {
  flex: 1;
  min-width: 180px;
  position: relative;
}
.app-admin-search-group label {
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: .3rem;
  display: block;
}
.app-admin-search-input {
  position: relative;
}
.app-admin-search-input i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: .85rem;
}
.app-admin-search-input input {
  width: 100%;
  padding: .7rem 1rem .7rem 2.5rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .85rem;
  transition: all .2s;
}
.app-admin-search-input input:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}

/* ── Custom Select Dropdown (Premium) ─────────────────────── */
.custom-select {
  position: relative;
  width: 100%;
  z-index: 100;
}
.custom-select-trigger {
  width: 100%;
  padding: .7rem 2rem .7rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .85rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all .2s;
  position: relative;
  z-index: 101;
}
.custom-select-trigger:hover {
  border-color: var(--navy-light);
}
.custom-select-trigger.open {
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.custom-select-trigger .arrow {
  transition: transform .2s;
}
.custom-select-trigger.open .arrow {
  transform: rotate(180deg);
}
.custom-select-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  left: 0;
  right: 0;
  background: var(--bg-alt);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  margin-top: 0;
  z-index: 9999 !important;
  display: none;
  max-height: 250px;
  overflow-y: auto;
  animation: dropdownSlide 0.2s ease;
  box-shadow: 0 20px 35px -10px rgba(0,0,0,0.4), 0 0 0 1px rgba(59,130,246,.1);
}
.custom-select-dropdown.show {
  display: block;
}
.custom-select-option {
  padding: .6rem 1rem;
  cursor: pointer;
  transition: all .15s;
  font-size: .85rem;
  color: var(--text-soft);
}
.custom-select-option:hover {
  background: var(--navy-light);
  color: #fff;
}
.custom-select-option.selected {
  background: rgba(59,130,246,.15);
  color: var(--navy-light);
  font-weight: 500;
}
.custom-select-option.selected:hover {
  background: var(--navy-light);
  color: #fff;
}

.app-admin-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

/* ── Table Card (matches dashboard) ───────────────────────── */
.app-admin-table-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  z-index: 1;
}
.app-admin-table-card::before {
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
.app-admin-table-card:hover::before { opacity: 1; }
.app-admin-table-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}
.app-admin-table-card .card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.app-admin-table-card .card-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.app-admin-table-card .card-title i {
  color: var(--gold);
  font-size: .78rem;
}
.app-admin-table-card .card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}

/* Premium Table */
.app-admin-table-wrapper {
  overflow-x: auto;
}
.app-admin-table {
  width: 100%;
  border-collapse: collapse;
}
.app-admin-table thead {
  background: #07101E;
  border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .app-admin-table thead { background: var(--surface); }
.app-admin-table thead th {
  padding: 1rem 1.2rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  white-space: nowrap;
}
.app-admin-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
  animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both;
}
.app-admin-table tbody tr:hover {
  background: rgba(59,130,246,.04);
}
.app-admin-table tbody td {
  padding: 1rem 1.2rem;
  vertical-align: middle;
}

.app-number {
  font-family: 'DM Mono', monospace;
  font-weight: 700;
  font-size: .85rem;
  color: var(--navy-light);
  letter-spacing: -.02em;
  text-decoration: none;
  transition: color .12s;
}
.app-number:hover {
  color: var(--info-light, #93C5FD);
  text-decoration: none;
}

.applicant-name {
  font-weight: 600;
  color: var(--text);
}
.app-type-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .7rem;
  background: rgba(200,145,26,.12);
  border: 1px solid rgba(200,145,26,.2);
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 500;
  color: var(--gold-light);
}
.officer-name {
  font-size: .8rem;
  color: var(--text-soft);
  display: flex;
  align-items: center;
  gap: .3rem;
}
.stage-text {
  font-size: .78rem;
  color: var(--text-soft);
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
  margin-bottom: 0;
}

/* Premium Pagination */
.app-admin-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.5rem;
  gap: 0.3rem;
}
.app-admin-pagination a,
.app-admin-pagination span {
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
.app-admin-pagination a:hover {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: #fff;
  transform: translateY(-2px);
}
.app-admin-pagination .current {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: #fff;
}

/* Responsive */
@media (max-width: 768px) {
  .app-admin-hero-inner { flex-direction: column; text-align: center; }
  .app-admin-stats-row { flex-wrap: wrap; }
  .app-admin-search-form { flex-direction: column; }
  .app-admin-search-group { width: 100%; }
  .app-admin-table thead th,
  .app-admin-table tbody td { padding: 0.75rem; }
}
</style>

<!-- Hero Section (matching dashboard) -->
<div class="app-admin-hero app-admin-animate">
  <div class="app-admin-hero-mesh"></div>
  <div class="app-admin-hero-grid"></div>
  <div class="app-admin-hero-inner">
    <div class="app-admin-hero-left">
      <div class="app-admin-hero-icon"><i class="fa fa-list-alt"></i></div>
      <div>
        <div class="app-admin-hero-eyebrow">System Administrator</div>
        <div class="app-admin-hero-name">All Applications</div>
        <div class="app-admin-hero-meta">
          <span class="app-admin-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="app-admin-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="app-admin-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Full System View
          </span>
        </div>
      </div>
    </div>
    <div class="app-admin-hero-right">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="app-admin-stats-row app-admin-animate app-admin-animate-d1">
  <div class="app-admin-stat-mini hover-card">
    <div class="value"><?= $total ?></div>
    <div class="label">Total Applications</div>
  </div>
  <div class="app-admin-stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'In-Progress')) ?></div>
    <div class="label">In Progress</div>
  </div>
  <div class="app-admin-stat-mini hover-card">
    <div class="value" style="color: #F59E0B;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'Pending')) ?></div>
    <div class="label">Pending</div>
  </div>
  <div class="app-admin-stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= count(array_filter($apps, fn($a) => $a['status'] === 'Completed')) ?></div>
    <div class="label">Completed</div>
  </div>
</div>

<!-- Search Card with Custom Select Dropdowns -->
<div class="app-admin-search-card app-admin-animate app-admin-animate-d2 hover-card" id="searchCard">
  <form method="GET" class="app-admin-search-form" id="filterForm">
    <div class="app-admin-search-group" style="flex:2;">
      <label><i class="fa fa-search"></i> Search</label>
      <div class="app-admin-search-input">
        <i class="fa fa-search"></i>
        <input type="text" name="q" id="searchInput" value="<?= e($search) ?>" placeholder="Search by name, application number, or national ID...">
      </div>
    </div>
    <div class="app-admin-search-group">
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
    <div class="app-admin-search-group">
      <label><i class="fa fa-passport"></i> Passport Type</label>
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
    <div class="app-admin-actions">
      <button type="submit" class="btn btn-primary" style="padding: .7rem 1.2rem;">
        <i class="fa fa-search"></i> Filter
      </button>
      <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline" style="padding: .7rem 1rem;">
        <i class="fa fa-times"></i>
      </a>
    </div>
  </form>
</div>

<!-- Applications Table Card -->
<div class="app-admin-table-card app-admin-animate app-admin-animate-d3 hover-card" id="tableCard">
  <div class="card-header">
    <div class="card-title">
      <i class="fa fa-table-list"></i> Application List
      <span class="card-badge"><?= $total ?> total</span>
    </div>
    <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline btn-sm">
      <i class="fa fa-chart-bar"></i> Export Report
    </a>
  </div>
  <div class="app-admin-table-wrapper">
    <table class="app-admin-table">
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
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <div class="empty-icon">
                <i class="fa fa-inbox"></i>
              </div>
              <h3>No applications found</h3>
              <p>Try adjusting your search or filter criteria.</p>
            </div>
           </td>
         </tr>
      <?php else: 
        $idx = 0;
        foreach ($apps as $a): 
      ?>
        <tr style="--i: <?= $idx++ ?>">
          <td>
            <a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $a['id'] ?>" class="app-number">
              <?= e($a['application_number']) ?>
            </a>
           </td>
          <td>
            <span class="applicant-name"><?= e($a['full_name']) ?></span>
           </td>
          <td>
            <span class="app-type-badge">
              <i class="fa fa-passport"></i> <?= e($a['passport_type']) ?>
            </span>
           </td>
          <td>
            <span class="officer-name">
              <i class="fa fa-user-circle"></i> <?= e($a['officer_name']) ?>
            </span>
           </td>
          <td>
            <span class="stage-text"><?= e($a['current_stage']) ?></span>
           </td>
          <td><?= statusBadge($a['status']) ?></td>
          <td style="font-size: .75rem; color: var(--muted); white-space: nowrap;">
            <i class="fa fa-calendar"></i> <?= e($a['application_date']) ?>
           </td>
          <td>
            <a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $a['id'] ?>" class="action-btn" title="View Application">
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
<div class="app-admin-pagination app-admin-animate app-admin-animate-d4">
  <?php if ($page > 1): ?>
    <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>">
      <i class="fa fa-chevron-left"></i>
    </a>
  <?php endif; ?>
  
  <?php 
  $start = max(1, $page - 2);
  $end = min($pages, $page + 2);
  if ($start > 1): ?>
    <a href="?page=1&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>">1</a>
    <?php if ($start > 2): ?><span>...</span><?php endif; ?>
  <?php endif; ?>
  
  <?php for ($p = $start; $p <= $end; $p++): ?>
    <?php if ($p == $page): ?>
      <span class="current"><?= $p ?></span>
    <?php else: ?>
      <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  
  <?php if ($end < $pages): ?>
    <?php if ($end < $pages - 1): ?><span>...</span><?php endif; ?>
    <a href="?page=<?= $pages ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>"><?= $pages ?></a>
  <?php endif; ?>
  
  <?php if ($page < $pages): ?>
    <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>">
      <i class="fa fa-chevron-right"></i>
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card');
  
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

// Custom Select Dropdown Initialization
(function() {
  function initCustomSelect(selectId, inputId) {
    const container = document.getElementById(selectId);
    if (!container) return;
    
    const trigger = container.querySelector('.custom-select-trigger');
    const dropdown = container.querySelector('.custom-select-dropdown');
    const options = container.querySelectorAll('.custom-select-option');
    const hiddenInput = document.getElementById(inputId);
    const selectedTextSpan = trigger.querySelector('.selected-text');
    
    // Set initial selected state
    const currentValue = hiddenInput.value;
    options.forEach(opt => {
      if (opt.dataset.value === currentValue) {
        opt.classList.add('selected');
        selectedTextSpan.textContent = opt.textContent;
      }
    });
    
    // Toggle dropdown
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = dropdown.classList.contains('show');
      // Close all other dropdowns
      document.querySelectorAll('.custom-select-dropdown.show').forEach(d => {
        if (d !== dropdown) d.classList.remove('show');
      });
      document.querySelectorAll('.custom-select-trigger.open').forEach(t => {
        if (t !== trigger) t.classList.remove('open');
      });
      
      dropdown.classList.toggle('show');
      trigger.classList.toggle('open');
      
      if (dropdown.classList.contains('show')) {
        dropdown.style.zIndex = '9999';
      }
    });
    
    // Select option
    options.forEach(opt => {
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        const value = opt.dataset.value;
        const text = opt.textContent;
        
        hiddenInput.value = value;
        selectedTextSpan.textContent = text;
        
        options.forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        
        dropdown.classList.remove('show');
        trigger.classList.remove('open');
        
        document.getElementById('filterForm').submit();
      });
    });
    
    document.addEventListener('click', (e) => {
      if (!container.contains(e.target)) {
        dropdown.classList.remove('show');
        trigger.classList.remove('open');
      }
    });
  }
  
  initCustomSelect('statusSelect', 'statusInput');
  initCustomSelect('typeSelect', 'typeInput');
})();

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

<style>
.action-btn {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--muted);
  transition: all .2s cubic-bezier(.34,1.56,.64,1);
  text-decoration: none;
}
.action-btn:hover {
  transform: scale(1.1);
  border-color: var(--navy-light);
  color: var(--navy-light);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>