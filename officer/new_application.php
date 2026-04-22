<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('officer');
$db  = getDB();
$uid = $_SESSION['user_id'];
$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name'      => trim($_POST['full_name'] ?? ''),
        'national_id'    => trim($_POST['national_id'] ?? ''),
        'date_of_birth'  => $_POST['date_of_birth'] ?? '',
        'gender'         => $_POST['gender'] ?? '',
        'address'        => trim($_POST['address'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'passport_type'  => $_POST['passport_type'] ?? 'Normal',
        'application_date' => date('Y-m-d'),
    ];

    foreach (['full_name','national_id','date_of_birth','gender','address','phone','email'] as $f) {
        if (empty($data[$f])) $errors[$f] = 'This field is required.';
    }
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email address.';
    if (!empty($data['date_of_birth']) && strtotime($data['date_of_birth']) >= time()) $errors['date_of_birth'] = 'Date of birth must be in the past.';
    if (!empty($data['phone']) && !preg_match('/^[+\d\s\-()]{7,20}$/', $data['phone'])) $errors['phone'] = 'Invalid phone number.';

    $photoPath = null;
    if (isset($_FILES['photo_upload']) && $_FILES['photo_upload']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['photo_upload'];
        $allowed = ['image/jpeg','image/png','image/jpg'];
        if (!in_array($file['type'], $allowed)) {
            $errors['photo_upload'] = 'Only JPG/PNG images allowed.';
        } elseif ($file['size'] > 2*1024*1024) {
            $errors['photo_upload'] = 'Photo must be under 2MB.';
        } else {
            $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
            $photoPath = uniqid('photo_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $photoPath);
        }
    }

    if (empty($errors)) {
        $appNum = generateApplicationNumber();
        $stmt = $db->prepare('INSERT INTO passport_applications
            (application_number,applicant_user_id,officer_id,full_name,national_id,date_of_birth,gender,address,phone,email,passport_type,application_date,photo_path,current_stage,status)
            VALUES (?,NULL,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $appNum, $uid,
            $data['full_name'], $data['national_id'], $data['date_of_birth'],
            $data['gender'], $data['address'], $data['phone'], $data['email'],
            $data['passport_type'], $data['application_date'], $photoPath,
            'Application Submitted', 'Pending'
        ]);
        $appId = $db->lastInsertId();

        $allStages = ['Application Submitted','Document Verification','Biometric Capture','Background Check','Passport Printing','Ready for Collection','Passport Released'];
        $ins = $db->prepare('INSERT INTO processing_stages (application_id,stage_name,status,officer_id) VALUES (?,?,?,?)');
        foreach ($allStages as $s) {
            $stStatus = ($s === 'Application Submitted') ? 'Completed' : 'Pending';
            $ins->execute([$appId, $s, $stStatus, $uid]);
        }

        logActivity($uid, 'NEW_APPLICATION', "Created application $appNum for {$data['full_name']}");
        flash('success', "Application $appNum created successfully!");
        redirect(APP_URL . '/officer/manage_application.php?id=' . $appId);
    }
}

$pageTitle = 'New Application';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   NEW APPLICATION PAGE — Premium Dashboard Matching Style
   ───────────────────────────────────────────────────────────── */

/* ── Animations ──────────────────────────────────────────── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes shimmer {
    0% { background-position: -200% center; }
    100% { background-position: 200% center; }
}
@keyframes pulse-ring {
    0% { box-shadow: 0 0 0 0 rgba(59,130,246,.4); }
    70% { box-shadow: 0 0 0 8px rgba(59,130,246,0); }
    100% { box-shadow: 0 0 0 0 rgba(59,130,246,0); }
}
@keyframes float {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
@keyframes calendarSlide {
    from { opacity: 0; transform: translateY(-10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.na-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both; }
.na-animate-d1 { animation-delay: .04s; }
.na-animate-d2 { animation-delay: .08s; }
.na-animate-d3 { animation-delay: .12s; }
.na-animate-d4 { animation-delay: .16s; }

/* ── Hero Section (matches dashboard) ────────────────────── */
.na-hero {
    position: relative;
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1.5rem;
    background: #060D1A;
    border: 1px solid rgba(59,130,246,.18);
    animation: fadeIn .6s ease both;
}
html[data-theme="light"] .na-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.na-hero-mesh {
    position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
        radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
        radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.na-hero-grid {
    position: absolute; inset: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.na-hero::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
    background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
    background-size: 200% 100%;
    animation: shimmer 3s linear infinite;
}

.na-hero-inner {
    position: relative; z-index: 2;
    display: flex; align-items: center;
    justify-content: space-between; flex-wrap: wrap;
    gap: 1.2rem; padding: 1.75rem 2rem;
}

.na-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.na-hero-icon {
    position: relative; width: 60px; height: 60px;
    border-radius: 16px; flex-shrink: 0;
    background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
    border: 1px solid rgba(59,130,246,.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: #93C5FD;
    box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
    animation: float 4s ease-in-out infinite;
}
.na-hero-icon::after {
    content: '';
    position: absolute; inset: -1px; border-radius: 17px;
    background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
    opacity: .5; pointer-events: none;
}

.na-hero-eyebrow {
    font-size: .67rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: rgba(255,255,255,.35);
    margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.na-hero-eyebrow::before {
    content: ''; width: 18px; height: 1.5px;
    background: var(--gold-light); border-radius: 2px; display: block;
}
.na-hero-name {
    font-size: 1.45rem; font-weight: 800; color: #fff;
    letter-spacing: -.03em; line-height: 1.15;
}
.na-hero-meta {
    display: flex; align-items: center; gap: 1rem;
    margin-top: .45rem; flex-wrap: wrap;
}
.na-hero-meta-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .73rem; color: rgba(255,255,255,.4);
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
    border-radius: 20px; padding: .2rem .65rem;
}
.na-hero-meta-chip i { font-size: .62rem; }

.na-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Form Card (matches dashboard cards) ────────────────── */
.na-card {
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    transform-style: preserve-3d;
    position: relative;
}

.na-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
                rgba(59, 130, 246, 0.08) 0%, 
                transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
    border-radius: inherit;
    z-index: 1;
}

.na-card:hover::before {
    opacity: 1;
}

.na-card:hover {
    transform: translateY(-2px);
    border-color: rgba(59, 130, 246, 0.3);
}

.na-card-hd {
    display: flex; align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.na-card-title {
    font-size: .88rem; font-weight: 700; color: var(--text);
    display: flex; align-items: center; gap: .45rem;
}
.na-card-title i { color: var(--gold); font-size: .78rem; }

.na-card-body {
    padding: 1.8rem;
}

/* ── Form Grid & Elements ────────────────────────────────── */
.na-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}
@media (max-width: 768px) {
    .na-form-grid { grid-template-columns: 1fr; }
}

.na-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.na-form-group.full-width {
    grid-column: 1 / -1;
}

.na-form-group label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.na-form-group label i {
    color: var(--gold-light);
    font-size: .7rem;
}

.na-form-group input,
.na-form-group select,
.na-form-group textarea {
    width: 100%;
    padding: .75rem 1rem;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-size: .9rem;
    transition: all .2s;
}
.na-form-group input:focus,
.na-form-group select:focus,
.na-form-group textarea:focus {
    outline: none;
    border-color: var(--navy-light);
    box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.na-form-group input.error,
.na-form-group select.error,
.na-form-group textarea.error {
    border-color: var(--danger);
}
.na-field-error {
    font-size: .7rem;
    color: var(--danger);
    display: none;
}
.na-field-error.show {
    display: block;
}

/* ── CUSTOM CALENDAR / DATE PICKER (Premium) ─────────────── */
.date-picker-wrapper {
    position: relative;
    width: 100%;
}
.date-input-container {
    position: relative;
    display: flex;
    align-items: center;
}
.date-input-container input {
    padding-right: 2.5rem;
    cursor: pointer;
}
.date-input-container .calendar-icon {
    position: absolute;
    right: 0.75rem;
    color: var(--muted);
    pointer-events: none;
    font-size: 0.9rem;
}
.custom-calendar {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: #1C2333;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 35px -10px rgba(0,0,0,0.4), 0 0 0 1px rgba(59,130,246,.1);
    z-index: 1000;
    display: none;
    animation: calendarSlide 0.2s ease;
    overflow: hidden;
}
.custom-calendar.show {
    display: block;
}
.calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, rgba(59,130,246,.1), transparent);
    border-bottom: 1px solid var(--border);
}
.calendar-nav {
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text-soft);
}
.calendar-nav:hover {
    background: var(--navy-light);
    border-color: var(--navy-light);
    color: #fff;
    transform: scale(1.05);
}
.calendar-month-year {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text);
    letter-spacing: 0.5px;
}
.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    padding: 0.6rem 0;
    background: rgba(59,130,246,.05);
    border-bottom: 1px solid var(--border);
}
.calendar-weekday {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gold-light);
    letter-spacing: 0.5px;
}
.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    padding: 0.5rem;
    gap: 2px;
}
.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text);
    background: transparent;
    margin: 2px;
}
.calendar-day:hover {
    background: rgba(59,130,246,.15);
    transform: scale(1.05);
}
.calendar-day.selected {
    background: linear-gradient(135deg, #1D5A9E, #3B82F6);
    color: white;
    box-shadow: 0 2px 8px rgba(59,130,246,.4);
}
.calendar-day.other-month {
    color: var(--muted);
    opacity: 0.5;
}
.calendar-day.disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
.calendar-day.disabled:hover {
    background: transparent;
    transform: none;
}
.calendar-day.today {
    border: 1px solid var(--gold-light);
    font-weight: 800;
}
.calendar-footer {
    padding: 0.6rem 1rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    background: rgba(0,0,0,.2);
}
.calendar-clear {
    font-size: 0.7rem;
    padding: 0.25rem 0.75rem;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 20px;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s;
}
.calendar-clear:hover {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
}
.calendar-today-btn {
    font-size: 0.7rem;
    padding: 0.25rem 0.75rem;
    background: linear-gradient(135deg, #1D5A9E, #3B82F6);
    border: none;
    border-radius: 20px;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
}
.calendar-today-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(59,130,246,.4);
}

/* Custom Select (matches dashboard style) */
.na-custom-select {
    position: relative;
    width: 100%;
}
.na-custom-select-trigger {
    width: 100%;
    padding: .75rem 1rem;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-size: .9rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.na-custom-select-trigger::after {
    content: '\f107';
    font-family: 'FontAwesome';
    font-size: .8rem;
    color: var(--muted);
}
.na-custom-select-trigger.open::after {
    content: '\f106';
}
.na-custom-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #1C2333;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    margin-top: 0.3rem;
    z-index: 100;
    display: none;
    max-height: 200px;
    overflow-y: auto;
}
.na-custom-select-dropdown.show {
    display: block;
}
.na-custom-select-option {
    padding: .6rem 1rem;
    cursor: pointer;
    transition: background .15s;
}
.na-custom-select-option:hover {
    background: var(--navy-light);
    color: #fff;
}

/* Photo Upload Zone (premium) */
.na-photo-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
    background: var(--surface);
    cursor: pointer;
    transition: all .2s;
}
.na-photo-zone:hover {
    border-color: var(--navy-light);
    background: rgba(59,130,246,.05);
}
.na-photo-zone i {
    font-size: 2rem;
    color: var(--muted);
    margin-bottom: 0.5rem;
}
.na-photo-zone p {
    margin: 0;
    font-size: .8rem;
    color: var(--text-soft);
}
.na-photo-zone .small {
    font-size: .7rem;
    color: var(--muted);
}
.na-photo-preview {
    margin-top: 1rem;
    display: none;
    justify-content: center;
}
.na-photo-preview img {
    max-width: 120px;
    max-height: 140px;
    border-radius: var(--radius);
    border: 2px solid var(--border);
    object-fit: cover;
}

