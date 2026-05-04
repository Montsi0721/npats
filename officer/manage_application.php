<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/notificationService.php';

    requireRole('officer');
    $db  = getDB();
    $uid = $_SESSION['user_id'];
    $id  = (int)($_GET['id'] ?? 0);

    $appQ = $db->prepare('SELECT * FROM passport_applications WHERE id=?');
    $appQ->execute([$id]);
    $app = $appQ->fetch();
    if (!$app) { 
        flash('error','Application not found.'); 
        redirect(APP_URL.'/officer/applications.php'); 
    }

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
                $db->prepare('UPDATE processing_stages SET status=?, officer_id=?, comments=?, updated_at=NOW() WHERE application_id=? AND stage_name=?')
                ->execute([$status, $uid, $comments, $id, $stageName]);
            } else {
                $db->prepare('INSERT INTO processing_stages (application_id, stage_name, status, officer_id, comments) VALUES (?,?,?,?,?)')
                ->execute([$id, $stageName, $status, $uid, $comments]);
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

            // Send notifications if applicant has a user account
            if (!empty($app['applicant_user_id'])) {
                $message = "Your application {$app['application_number']} stage '$stageName' is now: $status";
                
                if (function_exists('addNotification')) {
                    addNotification($app['applicant_user_id'], $id, $message);
                } else {
                    $notifStmt = $db->prepare('INSERT INTO notifications (user_id, application_id, message, created_at) VALUES (?, ?, ?, NOW())');
                    $notifStmt->execute([$app['applicant_user_id'], $id, $message]);
                }
                
                if (function_exists('sendApplicationEmail')) {
                    sendApplicationEmail($app['email'], $app['application_number'], $stageName, $status);
                }
                
                if (function_exists('sendApplicationSMS')) {
                    sendApplicationSMS($app['phone'], $app['application_number'], $stageName, $status);
                }
            }

            logActivity($uid, 'UPDATE_STAGE', "App {$app['application_number']} — $stageName → $status");
            flash('success', "Stage '$stageName' updated to '$status'.");
        } else {
            flash('error', 'Invalid stage or status value.');
        }
        redirect(APP_URL . '/officer/manage_application.php?id=' . $id);
    }

    // Refresh app data after potential update
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

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-edit"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Officer</div>
        <div class="hero-name">Manage Application</div>
        <div class="hero-meta">
            <div class="manage-header-text">
                <p>Track and update application progress</p>
            </div>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <div class="btn-group">
            <button class="btn btn-outline" data-print><i class="fa fa-print"></i> Print</button>
            <a href="<?= APP_URL ?>/officer/applications.php" class="btn btn-outline"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
    </div>
  </div>
</div>

<!-- Applicant Profile Card -->
<div class="profile-card hover-card" data-spotlight>
    <div class="profile-card-header">
        <div class="profile-card-title">
            <i class="fa fa-user-circle"></i> Applicant Information
            <span class="profile-badge"><?= e($app['application_number']) ?></span>
        </div>
        <?= statusBadge($app['status']) ?>
    </div>
    <div class="profile-body">
        <div class="profile-photo">
            <?php if (!empty($app['photo_path']) && file_exists(UPLOAD_DIR . $app['photo_path'])): ?>
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
            <?php if (!empty($app['applicant_user_id'])): ?>
            <div class="info-field full-width" style="margin-top: 8px;">
                <span class="info-label">Account Status</span>
                <span class="info-value"><i class="fa fa-check-circle" style="color: #10b981;"></i> Has Online Account</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Processing Stages Timeline -->
