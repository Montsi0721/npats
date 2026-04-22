<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

// ── Date filter ─────────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Stats in range
$totalRange   = $db->prepare('SELECT COUNT(*) FROM passport_applications WHERE application_date BETWEEN ? AND ?');
$totalRange->execute([$from,$to]);
$totalRange = $totalRange->fetchColumn();

$byStatus = $db->prepare("SELECT status, COUNT(*) as cnt FROM passport_applications WHERE application_date BETWEEN ? AND ? GROUP BY status");
$byStatus->execute([$from,$to]);
$byStatus = $byStatus->fetchAll();

$byType = $db->prepare("SELECT passport_type, COUNT(*) as cnt FROM passport_applications WHERE application_date BETWEEN ? AND ? GROUP BY passport_type");
$byType->execute([$from,$to]);
$byType = $byType->fetchAll();

$byOfficer = $db->prepare("SELECT u.full_name, COUNT(pa.id) as cnt FROM passport_applications pa JOIN users u ON u.id=pa.officer_id WHERE pa.application_date BETWEEN ? AND ? GROUP BY pa.officer_id ORDER BY cnt DESC");
$byOfficer->execute([$from,$to]);
$byOfficer = $byOfficer->fetchAll();

$readyList = $db->query("SELECT pa.application_number, pa.full_name, pa.phone, pa.email, pa.application_date FROM passport_applications pa WHERE pa.current_stage='Ready for Collection' ORDER BY pa.application_date")->fetchAll();

$pageTitle = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   REPORTS PAGE — Premium Edition with Custom Date Pickers
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
@keyframes modalSlide {
  from { opacity: 0; transform: translateY(30px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.report-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.report-animate-d1 { animation-delay:.06s }
.report-animate-d2 { animation-delay:.12s }
.report-animate-d3 { animation-delay:.18s }
.report-animate-d4 { animation-delay:.24s }

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
.report-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .report-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.report-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.report-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.report-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.report-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.report-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.report-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.report-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.report-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.report-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.report-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.report-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.report-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.report-hero-meta-chip i { font-size: .62rem; }

.report-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Stats Row (mini stats) ───────────────────────────────── */
.report-stats-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.report-stat-mini {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0.75rem 1.25rem;
  flex: 1;
  min-width: 100px;
  transition: all 0.2s;
  cursor: pointer;
}
.report-stat-mini:hover {
  transform: translateY(-2px);
  border-color: rgba(59,130,246,0.3);
  background: rgba(59,130,246,.02);
}
.report-stat-mini .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--gold-light);
  line-height: 1;
}
.report-stat-mini .label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── Filter Card with Custom Date Pickers ────────────────── */
.filter-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: visible;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  margin-bottom: 1.5rem;
  position: relative;
  z-index: 10;
}
.filter-card::before {
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
.filter-card:hover::before { opacity: 1; }
.filter-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}
.filter-form {
  padding: 1.25rem 1.5rem;
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: flex-end;
}
.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  position: relative;
}
.filter-group label {
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.filter-group label i {
  font-size: .65rem;
}
/* Custom Date Picker Inputs */
.date-picker-input {
  position: relative;
  cursor: pointer;
}
.date-picker-input input {
  padding: .7rem 2.2rem .7rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .85rem;
  transition: all .2s;
  cursor: pointer;
  width: 180px;
}
.date-picker-input input:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.date-picker-input .calendar-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  pointer-events: none;
  font-size: 0.85rem;
}
/* Custom Calendar Dropdown */
.custom-calendar {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 35px -10px rgba(0,0,0,0.4), 0 0 0 1px rgba(59,130,246,.1);
  z-index: 10000;
  display: none;
  animation: dropdownSlide 0.2s ease;
  width: 280px;
}
.custom-calendar.show {
  display: block;
}
.calendar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  background: linear-gradient(135deg, rgba(59,130,246,.1), transparent);
  border-bottom: 1px solid var(--border);
}
.calendar-nav {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: var(--text-soft);
}
.calendar-nav:hover {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: #fff;
  transform: scale(1.05);
}
.calendar-month-year {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text);
}
.calendar-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  padding: 0.6rem 0;
  background: rgba(59,130,246,.05);
  border-bottom: 1px solid var(--border);
}
.calendar-weekday {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--gold-light);
}
.calendar-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  padding: 0.5rem;
  gap: 2px;
}
.calendar-day {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s;
  color: var(--text);
  background: transparent;
}
.calendar-day:hover {
  background: rgba(59,130,246,.15);
  transform: scale(1.05);
}
.calendar-day.selected {
  background: linear-gradient(135deg, #1D5A9E, #3B82F6);
  color: white;
  box-shadow: 0 2px 8px rgba(59,130,246,.4);
}
.calendar-day.other-month {
  color: var(--muted);
  opacity: 0.5;
}
.calendar-day.today {
  border: 1px solid var(--gold-light);
  font-weight: 800;
}
.calendar-footer {
  padding: 0.6rem 1rem;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  background: rgba(0,0,0,.2);
}
.calendar-clear, .calendar-today-btn {
  font-size: 0.65rem;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}
.calendar-clear {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--muted);
}
.calendar-clear:hover {
  background: var(--danger);
  border-color: var(--danger);
  color: white;
}
.calendar-today-btn {
  background: linear-gradient(135deg, #1D5A9E, #3B82F6);
  color: white;
}
.calendar-today-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(59,130,246,.4);
}
.filter-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.quick-date-btns {
  display: flex;
  gap: 0.4rem;
  margin-top: 0.5rem;
}
.quick-date-btn {
  font-size: 0.65rem;
  padding: 0.2rem 0.6rem;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  color: var(--text-soft);
  cursor: pointer;
  transition: all 0.2s;
}
.quick-date-btn:hover {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: #fff;
}

