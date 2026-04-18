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
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-chart-bar"></i> Reports</h1>
  <button class="btn btn-outline" data-print><i class="fa fa-print"></i> Print Report</button>
</div>

<!-- Date Filter -->
<div class="card no-print">
  <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
    <div class="form-group"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Apply Filter</button>
    <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline"><i class="fa fa-times"></i> Reset</a>
  </form>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.2rem;margin-bottom:1.5rem;" class="report-grid">
  <!-- By Status -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-chart-pie"></i> By Status</span></div>
    <p style="font-size:2rem;font-weight:700;color:var(--navy-light);"><?= $totalRange ?></p>
    <p style="color:var(--muted);font-size:.8rem;margin-bottom:.8rem;">Total in period</p>
    <?php foreach ($byStatus as $s): ?>
    <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
      <span><span class="status-badge status-<?= strtolower(str_replace(' ','-',$s['status'])) ?>"><?= e($s['status']) ?></span></span>
      <strong><?= $s['cnt'] ?></strong>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- By Type -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-passport"></i> By Passport Type</span></div>
    <?php foreach ($byType as $t): ?>
    <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.9rem;">
      <span><?= e($t['passport_type']) ?></span>
      <strong><?= $t['cnt'] ?></strong>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- By Officer -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-user-tie"></i> By Officer</span></div>
    <?php if (empty($byOfficer)): ?>
    <p class="text-muted" style="font-size:.85rem;">No data.</p>
    <?php else: foreach ($byOfficer as $o): ?>
    <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
      <span><?= e($o['full_name']) ?></span>
      <strong><?= $o['cnt'] ?></strong>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Ready for Collection Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa fa-box-open"></i> Passports Ready for Collection (<?= count($readyList) ?>)</span>
  </div>
  <?php if (empty($readyList)): ?>
  <p class="text-muted text-center" style="padding:1.5rem;">No passports ready for collection.</p>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>App Number</th><th>Applicant</th><th>Phone</th><th>Email</th><th>App Date</th></tr></thead>
      <tbody>
      <?php foreach ($readyList as $r): ?>
      <tr>
        <td><?= e($r['application_number']) ?></td>
        <td><?= e($r['full_name']) ?></td>
        <td><?= e($r['phone']) ?></td>
        <td><?= e($r['email']) ?></td>
        <td><?= e($r['application_date']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<style>.report-grid { grid-template-columns:1fr 1fr 1fr; } @media(max-width:800px){ .report-grid { grid-template-columns:1fr; } }</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