<div class="stages-card hover-card" data-spotlight>
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
        <div class="timeline-item <?= $statusClass ?> hover-card" style="--i: <?= $idx++ ?>" data-spotlight>
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
                <?php if ($st && !empty($st['comments'])): ?>
                <div class="timeline-comments">
                    <i class="fa fa-comment"></i> <?= e($st['comments']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Stage Update Modal with Custom Select -->
<div id="stageModal" class="modal-overlay">
    <div class="modal-premium">
        <div class="modal-premium-header">
            <h3><i class="fa fa-edit"></i> Update Stage</h3>
            <button class="modal-close" data-modal-close="stageModal">&times;</button>
        </div>
        <form method="POST" id="stageUpdateForm">
            <input type="hidden" name="update_stage" value="1">
            <input type="hidden" name="stage_name" id="modalStageName">
            <div class="modal-premium-body">
                <div style="margin-bottom: 1.2rem; padding: 0.9rem; background: linear-gradient(135deg, var(--surface), var(--bg-alt)); border-radius: var(--radius);">
                    <strong id="modalStageLabel" style="color: var(--gold-light); font-size: 0.95rem;"></strong>
                </div>
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label><i class="fa fa-flag"></i> Status</label>
                    <!-- Custom Select Dropdown instead of native select -->
                    <div class="custom-select" id="modalStatusSelect">
                        <div class="custom-select-trigger">
                            <span class="selected-text">Pending</span>
                            <i class="fa fa-chevron-down arrow"></i>
                        </div>
                        <div class="custom-select-dropdown">
                            <div class="custom-select-option" data-value="Pending">
                                <i class="fa fa-clock" style="margin-right: 8px;"></i> Pending
                            </div>
                            <div class="custom-select-option" data-value="In-Progress">
                                <i class="fa fa-spinner" style="margin-right: 8px;"></i> In Progress
                            </div>
                            <div class="custom-select-option" data-value="Completed">
                                <i class="fa fa-check-circle" style="margin-right: 8px;"></i> Completed
                            </div>
                            <div class="custom-select-option" data-value="Rejected">
                                <i class="fa fa-times-circle" style="margin-right: 8px;"></i> Rejected
                            </div>
                        </div>
                        <input type="hidden" name="stage_status" id="modalStageStatus" value="Pending">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fa fa-sticky-note"></i> Comments</label>
                    <div class="input-wrap">
                        <textarea name="comments" id="modalComments" rows="3" placeholder="Add notes about this stage..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-premium-footer">
                <button type="button" class="btn btn-outline" data-modal-close="stageModal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveChangesBtn">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Global Loading Overlay -->
<div id="globalLoadingOverlay" class="global-loading-overlay" style="display: none;">
    <div class="loading-container">
        <div class="loading-spinner">
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-icon">
                <i class="fa fa-passport"></i>
            </div>
        </div>
        <div class="loading-text">Processing your request...</div>
        <div class="loading-subtext">Please wait</div>
    </div>
</div>

<!-- Print functionality -->
<script>
document.querySelector('[data-print]')?.addEventListener('click', function() {
    window.print();
});
</script>

<!-- Spotlight Effect JavaScript -->
<script>
(function() {
    // Spotlight effect for all hover-card elements
    const spotlightElements = document.querySelectorAll('.hover-card, [data-spotlight]');
    
    spotlightElements.forEach(el => {
        // Create spotlight overlay if not exists
        let spotlight = el.querySelector('.sc-spotlight');
        if (!spotlight && getComputedStyle(el).position !== 'static') {
            spotlight = document.createElement('div');
            spotlight.className = 'sc-spotlight';
            el.style.position = 'relative';
            el.style.overflow = 'hidden';
            el.appendChild(spotlight);
        }
        
        // Mouse move handler for spotlight
        el.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            
            // Update CSS variables for the gradient
            this.style.setProperty('--x', x + '%');
            this.style.setProperty('--y', y + '%');
            
            if (spotlight) {
                spotlight.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(59, 130, 246, 0.12) 0%, transparent 70%)`;
                spotlight.style.opacity = '1';
            }
        });
        
        el.addEventListener('mouseleave', function() {
            if (spotlight) {
                spotlight.style.opacity = '0';
            }
        });
    });
    
    // Add tilt effect to timeline items
    const tiltItems = document.querySelectorAll('.timeline-item');
    tiltItems.forEach(item => {
        let tiltX = 0, tiltY = 0;
        let targetX = 0, targetY = 0;
        
        item.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const deltaX = (e.clientX - centerX) / (rect.width / 2);
            const deltaY = (e.clientY - centerY) / (rect.height / 2);
            
            targetX = -deltaY * 3;
            targetY = deltaX * 3;
            
            requestAnimationFrame(() => {
                tiltX += (targetX - tiltX) * 0.15;
                tiltY += (targetY - tiltY) * 0.15;
                this.style.transform = `perspective(500px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
            });
        });
        
        item.addEventListener('mouseleave', function() {
            targetX = 0;
            targetY = 0;
            requestAnimationFrame(() => {
                this.style.transform = 'perspective(500px) rotateX(0deg) rotateY(0deg)';
            });
        });
    });
})();
</script>

<script>
// Loading animation handler
(function() {
    const form = document.getElementById('stageUpdateForm');
    const saveBtn = document.getElementById('saveChangesBtn');
    const loadingOverlay = document.getElementById('globalLoadingOverlay');
    
    if (form && saveBtn) {
        form.addEventListener('submit', function(e) {
            // Show loading overlay immediately
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
            }
            
            // Disable the submit button to prevent double submission
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
            
            // The form will submit normally
            // Note: If there's client-side validation, you might want to check before showing loading
        });
    }
    
    // Also handle any other forms with class 'needs-loading'
    document.querySelectorAll('form[data-show-loading="true"]').forEach(formEl => {
        formEl.addEventListener('submit', function() {
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
            }
        });
    });
})();

