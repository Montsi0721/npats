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

// Handle stage update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stage'])) {
    $stageName = $_POST['stage_name'] ?? '';
    $status    = $_POST['stage_status'] ?? '';
    $comments  = trim($_POST['comments'] ?? '');
    $validStatuses = ['Pending','In-Progress','Completed','Rejected'];

    if (in_array($stageName, $allStages) && in_array($status, $validStatuses)) {
        $check = $db->prepare('SELECT id FROM processing_stages WHERE application_id=? AND stage_name=?');
        $check->execute([$id, $stageName]);
        if ($check->fetch()) {
            $db->prepare('UPDATE processing_stages SET status=?,officer_id=?,comments=?,updated_at=NOW() WHERE application_id=? AND stage_name=?')
               ->execute([$status,$uid,$comments,$id,$stageName]);
        } else {
            $db->prepare('INSERT INTO processing_stages (application_id,stage_name,status,officer_id,comments) VALUES (?,?,?,?,?)')
               ->execute([$id,$stageName,$status,$uid,$comments]);
        }

        $db->prepare('UPDATE passport_applications SET current_stage=?, updated_at=NOW() WHERE id=?')
           ->execute([$stageName, $id]);

        if ($status === 'Rejected') {
            $db->prepare("UPDATE passport_applications SET status='Rejected' WHERE id=?")->execute([$id]);
        } elseif ($stageName === 'Passport Released' && $status === 'Completed') {
            $db->prepare("UPDATE passport_applications SET status='Completed' WHERE id=?")->execute([$id]);
        } else {
            $db->prepare("UPDATE passport_applications SET status='In-Progress' WHERE id=?")->execute([$id]);
        }

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

<style>
/* Premium Manage Application Styling */
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes shimmer {
    0% { background-position: -200% center; }
    100% { background-position: 200% center; }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}

.manage-header {
    background: linear-gradient(135deg, #0B1525 0%, #0D1B2F 100%);
    border-radius: var(--radius-lg);
    padding: 1.5rem 2rem;
    margin-bottom: 1.8rem;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(59,130,246,.2);
}

.manage-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--gold-light), #3B82F6, var(--gold-light), transparent);
    animation: shimmer 3s linear infinite;
    background-size: 200% auto;
}

.manage-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.manage-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.manage-header-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, rgba(59,130,246,.2), rgba(200,145,26,.1));
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--gold-light);
}

.manage-header-text h1 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.manage-header-text p {
    color: rgba(255,255,255,.5);
    font-size: .8rem;
    margin: .2rem 0 0;
}

/* Applicant Profile Card */
.profile-card {
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1.8rem;
    transition: all .2s;
}

.profile-card-header {
    background: linear-gradient(135deg, var(--surface), var(--bg-alt));
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.profile-card-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 600;
    color: var(--text);
}

.profile-card-title i {
    color: var(--gold);
}

.profile-badge {
    padding: .25rem .8rem;
    background: rgba(200,145,26,.15);
    border: 1px solid rgba(200,145,26,.3);
    border-radius: 20px;
    font-size: .7rem;
    font-weight: 600;
    color: var(--gold-light);
}

.profile-body {
    padding: 1.5rem;
    display: flex;
    gap: 1.8rem;
    flex-wrap: wrap;
}

.profile-photo {
    flex-shrink: 0;
}

.profile-photo img {
    width: 120px;
    height: 140px;
    object-fit: cover;
    border-radius: var(--radius);
    border: 3px solid rgba(59,130,246,.3);
    box-shadow: 0 8px 20px rgba(0,0,0,.3);
}

.profile-photo-placeholder {
    width: 120px;
    height: 140px;
    background: linear-gradient(135deg, var(--surface), var(--bg-alt));
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--muted);
}

.profile-info {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}

