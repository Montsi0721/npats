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

<!-- Hero Section (matching dashboard) -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-box-open"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Officer</div>
        <div class="hero-name">Passport Release Module</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Release & Collection
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/officer/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="stats-row animate animate-d1">
  <div class="stat-mini ready hover-card">
    <div class="value"><?= count($readyApps) ?></div>
    <div class="label">Ready for Collection</div>
  </div>
  <div class="stat-mini released hover-card">
    <div class="value"><?= count($releasedList) ?></div>
    <div class="label">Total Released</div>
  </div>
</div>

<!-- Ready for Collection Section -->
<div class="card animate animate-d2 hover-card" id="readyCard">
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
    <div class="table-wrapper">
      <table class="table">
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
<div class="card animate animate-d3 hover-card" id="releasedCard">
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
    <div class="table-wrapper">
      <table class=table">
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