/* Form Actions */
.na-form-actions {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}
.na-btn-primary {
    padding: .75rem 1.5rem;
    background: linear-gradient(135deg, #1D5A9E, #3B82F6);
    border: none;
    border-radius: var(--radius);
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}
.na-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(59,130,246,.4);
}
.na-btn-outline {
    padding: .75rem 1.5rem;
    background: transparent;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    color: var(--text-soft);
    cursor: pointer;
    transition: all .2s;
}
.na-btn-outline:hover {
    background: var(--surface);
    border-color: var(--navy-light);
    color: var(--text);
}

/* Alert */
.na-alert {
    margin-bottom: 1.5rem;
    padding: 1rem 1.25rem;
    border-radius: var(--radius);
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    color: #F87171;
    font-size: .85rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
</style>

<!-- Hero Section (matching dashboard) -->
<div class="na-hero na-animate">
    <div class="na-hero-mesh"></div>
    <div class="na-hero-grid"></div>
    <div class="na-hero-inner">
        <div class="na-hero-left">
            <div class="na-hero-icon"><i class="fa fa-file-circle-plus"></i></div>
            <div>
                <div class="na-hero-eyebrow">Passport Application</div>
                <div class="na-hero-name">New Application</div>
                <div class="na-hero-meta">
                    <span class="na-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
                    <span class="na-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
                    <span class="na-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
                        <i class="fa fa-passport"></i> Capture applicant details
                    </span>
                </div>
            </div>
        </div>
        <div class="na-hero-right">
            <a href="<?= APP_URL ?>/officer/dashboard.php" class="btn btn-outline">
                <i class="fa fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="na-alert na-animate na-animate-d1">
    <i class="fa fa-exclamation-circle"></i> Please correct the highlighted errors below.
</div>
<?php endif; ?>

<!-- Form Card -->
<div class="na-card na-animate na-animate-d1" id="formCard">
    <div class="na-card-hd">
        <div class="na-card-title">
            <i class="fa fa-user-plus"></i> Applicant Information
        </div>
    </div>
    <div class="na-card-body">
        <form method="POST" enctype="multipart/form-data" id="applicationForm">
            <div class="na-form-grid">
                <!-- Full Name -->
                <div class="na-form-group">
                    <label><i class="fa fa-user"></i> Full Name *</label>
                    <input type="text" name="full_name" required value="<?= e($data['full_name'] ?? '') ?>" class="<?= isset($errors['full_name'])?'error':'' ?>">
                    <span class="na-field-error <?= isset($errors['full_name'])?'show':'' ?>"><?= e($errors['full_name'] ?? '') ?></span>
                </div>
                
                <!-- National ID -->
                <div class="na-form-group">
                    <label><i class="fa fa-id-card"></i> National ID Number *</label>
                    <input type="text" name="national_id" required value="<?= e($data['national_id'] ?? '') ?>" class="<?= isset($errors['national_id'])?'error':'' ?>">
                    <span class="na-field-error <?= isset($errors['national_id'])?'show':'' ?>"><?= e($errors['national_id'] ?? '') ?></span>
                </div>
                
                <!-- Date of Birth with Custom Calendar -->
                <div class="na-form-group">
                    <label><i class="fa fa-birthday-cake"></i> Date of Birth *</label>
                    <div class="date-picker-wrapper" id="dobPicker">
                        <div class="date-input-container">
                            <input type="text" id="date_of_birth_display" placeholder="Select date of birth" autocomplete="off" value="<?= !empty($data['date_of_birth']) ? date('F d, Y', strtotime($data['date_of_birth'])) : '' ?>">
                            <i class="fa fa-calendar-alt calendar-icon"></i>
                        </div>
                        <input type="hidden" name="date_of_birth" id="date_of_birth" value="<?= e($data['date_of_birth'] ?? '') ?>">
                        <div class="custom-calendar" id="calendarDropdown"></div>
                    </div>
                    <span class="na-field-error <?= isset($errors['date_of_birth'])?'show':'' ?>"><?= e($errors['date_of_birth'] ?? '') ?></span>
                </div>
                
                <!-- Gender -->
                <div class="na-form-group">
                    <label><i class="fa fa-venus-mars"></i> Gender *</label>
                    <div class="na-custom-select" id="genderSelect">
                        <div class="na-custom-select-trigger">
                            <?= e($data['gender'] ?? 'Select Gender') ?>
                        </div>
                        <div class="na-custom-select-dropdown">
                            <div class="na-custom-select-option" data-value="Male">Male</div>
                            <div class="na-custom-select-option" data-value="Female">Female</div>
                        </div>
                        <input type="hidden" name="gender" id="genderInput" value="<?= e($data['gender'] ?? '') ?>">
                    </div>
                    <span class="na-field-error <?= isset($errors['gender'])?'show':'' ?>"><?= e($errors['gender'] ?? '') ?></span>
                </div>
                
                <!-- Phone -->
                <div class="na-form-group">
                    <label><i class="fa fa-phone"></i> Phone Number *</label>
                    <input type="tel" name="phone" required value="<?= e($data['phone'] ?? '') ?>" placeholder="+266..." class="<?= isset($errors['phone'])?'error':'' ?>">
                    <span class="na-field-error <?= isset($errors['phone'])?'show':'' ?>"><?= e($errors['phone'] ?? '') ?></span>
                </div>
                
                <!-- Email -->
                <div class="na-form-group">
                    <label><i class="fa fa-envelope"></i> Email Address *</label>
                    <input type="email" name="email" required value="<?= e($data['email'] ?? '') ?>" class="<?= isset($errors['email'])?'error':'' ?>">
                    <span class="na-field-error <?= isset($errors['email'])?'show':'' ?>"><?= e($errors['email'] ?? '') ?></span>
                </div>
                
                <!-- Passport Type -->
                <div class="na-form-group">
                    <label><i class="fa fa-passport"></i> Passport Type *</label>
                    <div class="na-custom-select" id="passportTypeSelect">
                        <div class="na-custom-select-trigger">
                            <?= e($data['passport_type'] ?? 'Normal') ?>
                        </div>
                        <div class="na-custom-select-dropdown">
                            <div class="na-custom-select-option" data-value="Normal">Normal (Standard Processing)</div>
                            <div class="na-custom-select-option" data-value="Express">Express (Priority Processing)</div>
                        </div>
                        <input type="hidden" name="passport_type" id="passportTypeInput" value="<?= e($data['passport_type'] ?? 'Normal') ?>">
                    </div>
                </div>
                
                <!-- Address -->
                <div class="na-form-group full-width">
                    <label><i class="fa fa-map-marker-alt"></i> Residential Address *</label>
                    <textarea name="address" rows="3" required class="<?= isset($errors['address'])?'error':'' ?>"><?= e($data['address'] ?? '') ?></textarea>
                    <span class="na-field-error <?= isset($errors['address'])?'show':'' ?>"><?= e($errors['address'] ?? '') ?></span>
                </div>
                
                <!-- Photo Upload -->
                <div class="na-form-group full-width">
                    <label><i class="fa fa-camera"></i> Applicant Photo</label>
                    <div class="na-photo-zone" id="photoDropzone">
                        <i class="fa fa-cloud-upload-alt"></i>
                        <p>Click or drag photo here</p>
                        <p class="small">JPG, PNG up to 2MB</p>
                    </div>
                    <input type="file" id="photo_upload" name="photo_upload" accept="image/jpeg,image/png" style="display: none;">
                    <div class="na-photo-preview" id="photoPreview">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                    <span class="na-field-error <?= isset($errors['photo_upload'])?'show':'' ?>"><?= e($errors['photo_upload'] ?? '') ?></span>
                </div>
            </div>
            
            <div class="na-form-actions">
                <button type="button" class="na-btn-outline" onclick="window.history.back()">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="submit" class="na-btn-primary" onclick="return confirm('Submit this passport application?')">
                    <i class="fa fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Spotlight effect for card (matching dashboard)
(function() {
    const card = document.getElementById('formCard');
    if (card) {
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            this.style.setProperty('--x', x + '%');
            this.style.setProperty('--y', y + '%');
        });
    }
})();

