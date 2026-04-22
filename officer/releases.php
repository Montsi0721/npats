<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_passport'])) {
    $appId          = (int)($_POST['application_id'] ?? 0);
    $collectionDate = $_POST['collection_date'] ?? '';
    $notes          = trim($_POST['notes'] ?? '');

    $check = $db->prepare("SELECT * FROM passport_applications WHERE id=? AND current_stage='Ready for Collection'");
    $check->execute([$appId]);
    $appRow = $check->fetch();

    if (!$appRow) {
        flash('error', 'Application not found or not ready for collection.');
    } elseif (!$collectionDate) {
        flash('error', 'Collection date is required.');
    } else {
        $ins = $db->prepare('INSERT INTO passport_releases (application_id,collection_date,applicant_name,officer_id,notes) VALUES (?,?,?,?,?)');
        $ins->execute([$appId, $collectionDate, $appRow['full_name'], $uid, $notes]);

        $db->prepare("UPDATE processing_stages SET status='Completed',officer_id=?,updated_at=NOW() WHERE application_id=? AND stage_name='Passport Released'")
           ->execute([$uid, $appId]);

        $db->prepare("UPDATE passport_applications SET current_stage='Passport Released', status='Completed', updated_at=NOW() WHERE id=?")
           ->execute([$appId]);

        if ($appRow['applicant_user_id']) {
            addNotification($appRow['applicant_user_id'], $appId,
                "Your passport ({$appRow['application_number']}) has been released. Collection date: $collectionDate.");
        }

        logActivity($uid, 'PASSPORT_RELEASED', "Released passport for {$appRow['application_number']}");
        flash('success', "Passport released for {$appRow['full_name']} (App# {$appRow['application_number']}).");
    }
    redirect(APP_URL . '/officer/releases.php');
}

$readyApps = $db->query("SELECT * FROM passport_applications WHERE current_stage='Ready for Collection' ORDER BY application_date")->fetchAll();

