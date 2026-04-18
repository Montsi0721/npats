<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$app = $db->prepare('SELECT pa.*, u.full_name AS officer_name, u.email AS officer_email
    FROM passport_applications pa JOIN users u ON u.id=pa.officer_id WHERE pa.id=?');
$app->execute([$id]);
$app = $app->fetch();
if (!$app) { flash('error','Application not found.'); redirect(APP_URL.'/admin/applications.php'); }

$stagesQ = $db->prepare('SELECT ps.*, u.full_name AS officer_name FROM processing_stages ps
    LEFT JOIN users u ON u.id=ps.officer_id WHERE ps.application_id=?
    ORDER BY FIELD(ps.stage_name,"Application Submitted","Document Verification","Biometric Capture","Background Check","Passport Printing","Ready for Collection","Passport Released")');
$stagesQ->execute([$id]);
$stages   = $stagesQ->fetchAll();
$stageMap = array_column($stages, null, 'stage_name');

$allStages = ['Application Submitted','Document Verification','Biometric Capture','Background Check','Passport Printing','Ready for Collection','Passport Released'];

$pageTitle = 'View Application';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-file-alt"></i> Application Details</h1>
  <div class="btn-group">
    <button class="btn btn-outline" data-print><i class="fa fa-print"></i> Print</button>
    <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.2rem;" class="app-detail-grid">
  <!-- Left: Application Info -->
  <div>
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-id-card"></i> Applicant Information</span>
        <span class="status-badge status-<?= strtolower(str_replace(' ','-',$app['status'])) ?>"><?= e($app['status']) ?></span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;font-size:.9rem;">
        <div><span style="color:var(--muted);">App Number:</span><br><strong><?= e($app['application_number']) ?></strong></div>
        <div><span style="color:var(--muted);">Passport Type:</span><br><strong><?= e($app['passport_type']) ?></strong></div>
        <div><span style="color:var(--muted);">Full Name:</span><br><?= e($app['full_name']) ?></div>
        <div><span style="color:var(--muted);">National ID:</span><br><?= e($app['national_id']) ?></div>
        <div><span style="color:var(--muted);">Date of Birth:</span><br><?= e($app['date_of_birth']) ?></div>
        <div><span style="color:var(--muted);">Gender:</span><br><?= e($app['gender']) ?></div>
        <div><span style="color:var(--muted);">Phone:</span><br><?= e($app['phone']) ?></div>
        <div><span style="color:var(--muted);">Email:</span><br><?= e($app['email']) ?></div>
        <div style="grid-column:1/-1;"><span style="color:var(--muted);">Address:</span><br><?= e($app['address']) ?></div>
        <div><span style="color:var(--muted);">Application Date:</span><br><?= e($app['application_date']) ?></div>
        <div><span style="color:var(--muted);">Officer:</span><br><?= e($app['officer_name']) ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa fa-tasks"></i> Processing Stages</span></div>
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
            <?php if ($st): ?>
              <?php if ($st['officer_name']): ?><p class="stage-date"><i class="fa fa-user"></i> <?= e($st['officer_name']) ?></p><?php endif; ?>
              <?php if ($st['updated_at']): ?><p class="stage-date"><i class="fa fa-calendar"></i> <?= e($st['updated_at']) ?></p><?php endif; ?>
              <?php if ($st['comments']): ?><p style="font-size:.78rem;color:var(--muted);"><?= e($st['comments']) ?></p><?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right: Photo -->
  <div>
    <div class="card" style="text-align:center;">
      <div class="card-header"><span class="card-title"><i class="fa fa-image"></i> Applicant Photo</span></div>
      <?php if ($app['photo_path'] && file_exists(UPLOAD_DIR . $app['photo_path'])): ?>
        <img src="<?= UPLOAD_URL . e($app['photo_path']) ?>" class="photo-preview" style="width:100%;max-width:200px;height:auto;">
      <?php else: ?>
        <div class="photo-placeholder" style="width:100%;max-width:200px;height:200px;margin:0 auto;"><i class="fa fa-user"></i></div>
        <p class="text-muted" style="font-size:.8rem;margin-top:.5rem;">No photo uploaded</p>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa fa-info-circle"></i> Quick Info</span></div>
      <p style="font-size:.85rem;"><span style="color:var(--muted);">Current Stage:</span><br><strong><?= e($app['current_stage']) ?></strong></p>
      <hr class="divider">
      <p style="font-size:.85rem;"><span style="color:var(--muted);">Created:</span><br><?= e($app['created_at']) ?></p>
      <p style="font-size:.85rem;margin-top:.5rem;"><span style="color:var(--muted);">Last Updated:</span><br><?= e($app['updated_at']) ?></p>
    </div>
  </div>
</div>

<style>.app-detail-grid { grid-template-columns: 2fr 1fr; } @media(max-width:700px){ .app-detail-grid { grid-template-columns:1fr; } }</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
