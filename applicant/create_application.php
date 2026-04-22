<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('applicant');
$db  = getDB();
$uid = $_SESSION['user_id'];

// Pre-fill from the logged-in user's profile
$user = $db->prepare('SELECT * FROM users WHERE id = ?');
$user->execute([$uid]);
$user = $user->fetch();

$errors = [];
$data   = [
    'full_name'  => $user['full_name'] ?? '',
    'email'      => $user['email']     ?? '',
    'phone'      => $user['phone']     ?? '',
    'national_id'    => '',
    'date_of_birth'  => '',
    'gender'         => '',
    'address'        => '',
    'passport_type'  => 'Normal',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name'     => trim($_POST['full_name']     ?? ''),
        'national_id'   => trim($_POST['national_id']   ?? ''),
        'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
        'gender'        => trim($_POST['gender']        ?? ''),
        'address'       => trim($_POST['address']       ?? ''),
        'phone'         => trim($_POST['phone']         ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'passport_type' => trim($_POST['passport_type'] ?? 'Normal'),
    ];

    // ── Validation ───────────────────────────────────────────
    foreach (['full_name','national_id','date_of_birth','gender','address','phone','email'] as $f) {
        if (empty($data[$f])) $errors[$f] = 'This field is required.';
    }
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Enter a valid email address.';
    if (!empty($data['date_of_birth']) && strtotime($data['date_of_birth']) >= time())
        $errors['date_of_birth'] = 'Date of birth must be in the past.';
    if (!empty($data['phone']) && !preg_match('/^[+\d\s\-()]{7,20}$/', $data['phone']))
        $errors['phone'] = 'Enter a valid phone number.';
    if (!in_array($data['passport_type'], ['Normal','Express']))
        $errors['passport_type'] = 'Select a passport type.';

    // ── Photo upload ─────────────────────────────────────────
    $photoPath = null;
    if (isset($_FILES['photo_upload']) && $_FILES['photo_upload']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['photo_upload'];
        $allowed = ['image/jpeg','image/png','image/jpg'];
        if (!in_array($file['type'], $allowed)) {
            $errors['photo_upload'] = 'Only JPG/PNG images are allowed.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['photo_upload'] = 'Photo must be under 2 MB.';
        } else {
            $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $photoPath = uniqid('photo_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $photoPath);
        }
    }

    // ── Save ─────────────────────────────────────────────────
    if (empty($errors)) {
        $appNum = generateApplicationNumber();

        $stmt = $db->prepare('INSERT INTO passport_applications
            (application_number, applicant_user_id, officer_id,
             full_name, national_id, date_of_birth, gender,
             address, phone, email, passport_type, application_date,
             photo_path, current_stage, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $appNum, $uid, $uid,
            $data['full_name'], $data['national_id'], $data['date_of_birth'],
            $data['gender'], $data['address'], $data['phone'], $data['email'],
            $data['passport_type'], date('Y-m-d'), $photoPath,
            'Application Submitted', 'Pending',
        ]);
        $appId = (int)$db->lastInsertId();

        // Create all stage records
        $allStages = [
            'Application Submitted','Document Verification','Biometric Capture',
            'Background Check','Passport Printing','Ready for Collection','Passport Released',
        ];
        $ins = $db->prepare('INSERT INTO processing_stages
            (application_id, stage_name, status, officer_id, comments) VALUES (?,?,?,?,?)');
        foreach ($allStages as $stage) {
            $stStatus  = ($stage === 'Application Submitted') ? 'Completed' : 'Pending';
            $comment   = ($stage === 'Application Submitted') ? 'Self-submitted by applicant' : null;
            $ins->execute([$appId, $stage, $stStatus, $uid, $comment]);
        }

        addNotification($uid, $appId,
            "Your application $appNum has been submitted and is pending review.");

        logActivity($uid, 'APPLICANT_SUBMITTED', "Self-submitted application $appNum");

        flash('success', "Application $appNum submitted successfully! An officer will review your details.");
        redirect(APP_URL . '/applicant/track.php?app_num=' . urlencode($appNum));
    }
}

$pageTitle = 'New Passport Application';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   CREATE APPLICATION PAGE — Premium Edition
   ───────────────────────────────────────────────────────────── */

/* ── Entry animations ──────────────────────────────────────── */
@keyframes fadeUp   { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn   { from{opacity:0} to{opacity:1} }
@keyframes shimmer  {
  0%  { background-position: -200% center }
  100%{ background-position:  200% center }
}
@keyframes float {
  0%,100%{ transform:translateY(0) }
  50%    { transform:translateY(-6px) }
}
@keyframes dropdownSlide {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes datePickerFadeIn {
  from { opacity: 0; transform: translateY(-10px) scale(0.96); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.ca-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.ca-animate-d1 { animation-delay:.06s }
.ca-animate-d2 { animation-delay:.12s }
.ca-animate-d3 { animation-delay:.18s }
.ca-animate-d4 { animation-delay:.24s }

/* ── Spotlight Card Effect ───────────────────────────────── */
.hover-card {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  overflow: hidden;
  transform-style: preserve-3d;
  will-change: transform;
}

.hover-card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: radial-gradient(circle at var(--x, 50%) var(--y, 50%), 
              rgba(59, 130, 246, 0.12) 0%, 
              transparent 70%);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  z-index: 1;
}

.hover-card:hover::before {
  opacity: 1;
}

.hover-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

/* ── Hero Section ────────────────────────────────────────── */
.ca-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .ca-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.ca-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.ca-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.ca-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.ca-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.ca-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.ca-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.ca-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}

.ca-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.ca-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.ca-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.ca-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.ca-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.ca-hero-meta-chip i { font-size: .62rem; }

.ca-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Main Grid ────────────────────────────────────────────── */
.ca-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
}
@media(max-width: 800px) { .ca-grid { grid-template-columns: 1fr; } }

/* ── Form Card ────────────────────────────────────────────── */
.ca-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.ca-card::before {
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
.ca-card:hover::before { opacity: 1; }
.ca-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.card-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.card-title i {
  color: var(--gold);
  font-size: .78rem;
}

.card-body {
  padding: 1.5rem;
}

/* Form Elements */
.ca-section-title {
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--gold-light);
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.ca-section-title i {
  font-size: .7rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}
.form-group.full { grid-column: 1 / -1; }
.form-group label {
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: .4rem;
  display: block;
}
.form-group label i {
  margin-right: 0.25rem;
}

.input-wrap {
  position: relative;
}
.input-wrap.has-prefix {
  position: relative;
}
.input-wrap.has-prefix .prefix {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 0.85rem;
  z-index: 1;
}
.input-wrap.has-prefix input,
.input-wrap.has-prefix textarea {
  padding-left: 2.2rem;
}
.input-wrap input,
.input-wrap textarea,
.form-group select {
  width: 100%;
  padding: .75rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .9rem;
  transition: all .2s;
}
.input-wrap input:focus,
.input-wrap textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--navy-light);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.input-wrap input.error,
.input-wrap textarea.error {
  border-color: var(--danger);
}
textarea {
  resize: vertical;
  font-family: inherit;
}

/* Custom Select */
.select-custom {
  position: relative;
  width: 100%;
}
.select-trigger {
  width: 100%;
  padding: .75rem 2rem .75rem 1rem;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: .9rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all .2s;
}
.select-trigger.error {
  border-color: var(--danger);
}
.select-trigger:hover {
  border-color: var(--navy-light);
}
.select-trigger::after {
  content: '\f107';
  font-family: 'FontAwesome';
  font-size: .8rem;
  color: var(--muted);
}
.select-trigger.open::after {
  content: '\f106';
}
.select-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  left: 0;
  right: 0;
  background: var(--bg-alt);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  z-index: 100;
  display: none;
  max-height: 200px;
  overflow-y: auto;
  animation: dropdownSlide 0.2s ease;
  box-shadow: 0 20px 35px -10px rgba(0,0,0,0.4);
}
.select-dropdown.show {
  display: block;
}
.select-option {
  padding: .6rem 1rem;
  cursor: pointer;
  transition: all .15s;
  font-size: .85rem;
  color: var(--text-soft);
}
.select-option:hover {
  background: var(--navy-light);
  color: #fff;
}
.select-option.selected {
  background: rgba(59,130,246,.15);
  color: var(--navy-light);
  font-weight: 500;
}

/* ── CUSTOM DATE PICKER (Premium Styled) ──────────────────── */
.date-picker-custom {
  position: relative;
  width: 100%;
}
.date-trigger {
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
  transition: all .2s;
}
.date-trigger.error {
  border-color: var(--danger);
}
.date-trigger:hover {
  border-color: var(--navy-light);
}
.date-trigger span:first-child {
  color: var(--text-soft);
}
.date-trigger .date-value {
  color: var(--text);
  font-weight: 500;
}
.date-trigger .date-placeholder {
  color: var(--muted);
}
.date-trigger i {
  font-size: 0.85rem;
  color: var(--muted);
  transition: transform 0.2s;
}
.date-trigger.open i {
  transform: rotate(180deg);
}
.date-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 35px -10px rgba(0,0,0,0.5);
  z-index: 200;
  display: none;
  animation: datePickerFadeIn 0.2s ease;
  backdrop-filter: blur(2px);
  padding: 1rem;
}
.date-dropdown.show {
  display: block;
}
.date-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border);
}
.date-month-year {
  font-weight: 700;
  color: var(--gold-light);
  font-size: 0.85rem;
}
.date-nav-btn {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  width: 28px;
  height: 28px;
  cursor: pointer;
  color: var(--text-soft);
  transition: all 0.2s;
}
.date-nav-btn:hover {
  background: var(--navy-light);
  color: white;
  border-color: var(--navy-light);
}
.date-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  margin-bottom: 0.5rem;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
}
.date-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.date-day {
  text-align: center;
  padding: 8px 0;
  border-radius: var(--radius);
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.15s;
  color: var(--text-soft);
}
.date-day:hover:not(.empty) {
  background: rgba(59,130,246,0.2);
  color: var(--navy-light);
  transform: scale(1.02);
}
.date-day.selected {
  background: var(--navy-light);
  color: white;
  box-shadow: 0 2px 6px rgba(59,130,246,0.4);
}
.date-day.empty {
  cursor: default;
  opacity: 0.3;
}
.date-day.disabled {
  opacity: 0.35;
  cursor: not-allowed;
  background: transparent;
}
.date-day.today {
  border: 1px solid var(--gold-light);
  font-weight: 700;
}
.date-quick-buttons {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border);
  flex-wrap: wrap;
}
.date-quick-btn {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 0.3rem 0.8rem;
  font-size: 0.7rem;
  cursor: pointer;
  transition: all 0.2s;
  color: var(--text-soft);
}
.date-quick-btn:hover {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: white;
}
/* end date picker */