.info-field {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.info-label {
    font-size: .65rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
}

.info-value {
    font-size: .9rem;
    font-weight: 500;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.info-value i {
    color: var(--gold-light);
    font-size: .75rem;
    width: 18px;
}

/* Stages Timeline */
.stages-card {
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.stages-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.stages-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 600;
    color: var(--text);
}

.stages-title i {
    color: var(--gold);
}

.stages-progress {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-stats {
    font-size: .75rem;
    color: var(--text-soft);
}

.progress-bar-container {
    width: 150px;
    height: 6px;
    background: var(--surface);
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    border-radius: 3px;
    transition: width .5s ease;
}

/* Timeline */
.timeline {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.timeline-item {
    display: flex;
    gap: 1.2rem;
    position: relative;
    padding-bottom: 1.5rem;
    animation: fadeSlideUp .4s ease-out both;
    animation-delay: calc(var(--i, 0) * 0.05s);
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 19px;
    top: 44px;
    width: 2px;
    height: calc(100% - 20px);
    background: linear-gradient(to bottom, var(--border), transparent);
}

.timeline-item.completed:not(:last-child)::before {
    background: linear-gradient(to bottom, var(--success), var(--border));
}

.timeline-marker {
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.marker-dot {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
    font-weight: 600;
    transition: all .2s;
}

.timeline-item.pending .marker-dot {
    background: var(--surface);
    border: 2px solid var(--border);
    color: var(--muted);
}

.timeline-item.progress .marker-dot {
    background: linear-gradient(135deg, #1D5A9E, #3B82F6);
    border: 2px solid #3B82F6;
    color: #fff;
    box-shadow: 0 0 15px rgba(59,130,246,.4);
}

.timeline-item.completed .marker-dot {
    background: linear-gradient(135deg, #065F46, #34D399);
    border: 2px solid #34D399;
    color: #fff;
}

.timeline-item.rejected .marker-dot {
    background: linear-gradient(135deg, #7A1E1E, #F87171);
    border: 2px solid #F87171;
    color: #fff;
}

.timeline-content {
    flex: 1;
    padding-bottom: 0.5rem;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.timeline-stage {
    font-weight: 700;
    font-size: .95rem;
    color: var(--text);
}

.timeline-status {
    font-size: .7rem;
    padding: .2rem .6rem;
    border-radius: 20px;
    font-weight: 600;
}

.status-pending {
    background: rgba(251,191,36,.15);
    color: #FBBF24;
    border: 1px solid rgba(251,191,36,.3);
}

.status-progress {
    background: rgba(59,130,246,.15);
    color: #60A5FA;
    border: 1px solid rgba(59,130,246,.3);
}

.status-completed {
    background: rgba(52,211,153,.15);
    color: #34D399;
    border: 1px solid rgba(52,211,153,.3);
}

.status-rejected {
    background: rgba(248,113,113,.15);
    color: #F87171;
    border: 1px solid rgba(248,113,113,.3);
}

.timeline-meta {
    display: flex;
    gap: 1rem;
    font-size: .7rem;
    color: var(--muted);
    margin-bottom: 0.5rem;
}

.timeline-meta i {
    margin-right: 0.2rem;
}

.timeline-comments {
    background: var(--surface);
    border-left: 3px solid var(--gold);
    padding: 0.5rem 0.8rem;
    border-radius: 8px;
    font-size: .8rem;
    color: var(--text-soft);
    margin-top: 0.5rem;
}

.update-btn {
    padding: .3rem .9rem;
    font-size: .75rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-soft);
    cursor: pointer;
    transition: all .2s;
}

.update-btn:hover {
    background: var(--navy-light);
    border-color: var(--navy-light);
    color: #fff;
}

/* Modal Premium */
.modal-premium {
    background: var(--bg-alt);
    border-radius: var(--radius-lg);
    max-width: 500px;
    width: 90%;
    border: 1px solid var(--border);
    box-shadow: 0 25px 50px rgba(0,0,0,.5);
    animation: fadeSlideUp .3s ease;
}

.modal-premium-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-premium-header h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-premium-header h3 i {
    color: var(--gold);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: var(--muted);
    padding: 0.2rem;
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
}

/* Responsive */
@media (max-width: 768px) {
    .profile-body {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .timeline-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .timeline-meta {
        flex-wrap: wrap;
    }
}
</style>

<!-- Header -->
<div class="manage-header">
    <div class="manage-header-content">
        <div class="manage-header-title">
            <div class="manage-header-icon">
                <i class="fa fa-edit"></i>
            </div>
            <div class="manage-header-text">
                <h1>Manage Application</h1>
                <p>Track and update application progress</p>
            </div>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline" data-print><i class="fa fa-print"></i> Print</button>
            <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-outline"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<!-- Applicant Profile Card -->
<div class="profile-card">
    <div class="profile-card-header">
        <div class="profile-card-title">
            <i class="fa fa-user-circle"></i> Applicant Information
            <span class="profile-badge"><?= e($app['application_number']) ?></span>
        </div>
        <?= statusBadge($app['status']) ?>
    </div>
    <div class="profile-body">
        <div class="profile-photo">
            <?php if ($app['photo_path'] && file_exists(UPLOAD_DIR . $app['photo_path'])): ?>
                <img src="<?= UPLOAD_URL . e($app['photo_path']) ?>" alt="Applicant Photo">
            <?php else: ?>
                <div class="profile-photo-placeholder">
                    <i class="fa fa-user"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <div class="info-field">
                <span class="info-label">Full Name</span>
                <span class="info-value"><i class="fa fa-user"></i> <?= e($app['full_name']) ?></span>
            </div>
            <div class="info-field">
                <span class="info-label">National ID</span>
                <span class="info-value"><i class="fa fa-id-card"></i> <?= e($app['national_id']) ?></span>
            </div>
            <div class="info-field">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><i class="fa fa-birthday-cake"></i> <?= e($app['date_of_birth']) ?></span>
            </div>
            <div class="info-field">
                <span class="info-label">Gender</span>
                <span class="info-value"><i class="fa fa-venus-mars"></i> <?= e($app['gender']) ?></span>
            </div>
            <div class="info-field">
                <span class="info-label">Phone</span>
                <span class="info-value"><i class="fa fa-phone"></i> <?= e($app['phone']) ?></span>
            </div>
            <div class="info-field">
                <span class="info-label">Email</span>
                <span class="info-value"><i class="fa fa-envelope"></i> <?= e($app['email']) ?></span>
            </div>
            <div class="info-field">
                <span class="info-label">Passport Type</span>
                <span class="info-value"><i class="fa fa-passport"></i> <?= e($app['passport_type']) ?></span>
            </div>
            <div class="info-field">
                <span class="info-label">Application Date</span>
                <span class="info-value"><i class="fa fa-calendar"></i> <?= e($app['application_date']) ?></span>
            </div>
            <div class="info-field full-width">
                <span class="info-label">Address</span>
                <span class="info-value"><i class="fa fa-map-marker-alt"></i> <?= e($app['address']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Processing Stages Timeline -->
<div class="stages-card">
    <div class="stages-header">
        <div class="stages-title">
            <i class="fa fa-tasks"></i> Processing Timeline
        </div>
        <div class="stages-progress">
            <?php
            $completedCount = count(array_filter($stages, fn($s) => $s['status'] === 'Completed'));
            $progressPercent = round($completedCount / count($allStages) * 100);
            ?>
            <span class="progress-stats"><?= $completedCount ?>/<?= count($allStages) ?> stages</span>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?= $progressPercent ?>%"></div>
            </div>
        </div>
    </div>
    
    <div class="timeline">
        <?php 
        $idx = 0;
        foreach ($allStages as $stageName):
            $st = $stageMap[$stageName] ?? null;
            $status = $st['status'] ?? 'Pending';
            $statusClass = match($status) { 
                'Completed' => 'completed',
                'In-Progress' => 'progress', 
                'Rejected' => 'rejected', 
                default => 'pending' 
            };
            $statusLabel = match($status) { 
                'Completed' => 'Completed',
                'In-Progress' => 'In Progress', 
                'Rejected' => 'Rejected', 
                default => 'Pending' 
            };
            $statusBadgeClass = match($status) {
                'Completed' => 'status-completed',
                'In-Progress' => 'status-progress',
                'Rejected' => 'status-rejected',
                default => 'status-pending'
            };
            $icon = match($statusClass) {
                'completed' => '✓',
                'progress' => '●',
                'rejected' => '✗',
                default => ($idx + 1)
            };
        ?>
        <div class="timeline-item <?= $statusClass ?>" style="--i: <?= $idx++ ?>">
            <div class="timeline-marker">
                <div class="marker-dot"><?= $icon ?></div>
            </div>
            <div class="timeline-content">
                <div class="timeline-header">
                    <span class="timeline-stage"><?= e($stageName) ?></span>
                    <span class="timeline-status <?= $statusBadgeClass ?>"><?= $statusLabel ?></span>
                    <button class="update-btn" onclick="openStageModal('<?= addslashes($stageName) ?>', '<?= $status ?>', '<?= addslashes($st['comments'] ?? '') ?>')">
                        <i class="fa fa-edit"></i> Update
                    </button>
                </div>
                <?php if ($st && $st['updated_at']): ?>
                <div class="timeline-meta">
                    <span><i class="fa fa-user"></i> <?= e($st['officer_name'] ?? 'System') ?></span>
                    <span><i class="fa fa-clock"></i> <?= e(date('M d, Y H:i', strtotime($st['updated_at']))) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($st && $st['comments']): ?>
                <div class="timeline-comments">
                    <i class="fa fa-comment"></i> <?= e($st['comments']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Stage Update Modal -->
<div id="stageModal" class="modal-overlay">
    <div class="modal-premium">
        <div class="modal-premium-header">
            <h3><i class="fa fa-edit"></i> Update Stage</h3>
            <button class="modal-close" data-modal-close="stageModal">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="update_stage" value="1">
            <input type="hidden" name="stage_name" id="modalStageName">
            <div class="modal-premium-body">
                <div style="margin-bottom: 1rem; padding: 0.8rem; background: var(--surface); border-radius: var(--radius);">
                    <strong id="modalStageLabel" style="color: var(--gold-light);"></strong>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Status</label>
                    <select name="stage_status" id="modalStageStatus" class="form-control">
                        <option value="Pending">⏳ Pending</option>
                        <option value="In-Progress">🔄 In Progress</option>
                        <option value="Completed">✅ Completed</option>
                        <option value="Rejected">❌ Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Comments</label>
                    <textarea name="comments" id="modalComments" rows="3" placeholder="Add notes about this stage..."></textarea>
                </div>
            </div>
            <div class="modal-premium-footer">
                <button type="button" class="btn btn-outline" data-modal-close="stageModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStageModal(stageName, currentStatus, comments) {
    document.getElementById('modalStageName').value = stageName;
    document.getElementById('modalStageLabel').textContent = stageName;
    document.getElementById('modalStageStatus').value = currentStatus;
    document.getElementById('modalComments').value = comments;
    openModal('stageModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>