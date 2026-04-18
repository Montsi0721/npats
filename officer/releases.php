<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];

// ── Handle release form submission ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_passport'])) {
    $appId          = (int)($_POST['application_id'] ?? 0);
    $collectionDate = $_POST['collection_date'] ?? '';
    $notes          = trim($_POST['notes'] ?? '');

    // Verify application exists and is 'Ready for Collection'
    $check = $db->prepare("SELECT * FROM passport_applications WHERE id=? AND current_stage='Ready for Collection'");
    $check->execute([$appId]);
    $appRow = $check->fetch();

    if (!$appRow) {
        flash('error', 'Application not found or not ready for collection.');
    } elseif (!$collectionDate) {
        flash('error', 'Collection date is required.');
    } else {
        // Insert release record
        $ins = $db->prepare('INSERT INTO passport_releases (application_id,collection_date,applicant_name,officer_id,notes) VALUES (?,?,?,?,?)');
        $ins->execute([$appId, $collectionDate, $appRow['full_name'], $uid, $notes]);

        // Update stage
        $db->prepare("UPDATE processing_stages SET status='Completed',officer_id=?,updated_at=NOW() WHERE application_id=? AND stage_name='Passport Released'")
           ->execute([$uid, $appId]);

        // Update application
        $db->prepare("UPDATE passport_applications SET current_stage='Passport Released', status='Completed', updated_at=NOW() WHERE id=?")
           ->execute([$appId]);

        // Notify applicant
        if ($appRow['applicant_user_id']) {
            addNotification($appRow['applicant_user_id'], $appId,
                "Your passport ({$appRow['application_number']}) has been released. Collection date: $collectionDate.");
        }

        logActivity($uid, 'PASSPORT_RELEASED', "Released passport for {$appRow['application_number']}");
        flash('success', "Passport released for {$appRow['full_name']} (App# {$appRow['application_number']}).");
    }
    redirect(APP_URL . '/officer/releases.php');
}

// ── Load data ────────────────────────────────────────────
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
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-box-open"></i> Passport Release Module</h1>
  <button class="btn btn-outline" data-print><i class="fa fa-print"></i> Print</button>
</div>

<!-- Ready for Collection -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa fa-clock"></i> Ready for Collection (<?= count($readyApps) ?>)</span>
  </div>
  <?php if (empty($readyApps)): ?>
  <p class="text-muted text-center" style="padding:1.5rem;"><i class="fa fa-check-circle" style="font-size:2rem;color:var(--success);display:block;margin-bottom:.5rem;"></i> No passports awaiting collection.</p>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>App Number</th><th>Applicant</th><th>Phone</th><th>Type</th><th>App Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($readyApps as $a): ?>
      <tr>
        <td><strong><?= e($a['application_number']) ?></strong></td>
        <td><?= e($a['full_name']) ?></td>
        <td><?= e($a['phone']) ?></td>
        <td><?= e($a['passport_type']) ?></td>
        <td><?= e($a['application_date']) ?></td>
        <td>
          <button class="btn btn-sm btn-success"
            onclick="openReleaseModal(<?= $a['id'] ?>, '<?= addslashes(e($a['full_name'])) ?>', '<?= addslashes(e($a['application_number'])) ?>')">
            <i class="fa fa-check"></i> Release
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Released Passports -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa fa-passport"></i> Released Passports</span>
    <span class="text-muted"><?= count($releasedList) ?> records</span>
  </div>
  <?php if (empty($releasedList)): ?>
  <p class="text-muted text-center" style="padding:1.5rem;">No released passports yet.</p>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>App Number</th><th>Applicant</th><th>Collection Date</th><th>Released By</th><th>Notes</th></tr></thead>
      <tbody>
      <?php foreach ($releasedList as $r): ?>
      <tr>
        <td><?= e($r['application_number']) ?></td>
        <td><?= e($r['applicant_name']) ?></td>
        <td><?= e($r['collection_date']) ?></td>
        <td><?= e($r['officer_name']) ?></td>
        <td style="font-size:.82rem;color:var(--muted);"><?= e($r['notes'] ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Release Modal -->
<div class="modal-overlay" id="releaseModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-passport"></i> Release Passport</span>
      <button class="modal-close" data-modal-close="releaseModal">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="release_passport" value="1">
      <input type="hidden" name="application_id" id="releaseAppId">
      <div class="modal-body">
        <div class="alert alert-info"><i class="fa fa-info-circle"></i> Releasing passport for: <strong id="releaseAppName"></strong> — App# <strong id="releaseAppNum"></strong></div>
        <div class="form-group" style="margin-bottom:.9rem;">
          <label>Collection Date *</label>
          <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Notes (optional)</label>
          <textarea name="notes" placeholder="Any additional remarks…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="releaseModal">Cancel</button>
        <button type="submit" class="btn btn-success" onclick="return confirm('Confirm passport release?')"><i class="fa fa-check"></i> Confirm Release</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReleaseModal(id, name, appNum) {
  document.getElementById('releaseAppId').value  = id;
  document.getElementById('releaseAppName').textContent = name;
  document.getElementById('releaseAppNum').textContent  = appNum;
  openModal('releaseModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
