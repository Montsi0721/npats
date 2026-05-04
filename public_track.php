<?php
require_once __DIR__ . '/includes/config.php';

$db     = getDB();
$app    = null;
$stages = [];
$error  = null;

$query = trim($_GET['app_num'] ?? '');

if ($query !== '') {
    $stmt = $db->prepare('
        SELECT pa.*, u.full_name AS officer_name
        FROM   passport_applications pa
        LEFT   JOIN users u ON u.id = pa.officer_id
        WHERE  pa.application_number = ?
        LIMIT  1
    ');
    $stmt->execute([$query]);
    $app = $stmt->fetch();

    if ($app) {
        $sStmt = $db->prepare('
            SELECT * FROM processing_stages
            WHERE  application_id = ?
            ORDER  BY FIELD(stage_name,
                "Application Submitted",
                "Document Verification",
                "Biometric Capture",
                "Background Check",
                "Passport Printing",
                "Ready for Collection",
                "Passport Released")
        ');
        $sStmt->execute([$app['id']]);
        $stages = $sStmt->fetchAll();
    } else {
        $error = 'No application found with that number. Please check and try again.';
    }
}

function progressPct(array $stages): int {
    if (empty($stages)) return 0;
    $done = count(array_filter($stages, fn($s) => $s['status'] === 'Completed'));
    return (int) round(($done / count($stages)) * 100);
}

$pageTitle = 'Track Application';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-passport"></i></div>
      <div>
        <div class="hero-eyebrow">Passport tracker</div>
        <div class="hero-name">Track Your Application</div>
        <div class="hero-meta">
          <p class="page-subtitle">Enter your application number to check its current status</p>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="search-card animate animate-d1">
    <form method="GET" class="search-form">
        <div class="search-group" style="flex: 2;">
            <label><i class="fa fa-hashtag"></i> Application Number</label>
            <div class="search-input">
                <i class="fa fa-search"></i>
                <input
                    type="text"
                    name="app_num"
                    value="<?= e($query) ?>"
                    placeholder="e.g. APP-2024-00001"
                    autocomplete="off"
                    autofocus
                >
            </div>
        </div>
        <div class="actions" style="padding: 0; flex-direction: row; gap: .5rem;">
            <button type="submit" class="btn-primary btn-sm">
                <i class="fa fa-search"></i> Track
            </button>
            <?php if ($query !== ''): ?>
            <a href="<?= APP_URL ?>/public_track.php" class="btn-outline btn-sm">
                <i class="fa fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($error): ?>

<!-- ── Error ───────────────────────────────────────────────── -->
<div class="alert alert-error animate animate-d2">
    <i class="fa fa-circle-exclamation"></i>
    <div><?= e($error) ?></div>
</div>

<?php elseif ($app): ?>
<?php
    $pct         = progressPct($stages);
    $totalDone   = count(array_filter($stages, fn($s) => $s['status'] === 'Completed'));
    $totalStages = count($stages);
    $statusClass = match ($app['status']) {
        'In-Progress' => 'in-progress',
        'Completed'   => 'completed',
        'Rejected'    => 'rejected',
        default       => 'pending',
    };
    $statusIcon = match ($app['status']) {
        'In-Progress' => 'fa-spinner fa-spin',
        'Completed'   => 'fa-circle-check',
        'Rejected'    => 'fa-circle-xmark',
        default       => 'fa-clock',
    };
?>

<!-- ── Application hero ─────────────────────────────────────── -->
<div class="app-hero animate animate-d2">
    <div class="app-hero-inner">

        <?php if (!empty($app['photo_path']) && file_exists(UPLOAD_DIR . $app['photo_path'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($app['photo_path']) ?>" alt="" class="app-hero-photo">
        <?php else: ?>
            <div class="app-hero-ph"><i class="fa fa-user"></i></div>
        <?php endif; ?>

        <div>
            <div class="app-hero-name"><?= e($app['full_name']) ?></div>

            <div class="app-hero-meta">
                <span><i class="fa fa-hashtag"></i><?= e($app['application_number']) ?></span>
                <span><i class="fa fa-passport"></i><?= e($app['passport_type']) ?> Passport</span>
                <span><i class="fa fa-calendar"></i>Applied <?= e($app['application_date']) ?></span>
                <?php if (!empty($app['officer_name'])): ?>
                <span><i class="fa fa-user-tie"></i>Officer: <?= e($app['officer_name']) ?></span>
                <?php endif; ?>
            </div>

            <div style="margin-top: .9rem;">
                <span class="status-badge-large <?= $statusClass ?>">
                    <i class="fa <?= $statusIcon ?>"></i> <?= e($app['status']) ?>
                </span>
            </div>

            <?php if ($app['status'] !== 'Rejected'): ?>
            <div style="margin-top: 1rem; max-width: 360px;">
                <div class="progress-stats" style="margin-bottom: .4rem;">
                    <span><?= $totalDone ?> of <?= $totalStages ?> stages complete</span>
                    <span><?= $pct ?>%</span>
                </div>
                <div class="progress-bar-container" style="width: 100%;">
                    <div class="progress-bar-fill" style="width: <?= $pct ?>%;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php if ($app['status'] === 'Rejected'): ?>
<div class="alert alert-error animate animate-d3">
    <i class="fa fa-circle-xmark"></i>
    <div>
        <strong>Application Rejected</strong><br>
        Your application has been rejected. Please visit the passport office with your original documents for further assistance.
    </div>
</div>
<?php elseif ($app['current_stage'] === 'Passport Released'): ?>
<div class="alert alert-success animate animate-d3">
    <i class="fa fa-passport"></i>
    <div>
        <strong>Passport Released</strong><br>
        Your passport has been released. Please bring your national ID when collecting.
    </div>
</div>
<?php elseif ($app['current_stage'] === 'Ready for Collection'): ?>
<div class="alert alert-info animate animate-d3">
    <i class="fa fa-circle-check"></i>
    <div>
        <strong>Ready for Collection!</strong><br>
        Your passport is ready. Please visit the passport office to collect it.
    </div>
</div>
<?php endif; ?>

<div class="grid animate animate-d3">

    <div class="stages-card">
        <div class="stages-header">
            <div class="stages-title">
                <i class="fa fa-list-check"></i> Processing Timeline
            </div>
            <span class="card-badge"><?= $totalDone ?>/<?= $totalStages ?> done</span>
        </div>

        <div class="timeline">
            <?php
            $idx = 0;
            foreach ($stages as $stage):
                $stateClass = match ($stage['status']) {
                    'Completed'   => 'completed',
                    'In-Progress' => 'progress',
                    'Rejected'    => 'rejected',
                    default       => 'pending',
                };
                $dotContent = match ($stateClass) {
                    'completed' => '<i class="fa fa-check"></i>',
                    'rejected'  => '<i class="fa fa-times"></i>',
                    'progress'  => '<i class="fa fa-spinner fa-spin"></i>',
                    default     => ($idx + 1),
                };
                $statusBadge = match ($stage['status']) {
                    'Completed'   => 'status-completed',
                    'In-Progress' => 'status-progress',
                    'Rejected'    => 'status-rejected',
                    default       => 'status-pending',
                };
            ?>
            <div class="timeline-item <?= $stateClass ?>" style="--i: <?= $idx++ ?>;">
                <div class="timeline-marker">
                    <div class="marker-dot"><?= $dotContent ?></div>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-stage"><?= e($stage['stage_name']) ?></span>
                        <span class="timeline-status <?= $statusBadge ?>"><?= e($stage['status']) ?></span>
                    </div>
                    <?php if (!empty($stage['updated_at'])): ?>
                    <div class="timeline-meta">
                        <span><i class="fa fa-clock"></i> <?= e(date('M d, Y', strtotime($stage['updated_at']))) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($stage['comments'])): ?>
                    <div class="timeline-comments">
                        <i class="fa fa-comment-dots"></i> <?= e($stage['comments']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div>

        <!-- Application details -->
        <div class="sidebar-card">
            <div class="card-header">
                <div class="card-title"><i class="fa fa-circle-info"></i> Application Info</div>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div>
                        <div class="info-label">App Number</div>
                        <div class="info-value monospace" style="color: var(--navy-light); font-weight: 700;">
                            <?= e($app['application_number']) ?>
                        </div>
                    </div>
                    <div>
                        <div class="info-label">Type</div>
                        <div class="info-value"><?= e($app['passport_type']) ?></div>
                    </div>
                    <div>
                        <div class="info-label">Applied</div>
                        <div class="info-value"><?= e($app['application_date']) ?></div>
                    </div>
                    <div>
                        <div class="info-label">Current Stage</div>
                        <div class="info-value"><?= e($app['current_stage']) ?></div>
                    </div>
                    <div>
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?= e($app['gender']) ?></div>
                    </div>
                    <div>
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?= e($app['date_of_birth']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mini stats -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="value"><?= $totalDone ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: #60A5FA;">
                    <?= count(array_filter($stages, fn($s) => $s['status'] === 'In-Progress')) ?>
                </div>
                <div class="label">In Progress</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--muted);">
                    <?= count(array_filter($stages, fn($s) => $s['status'] === 'Pending')) ?>
                </div>
                <div class="label">Pending</div>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="card-header">
                <div class="card-title"><i class="fa fa-bag-shopping"></i> When Collecting</div>
            </div>
            <div class="card-body">
                <ul class="permission-list">
                    <li><i class="fa fa-check"></i> Original National ID</li>
                    <li><i class="fa fa-check"></i> Application number (this page)</li>
                    <li><i class="fa fa-check"></i> Payment receipt (if applicable)</li>
                    <li><i class="fa fa-circle-info"></i> Mon–Fri, 8am–4pm</li>
                </ul>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="track-quick-actions">
            <a href="<?= APP_URL ?>/public_track.php" class="btn-outline">
                <i class="fa fa-rotate-left"></i> Track Another
            </a>
            <button type="button" onclick="window.print()" class="btn-outline">
                <i class="fa fa-print"></i> Print
            </button>
        </div>

    </div>
</div>

<?php else: ?>

<div class="card animate animate-d2">
    <div class="card-header">
        <div class="card-title"><i class="fa fa-circle-question"></i> How it works</div>
    </div>
    <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-passport"></i></div>
        <h3>Track your passport application</h3>
        <p>Enter the application number you received when you submitted your application to see its current status and processing stages.</p>
    </div>
    <ul class="step-list" style="max-width: 440px; margin: 0 auto 1rem;">
        <li class="step-item">
            <div class="step-icon info"><i class="fa fa-hashtag"></i></div>
            <div>
                <div class="step-title">Find your application number</div>
                <div class="step-desc">It starts with <span style="font-family:monospace;color:var(--navy-light);">APP-</span> and was given to you at submission.</div>
            </div>
        </li>
        <li class="step-item">
            <div class="step-icon gold"><i class="fa fa-keyboard"></i></div>
            <div>
                <div class="step-title">Enter it above</div>
                <div class="step-desc">Type or paste your application number into the search box and click Track.</div>
            </div>
        </li>
        <li class="step-item">
            <div class="step-icon success"><i class="fa fa-list-check"></i></div>
            <div>
                <div class="step-title">View live status</div>
                <div class="step-desc">See which stages are complete, what's in progress, and when your passport is ready.</div>
            </div>
        </li>
    </ul>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>