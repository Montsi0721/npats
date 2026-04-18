<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('applicant');
$db  = getDB();
$uid = $_SESSION['user_id'];

$count = fn($q,$p=[]) => (function() use($db,$q,$p){
    $st = $db->prepare($q); $st->execute($p); return (int)$st->fetchColumn();
})();

$myApps   = $count('SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=?', [$uid]);
$pending  = $count("SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=? AND status='Pending'", [$uid]);
$inProg   = $count("SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=? AND status='In-Progress'", [$uid]);
$complete = $count("SELECT COUNT(*) FROM passport_applications WHERE applicant_user_id=? AND status='Completed'", [$uid]);

$latest = $db->prepare('SELECT * FROM passport_applications WHERE applicant_user_id=? ORDER BY created_at DESC LIMIT 1');
$latest->execute([$uid]);
$latest = $latest->fetch();

$allStages = [
    'Application Submitted', 'Document Verification', 'Biometric Capture',
    'Background Check', 'Passport Printing', 'Ready for Collection', 'Passport Released',
];
$stageMap = [];
if ($latest) {
    $sq = $db->prepare('SELECT * FROM processing_stages WHERE application_id=?
        ORDER BY FIELD(stage_name,
            "Application Submitted","Document Verification","Biometric Capture",
            "Background Check","Passport Printing","Ready for Collection","Passport Released")');
    $sq->execute([$latest['id']]);
    foreach ($sq->fetchAll() as $s) $stageMap[$s['stage_name']] = $s;
}

$pageTitle = 'My Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-gauge"></i> My Dashboard</h1>
    <p class="page-subtitle">Welcome, <?= e($_SESSION['user_name']) ?></p>
  </div>
</div>

<!-- ── Make Application CTA ───────────────────────────────── -->
<div class="make-app-banner">
  <div class="make-app-text">
    <div class="make-app-heading">
      <i class="fa fa-passport"></i> Ready to apply for a passport?
    </div>
    <p class="make-app-sub">
      Visit any passport office with your documents. The officer will create your application
      and link it to your account — you can then track every step here.
    </p>
  </div>
  <a href="<?= APP_URL ?>/applicant/track.php" class="btn btn-gold">
    <i class="fa fa-magnifying-glass"></i> Track My Application
  </a>
</div>

<style>
.make-app-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  background: linear-gradient(135deg, #0F1D35 0%, #142545 100%);
  border: 1px solid #2A3A55;
  border-left: 4px solid var(--gold);
  border-radius: var(--radius-lg);
  padding: 1.4rem 1.75rem;
  margin-bottom: 1.75rem;
}
html[data-theme="light"] .make-app-banner {
  background: linear-gradient(135deg, #EEF4FF 0%, #E6EDF8 100%);
  border-color: #C8D8F0;
  border-left-color: var(--gold);
}
.make-app-heading {
  font-size: 1.05rem;
  font-weight: 600;
  color: #E2E8F4;
  margin-bottom: .3rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
html[data-theme="light"] .make-app-heading { color: var(--navy); }
.make-app-heading i { color: var(--gold-light); }
html[data-theme="light"] .make-app-heading i { color: var(--gold); }
.make-app-sub {
  font-size: .82rem;
  color: rgba(255,255,255,.6);
  max-width: 520px;
  line-height: 1.55;
}
html[data-theme="light"] .make-app-sub { color: var(--text-soft); }
</style>

<!-- ── Stat cards ─────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-icon"><i class="fa fa-file-lines"></i></div>
    <div><div class="stat-num"><?= $myApps ?></div><div class="stat-label">My Applications</div></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-icon"><i class="fa fa-clock"></i></div>
    <div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
  </div>
  <div class="stat-card teal">
    <div class="stat-icon"><i class="fa fa-rotate"></i></div>
    <div><div class="stat-num"><?= $inProg ?></div><div class="stat-label">In Progress</div></div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="fa fa-circle-check"></i></div>
    <div><div class="stat-num"><?= $complete ?></div><div class="stat-label">Completed</div></div>
  </div>
</div>

<?php if ($latest): ?>
<!-- ── Latest application + progress ──────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:1.2rem;" class="app-dash-grid">

  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-passport"></i> Latest Application</span>
      <?= statusBadge($latest['status']) ?>
    </div>
    <div style="font-size:.9rem;display:flex;flex-direction:column;gap:.55rem;">
      <div>
        <div class="info-label">Application Number</div>
        <div class="info-value" style="font-size:1.05rem;font-weight:600;color:var(--navy-light);">
          <?= e($latest['application_number']) ?>
        </div>
      </div>
      <div class="info-grid">
        <div>
          <div class="info-label">Passport Type</div>
          <div class="info-value"><?= e($latest['passport_type']) ?></div>
        </div>
        <div>
          <div class="info-label">Applied On</div>
          <div class="info-value"><?= e($latest['application_date']) ?></div>
        </div>
        <div style="grid-column:1/-1;">
          <div class="info-label">Current Stage</div>
          <div class="info-value" style="font-weight:500;"><?= e($latest['current_stage']) ?></div>
        </div>
      </div>
    </div>
    <div class="mt-2 btn-group">
      <a href="<?= APP_URL ?>/applicant/track.php?app_num=<?= urlencode($latest['application_number']) ?>"
         class="btn btn-primary btn-sm"><i class="fa fa-magnifying-glass"></i> Track Progress</a>
      <a href="<?= APP_URL ?>/applicant/my_applications.php"
         class="btn btn-ghost btn-sm">View all</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-list-check"></i> Processing Progress</span>
    </div>
    <div class="stage-list">
      <?php foreach ($allStages as $i => $stageName):
        $st     = $stageMap[$stageName] ?? null;
        $status = $st['status'] ?? 'Pending';
        $cls    = match($status) {
            'Completed'   => 'done',
            'In-Progress' => 'active',
            'Rejected'    => 'rejected',
            default       => 'pending',
        };
        $icon = match($status) {
            'Completed'   => '<i class="fa fa-check"   style="font-size:.65rem;"></i>',
            'Rejected'    => '<i class="fa fa-xmark"   style="font-size:.65rem;"></i>',
            'In-Progress' => '<i class="fa fa-circle-dot" style="font-size:.65rem;"></i>',
            default       => $i + 1,
        };
      ?>
      <div class="stage-item <?= $cls ?>" style="padding:.6rem 0;">
        <div class="stage-dot" style="width:28px;height:28px;font-size:.7rem;"><?= $icon ?></div>
        <div class="stage-info">
          <h4 style="font-size:.85rem;"><?= e($stageName) ?></h4>
          <?= statusBadge($status) ?>
          <?php if ($st && $st['updated_at']): ?>
          <div class="stage-meta">
            <i class="fa fa-calendar-days"></i>
            <?= e(date('d M Y', strtotime($st['updated_at']))) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php else: ?>
<!-- ── Empty state ────────────────────────────────────────── -->
<div class="card" style="text-align:center;padding:3.5rem 2rem;">
  <div style="width:72px;height:72px;background:var(--info-bg);border-radius:50%;
              display:flex;align-items:center;justify-content:center;
              margin:0 auto 1.2rem;font-size:1.8rem;color:var(--info);">
    <i class="fa fa-passport"></i>
  </div>
  <h3 style="color:var(--text);margin-bottom:.4rem;font-weight:500;">No applications yet</h3>
  <p style="color:var(--muted);font-size:.88rem;max-width:380px;margin:0 auto 1.5rem;line-height:1.6;">
    Visit a passport office with your documents and ask an officer to register your application.
    Once created, you can track it here or by application number.
  </p>
  <div class="btn-group" style="justify-content:center;">
    <a href="<?= APP_URL ?>/applicant/track.php" class="btn btn-primary">
      <i class="fa fa-magnifying-glass"></i> Track by Application Number
    </a>
  </div>
</div>
<?php endif; ?>

<style>
.app-dash-grid { grid-template-columns: 1fr 1.6fr; }
@media(max-width:700px) { .app-dash-grid { grid-template-columns: 1fr; } }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
