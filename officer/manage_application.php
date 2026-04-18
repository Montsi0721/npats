<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

$appQ = $db->prepare('SELECT * FROM passport_applications WHERE id=?');
$appQ->execute([$id]);
$app = $appQ->fetch();
if (!$app) { flash('error','Application not found.'); redirect(APP_URL.'/officer/applications.php'); }

$allStages = [
    'Application Submitted','Document Verification','Biometric Capture',
    'Background Check','Passport Printing','Ready for Collection','Passport Released'
];

// ── Handle stage update ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stage'])) {
    $stageName = $_POST['stage_name'] ?? '';
    $status    = $_POST['stage_status'] ?? '';
    $comments  = trim($_POST['comments'] ?? '');
    $validStatuses = ['Pending','In-Progress','Completed','Rejected'];

    if (in_array($stageName, $allStages) && in_array($status, $validStatuses)) {
        // Upsert the stage
        $check = $db->prepare('SELECT id FROM processing_stages WHERE application_id=? AND stage_name=?');
        $check->execute([$id, $stageName]);
        if ($check->fetch()) {
            $db->prepare('UPDATE processing_stages SET status=?,officer_id=?,comments=?,updated_at=NOW() WHERE application_id=? AND stage_name=?')
               ->execute([$status,$uid,$comments,$id,$stageName]);
        } else {
            $db->prepare('INSERT INTO processing_stages (application_id,stage_name,status,officer_id,comments) VALUES (?,?,?,?,?)')
               ->execute([$id,$stageName,$status,$uid,$comments]);
        }

        // Update application's current stage and overall status
        $db->prepare('UPDATE passport_applications SET current_stage=?, updated_at=NOW() WHERE id=?')
           ->execute([$stageName, $id]);

        // Overall status logic
        if ($status === 'Rejected') {
            $db->prepare("UPDATE passport_applications SET status='Rejected' WHERE id=?")->execute([$id]);
        } elseif ($stageName === 'Passport Released' && $status === 'Completed') {
            $db->prepare("UPDATE passport_applications SET status='Completed' WHERE id=?")->execute([$id]);
        } else {
            $db->prepare("UPDATE passport_applications SET status='In-Progress' WHERE id=?")->execute([$id]);
        }

        // Notify applicant if linked
        if ($app['applicant_user_id']) {
            addNotification($app['applicant_user_id'], $id,
                "Your application {$app['application_number']} stage '$stageName' is now: $status");
        }

        logActivity($uid, 'UPDATE_STAGE', "App {$app['application_number']} — $stageName → $status");
        flash('success', "Stage '$stageName' updated to '$status'.");
    } else {
        flash('error', 'Invalid stage or status value.');
    }
    redirect(APP_URL . '/officer/manage_application.php?id=' . $id);
}

// ── Reload after redirect ────────────────────────────────
$appQ->execute([$id]);
$app = $appQ->fetch();