// ============================================================
// PREMIUM CUSTOM CALENDAR DATE PICKER
// ============================================================
class PremiumDatePicker {
    constructor(inputId, hiddenInputId, containerId, options = {}) {
        this.input = document.getElementById(inputId);
        this.hiddenInput = document.getElementById(hiddenInputId);
        this.container = document.getElementById(containerId);
        this.currentDate = new Date();
        this.selectedDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : null;
        this.maxDate = options.maxDate ? new Date(options.maxDate) : new Date();
        this.minDate = options.minDate ? new Date(options.minDate) : null;
        
        this.init();
    }
    
    init() {
        this.renderCalendar();
        this.attachEvents();
    }
    
    renderCalendar() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const firstDayOfMonth = new Date(year, month, 1);
        const lastDayOfMonth = new Date(year, month + 1, 0);
        const startDayOfWeek = firstDayOfMonth.getDay();
        const daysInMonth = lastDayOfMonth.getDate();
        
        // Get previous month days
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const weekdays = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
        
        let calendarHtml = `
            <div class="calendar-header">
                <div class="calendar-nav" data-action="prev"><i class="fa fa-chevron-left"></i></div>
                <div class="calendar-month-year">${monthNames[month]} ${year}</div>
                <div class="calendar-nav" data-action="next"><i class="fa fa-chevron-right"></i></div>
            </div>
            <div class="calendar-weekdays">
                ${weekdays.map(day => `<div class="calendar-weekday">${day}</div>`).join('')}
            </div>
            <div class="calendar-days">
        `;
        