/* ── Report Cards (Premium) ───────────────────────────────── */
.report-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.report-card::before {
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
.report-card:hover::before { opacity: 1; }
.report-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.report-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.report-card-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.report-card-title i {
  color: var(--gold);
  font-size: .78rem;
}

.report-card-body {
  padding: 1.2rem 1.5rem;
}

/* Total Stats Display */
.total-stat {
  text-align: center;
  margin-bottom: 1.2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}
.total-number {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--navy-light);
  letter-spacing: -.03em;
  line-height: 1;
}
.total-label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-top: 0.3rem;
}

/* Stat List Items */
.stat-list-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.7rem 0;
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.stat-list-item:last-child {
  border-bottom: none;
}
.stat-list-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: .85rem;
  color: var(--text-soft);
}
.stat-list-badge {
  display: inline-flex;
  padding: .15rem .6rem;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 500;
}
.stat-list-value {
  font-weight: 700;
  color: var(--text);
  font-size: .9rem;
}

/* Progress Bar for Stats */
.stat-progress {
  margin-top: 0.2rem;
  height: 4px;
  background: var(--surface);
  border-radius: 4px;
  overflow: hidden;
}
.stat-progress-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.5s ease;
}

/* Ready Table */
.ready-table-wrapper {
  overflow-x: auto;
}
.ready-table {
  width: 100%;
  border-collapse: collapse;
}
.ready-table thead {
  background: #07101E;
  border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .ready-table thead { background: var(--surface); }
.ready-table thead th {
  padding: 1rem 1.2rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  white-space: nowrap;
}
.ready-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.ready-table tbody tr:hover {
  background: rgba(59,130,246,.04);
}
.ready-table tbody td {
  padding: 1rem 1.2rem;
  vertical-align: middle;
  font-size: .85rem;
}
.app-number-link {
  font-family: 'DM Mono', monospace;
  font-weight: 700;
  font-size: .85rem;
  color: var(--navy-light);
  text-decoration: none;
  transition: color .12s;
}
.app-number-link:hover {
  color: var(--info-light, #93C5FD);
  text-decoration: none;
}
.ready-applicant {
  font-weight: 600;
  color: var(--text);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 2rem;
}
.empty-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 0.8rem;
  background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(200,145,26,.05));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  color: var(--muted);
}
.empty-state h4 {
  font-size: .9rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 0.2rem;
}
.empty-state p {
  color: var(--muted);
  font-size: .75rem;
}

/* Report Grid */
.report-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.2rem;
  margin-bottom: 1.5rem;
}
@media(max-width:900px){ .report-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width:600px){ .report-grid { grid-template-columns: 1fr; } }