$stagesQ = $db->prepare('SELECT ps.*, u.full_name AS officer_name FROM processing_stages ps
    LEFT JOIN users u ON u.id=ps.officer_id WHERE ps.application_id=?
    ORDER BY FIELD(ps.stage_name,"Application Submitted","Document Verification","Biometric Capture",
    "Background Check","Passport Printing","Ready for Collection","Passport Released")');
$stagesQ->execute([$id]);
$stages   = $stagesQ->fetchAll();
$stageMap = array_column($stages, null, 'stage_name');

$pageTitle = 'Manage Application';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-edit"></i> Manage Application</h1>
  <div class="btn-group">
    <button class="btn btn-outline" data-print><i class="fa fa-print"></i> Print</button>
    <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-outline"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
</div>

<!-- Application Summary Card -->
<div class="card" style="background:var(--surface);border:1px solid var(--border);">
  <div style="display:flex;align-items:flex-start;gap:1.5rem;flex-wrap:wrap;">
    <?php if ($app['photo_path'] && file_exists(UPLOAD_DIR . $app['photo_path'])): ?>
      <img src="<?= UPLOAD_URL . e($app['photo_path']) ?>" style="width:90px;height:110px;object-fit:cover;border-radius:8px;border:3px solid rgba(255,255,255,.3);" alt="Photo">
    <?php else: ?>
      <div style="width:90px;height:110px;background:rgba(255,255,255,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:rgba(255,255,255,.5);flex-shrink:0;"><i class="fa fa-user"></i></div>
    <?php endif; ?>
    <div style="flex:1;">
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem;">
        <h2 style="font-size:1.3rem;margin:0;"><?= e($app['full_name']) ?></h2>
        <span style="background:rgba(255,255,255,.2);padding:.2rem .7rem;border-radius:20px;font-size:.75rem;"><?= e($app['passport_type']) ?></span>
        <span class="status-badge status-<?= strtolower(str_replace(' ','-',$app['status'])) ?>"><?= e($app['status']) ?></span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.4rem;font-size:.85rem;opacity:.9;">
        <div><i class="fa fa-hashtag"></i> <?= e($app['application_number']) ?></div>
        <div><i class="fa fa-id-card"></i> <?= e($app['national_id']) ?></div>
        <div><i class="fa fa-birthday-cake"></i> <?= e($app['date_of_birth']) ?></div>
        <div><i class="fa fa-venus-mars"></i> <?= e($app['gender']) ?></div>
        <div><i class="fa fa-phone"></i> <?= e($app['phone']) ?></div>
        <div><i class="fa fa-envelope"></i> <?= e($app['email']) ?></div>
        <div><i class="fa fa-map-marker-alt"></i> <?= e($app['address']) ?></div>
        <div><i class="fa fa-calendar"></i> Applied: <?= e($app['application_date']) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Stage Management -->
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
      <div class="stage-info" style="flex:1;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
          <h4><?= e($stageName) ?></h4>
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
            <span class="status-badge status-<?= strtolower(str_replace(' ','-',$status)) ?>"><?= e($status) ?></span>
            <button class="btn btn-sm btn-outline no-print"
              onclick="openStageModal('<?= addslashes($stageName) ?>', '<?= $status ?>', '<?= addslashes($st['comments'] ?? '') ?>')">
              <i class="fa fa-edit"></i> Update
            </button>
          </div>
        </div>
        <?php if ($st): ?>
          <p class="stage-date">
            <?php if ($st['officer_name']): ?><i class="fa fa-user"></i> <?= e($st['officer_name']) ?> &nbsp;<?php endif; ?>
            <?php if ($st['updated_at']): ?><i class="fa fa-clock"></i> <?= e($st['updated_at']) ?><?php endif; ?>
          </p>
          <?php if ($st['comments']): ?>
          <p style="font-size:.8rem;color:var(--muted);margin-top:.2rem;background:var(--surface);padding:.4rem .6rem;border-radius:5px;"><?= e($st['comments']) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Stage Update Modal -->
<div class="modal-overlay" id="stageModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-edit"></i> Update Stage</span>
      <button class="modal-close" data-modal-close="stageModal">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="update_stage" value="1">
      <input type="hidden" name="stage_name" id="modalStageName">
      <div class="modal-body">
        <p style="margin-bottom:.8rem;font-weight:600;" id="modalStageLabel"></p>
        <div class="form-group" style="margin-bottom:.9rem;">
          <label>Status *</label>
          <select name="stage_status" id="modalStageStatus">
            <option value="Pending">Pending</option>
            <option value="In-Progress">In-Progress</option>
            <option value="Completed">Completed</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>
        <div class="form-group">
          <label>Comments</label>
          <textarea name="comments" id="modalComments" placeholder="Add any relevant notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="stageModal">Cancel</button>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Update this stage?')"><i class="fa fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openStageModal(stageName, currentStatus, comments) {
  document.getElementById('modalStageName').value  = stageName;
  document.getElementById('modalStageLabel').textContent = stageName;
  document.getElementById('modalStageStatus').value = currentStatus;
  document.getElementById('modalComments').value    = comments;
  openModal('stageModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