        // Empty cells for days before month starts
        for (let i = 0; i < startDayOfWeek; i++) {
            const prevDate = prevMonthLastDay - startDayOfWeek + i + 1;
            calendarHtml += `<div class="calendar-day other-month" data-date="${year}-${month}-${prevDate}">${prevDate}</div>`;
        }
        
        // Days of current month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateObj = new Date(year, month, day);
            const dateStr = this.formatDate(dateObj);
            const isSelected = this.selectedDate && this.formatDate(this.selectedDate) === dateStr;
            const isToday = this.formatDate(new Date()) === dateStr;
            const isDisabled = this.isDateDisabled(dateObj);
            
            let classes = 'calendar-day';
            if (isSelected) classes += ' selected';
            if (isToday) classes += ' today';
            if (isDisabled) classes += ' disabled';
            
            calendarHtml += `<div class="${classes}" data-date="${dateStr}" data-year="${year}" data-month="${month}" data-day="${day}">${day}</div>`;
        }
        
        // Next month days to fill grid (6 rows = 42 cells)
        const totalCells = 42;
        const currentCells = startDayOfWeek + daysInMonth;
        const nextMonthDays = totalCells - currentCells;
        
        for (let day = 1; day <= nextMonthDays; day++) {
            calendarHtml += `<div class="calendar-day other-month" data-date="${year}-${month + 1}-${day}">${day}</div>`;
        }
        
        calendarHtml += `
            </div>
            <div class="calendar-footer">
                <button type="button" class="calendar-clear" id="calendarClearBtn"><i class="fa fa-times-circle"></i> Clear</button>
                <button type="button" class="calendar-today-btn" id="calendarTodayBtn"><i class="fa fa-calendar-day"></i> Today</button>
            </div>
        `;
        
        this.container.innerHTML = calendarHtml;
    }
    
    formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    
    formatDisplayDate(date) {
        if (!date) return '';
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString(undefined, options);
    }
    
    isDateDisabled(date) {
        if (this.maxDate && date > this.maxDate) return true;
        if (this.minDate && date < this.minDate) return true;
        return false;
    }
    
    selectDate(date) {
        if (this.isDateDisabled(date)) return;
        this.selectedDate = date;
        this.hiddenInput.value = this.formatDate(date);
        this.input.value = this.formatDisplayDate(date);
        this.renderCalendar();
        this.container.classList.remove('show');
    }
    
    attachEvents() {
        // Toggle calendar
        this.input.addEventListener('click', (e) => {
            e.stopPropagation();
            this.container.classList.toggle('show');
            this.renderCalendar();
        });
        
        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target) && e.target !== this.input) {
                this.container.classList.remove('show');
            }
        });
        
        // Calendar navigation and selection (event delegation)
        this.container.addEventListener('click', (e) => {
            const dayDiv = e.target.closest('.calendar-day');
            const navBtn = e.target.closest('.calendar-nav');
            const clearBtn = e.target.closest('#calendarClearBtn');
            const todayBtn = e.target.closest('#calendarTodayBtn');
            
            if (dayDiv && !dayDiv.classList.contains('disabled')) {
                const dateStr = dayDiv.dataset.date;
                if (dateStr) {
                    const [year, month, day] = dateStr.split('-').map(Number);
                    const newDate = new Date(year, month, day);
                    if (!isNaN(newDate.getTime())) {
                        this.selectDate(newDate);
                    }
                }
            }
            
            if (navBtn) {
                const action = navBtn.dataset.action;
                if (action === 'prev') {
                    this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
                } else if (action === 'next') {
                    this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
                }
                this.renderCalendar();
            }
            
            if (clearBtn) {
                this.selectedDate = null;
                this.hiddenInput.value = '';
                this.input.value = '';
                this.renderCalendar();
                this.container.classList.remove('show');
            }
            
            if (todayBtn) {
                const today = new Date();
                if (!this.isDateDisabled(today)) {
                    this.selectDate(today);
                }
            }
        });
    }
}