/* Photo Upload */
.photo-upload {
  grid-column: 1 / -1;
}
.photo-box {
  display: block;
  width: 100%;
  min-height: 150px;
  background: var(--surface);
  border: 2px dashed var(--border);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all .2s;
  text-align: center;
}
.photo-box:hover {
  border-color: var(--navy-light);
  background: rgba(59,130,246,.05);
}
.photo-box.error {
  border-color: var(--danger);
}
.photo-placeholder {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  color: var(--muted);
}
.photo-placeholder i {
  font-size: 2rem;
}
.photo-placeholder span {
  font-size: .8rem;
}
.photo-preview {
  display: none;
  max-width: 150px;
  margin: 1rem auto;
  border-radius: var(--radius);
  border: 1px solid var(--border);
}
input[type="file"] {
  display: none;
}

/* Field Error */
.field-error {
  font-size: .7rem;
  color: var(--danger);
  display: none;
  margin-top: 0.3rem;
}
.field-error.show {
  display: block;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border);
}

/* Alert Boxes */
.alert {
  padding: 1rem 1.25rem;
  border-radius: var(--radius);
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}
.alert i {
  font-size: 1.1rem;
  flex-shrink: 0;
}
.alert-error {
  background: rgba(239,68,68,.1);
  color: #F87171;
}
.alert-error div {
  color: var(--text-soft);
}

