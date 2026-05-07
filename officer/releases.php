<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];

// ── Handle Print Report Request ──────────────────────────────────
if (isset($_GET['print_report']) && isset($_GET['release_id'])) {
    $releaseId = (int)$_GET['release_id'];
    $stmt = $db->prepare("
        SELECT pr.*, pa.application_number, pa.full_name, pa.national_id, pa.passport_type, 
              pa.photo_path AS photo, pa.applicant_user_id, u.full_name AS officer_name, u.username AS officer_username
        FROM passport_releases pr
        JOIN passport_applications pa ON pa.id = pr.application_id
        JOIN users u ON u.id = pr.officer_id
        WHERE pr.id = ?
    ");
    $stmt->execute([$releaseId]);
    $releaseData = $stmt->fetch();
    
    if ($releaseData) {
        // Display printable report
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Passport Release Report - <?= e($releaseData['application_number']) ?></title>
            <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/headerIcon.png">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
                    background: #f5f5f5;
                    padding: 30px;
                    color: #333;
                }
                .report-container {
                    max-width: 850px;
                    margin: 0 auto;
                    background: white;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                .report-header {
                    padding: 40px 40px 20px 40px;
                    text-align: center;
                    border-bottom: 1px solid #e0e0e0;
                }
                .report-header h1 {
                    font-size: 24px;
                    font-weight: 500;
                    letter-spacing: 1px;
                    color: #222;
                    margin-bottom: 8px;
                }
                .report-header p {
                    font-size: 13px;
                    color: #6a6a6a;
                    margin-top: 5px;
                }
                .report-header .report-id {
                    font-size: 12px;
                    color: #6a6a6a;
                    margin-top: 12px;
                    font-family: monospace;
                }
                .report-body {
                    padding: 35px 40px;
                }
                .section {
                    margin-bottom: 32px;
                }
                .section-title {
                    font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: #6a6a6a;
                    margin-bottom: 20px;
                    padding-bottom: 8px;
                    border-bottom: 1px solid #e0e0e0;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .section-title i {
                    font-size: 14px;
                    color: #6a6a6a;
                    width: 18px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px 30px;
                }
                .info-item {
                    display: flex;
                    flex-direction: column;
                }
                .info-label {
                    font-size: 11px;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    color: #6a6a6a;
                    margin-bottom: 6px;
                }
                .info-value {
                    font-size: 15px;
                    font-weight: 400;
                    color: #333;
                }
                .photo-section {
                    display: flex;
                    justify-content: center;
                    margin-bottom: 30px;
                }
                .applicant-photo {
                    width: 100px;
                    height: 100px;
                    border-radius: 2px;
                    object-fit: cover;
                    border: 1px solid #e0e0e0;
                }
                .status-badge {
                    display: inline-block;
                    padding: 6px 16px;
                    font-size: 12px;
                    font-weight: 500;
                    letter-spacing: 0.5px;
                    background: #f0f0f0;
                    color: #555;
                }
                .signature-section {
                    margin-top: 50px;
                    display: flex;
                    justify-content: space-between;
                    padding-top: 20px;
                }
                .signature-line {
                    text-align: center;
                    width: 220px;
                }
                .signature-line .line {
                    border-top: 1px solid #ccc;
                    margin-top: 50px;
                    padding-top: 10px;
                    font-size: 11px;
                    color: #999;
                }
                .footer {
                    background: #fafafa;
                    padding: 20px 40px;
                    text-align: center;
                    font-size: 11px;
                    color: #aaa;
                    border-top: 1px solid #e0e0e0;
                }
                @media print {
                    body {
                        background: white;
                        padding: 0;
                    }
                    .report-container {
                        box-shadow: none;
                    }
                    .no-print {
                        display: none;
                    }
                    .status-badge {
                        border: 1px solid #ddd;
                        background: none;
                    }
                }
                .print-button {
                    display: inline-block;
                    background: #333;
                    color: white;
                    padding: 10px 24px;
                    font-size: 13px;
                    border: none;
                    border-radius: 2px;
                    text-decoration: none;
                    margin: 0 6px;
                    cursor: pointer;
                    font-family: inherit;
                }
                .print-button:hover {
                    background: #555;
                }
                .print-button.secondary {
                    background: #bbb;
                }
                .print-button.secondary:hover {
                    background: #999;
                }
                .actions {
                    text-align: center;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="report-container">
                <div class="report-header">
                    <h1>PASSPORT RELEASE REPORT</h1>
                    <p>Official Collection Certificate</p>
                    <div class="report-id">Release ID: #<?= str_pad($releaseData['id'], 8, '0', STR_PAD_LEFT) ?></div>
                </div>
                
                <div class="report-body">
                    <?php if (!empty($releaseData['photo'])): ?>
                    <div class="photo-section">
                        <img src="<?= APP_URL . '/assets/photos/' . e($releaseData['photo']) ?>" alt="Applicant Photo" class="applicant-photo" onerror="this.src='<?= APP_URL ?>/assets/default-avatar.png'">
                    </div>
                    <?php endif; ?>
                    
                    <div class="section">
                        <div class="section-title">
                            <i class="fas fa-user"></i> APPLICANT INFORMATION
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= e($releaseData['full_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">National ID</div>
                                <div class="info-value"><?= e($releaseData['national_id']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Application Number</div>
                                <div class="info-value"><?= e($releaseData['application_number']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Passport Type</div>
                                <div class="info-value"><?= e($releaseData['passport_type']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt"></i> COLLECTION DETAILS
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Collection Date</div>
                                <div class="info-value"><?= date('d F Y', strtotime($releaseData['collection_date'])) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Released On</div>
                                <div class="info-value"><?= date('d F Y H:i', strtotime($releaseData['created_at'])) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Released By</div>
                                <div class="info-value"><?= e($releaseData['officer_name']) ?></div>
                            </div>
                            <?php if ($releaseData['notes']): ?>
                            <div class="info-item">
                                <div class="info-label">Notes</div>
                                <div class="info-value"><?= nl2br(e($releaseData['notes'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">
                            <i class="fas fa-check-circle"></i> RELEASE STATUS
                        </div>
                        <div style="text-align: center; padding: 20px 0;">
                            <span class="status-badge">
                                <i class="fas fa-check"></i> PASSPORT COLLECTED
                            </span>
                        </div>
                    </div>
                    
                    <div class="signature-section">
                        <div class="signature-line">
                            <div class="line">Applicant Signature</div>
                        </div>
                        <div class="signature-line">
                            <div class="line">Officer Signature</div>
                        </div>
                        <div class="signature-line">
                            <div class="line">Official Stamp</div>
                        </div>
                    </div>
                </div>
                
                <div class="footer">
                    <p>This is a computer-generated document</p>
                    <p style="margin-top: 5px;">Passport Office &copy; <?= date('Y') ?></p>
                </div>
            </div>
            
            <div class="actions no-print">
                <button onclick="window.print();" class="print-button">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="history.back();" class="print-button secondary">
                    <i class="fas fa-arrow-left"></i> Go Back
                </button>
            </div>
            
            <script>
                // Auto-trigger print dialog - uncomment if needed
                // window.print();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

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
        $ins = $db->prepare('INSERT INTO passport_releases (application_id,collection_date,applicant_name,officer_id,notes,created_at) VALUES (?,?,?,?,?,NOW())');
        $ins->execute([$appId, $collectionDate, $appRow['full_name'], $uid, $notes]);
        $releaseId = $db->lastInsertId();

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
        
        // Redirect to print report
        redirect(APP_URL . "/officer/releases.php?print_report=1&release_id=$releaseId");
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
      <table class="table">
        <thead>
          <tr>
            <th>App Number</th>
            <th>Applicant</th>
            <th>Collection Date</th>
            <th>Released By</th>
            <th>Notes</th>
            <th style="text-align:center;">Report</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($releasedList as $r): ?>
          <tr>
            <td><span class="app-number"><?= e($r['application_number']) ?></span></td>
            <td><span class="applicant-name"><?= e($r['applicant_name']) ?></span></td>
            <td><span class="release-date-badge"><i class="fa fa-calendar-check"></i> <?= date('d M Y', strtotime($r['collection_date'])) ?></span></td>
            <td><span class="officer-name"><i class="fa fa-user-shield"></i> <?= e($r['officer_name']) ?></span></td>
            <td style="font-size: .78rem; color: var(--muted);"><?= e($r['notes'] ?: '—') ?></td>
            <td style="text-align:center;">
              <a href="?print_report=1&release_id=<?= $r['id'] ?>" target="_blank" class="action-btn" title="Print Release Report">
                <i class="fa fa-print"></i>
              </a>
            </td
           </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Release Modal with Custom Date Picker -->
<div id="releaseModal" class="modal-overlay">
  <div class="modal-premium" style="overflow: visible;">
    <div class="modal-premium-header" style="border-radius: 16px 16px 0 0">
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
        
        <!-- Custom Date Picker -->
        <div class="form-group" style="margin-bottom: 1rem;">
          <label><i class="fa fa-calendar"></i> Collection Date *</label>
          <div class="custom-datepicker" id="collectionDatePicker">
            <div class="custom-datepicker-trigger">
              <i class="fa fa-calendar-alt"></i>
              <span class="selected-date"><?= date('Y-m-d') ?></span>
              <i class="fa fa-chevron-down arrow"></i>
            </div>
            <div class="custom-datepicker-dropdown">
              <div class="datepicker-header">
                <button type="button" class="datepicker-nav prev-month"><i class="fa fa-chevron-left"></i></button>
                <span class="datepicker-month-year"></span>
                <button type="button" class="datepicker-nav next-month"><i class="fa fa-chevron-right"></i></button>
              </div>
              <div class="datepicker-weekdays">
                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
              </div>
              <div class="datepicker-days"></div>
            </div>
            <input type="hidden" name="collection_date" id="collectionDateInput" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        
        <div class="form-group input-wrap">
          <label><i class="fa fa-sticky-note"></i> Notes (optional)</label>
          <textarea name="notes" rows="3" placeholder="Additional remarks about the collection..."></textarea>
        </div>
      </div>
      <div class="modal-premium-footer" style="border-radius: 0 0 16px 16px">
        <button type="button" class="btn btn-outline" data-modal-close="releaseModal">Cancel</button>
        <button type="submit" class="btn btn-success" onclick="return confirm('Confirm passport release? This action cannot be undone.')">
          <i class="fa fa-check"></i> Confirm Release
        </button>
      </div>
    </form>
  </div>
</div>

<style>
/* Custom Date Picker Styles */
.custom-datepicker {
  position: relative;
  width: 100%;
}

.custom-datepicker-trigger {
  background: var(--surface);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 12px 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: all 0.2s ease;
}

.custom-datepicker-trigger:hover {
  border-color: rgba(59, 130, 246, 0.5);
  background: rgba(59, 130, 246, 0.05);
}

.custom-datepicker-trigger .selected-date {
  flex: 1;
  font-weight: 500;
}

.custom-datepicker-trigger .arrow {
  transition: transform 0.2s ease;
}

.custom-datepicker-trigger.open .arrow {
  transform: rotate(180deg);
}

.custom-datepicker-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  margin-top: 8px;
  background: var(--surface);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 16px;
  width: 300px;
  z-index: 1000;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
  display: none;
}

.custom-datepicker-dropdown.show {
  display: block;
}

.datepicker-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.datepicker-nav {
  background: none;
  border: none;
  color: var(--text);
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.2s;
}

.datepicker-nav:hover {
  background: rgba(59, 130, 246, 0.1);
  color: #60A5FA;
}

.datepicker-month-year {
  font-weight: 600;
  font-size: 0.95rem;
}

.datepicker-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  margin-bottom: 8px;
  text-align: center;
}

.datepicker-weekdays span {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--muted);
  padding: 4px;
}

.datepicker-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.datepicker-day {
  text-align: center;
  padding: 8px 4px;
  cursor: pointer;
  border-radius: 8px;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.datepicker-day:hover {
  background: rgba(59, 130, 246, 0.1);
  color: #60A5FA;
}

.datepicker-day.selected {
  background: linear-gradient(135deg, #3B82F6, #2563EB);
  color: white;
}

.datepicker-day.other-month {
  color: var(--muted);
  opacity: 0.5;
}

.datepicker-day.today {
  border: 1px solid #60A5FA;
  font-weight: 600;
}

.action-btn {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 6px 10px;
  border-radius: 8px;
  transition: all 0.2s;
}

.action-btn:hover {
  background: rgba(59, 130, 246, 0.1);
  color: #60A5FA;
}
</style>

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

// Custom Date Picker Implementation
class CustomDatePicker {
  constructor(container) {
    this.container = container;
    this.trigger = container.querySelector('.custom-datepicker-trigger');
    this.dropdown = container.querySelector('.custom-datepicker-dropdown');
    this.hiddenInput = container.querySelector('#collectionDateInput');
    this.selectedDateSpan = this.trigger.querySelector('.selected-date');
    this.currentDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : new Date();
    this.selectedDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : new Date();
    
    this.init();
  }
  
  init() {
    this.renderCalendar();
    this.attachEvents();
  }
  
  attachEvents() {
    // Toggle dropdown
    this.trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      this.dropdown.classList.toggle('show');
      this.trigger.classList.toggle('open');
      if (this.dropdown.classList.contains('show')) {
        this.renderCalendar();
      }
    });
    
    // Navigation buttons
    const prevBtn = this.container.querySelector('.prev-month');
    const nextBtn = this.container.querySelector('.next-month');
    
    prevBtn.addEventListener('click', () => {
      this.currentDate.setMonth(this.currentDate.getMonth() - 1);
      this.renderCalendar();
    });
    
    nextBtn.addEventListener('click', () => {
      this.currentDate.setMonth(this.currentDate.getMonth() + 1);
      this.renderCalendar();
    });
  }
  
  renderCalendar() {
    const year = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();
    
    // Update month/year display
    const monthYearSpan = this.container.querySelector('.datepicker-month-year');
    monthYearSpan.textContent = `${this.currentDate.toLocaleString('default', { month: 'long' })} ${year}`;
    
    // Get first day of month and total days
    const firstDay = new Date(year, month, 1);
    const startDay = firstDay.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const prevMonthDays = new Date(year, month, 0).getDate();
    
    const daysContainer = this.container.querySelector('.datepicker-days');
    daysContainer.innerHTML = '';
    
    // Previous month days
    for (let i = startDay - 1; i >= 0; i--) {
      const day = prevMonthDays - i;
      const dayElement = this.createDayElement(day, true);
      daysContainer.appendChild(dayElement);
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(year, month, day);
      const dayElement = this.createDayElement(day, false, date);
      daysContainer.appendChild(dayElement);
    }
    
    // Next month days (to fill 6 rows = 42 days)
    const totalDays = daysContainer.children.length;
    const remainingDays = 42 - totalDays;
    for (let day = 1; day <= remainingDays; day++) {
      const dayElement = this.createDayElement(day, true);
      daysContainer.appendChild(dayElement);
    }
  }
  
  createDayElement(day, isOtherMonth, date = null) {
    const dayElement = document.createElement('div');
    dayElement.className = 'datepicker-day';
    if (isOtherMonth) dayElement.classList.add('other-month');
    dayElement.textContent = day;
    
    if (date) {
      const isSelected = this.selectedDate && 
        date.getDate() === this.selectedDate.getDate() &&
        date.getMonth() === this.selectedDate.getMonth() &&
        date.getFullYear() === this.selectedDate.getFullYear();
      
      if (isSelected) dayElement.classList.add('selected');
      
      const today = new Date();
      if (date.toDateString() === today.toDateString()) {
        dayElement.classList.add('today');
      }
      
      dayElement.addEventListener('click', () => {
        this.selectDate(date);
      });
    } else if (isOtherMonth) {
      const otherDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + (day <= 7 ? 1 : -1), day);
      dayElement.addEventListener('click', () => {
        this.selectDate(otherDate);
      });
    }
    
    return dayElement;
  }
  
  selectDate(date) {
    this.selectedDate = date;
    this.currentDate = new Date(date);
    const formattedDate = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    this.hiddenInput.value = formattedDate;
    this.selectedDateSpan.textContent = formattedDate;
    this.dropdown.classList.remove('show');
    this.trigger.classList.remove('open');
  }
}

// Modal functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    // Initialize date picker when modal opens
    const datePicker = document.querySelector('#collectionDatePicker');
    if (datePicker && !datePicker.datePickerInstance) {
      datePicker.datePickerInstance = new CustomDatePicker(datePicker);
    }
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

// Close date picker dropdown when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.custom-datepicker')) {
    document.querySelectorAll('.custom-datepicker-dropdown.show').forEach(dropdown => {
      dropdown.classList.remove('show');
    });
    document.querySelectorAll('.custom-datepicker-trigger.open').forEach(trigger => {
      trigger.classList.remove('open');
    });
  }
});

// Initialize date picker if modal is already visible
document.addEventListener('DOMContentLoaded', () => {
  const datePicker = document.querySelector('#collectionDatePicker');
  if (datePicker) {
    datePicker.datePickerInstance = new CustomDatePicker(datePicker);
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>