// Initialize custom date picker (max date = today)
new PremiumDatePicker('date_of_birth_display', 'date_of_birth', 'calendarDropdown', {
    maxDate: new Date()
});

// Custom select initialization
function initCustomSelect(wrapperId, inputId) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    
    const trigger = wrapper.querySelector('.na-custom-select-trigger');
    const dropdown = wrapper.querySelector('.na-custom-select-dropdown');
    const input = document.getElementById(inputId);
    const options = wrapper.querySelectorAll('.na-custom-select-option');
    
    trigger.addEventListener('click', () => {
        dropdown.classList.toggle('show');
        trigger.classList.toggle('open');
    });
    
    options.forEach(opt => {
        opt.addEventListener('click', () => {
            trigger.innerHTML = opt.textContent;
            input.value = opt.dataset.value;
            dropdown.classList.remove('show');
            trigger.classList.remove('open');
        });
    });
    
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            dropdown.classList.remove('show');
            trigger.classList.remove('open');
        }
    });
}

initCustomSelect('genderSelect', 'genderInput');
initCustomSelect('passportTypeSelect', 'passportTypeInput');

// Photo upload preview
const photoInput = document.getElementById('photo_upload');
const dropzone = document.getElementById('photoDropzone');
const previewDiv = document.getElementById('photoPreview');
const previewImg = document.getElementById('previewImg');

dropzone.addEventListener('click', () => photoInput.click());

photoInput.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            previewImg.src = ev.target.result;
            previewDiv.style.display = 'flex';
            dropzone.style.display = 'none';
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Drag and drop
dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.style.borderColor = '#3B82F6';
    dropzone.style.background = 'rgba(59,130,246,.05)';
});

dropzone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    dropzone.style.borderColor = '';
    dropzone.style.background = '';
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.style.borderColor = '';
    dropzone.style.background = '';
    const files = e.dataTransfer.files;
    if (files.length) {
        photoInput.files = files;
        const event = new Event('change');
        photoInput.dispatchEvent(event);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>