// Custom Select initialization for modal
function initModalCustomSelect() {
    const container = document.getElementById('modalStatusSelect');
    if (!container) return;
    
    const trigger = container.querySelector('.custom-select-trigger');
    const dropdown = container.querySelector('.custom-select-dropdown');
    const options = container.querySelectorAll('.custom-select-option');
    const hiddenInput = document.getElementById('modalStageStatus');
    const selectedTextSpan = trigger.querySelector('.selected-text');
    
    // Remove any existing event listeners by cloning and replacing
    const newTrigger = trigger.cloneNode(true);
    trigger.parentNode.replaceChild(newTrigger, trigger);
    const newDropdown = dropdown.cloneNode(true);
    dropdown.parentNode.replaceChild(newDropdown, dropdown);
    
    const finalTrigger = container.querySelector('.custom-select-trigger');
    const finalDropdown = container.querySelector('.custom-select-dropdown');
    const finalOptions = container.querySelectorAll('.custom-select-option');
    const finalSelectedTextSpan = finalTrigger.querySelector('.selected-text');
    
    // Set initial selected state
    const currentValue = hiddenInput.value;
    finalOptions.forEach(opt => {
        if (opt.dataset.value === currentValue) {
            opt.classList.add('selected');
            finalSelectedTextSpan.textContent = opt.textContent.trim();
        }
    });
    
    // Toggle dropdown
    finalTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = finalDropdown.classList.contains('show');
        // Close all other dropdowns
        document.querySelectorAll('.custom-select-dropdown.show').forEach(d => {
            if (d !== finalDropdown) d.classList.remove('show');
        });
        document.querySelectorAll('.custom-select-trigger.open').forEach(t => {
            if (t !== finalTrigger) t.classList.remove('open');
        });
        
        finalDropdown.classList.toggle('show');
        finalTrigger.classList.toggle('open');
    });
    
    // Select option
    finalOptions.forEach(opt => {
        opt.addEventListener('click', () => {
            const value = opt.dataset.value;
            const text = opt.textContent.trim();
            
            hiddenInput.value = value;
            finalSelectedTextSpan.textContent = text;
            
            // Update selected class
            finalOptions.forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            
            // Close dropdown
            finalDropdown.classList.remove('show');
            finalTrigger.classList.remove('open');
        });
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            finalDropdown.classList.remove('show');
            finalTrigger.classList.remove('open');
        }
    });
}

function openStageModal(stageName, currentStatus, comments) {
    document.getElementById('modalStageName').value = stageName;
    document.getElementById('modalStageLabel').textContent = stageName;
    document.getElementById('modalComments').value = comments || '';
    
    // Reset the save button state
    const saveBtn = document.getElementById('saveChangesBtn');
    if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa fa-save"></i> Save Changes';
    }
    
    // Update custom select to current status
    const hiddenInput = document.getElementById('modalStageStatus');
    const container = document.getElementById('modalStatusSelect');
    if (container && hiddenInput) {
        hiddenInput.value = currentStatus;
        const trigger = container.querySelector('.custom-select-trigger');
        if (trigger) {
            const selectedTextSpan = trigger.querySelector('.selected-text');
            const options = container.querySelectorAll('.custom-select-option');
            
            // Update selected text and class
            options.forEach(opt => {
                opt.classList.remove('selected');
                if (opt.dataset.value === currentStatus) {
                    opt.classList.add('selected');
                    if (selectedTextSpan) {
                        selectedTextSpan.textContent = opt.textContent.trim();
                    }
                }
            });
        }
    }
    
    openModal('stageModal');
}

// Initialize custom select when modal is opened
document.addEventListener('DOMContentLoaded', function() {
    initModalCustomSelect();
});

// Re-initialize when modal opens
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => {
            initModalCustomSelect();
        }, 100);
    }
}

// Close modal functionality
document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', function() {
        const modalId = this.getAttribute('data-modal-close');
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});
</script>

<style>
/* Print styles */
@media print {
    .hero-right, .update-btn, .modal-overlay, .btn-group, [data-print] {
        display: none !important;
    }
    .timeline-item {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .profile-card, .stages-card {
        break-inside: avoid;
    }
}

/* Global Loading Overlay Styles */
.global-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        backdrop-filter: blur(0px);
    }
    to {
        opacity: 1;
        backdrop-filter: blur(8px);
    }
}

.loading-container {
    text-align: center;
    padding: 2rem;
}

.loading-spinner {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 1.5rem;
}

.spinner-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-radius: 50%;
    animation: spin 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
}

.spinner-ring:nth-child(1) {
    border-top-color: #3B82F6;
    border-left-color: #3B82F6;
    animation-delay: 0s;
}

.spinner-ring:nth-child(2) {
    border-right-color: #C8911A;
    border-bottom-color: #C8911A;
    width: 80%;
    height: 80%;
    top: 10%;
    left: 10%;
    animation-delay: 0.2s;
    animation-duration: 1.8s;
}

.spinner-ring:nth-child(3) {
    border-left-color: #10B981;
    border-top-color: #10B981;
    width: 60%;
    height: 60%;
    top: 20%;
    left: 20%;
    animation-delay: 0.4s;
    animation-duration: 2s;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.spinner-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    color: #C8911A;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
    }
    50% {
        transform: translate(-50%, -50%) scale(1.1);
        opacity: 0.8;
    }
}

.loading-text {
    font-size: 1.2rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
    letter-spacing: 0.5px;
}

.loading-subtext {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    letter-spacing: 0.3px;
}

/* Button loading state */
.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.fa-spin {
    animation: fa-spin 1s infinite linear;
}

@keyframes fa-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>