$releasedList = $db->prepare('SELECT pr.*, pa.application_number, u.full_name AS officer_name
    FROM passport_releases pr
    JOIN passport_applications pa ON pa.id=pr.application_id
    JOIN users u ON u.id=pr.officer_id
    ORDER BY pr.collection_date DESC LIMIT 50');
$releasedList->execute();
$releasedList = $releasedList->fetchAll();

$pageTitle = 'Passport Releases';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   RELEASES PAGE — Premium Edition (Dashboard Matching)
   ───────────────────────────────────────────────────────────── */

/* ── Entry animations ──────────────────────────────────────── */
@keyframes fadeUp   { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn   { from{opacity:0} to{opacity:1} }
@keyframes scaleIn  { from{opacity:0;transform:scale(.94)} to{opacity:1;transform:scale(1)} }
@keyframes shimmer  {
  0%  { background-position: -200% center }
  100%{ background-position:  200% center }
}
@keyframes pulse-ring {
  0%  { box-shadow: 0 0 0 0 rgba(52,211,153,.5) }
  70% { box-shadow: 0 0 0 8px rgba(52,211,153,0) }
  100%{ box-shadow: 0 0 0 0 rgba(52,211,153,0) }
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

.rel-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.rel-animate-d1 { animation-delay:.06s }
.rel-animate-d2 { animation-delay:.12s }
.rel-animate-d3 { animation-delay:.18s }
.rel-animate-d4 { animation-delay:.24s }

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

/* ── Hero Section (matches dashboard) ────────────────────── */
.rel-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .rel-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.rel-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.rel-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.rel-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.rel-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.rel-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.rel-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.rel-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.rel-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.rel-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.rel-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.rel-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.rel-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.rel-hero-meta-chip i { font-size: .62rem; }

.rel-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Stats Row (mini stats) ───────────────────────────────── */
.rel-stats-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.rel-stat-mini {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0.75rem 1.25rem;
  flex: 1;
  min-width: 100px;
  transition: all 0.2s;
  cursor: pointer;
}
.rel-stat-mini:hover {
  transform: translateY(-2px);
  border-color: rgba(59,130,246,0.3);
  background: rgba(59,130,246,.02);
}
.rel-stat-mini .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--gold-light);
  line-height: 1;
}
.rel-stat-mini .label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}
.rel-stat-mini.ready .value { color: #F59E0B; }
.rel-stat-mini.released .value { color: #34D399; }

/* ── Section Cards (matches dashboard) ────────────────────── */
.rel-section-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  margin-bottom: 1.5rem;
  position: relative;
}
.rel-section-card::before {
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
.rel-section-card:hover::before { opacity: 1; }
.rel-section-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.section-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.section-title i {
  color: var(--gold);
  font-size: .78rem;
}
.section-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}
.section-badge.ready {
  background: rgba(245,158,11,.12);
  border-color: rgba(245,158,11,.2);
  color: #F59E0B;
}
.section-badge.released {
  background: rgba(52,211,153,.12);
  border-color: rgba(52,211,153,.2);
  color: #34D399;
}

/* Premium Tables */
.rel-table-wrapper {
  overflow-x: auto;
}
.rel-table {
  width: 100%;
  border-collapse: collapse;
}
.rel-table thead {
  background: #07101E;
  border-bottom: 1px solid var(--border);
}
html[data-theme="light"] .rel-table thead { background: var(--surface); }
.rel-table thead th {
  padding: 1rem 1.2rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  white-space: nowrap;
}
.rel-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.rel-table tbody tr:hover {
  background: rgba(59,130,246,.04);
}
.rel-table tbody td {
  padding: 1rem 1.2rem;
  vertical-align: middle;
}

.app-number {
  font-family: 'DM Mono', monospace;
  font-weight: 700;
  font-size: .85rem;
  color: var(--navy-light);
  letter-spacing: -.02em;
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
.release-date-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: .75rem;
  color: #34D399;
}
.officer-name {
  font-size: .78rem;
  color: var(--text-soft);
  display: flex;
  align-items: center;
  gap: .3rem;
}

/* Release Button */
.release-btn {
  padding: .5rem 1rem;
  background: linear-gradient(135deg, #065F46, #34D399);
  border: none;
  border-radius: 8px;
  color: #fff;
  font-size: .75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s cubic-bezier(.34,1.56,.64,1);
  display: inline-flex;
  align-items: center;
  gap: .4rem;
}
.release-btn:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 20px rgba(52,211,153,.3);
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

/* Premium Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  visibility: hidden;
  opacity: 0;
  transition: all 0.25s ease;
}
.modal-overlay.active {
  visibility: visible;
  opacity: 1;
}
.modal-premium {
  background: var(--bg-alt);
  border-radius: var(--radius-lg);
  max-width: 500px;
  width: 90%;
  border: 1px solid var(--border);
  box-shadow: 0 25px 50px rgba(0,0,0,.5);
  animation: modalSlide 0.3s cubic-bezier(.34,1.56,.64,1);
  position: relative;
}
.modal-premium-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.modal-premium-header h3 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.modal-premium-header h3 i {
  color: var(--gold);
}
.modal-close {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  cursor: pointer;
  color: var(--muted);
  transition: all .2s;
}
.modal-close:hover {
  background: var(--danger);
  border-color: var(--danger);
  color: #fff;
  transform: scale(1.05);
}
.modal-premium-body {
  padding: 1.5rem;
}
.modal-premium-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 0.8rem;
  background: var(--surface);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}
.release-info {
  background: rgba(52,211,153,.08);
  border-left: 3px solid #34D399;
  padding: 0.8rem 1rem;
  border-radius: 8px;
  margin-bottom: 1.2rem;
}
.release-info p {
  margin: 0;
  font-size: .85rem;
  color: var(--text-soft);
}
.release-info strong {
  color: #34D399;
}
.form-group label {
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: .3rem;
  display: block;
}
.form-group input,
.form-group textarea {
  width: 100%;
  padding: .7rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .85rem;
  transition: all .2s;
}
.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}

/* Responsive */
@media (max-width: 768px) {
  .rel-hero-inner { flex-direction: column; text-align: center; }
  .rel-stats-row { flex-wrap: wrap; }
  .rel-table thead th,
  .rel-table tbody td { padding: 0.75rem; }
  .modal-premium { width: 95%; margin: 1rem; }
}
</style>

<!-- Hero Section (matching dashboard) -->
<div class="rel-hero rel-animate">
  <div class="rel-hero-mesh"></div>
  <div class="rel-hero-grid"></div>
  <div class="rel-hero-inner">
    <div class="rel-hero-left">
      <div class="rel-hero-icon"><i class="fa fa-box-open"></i></div>
      <div>
        <div class="rel-hero-eyebrow">Passport Officer</div>
        <div class="rel-hero-name">Passport Release Module</div>
        <div class="rel-hero-meta">
          <span class="rel-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="rel-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="rel-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Release & Collection
          </span>
        </div>
      </div>
    </div>
    <div class="rel-hero-right">
      <a href="<?= APP_URL ?>/officer/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="rel-stats-row rel-animate rel-animate-d1">
  <div class="rel-stat-mini ready hover-card">
    <div class="value"><?= count($readyApps) ?></div>
    <div class="label">Ready for Collection</div>
  </div>
  <div class="rel-stat-mini released hover-card">
    <div class="value"><?= count($releasedList) ?></div>
    <div class="label">Total Released</div>
  </div>
</div>

<!-- Ready for Collection Section -->
<div class="rel-section-card rel-animate rel-animate-d2 hover-card" id="readyCard">
  <div class="section-header">
    <div class="section-title">
      <i class="fa fa-clock"></i> Ready for Collection
      <span class="section-badge ready"><?= count($readyApps) ?> pending</span>
    </div>
    <button class="btn btn-outline btn-sm" onclick="window.print()">
      <i class="fa fa-print"></i> Print
    </button>
  </div>
  
  <?php if (empty($readyApps)): ?>
    <div class="empty-state">
      <div class="empty-icon">
        <i class="fa fa-check-circle"></i>
      </div>
      <h3>No passports awaiting collection</h3>
      <p>All clear! Applications ready for release will appear here.</p>
    </div>
  <?php else: ?>
    <div class="rel-table-wrapper">
      <table class="rel-table">
        <thead>
          <tr>
            <th>App Number</th>
            <th>Applicant</th>
            <th>Phone</th>
            <th>Type</th>
            <th>Applied On</th>
            <th style="text-align:center;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($readyApps as $a): ?>
          <tr>
            <td><span class="app-number"><?= e($a['application_number']) ?></span></td>
            <td><span class="applicant-name"><?= e($a['full_name']) ?></span></td>
            <td style="font-size: .8rem;"><?= e($a['phone']) ?></td>
            <td><span class="app-type-badge"><?= e($a['passport_type']) ?></span></td>
            <td style="font-size: .75rem; color: var(--muted);"><?= e($a['application_date']) ?></td>
            <td style="text-align:center;">
              <button class="release-btn" onclick="openReleaseModal(<?= $a['id'] ?>, '<?= addslashes(e($a['full_name'])) ?>', '<?= addslashes(e($a['application_number'])) ?>')">
                <i class="fa fa-check"></i> Release Passport
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
       </table>
    </div>
  <?php endif; ?>
</div>

<!-- Released Passports Section -->
<div class="rel-section-card rel-animate rel-animate-d3 hover-card" id="releasedCard">
  <div class="section-header">
    <div class="section-title">
      <i class="fa fa-passport"></i> Released Passports
      <span class="section-badge released"><?= count($releasedList) ?> records</span>
    </div>
  </div>
  
  <?php if (empty($releasedList)): ?>
    <div class="empty-state">
      <div class="empty-icon">
        <i class="fa fa-inbox"></i>
      </div>
      <h3>No released passports yet</h3>
      <p>Released passports will be logged here.</p>
    </div>
  <?php else: ?>
    <div class="rel-table-wrapper">
      <table class="rel-table">
        <thead>
          <tr>
            <th>App Number</th>
            <th>Applicant</th>
            <th>Collection Date</th>
            <th>Released By</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($releasedList as $r): ?>
          <tr>
            <td><span class="app-number"><?= e($r['application_number']) ?></span></td>
            <td><span class="applicant-name"><?= e($r['applicant_name']) ?></span></td>
            <td><span class="release-date-badge"><i class="fa fa-calendar-check"></i> <?= e($r['collection_date']) ?></span></td>
            <td><span class="officer-name"><i class="fa fa-user-shield"></i> <?= e($r['officer_name']) ?></span></td>
            <td style="font-size: .78rem; color: var(--muted);"><?= e($r['notes'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
       </table>
    </div>
  <?php endif; ?>
</div>

<!-- Release Modal -->
<div id="releaseModal" class="modal-overlay">
  <div class="modal-premium">
    <div class="modal-premium-header">
      <h3><i class="fa fa-passport"></i> Release Passport</h3>
      <button class="modal-close" data-modal-close="releaseModal">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="release_passport" value="1">
      <input type="hidden" name="application_id" id="releaseAppId">
      <div class="modal-premium-body">
        <div class="release-info">
          <p><i class="fa fa-user"></i> <strong id="releaseAppName"></strong></p>
          <p><i class="fa fa-hashtag"></i> Application #<strong id="releaseAppNum"></strong></p>
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
          <label><i class="fa fa-calendar"></i> Collection Date *</label>
          <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label><i class="fa fa-sticky-note"></i> Notes (optional)</label>
          <textarea name="notes" rows="3" placeholder="Additional remarks about the collection..."></textarea>
        </div>
      </div>
      <div class="modal-premium-footer">
        <button type="button" class="btn btn-outline" data-modal-close="releaseModal">Cancel</button>
        <button type="submit" class="btn btn-success" onclick="return confirm('Confirm passport release? This action cannot be undone.')">
          <i class="fa fa-check"></i> Confirm Release
        </button>
      </div>
    </form>
  </div>
</div>

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

// Modal functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function openReleaseModal(id, name, appNum) {
  document.getElementById('releaseAppId').value = id;
  document.getElementById('releaseAppName').textContent = name;
  document.getElementById('releaseAppNum').textContent = appNum;
  openModal('releaseModal');
}

// Close modal with close buttons
document.querySelectorAll('[data-modal-close]').forEach(btn => {
  btn.addEventListener('click', () => {
    const modalId = btn.getAttribute('data-modal-close');
    closeModal(modalId);
  });
});

// Close modal when clicking overlay
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  });
});

// Escape key to close modal
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(modal => {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>