/* Responsive */
@media (max-width: 768px) {
  .report-hero-inner { flex-direction: column; text-align: center; }
  .filter-form { flex-direction: column; align-items: stretch; }
  .filter-group { width: 100%; }
  .date-picker-input input { width: 100%; }
  .custom-calendar { width: 100%; left: 0; right: 0; }
}

/* Print Styles */
@media print {
  .no-print { display: none; }
  .report-card { break-inside: avoid; page-break-inside: avoid; }
  body { background: white; }
  .report-hero { background: #fff; color: #000; border: 1px solid #ddd; }
  .report-hero::before { display: none; }
}
</style>

<!-- Hero Section -->
<div class="report-hero report-animate">
  <div class="report-hero-mesh"></div>
  <div class="report-hero-grid"></div>
  <div class="report-hero-inner">
    <div class="report-hero-left">
      <div class="report-hero-icon"><i class="fa fa-chart-bar"></i></div>
      <div>
        <div class="report-hero-eyebrow">System Administrator</div>
        <div class="report-hero-name">Reports & Analytics</div>
        <div class="report-hero-meta">
          <span class="report-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="report-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="report-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-chart-line"></i> Performance Overview
          </span>
        </div>
      </div>
    </div>
    <div class="report-hero-right no-print">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
      <button class="btn btn-primary" onclick="window.print()">
        <i class="fa fa-print"></i> Print Report
      </button>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="report-stats-row report-animate report-animate-d1">
  <div class="report-stat-mini hover-card">
    <div class="value"><?= $totalRange ?></div>
    <div class="label">Applications in Period</div>
  </div>
  <div class="report-stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= count($readyList) ?></div>
    <div class="label">Ready for Collection</div>
  </div>
  <div class="report-stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= count($byOfficer) ?></div>
    <div class="label">Active Officers</div>
  </div>
</div>

<!-- Date Filter Card with Custom Date Pickers -->
<div class="filter-card report-animate report-animate-d2 hover-card no-print">
  <form method="GET" class="filter-form" id="reportForm">
    <div class="filter-group">
      <label><i class="fa fa-calendar-alt"></i> From Date</label>
      <div class="date-picker-input" id="fromDatePicker">
        <input type="text" id="fromDateDisplay" placeholder="Select start date" autocomplete="off" value="<?= date('F d, Y', strtotime($from)) ?>">
        <i class="fa fa-calendar-alt calendar-icon"></i>
        <input type="hidden" name="from" id="fromDateHidden" value="<?= e($from) ?>">
        <div class="custom-calendar" id="fromCalendar"></div>
      </div>
    </div>
    <div class="filter-group">
      <label><i class="fa fa-calendar-alt"></i> To Date</label>
      <div class="date-picker-input" id="toDatePicker">
        <input type="text" id="toDateDisplay" placeholder="Select end date" autocomplete="off" value="<?= date('F d, Y', strtotime($to)) ?>">
        <i class="fa fa-calendar-alt calendar-icon"></i>
        <input type="hidden" name="to" id="toDateHidden" value="<?= e($to) ?>">
        <div class="custom-calendar" id="toCalendar"></div>
      </div>
    </div>
    <div class="filter-actions">
      <button type="submit" class="btn btn-primary">
        <i class="fa fa-filter"></i> Apply Filter
      </button>
      <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline">
        <i class="fa fa-times"></i> Reset
      </a>
    </div>
  </form>
  <div class="quick-date-btns" style="padding: 0 1.5rem 1rem 1.5rem;">
    <button type="button" class="quick-date-btn" data-range="today">Today</button>
    <button type="button" class="quick-date-btn" data-range="yesterday">Yesterday</button>
    <button type="button" class="quick-date-btn" data-range="week">This Week</button>
    <button type="button" class="quick-date-btn" data-range="month">This Month</button>
    <button type="button" class="quick-date-btn" data-range="quarter">Last 90 Days</button>
    <button type="button" class="quick-date-btn" data-range="year">This Year</button>
  </div>
</div>

<!-- Report Cards Grid -->
<div class="report-grid">
  <!-- By Status Card -->
  <div class="report-card report-animate report-animate-d3 hover-card">
    <div class="report-card-header">
      <span class="report-card-title"><i class="fa fa-chart-pie"></i> By Status</span>
    </div>
    <div class="report-card-body">
      <div class="total-stat">
        <div class="total-number"><?= $totalRange ?></div>
        <div class="total-label">Total Applications</div>
      </div>
      <?php 
      $statusColors = [
        'Pending' => '#F59E0B',
        'In-Progress' => '#60A5FA',
        'Completed' => '#34D399',
        'Rejected' => '#F87171'
      ];
      foreach ($byStatus as $s): 
        $color = $statusColors[$s['status']] ?? '#6B7280';
        $percent = $totalRange > 0 ? round(($s['cnt'] / $totalRange) * 100) : 0;
      ?>
      <div class="stat-list-item">
        <div class="stat-list-label">
          <span class="stat-list-badge" style="background:<?= $color ?>20; color:<?= $color ?>;"><?= e($s['status']) ?></span>
        </div>
        <div class="stat-list-value"><?= $s['cnt'] ?> (<?= $percent ?>%)</div>
      </div>
      <div class="stat-progress">
        <div class="stat-progress-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byStatus)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-chart-simple"></i></div>
        <h4>No data available</h4>
        <p>No applications in selected period</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- By Type Card -->
  <div class="report-card report-animate report-animate-d3 hover-card">
    <div class="report-card-header">
      <span class="report-card-title"><i class="fa fa-passport"></i> By Passport Type</span>
    </div>
    <div class="report-card-body">
      <?php 
      $typeColors = [
        'Normal' => '#3B82F6',
        'Express' => '#F59E0B'
      ];
      foreach ($byType as $t): 
        $color = $typeColors[$t['passport_type']] ?? '#6B7280';
        $percent = $totalRange > 0 ? round(($t['cnt'] / $totalRange) * 100) : 0;
      ?>
      <div class="stat-list-item">
        <div class="stat-list-label">
          <i class="fa fa-passport" style="color: <?= $color ?>;"></i>
          <span><?= e($t['passport_type']) ?></span>
        </div>
        <div class="stat-list-value"><?= $t['cnt'] ?> (<?= $percent ?>%)</div>
      </div>
      <div class="stat-progress">
        <div class="stat-progress-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byType)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-passport"></i></div>
        <h4>No data available</h4>
        <p>No applications in selected period</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- By Officer Card -->
  <div class="report-card report-animate report-animate-d3 hover-card">
    <div class="report-card-header">
      <span class="report-card-title"><i class="fa fa-user-tie"></i> By Officer</span>
    </div>
    <div class="report-card-body">
      <?php 
      $maxCnt = !empty($byOfficer) ? max(array_column($byOfficer, 'cnt')) : 1;
      $colors = ['#60A5FA', '#34D399', '#F59E0B', '#F87171', '#A78BFA'];
      foreach ($byOfficer as $idx => $o): 
        $percent = round(($o['cnt'] / $maxCnt) * 100);
        $color = $colors[$idx % count($colors)];
      ?>
      <div class="stat-list-item">
        <div class="stat-list-label">
          <i class="fa fa-user-circle" style="color: <?= $color ?>;"></i>
          <span><?= e($o['full_name']) ?></span>
        </div>
        <div class="stat-list-value"><?= $o['cnt'] ?></div>
      </div>
      <div class="stat-progress">
        <div class="stat-progress-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byOfficer)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-users"></i></div>
        <h4>No data available</h4>
        <p>No applications processed in selected period</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Ready for Collection Table Card -->
<div class="report-card report-animate report-animate-d4 hover-card">
  <div class="report-card-header">
    <span class="report-card-title"><i class="fa fa-box-open"></i> Passports Ready for Collection</span>
    <span class="card-badge" style="background: rgba(52,211,153,.15); color: #34D399; padding: 0.2rem 0.7rem; border-radius: 20px; font-size: 0.7rem;">
      <?= count($readyList) ?> pending
    </span>
  </div>
  <?php if (empty($readyList)): ?>
    <div class="empty-state" style="padding: 2rem;">
      <div class="empty-icon"><i class="fa fa-check-circle" style="color: #34D399;"></i></div>
      <h4>No passports ready for collection</h4>
      <p>All clear! Applications ready for pickup will appear here.</p>
    </div>
  <?php else: ?>
  <div class="ready-table-wrapper">
    <table class="ready-table">
      <thead>
        <tr>
          <th>App Number</th>
          <th>Applicant</th>
          <th>Phone</th>
          <th>Email</th>
          <th>App Date</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($readyList as $r): ?>
      <tr>
        <td><a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $r['id'] ?? '' ?>" class="app-number-link"><?= e($r['application_number']) ?></a></td>
        <td><span class="ready-applicant"><?= e($r['full_name']) ?></span></td>
        <td><?= e($r['phone']) ?></td>
        <td><?= e($r['email']) ?></td>
        <td><span style="font-size: .75rem; color: var(--muted);"><i class="fa fa-calendar"></i> <?= e($r['application_date']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .report-card, .filter-card');
  
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

// ============================================================
// PREMIUM CUSTOM CALENDAR DATE PICKER
// ============================================================
class PremiumDatePicker {
  constructor(inputId, hiddenInputId, containerId, options = {}) {
    this.input = document.getElementById(inputId);
    this.hiddenInput = document.getElementById(hiddenInputId);
    this.container = document.getElementById(containerId);
    this.currentDate = new Date();
    this.selectedDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : null;
    this.maxDate = options.maxDate ? new Date(options.maxDate) : null;
    this.minDate = options.minDate ? new Date(options.minDate) : null;
    this.onSelect = options.onSelect || null;
    
    this.init();
  }
  
  init() {
    this.renderCalendar();
    this.attachEvents();
  }
  
  renderCalendar() {
    const year = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();
    const firstDayOfMonth = new Date(year, month, 1);
    const lastDayOfMonth = new Date(year, month + 1, 0);
    const startDayOfWeek = firstDayOfMonth.getDay();
    const daysInMonth = lastDayOfMonth.getDate();
    
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const weekdays = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
    
    let calendarHtml = `
      <div class="calendar-header">
        <div class="calendar-nav" data-action="prev"><i class="fa fa-chevron-left"></i></div>
        <div class="calendar-month-year">${monthNames[month]} ${year}</div>
        <div class="calendar-nav" data-action="next"><i class="fa fa-chevron-right"></i></div>
      </div>
      <div class="calendar-weekdays">
        ${weekdays.map(day => `<div class="calendar-weekday">${day}</div>`).join('')}
      </div>
      <div class="calendar-days">
    `;
    
    for (let i = 0; i < startDayOfWeek; i++) {
      const prevDate = prevMonthLastDay - startDayOfWeek + i + 1;
      calendarHtml += `<div class="calendar-day other-month" data-date="${year}-${month}-${prevDate}">${prevDate}</div>`;
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
      const dateObj = new Date(year, month, day);
      const dateStr = this.formatDate(dateObj);
      const isSelected = this.selectedDate && this.formatDate(this.selectedDate) === dateStr;
      const isToday = this.formatDate(new Date()) === dateStr;
      const isDisabled = this.isDateDisabled(dateObj);
      
      let classes = 'calendar-day';
      if (isSelected) classes += ' selected';
      if (isToday) classes += ' today';
      if (isDisabled) classes += ' disabled';
      
      calendarHtml += `<div class="${classes}" data-date="${dateStr}" data-year="${year}" data-month="${month}" data-day="${day}">${day}</div>`;
    }
    
    const totalCells = 42;
    const currentCells = startDayOfWeek + daysInMonth;
    const nextMonthDays = totalCells - currentCells;
    
    for (let day = 1; day <= nextMonthDays; day++) {
      calendarHtml += `<div class="calendar-day other-month" data-date="${year}-${month + 1}-${day}">${day}</div>`;
    }
    
    calendarHtml += `
        </div>
        <div class="calendar-footer">
          <button type="button" class="calendar-clear" id="calendarClearBtn"><i class="fa fa-times-circle"></i> Clear</button>
          <button type="button" class="calendar-today-btn" id="calendarTodayBtn"><i class="fa fa-calendar-day"></i> Today</button>
        </div>
      `;
    
    this.container.innerHTML = calendarHtml;
  }
  
  formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
  
  formatDisplayDate(date) {
    if (!date) return '';
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString(undefined, options);
  }
  
  isDateDisabled(date) {
    if (this.maxDate && date > this.maxDate) return true;
    if (this.minDate && date < this.minDate) return true;
    return false;
  }
  
  selectDate(date) {
    if (this.isDateDisabled(date)) return;
    this.selectedDate = date;
    this.hiddenInput.value = this.formatDate(date);
    this.input.value = this.formatDisplayDate(date);
    this.renderCalendar();
    this.container.classList.remove('show');
    if (this.onSelect) this.onSelect(date);
  }
  
  attachEvents() {
    this.input.addEventListener('click', (e) => {
      e.stopPropagation();
      // Close other calendars
      document.querySelectorAll('.custom-calendar.show').forEach(cal => {
        if (cal !== this.container) cal.classList.remove('show');
      });
      this.container.classList.toggle('show');
      this.renderCalendar();
    });
    
    document.addEventListener('click', (e) => {
      if (!this.container.contains(e.target) && e.target !== this.input) {
        this.container.classList.remove('show');
      }
    });
    
    this.container.addEventListener('click', (e) => {
      const dayDiv = e.target.closest('.calendar-day');
      const navBtn = e.target.closest('.calendar-nav');
      const clearBtn = e.target.closest('#calendarClearBtn');
      const todayBtn = e.target.closest('#calendarTodayBtn');
      
      if (dayDiv && !dayDiv.classList.contains('disabled')) {
        const dateStr = dayDiv.dataset.date;
        if (dateStr) {
          const [year, month, day] = dateStr.split('-').map(Number);
          const newDate = new Date(year, month, day);
          if (!isNaN(newDate.getTime())) {
            this.selectDate(newDate);
          }
        }
      }
      
      if (navBtn) {
        const action = navBtn.dataset.action;
        if (action === 'prev') {
          this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
        } else if (action === 'next') {
          this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
        }
        this.renderCalendar();
      }
      
      if (clearBtn) {
        this.selectedDate = null;
        this.hiddenInput.value = '';
        this.input.value = '';
        this.renderCalendar();
        this.container.classList.remove('show');
        if (this.onSelect) this.onSelect(null);
      }
      
      if (todayBtn) {
        const today = new Date();
        if (!this.isDateDisabled(today)) {
          this.selectDate(today);
        }
      }
    });
  }
}

// Initialize date pickers
const fromPicker = new PremiumDatePicker('fromDateDisplay', 'fromDateHidden', 'fromCalendar');
const toPicker = new PremiumDatePicker('toDateDisplay', 'toDateHidden', 'toCalendar');

// Quick date range buttons
document.querySelectorAll('.quick-date-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const range = btn.dataset.range;
    const today = new Date();
    let fromDate = new Date();
    let toDate = new Date();
    
    switch(range) {
      case 'today':
        fromDate = today;
        toDate = today;
        break;
      case 'yesterday':
        fromDate = new Date(today.setDate(today.getDate() - 1));
        toDate = new Date(fromDate);
        break;
      case 'week':
        const startOfWeek = new Date(today);
        startOfWeek.setDate(today.getDate() - today.getDay());
        fromDate = startOfWeek;
        toDate = new Date();
        break;
      case 'month':
        fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
        toDate = new Date();
        break;
      case 'quarter':
        fromDate = new Date(today.setDate(today.getDate() - 90));
        toDate = new Date();
        break;
      case 'year':
        fromDate = new Date(today.getFullYear(), 0, 1);
        toDate = new Date();
        break;
    }
    
    fromPicker.selectDate(fromDate);
    toPicker.selectDate(toDate);
    
    // Submit the form
    document.getElementById('reportForm').submit();
  });
});

// Animate progress bars on load
document.addEventListener('DOMContentLoaded', function() {
  const progressBars = document.querySelectorAll('.stat-progress-fill');
  progressBars.forEach(bar => {
    const width = bar.style.width;
    bar.style.width = '0';
    setTimeout(() => {
      bar.style.width = width;
    }, 100);
  });
});
</script>

<style>
.card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
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
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>