/* Sidebar Cards */
.sidebar-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1rem;
}
.sidebar-card:last-child {
  margin-bottom: 0;
}
.sidebar-card .card-header {
  padding: 1rem 1.25rem;
}
.sidebar-card .card-body {
  padding: 1rem 1.25rem;
}
.step-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.step-item {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}
.step-icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: .7rem;
}
.step-icon.navy-light { background: rgba(59,130,246,.12); color: #60A5FA; }
.step-icon.info { background: rgba(59,130,246,.12); color: #60A5FA; }
.step-icon.warning { background: rgba(245,158,11,.12); color: #F59E0B; }
.step-icon.success { background: rgba(52,211,153,.12); color: #34D399; }
.step-icon.gold { background: rgba(200,145,26,.12); color: var(--gold-light); }
.step-title {
  font-size: .82rem;
  font-weight: 600;
  color: var(--text);
}
.step-desc {
  font-size: .72rem;
  color: var(--muted);
  margin-top: 0.1rem;
}
.important-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}
.important-list li {
  font-size: .8rem;
  color: var(--text-soft);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.important-list li i {
  color: var(--gold);
  font-size: .55rem;
}

/* Divider */
.divider-light {
  border: none;
  border-top: 1px solid var(--border);
  margin: 1rem 0;
}

/* Buttons */
.btn-primary {
  background: linear-gradient(135deg, #1D5A9E, #3B82F6);
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: #fff;
  font-weight: 600;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59,130,246,.3);
}
.btn-outline {
  background: transparent;
  border: 1.5px solid var(--border);
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: var(--text-soft);
  font-weight: 500;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.btn-outline:hover {
  border-color: var(--navy-light);
  color: var(--navy-light);
  transform: translateY(-1px);
}
.btn-ghost {
  background: transparent;
  border: 1.5px solid transparent;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  color: var(--text-soft);
  font-weight: 500;
  transition: all .2s;
}
.btn-ghost:hover {
  background: rgba(59,130,246,.08);
  color: var(--navy-light);
}
.btn-lg {
  padding: 0.8rem 1.5rem;
  font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .ca-hero-inner {
    flex-direction: column;
    text-align: center;
  }
  .form-actions {
    flex-direction: column;
  }
  .form-actions .btn {
    width: 100%;
    text-align: center;
    justify-content: center;
  }
}
</style>

<!-- Hero Section -->
<div class="ca-hero ca-animate">
  <div class="ca-hero-mesh"></div>
  <div class="ca-hero-grid"></div>
  <div class="ca-hero-inner">
    <div class="ca-hero-left">
      <div class="ca-hero-icon"><i class="fa fa-file-circle-plus"></i></div>
      <div>
        <div class="ca-hero-eyebrow">Passport Applicant</div>
        <div class="ca-hero-name">New Passport Application</div>
        <div class="ca-hero-meta">
          <span class="ca-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="ca-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="ca-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Fill in your details
          </span>
        </div>
      </div>
    </div>
    <div class="ca-hero-right">
      <a href="<?= APP_URL ?>/applicant/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
      </a>
    </div>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error ca-animate ca-animate-d1">
  <i class="fa fa-circle-exclamation"></i>
  <div>Please correct the highlighted errors before submitting.</div>
</div>
<?php endif; ?>

<!-- Main Grid -->
<div class="ca-grid">

  <!-- Main Form Card -->
  <div class="ca-card ca-animate ca-animate-d2 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-file-alt"></i> Application Form</span>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data" id="applicationForm">

        <!-- Section: Personal Info -->
        <div class="ca-section-title">
          <i class="fa fa-id-card"></i> Personal Information
        </div>
        <div class="form-grid">

          <div class="form-group full">
            <label for="full_name"><i class="fa fa-user"></i> Full Name *</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-user"></i>
              <input type="text" id="full_name" name="full_name" required
                     value="<?= e($data['full_name']) ?>"
                     placeholder="As it appears on your national ID"
                     class="<?= isset($errors['full_name']) ? 'error' : '' ?>">
            </div>
            <span class="field-error <?= isset($errors['full_name']) ? 'show' : '' ?>">
              <?= e($errors['full_name'] ?? '') ?>
            </span>
          </div>

          <div class="form-group">
            <label for="national_id"><i class="fa fa-id-card"></i> National ID Number *</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-id-card"></i>
              <input type="text" id="national_id" name="national_id" required
                     value="<?= e($data['national_id']) ?>"
                     placeholder="e.g., 123456789"
                     class="<?= isset($errors['national_id']) ? 'error' : '' ?>">
            </div>
            <span class="field-error <?= isset($errors['national_id']) ? 'show' : '' ?>">
              <?= e($errors['national_id'] ?? '') ?>
            </span>
          </div>

          <!-- CUSTOM DATE PICKER FIELD (replaces native date input) -->
          <div class="form-group">
            <label for="date_of_birth"><i class="fa fa-birthday-cake"></i> Date of Birth *</label>
            <div class="date-picker-custom" id="dobDatePicker">
              <div class="date-trigger <?= isset($errors['date_of_birth']) ? 'error' : '' ?>" id="dobTrigger">
                <span class="date-placeholder" id="dobPlaceholder">Select date of birth</span>
                <span class="date-value" id="dobValue" style="display:none;"></span>
                <i class="fa fa-calendar-alt"></i>
              </div>
              <div class="date-dropdown" id="dobDropdown"></div>
              <input type="hidden" name="date_of_birth" id="dobHidden" value="<?= e($data['date_of_birth']) ?>">
            </div>
            <span class="field-error <?= isset($errors['date_of_birth']) ? 'show' : '' ?>">
              <?= e($errors['date_of_birth'] ?? '') ?>
            </span>
          </div>

          <div class="form-group">
            <label><i class="fa fa-venus-mars"></i> Gender *</label>
            <div class="select-custom" id="genderSelect">
              <div class="select-trigger <?= isset($errors['gender']) ? 'error' : '' ?>">
                <span class="select-placeholder">
                  <?= $data['gender'] ? e($data['gender']) : 'Select gender…' ?>
                </span>
              </div>
              <div class="select-dropdown">
                <div class="select-option <?= $data['gender']==='Male' ? 'selected' : '' ?>" data-value="Male">Male</div>
                <div class="select-option <?= $data['gender']==='Female' ? 'selected' : '' ?>" data-value="Female">Female</div>
              </div>
              <input type="hidden" name="gender" id="genderInput" value="<?= e($data['gender']) ?>">
            </div>
            <span class="field-error <?= isset($errors['gender']) ? 'show' : '' ?>">
              <?= e($errors['gender'] ?? '') ?>
            </span>
          </div>

          <div class="form-group full">
            <label for="address"><i class="fa fa-map-marker-alt"></i> Residential Address *</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-map-marker-alt"></i>
              <textarea id="address" name="address" rows="3"
                        placeholder="Street, village/town, district"
                        class="<?= isset($errors['address']) ? 'error' : '' ?>"><?= e($data['address']) ?></textarea>
            </div>
            <span class="field-error <?= isset($errors['address']) ? 'show' : '' ?>">
              <?= e($errors['address'] ?? '') ?>
            </span>
          </div>

        </div>

        <div class="divider-light"></div>

        <!-- Section: Contact -->
        <div class="ca-section-title">
          <i class="fa fa-address-book"></i> Contact Details
        </div>
        <div class="form-grid">

          <div class="form-group">
            <label for="phone"><i class="fa fa-phone"></i> Phone Number *</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-phone"></i>
              <input type="tel" id="phone" name="phone" required
                     value="<?= e($data['phone']) ?>"
                     placeholder="+266 ..."
                     class="<?= isset($errors['phone']) ? 'error' : '' ?>">
            </div>
            <span class="field-error <?= isset($errors['phone']) ? 'show' : '' ?>">
              <?= e($errors['phone'] ?? '') ?>
            </span>
          </div>

          <div class="form-group">
            <label for="email"><i class="fa fa-envelope"></i> Email Address *</label>
            <div class="input-wrap has-prefix">
              <i class="prefix fa fa-envelope"></i>
              <input type="email" id="email" name="email" required
                     value="<?= e($data['email']) ?>"
                     placeholder="you@example.com"
                     class="<?= isset($errors['email']) ? 'error' : '' ?>">
            </div>
            <span class="field-error <?= isset($errors['email']) ? 'show' : '' ?>">
              <?= e($errors['email'] ?? '') ?>
            </span>
          </div>

        </div>

        <div class="divider-light"></div>

        <!-- Section: Passport details -->
        <div class="ca-section-title">
          <i class="fa fa-passport"></i> Passport Details
        </div>
        <div class="form-grid">

          <div class="form-group">
            <label><i class="fa fa-tag"></i> Passport Type *</label>
            <div class="select-custom" id="passportTypeSelect">
              <div class="select-trigger <?= isset($errors['passport_type']) ? 'error' : '' ?>">
                <span class="select-placeholder">
                  <?= e($data['passport_type'] ?: 'Select type…') ?>
                </span>
              </div>
              <div class="select-dropdown">
                <div class="select-option <?= $data['passport_type']==='Normal' ? 'selected' : '' ?>" data-value="Normal">
                  Normal <span style="font-size:.75rem;color:var(--muted);margin-left:.35rem;">Standard processing</span>
                </div>
                <div class="select-option <?= $data['passport_type']==='Express' ? 'selected' : '' ?>" data-value="Express">
                  Express <span style="font-size:.75rem;color:var(--muted);margin-left:.35rem;">Expedited processing</span>
                </div>
              </div>
              <input type="hidden" name="passport_type" id="passportTypeInput" value="<?= e($data['passport_type']) ?>">
            </div>
            <span class="field-error <?= isset($errors['passport_type']) ? 'show' : '' ?>">
              <?= e($errors['passport_type'] ?? '') ?>
            </span>
          </div>

          <div class="form-group photo-upload">
            <label><i class="fa fa-camera"></i> Passport Photo <span style="color:var(--muted);font-weight:400;">(JPG/PNG, max 2 MB)</span></label>
            <label class="photo-box <?= isset($errors['photo_upload']) ? 'error' : '' ?>" for="photo_upload" id="photo_box">
              <div class="photo-placeholder" id="photo_placeholder">
                <i class="fa fa-cloud-upload-alt"></i>
                <span>Click or drag photo here</span>
              </div>
              <img id="photo_preview" class="photo-preview" src="" alt="Preview">
            </label>
            <input type="file" id="photo_upload" name="photo_upload" accept="image/jpeg,image/png">
            <span class="field-error <?= isset($errors['photo_upload']) ? 'show' : '' ?>">
              <?= e($errors['photo_upload'] ?? '') ?>
            </span>
          </div>

        </div>

        <div class="form-actions">
          <a href="<?= APP_URL ?>/applicant/dashboard.php" class="btn btn-outline">
            <i class="fa fa-times"></i> Cancel
          </a>
          <button type="submit" class="btn btn-primary btn-lg"
                  onclick="return confirm('Submit this passport application? Please ensure all details are correct.')">
            <i class="fa fa-paper-plane"></i> Submit Application
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- Sidebar -->
  <div>

    <!-- What happens next? Card -->
    <div class="sidebar-card accent-blue ca-animate ca-animate-d3 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-circle-info"></i> What happens next?</span>
      </div>
      <div class="card-body">
        <ul class="step-list">
          <li class="step-item">
            <div class="step-icon navy-light"><i class="fa fa-paper-plane"></i></div>
            <div><div class="step-title">Submitted</div><div class="step-desc">Your application enters the queue.</div></div>
          </li>
          <li class="step-item">
            <div class="step-icon info"><i class="fa fa-file-check"></i></div>
            <div><div class="step-title">Document Verification</div><div class="step-desc">An officer reviews your details.</div></div>
          </li>
          <li class="step-item">
            <div class="step-icon warning"><i class="fa fa-fingerprint"></i></div>
            <div><div class="step-title">Biometric Capture</div><div class="step-desc">Visit the office for fingerprints.</div></div>
          </li>
          <li class="step-item">
            <div class="step-icon success"><i class="fa fa-shield-check"></i></div>
            <div><div class="step-title">Background Check</div><div class="step-desc">Security clearance is processed.</div></div>
          </li>
          <li class="step-item">
            <div class="step-icon info"><i class="fa fa-print"></i></div>
            <div><div class="step-title">Passport Printing</div><div class="step-desc">Your passport is printed.</div></div>
          </li>
          <li class="step-item">
            <div class="step-icon gold"><i class="fa fa-box-open"></i></div>
            <div><div class="step-title">Ready for Collection</div><div class="step-desc">You will be notified to collect.</div></div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Important Information Card -->
    <div class="sidebar-card accent-gold ca-animate ca-animate-d4 hover-card">
      <div class="card-header">
        <span class="card-title"><i class="fa fa-triangle-exclamation"></i> Important</span>
      </div>
      <div class="card-body">
        <ul class="important-list">
          <li><i class="fa fa-circle-dot"></i> Ensure your national ID number is correct.</li>
          <li><i class="fa fa-circle-dot"></i> Use a clear, recent passport-style photo.</li>
          <li><i class="fa fa-circle-dot"></i> You must visit an office for biometric capture.</li>
          <li><i class="fa fa-circle-dot"></i> Express passports incur an additional fee.</li>
          <li><i class="fa fa-circle-dot"></i> An officer will review your submission before it proceeds.</li>
        </ul>
      </div>
    </div>

  </div><!-- /sidebar -->
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

// Custom select dropdowns
function initCustomSelect(selectId, inputId) {
  const container = document.getElementById(selectId);
  if (!container) return;
  
  const trigger = container.querySelector('.select-trigger');
  const dropdown = container.querySelector('.select-dropdown');
  const options = container.querySelectorAll('.select-option');
  const hiddenInput = document.getElementById(inputId);
  const placeholderSpan = trigger.querySelector('.select-placeholder');
  
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    document.querySelectorAll('.select-dropdown.show').forEach(d => {
      if (d !== dropdown) d.classList.remove('show');
    });
    document.querySelectorAll('.select-trigger.open').forEach(t => {
      if (t !== trigger) t.classList.remove('open');
    });
    dropdown.classList.toggle('show');
    trigger.classList.toggle('open');
  });
  
  options.forEach(opt => {
    opt.addEventListener('click', () => {
      const value = opt.dataset.value;
      const text = opt.textContent.trim();
      hiddenInput.value = value;
      placeholderSpan.textContent = value;
      options.forEach(o => o.classList.remove('selected'));
      opt.classList.add('selected');
      dropdown.classList.remove('show');
      trigger.classList.remove('open');
    });
  });
  
  document.addEventListener('click', (e) => {
    if (!container.contains(e.target)) {
      dropdown.classList.remove('show');
      trigger.classList.remove('open');
    }
  });
}

initCustomSelect('genderSelect', 'genderInput');
initCustomSelect('passportTypeSelect', 'passportTypeInput');

// ======================== CUSTOM DATE PICKER ========================
class CustomDatePicker {
  constructor(containerId, hiddenInputId, options = {}) {
    this.container = document.getElementById(containerId);
    this.hiddenInput = document.getElementById(hiddenInputId);
    this.trigger = this.container.querySelector('.date-trigger');
    this.dropdown = this.container.querySelector('.date-dropdown');
    this.placeholderSpan = this.container.querySelector('.date-placeholder');
    this.valueSpan = this.container.querySelector('.date-value');
    
    this.currentDate = new Date();
    this.selectedDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : null;
    this.maxDate = new Date(); // today (must be in the past)
    this.maxDate.setHours(0,0,0,0);
    
    this.init();
  }
  
  init() {
    this.renderCalendar();
    this.attachEvents();
    this.updateTriggerDisplay();
  }
  
  updateTriggerDisplay() {
    if (this.selectedDate) {
      const formatted = this.formatDate(this.selectedDate);
      this.placeholderSpan.style.display = 'none';
      this.valueSpan.style.display = 'inline';
      this.valueSpan.textContent = formatted;
      this.trigger.classList.remove('error');
    } else {
      this.placeholderSpan.style.display = 'inline';
      this.valueSpan.style.display = 'none';
    }
  }
  
  formatDate(date) {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${year}-${month}-${day}`;
  }
  
  formatDisplay(date) {
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
  }
  
  renderCalendar() {
    const year = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const startWeekday = firstDay.getDay(); // 0 = Sunday
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    let calendarHtml = `
      <div class="date-header">
        <button type="button" class="date-nav-btn" data-nav="prev"><i class="fa fa-chevron-left"></i></button>
        <span class="date-month-year">${firstDay.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</span>
        <button type="button" class="date-nav-btn" data-nav="next"><i class="fa fa-chevron-right"></i></button>
      </div>
      <div class="date-weekdays">
        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
      </div>
      <div class="date-days">
    `;
    
    // Empty cells before first day
    for (let i = 0; i < startWeekday; i++) {
      calendarHtml += `<div class="date-day empty"></div>`;
    }
    
    // Days of month
    for (let d = 1; d <= daysInMonth; d++) {
      const cellDate = new Date(year, month, d);
      const isPast = cellDate < this.maxDate || cellDate.getTime() === this.maxDate.getTime();
      const isSelected = this.selectedDate && this.selectedDate.toDateString() === cellDate.toDateString();
      const isToday = cellDate.toDateString() === new Date().toDateString();
      let classes = 'date-day';
      if (!isPast) classes += ' disabled';
      if (isSelected) classes += ' selected';
      if (isToday) classes += ' today';
      calendarHtml += `<div class="${classes}" data-year="${year}" data-month="${month}" data-day="${d}">${d}</div>`;
    }
    
    calendarHtml += `</div><div class="date-quick-buttons">
      <button type="button" class="date-quick-btn" data-quick="today">Today</button>
      <button type="button" class="date-quick-btn" data-quick="clear">Clear</button>
    </div>`;
    
    this.dropdown.innerHTML = calendarHtml;
  }
  
  attachEvents() {
    // Toggle dropdown
    this.trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      document.querySelectorAll('.date-dropdown.show').forEach(d => {
        if (d !== this.dropdown) d.classList.remove('show');
      });
      document.querySelectorAll('.date-trigger.open').forEach(t => {
        if (t !== this.trigger) t.classList.remove('open');
      });
      this.dropdown.classList.toggle('show');
      this.trigger.classList.toggle('open');
      if (this.dropdown.classList.contains('show')) {
        this.renderCalendar(); // re-render in case month changed elsewhere
        this.attachDropdownEvents();
      }
    });
    
    document.addEventListener('click', (e) => {
      if (!this.container.contains(e.target)) {
        this.dropdown.classList.remove('show');
        this.trigger.classList.remove('open');
      }
    });
  }
  
  attachDropdownEvents() {
    // Navigation buttons
    const prevBtn = this.dropdown.querySelector('[data-nav="prev"]');
    const nextBtn = this.dropdown.querySelector('[data-nav="next"]');
    if (prevBtn) {
      prevBtn.replaceWith(prevBtn.cloneNode(true));
      this.dropdown.querySelector('[data-nav="prev"]').addEventListener('click', (e) => {
        e.stopPropagation();
        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
        this.renderCalendar();
        this.attachDropdownEvents();
      });
    }
    if (nextBtn) {
      nextBtn.replaceWith(nextBtn.cloneNode(true));
      this.dropdown.querySelector('[data-nav="next"]').addEventListener('click', (e) => {
        e.stopPropagation();
        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
        this.renderCalendar();
        this.attachDropdownEvents();
      });
    }
    
    // Day selection
    const dayCells = this.dropdown.querySelectorAll('.date-day:not(.empty):not(.disabled)');
    dayCells.forEach(cell => {
      cell.addEventListener('click', () => {
        const year = parseInt(cell.dataset.year);
        const month = parseInt(cell.dataset.month);
        const day = parseInt(cell.dataset.day);
        const newDate = new Date(year, month, day);
        if (newDate < this.maxDate || newDate.getTime() === this.maxDate.getTime()) {
          this.selectedDate = newDate;
          this.hiddenInput.value = this.formatDate(newDate);
          this.updateTriggerDisplay();
          this.dropdown.classList.remove('show');
          this.trigger.classList.remove('open');
          // trigger change event for any other validation
          const changeEvent = new Event('change', { bubbles: true });
          this.hiddenInput.dispatchEvent(changeEvent);
        }
      });
    });
    
    // Quick buttons
    const todayBtn = this.dropdown.querySelector('[data-quick="today"]');
    const clearBtn = this.dropdown.querySelector('[data-quick="clear"]');
    if (todayBtn) {
      todayBtn.addEventListener('click', () => {
        const today = new Date();
        today.setHours(0,0,0,0);
        if (today <= this.maxDate) {
          this.selectedDate = today;
          this.hiddenInput.value = this.formatDate(today);
          this.updateTriggerDisplay();
          this.dropdown.classList.remove('show');
          this.trigger.classList.remove('open');
        }
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        this.selectedDate = null;
        this.hiddenInput.value = '';
        this.updateTriggerDisplay();
        this.dropdown.classList.remove('show');
        this.trigger.classList.remove('open');
      });
    }
  }
}

// Initialize custom date picker
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('dobDatePicker')) {
    new CustomDatePicker('dobDatePicker', 'dobHidden');
  }
});

// Photo preview
const photoInput = document.getElementById('photo_upload');
const photoPreview = document.getElementById('photo_preview');
const photoPH = document.getElementById('photo_placeholder');

if (photoInput) {
  photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = e => {
        photoPreview.src = e.target.result;
        photoPreview.style.display = 'block';
        photoPH.style.display = 'none';
      };
      reader.readAsDataURL(file);
    } else {
      photoPreview.style.display = 'none';
      photoPH.style.display = 'flex';
    }
  });
}

// Drag and drop for photo upload
const photoBox = document.getElementById('photo_box');
if (photoBox) {
  photoBox.addEventListener('dragover', (e) => {
    e.preventDefault();
    photoBox.style.borderColor = '#3B82F6';
    photoBox.style.background = 'rgba(59,130,246,.05)';
  });
  
  photoBox.addEventListener('dragleave', (e) => {
    e.preventDefault();
    photoBox.style.borderColor = '';
    photoBox.style.background = '';
  });
  
  photoBox.addEventListener('drop', (e) => {
    e.preventDefault();
    photoBox.style.borderColor = '';
    photoBox.style.background = '';
    const files = e.dataTransfer.files;
    if (files.length) {
      photoInput.files = files;
      const event = new Event('change');
      photoInput.dispatchEvent(event);
    }
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>