<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('applicant');
$db  = getDB();
$uid = $_SESSION['user_id'];

$appNum   = trim($_GET['app_num'] ?? '');
$app      = null;
$stages   = [];
$stageMap = [];
$notFound = false;

$allStages = ['Application Submitted','Document Verification','Biometric Capture','Background Check','Passport Printing','Ready for Collection','Passport Released'];

if ($appNum !== '') {
    $stmt = $db->prepare('SELECT * FROM passport_applications WHERE application_number=?');
    $stmt->execute([$appNum]);
    $app = $stmt->fetch();
    if ($app) {
        $sq = $db->prepare('SELECT ps.*, u.full_name AS officer_name FROM processing_stages ps
            LEFT JOIN users u ON u.id=ps.officer_id WHERE ps.application_id=?
            ORDER BY FIELD(ps.stage_name,"Application Submitted","Document Verification","Biometric Capture","Background Check","Passport Printing","Ready for Collection","Passport Released")');
        $sq->execute([$app['id']]);
        $stages = $sq->fetchAll();
        foreach ($stages as $s) $stageMap[$s['stage_name']] = $s;
    } else {
        $notFound = true;
    }
}

$pageTitle = 'Track Application';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-search"></i> Track Application</h1>
</div>

<div class="card" style="max-width:680px;margin:0 auto 1.5rem;">
  <form method="GET" style="display:flex;gap:.6rem;">
    <input type="text" name="app_num" value="<?= e($appNum) ?>" placeholder="Enter application number (e.g. NPATS-2026-AB1C2D)" style="flex:1;" required>
    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Track</button>
  </form>
</div>

<?php if ($notFound): ?>
<div class="alert alert-error" style="max-width:680px;margin:0 auto;">
  <i class="fa fa-exclamation-circle"></i> No application found with number <strong><?= e($appNum) ?></strong>.
</div>

<?php elseif ($app): ?>
<div style="max-width:680px;margin:0 auto;">
  <!-- Summary -->
  <div class="card" style="background:var(--surface);border:1px solid var(--border);margin-bottom:1.2rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.8rem;">
      <div>
        <p style="opacity:.7;font-size:.75rem;">APPLICATION NUMBER</p>
        <p style="font-size:1.3rem;font-weight:700;"><?= e($app['application_number']) ?></p>
      </div>
      <span class="status-badge status-<?= strtolower(str_replace(' ','-',$app['status'])) ?>"><?= e($app['status']) ?></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.85rem;opacity:.9;">
      <div><span style="opacity:.7;">Applicant:</span> <strong><?= e($app['full_name']) ?></strong></div>
      <div><span style="opacity:.7;">Type:</span> <?= e($app['passport_type']) ?></div>
      <div><span style="opacity:.7;">Applied:</span> <?= e($app['application_date']) ?></div>
      <div><span style="opacity:.7;">Stage:</span> <?= e($app['current_stage']) ?></div>
    </div>
  </div>

  <!-- Progress -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-tasks"></i> Processing Progress</span></div>
    <div class="stage-list">
      <?php foreach ($allStages as $i => $stageName):
        $st     = $stageMap[$stageName] ?? null;
        $status = $st['status'] ?? 'Pending';
        $cls    = match($status) { 'Completed'=>'done','In-Progress'=>'active','Rejected'=>'rejected', default=>'pending' };
        $icon   = match($status) { 'Completed'=>'✓','Rejected'=>'✗','In-Progress'=>'●', default=>($i+1) };
      ?>
      <div class="stage-item <?= $cls ?>">
        <div class="stage-dot"><?= $icon ?></div>
        <div class="stage-info">
          <h4><?= e($stageName) ?></h4>
          <p><span class="status-badge status-<?= strtolower(str_replace(' ','-',$status)) ?>"><?= e($status) ?></span></p>
          <?php if ($st && $st['updated_at']): ?>
          <p class="stage-date"><i class="fa fa-calendar-alt"></i> <?= e($st['updated_at']) ?></p>
          <?php endif; ?>
          <?php if ($st && $st['comments']): ?>
          <p style="font-size:.78rem;color:var(--muted);background:var(--surface);padding:.3rem .5rem;border-radius:4px;margin-top:.2rem;"><?= e($st['comments']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
