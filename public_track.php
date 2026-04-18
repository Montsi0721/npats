<?php
require_once __DIR__ . '/includes/config.php';

$appNum   = trim($_GET['app_num'] ?? '');
$app      = null;
$stages   = [];
$notFound = false;

if ($appNum !== '') {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM passport_applications WHERE application_number = ?');
    $stmt->execute([$appNum]);
    $app  = $stmt->fetch();

    if ($app) {
        $s = $db->prepare('SELECT * FROM processing_stages WHERE application_id = ? ORDER BY FIELD(stage_name,
            "Application Submitted","Document Verification","Biometric Capture",
            "Background Check","Passport Printing","Ready for Collection","Passport Released")');
        $s->execute([$app['id']]);
        $stages = $s->fetchAll();
    } else {
        $notFound = true;
    }
}

$allStages = [
    'Application Submitted','Document Verification','Biometric Capture',
    'Background Check','Passport Printing','Ready for Collection','Passport Released'
];
// Build a map
$stageMap = [];
foreach ($stages as $st) $stageMap[$st['stage_name']] = $st;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Track Application — NPATS</title>
<script>(function(){var t=localStorage.getItem('npats_theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}else{document.documentElement.removeAttribute('data-theme');}})()</script>
<link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body style="background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;">
<div style="width:100%;max-width:640px;">

  <div class="card">
    <div style="text-align:center;margin-bottom:1.2rem;">
      <i class="fa fa-passport" style="font-size:2.5rem;color:var(--navy-light);"></i>
      <h1 style="font-size:1.4rem;color:var(--navy-light);margin-top:.4rem;">Track Your Application</h1>
      <p style="color:var(--muted);font-size:.85rem;">Enter your application number to check status</p>
    </div>

    <form method="GET" action="" style="display:flex;gap:.6rem;margin-bottom:1.2rem;">
      <input type="text" name="app_num" value="<?= e($appNum) ?>" placeholder="e.g. NPATS-2026-AB1C2D" style="flex:1;" required>
      <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Track</button>
    </form>

    <?php if ($notFound): ?>
    <div class="alert alert-error"><i class="fa fa-exclamation-circle"></i> No application found with number <strong><?= e($appNum) ?></strong>. Please check and try again.</div>

    <?php elseif ($app): ?>
    <!-- Application Summary -->
    <div style="background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:1rem;margin-bottom:1.2rem;">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
        <div>
          <p style="font-size:.75rem;color:var(--muted);">APPLICATION NUMBER</p>
          <p style="font-weight:700;color:var(--navy-light);font-size:1.1rem;"><?= e($app['application_number']) ?></p>
        </div>
        <span class="status-badge status-<?= strtolower(str_replace(' ','-',$app['status'])) ?>">
          <?= e($app['status']) ?>
        </span>
      </div>
      <hr class="divider">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.85rem;">
        <div><span style="color:var(--muted);">Applicant:</span> <strong><?= e($app['full_name']) ?></strong></div>
        <div><span style="color:var(--muted);">Passport Type:</span> <strong><?= e($app['passport_type']) ?></strong></div>
        <div><span style="color:var(--muted);">Application Date:</span> <?= e($app['application_date']) ?></div>
        <div><span style="color:var(--muted);">Current Stage:</span> <strong><?= e($app['current_stage']) ?></strong></div>
      </div>
    </div>

    <!-- Progress Tracker -->
    <h3 style="font-size:.95rem;color:var(--navy-light);margin-bottom:.8rem;"><i class="fa fa-list-check"></i> Processing Progress</h3>
    <div class="stage-list">
      <?php foreach ($allStages as $i => $stageName):
        $st     = $stageMap[$stageName] ?? null;
        $status = $st['status'] ?? 'Pending';
        $cls    = 'pending';
        $icon   = ($i+1);
        if ($status === 'Completed')   { $cls = 'done';     $icon = '✓'; }
        elseif ($status === 'Rejected') { $cls = 'rejected'; $icon = '✗'; }
        elseif ($status === 'In-Progress') { $cls = 'active'; $icon = '●'; }
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
          <p style="font-size:.78rem;color:var(--muted);margin-top:.2rem;"><?= e($st['comments']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p class="text-center mt-2" style="font-size:.8rem;">
      <a href="<?= APP_URL ?>/index.php"><i class="fa fa-arrow-left"></i> Back to Login</a>
    </p>
  </div>
</div>
<script src="<?= APP_URL ?>/js/main.js"></script>
</body></html>
