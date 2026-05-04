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

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-file-circle-plus"></i></div>
      <div>
        <div class="hero-eyebrow">Passport Applicant</div>
        <div class="hero-name">New Passport Application</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-passport"></i> Fill in your details
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <a href="<?= APP_URL ?>/applicant/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
      </a>
    </div>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error animate animate-d1">
  <i class="fa fa-circle-exclamation"></i>
  <div>Please correct the highlighted errors before submitting.</div>
</div>
<?php endif; ?>

<!-- Main Grid -->
<div class="grid">

  <!-- Main Form Card -->
  <div class="card animate animate-d2 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-file-alt"></i> Application Form</span>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data" id="applicationForm">

        <!-- Section: Personal Info -->
        <div class="section-title">
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
        <div class="section-title">
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
        <div class="section-title">
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
    <div class="sidebar-card accent-blue animate animate-d3 hover-card">
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
    <div class="sidebar-card accent-gold animate animate-d4 hover-card">
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
    this.container    = document.getElementById(containerId);
    this.hiddenInput  = document.getElementById(hiddenInputId);
    this.trigger      = this.container.querySelector('.date-trigger');
    this.dropdown     = this.container.querySelector('.date-dropdown');
    this.placeholderSpan = this.container.querySelector('.date-placeholder');
    this.valueSpan    = this.container.querySelector('.date-value');

    this.currentDate  = new Date();
    this.selectedDate = this.hiddenInput.value
      ? new Date(this.hiddenInput.value + 'T00:00:00') : null;
    this.maxDate = new Date();
    this.maxDate.setHours(0, 0, 0, 0);

    // view: 'day' | 'month' | 'year'
    this.view = 'day';
    this.yearRangeStart = Math.floor(this.currentDate.getFullYear() / 12) * 12;

    this.init();
  }

  init() {
    this.render();
    this.attachEvents();
    this.updateTriggerDisplay();
  }

  updateTriggerDisplay() {
    if (this.selectedDate) {
      this.placeholderSpan.style.display = 'none';
      this.valueSpan.style.display = 'inline';
      this.valueSpan.textContent = this.formatDisplay(this.selectedDate);
      this.trigger.classList.remove('error');
    } else {
      this.placeholderSpan.style.display = 'inline';
      this.valueSpan.style.display = 'none';
    }
  }

  formatDate(date) {
    const d = String(date.getDate()).padStart(2, '0');
    const m = String(date.getMonth() + 1).padStart(2, '0');
    return `${date.getFullYear()}-${m}-${d}`;
  }

  formatDisplay(date) {
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
  }

  // ── Render dispatcher ──────────────────────────────────────
  render() {
    if (this.view === 'day')   this.renderDays();
    if (this.view === 'month') this.renderMonths();
    if (this.view === 'year')  this.renderYears();
    this.attachDropdownEvents();
  }

  // ── Day view ───────────────────────────────────────────────
  renderDays() {
    const year  = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();
    const startWeekday  = new Date(year, month, 1).getDay();
    const daysInMonth   = new Date(year, month + 1, 0).getDate();
    const MONTHS = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];

    let html = `
      <div class="date-header">
        <button type="button" class="date-nav-btn" data-nav="prev"><i class="fa fa-chevron-left"></i></button>
        <button type="button" class="date-month-year dp-header-btn" data-nav="switch-month">
          ${MONTHS[month]} ${year} <i class="fa fa-caret-down" style="font-size:.6rem;margin-left:.2rem;opacity:.6;"></i>
        </button>
        <button type="button" class="date-nav-btn" data-nav="next"><i class="fa fa-chevron-right"></i></button>
      </div>
      <div class="date-weekdays">
        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
      </div>
      <div class="date-days">`;

    for (let i = 0; i < startWeekday; i++) {
      html += `<div class="date-day empty"></div>`;
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const cell = new Date(year, month, d);
      const disabled = cell > this.maxDate;
      const selected = this.selectedDate && this.selectedDate.toDateString() === cell.toDateString();
      const today    = cell.toDateString() === new Date().toDateString();
      let cls = 'date-day';
      if (disabled) cls += ' disabled';
      if (selected) cls += ' selected';
      if (today)    cls += ' today';
      html += `<div class="${cls}" data-year="${year}" data-month="${month}" data-day="${d}">${d}</div>`;
    }

    html += `</div>
      <div class="date-quick-buttons">
        <button type="button" class="date-quick-btn" data-quick="today">Today</button>
        <button type="button" class="date-quick-btn" data-quick="clear">Clear</button>
      </div>`;

    this.dropdown.innerHTML = html;
  }

  // ── Month view ─────────────────────────────────────────────
  renderMonths() {
    const year = this.currentDate.getFullYear();
    const MONTHS_SHORT = ['Jan','Feb','Mar','Apr','May','Jun',
                          'Jul','Aug','Sep','Oct','Nov','Dec'];

    let html = `
      <div class="date-header">
        <button type="button" class="date-nav-btn" data-nav="prev-year"><i class="fa fa-chevron-left"></i></button>
        <button type="button" class="date-month-year dp-header-btn" data-nav="switch-year">
          ${year} <i class="fa fa-caret-down" style="font-size:.6rem;margin-left:.2rem;opacity:.6;"></i>
        </button>
        <button type="button" class="date-nav-btn" data-nav="next-year"><i class="fa fa-chevron-right"></i></button>
      </div>
      <div class="dp-grid-month">`;

    MONTHS_SHORT.forEach((m, i) => {
      const isCur = i === this.currentDate.getMonth();
      const isSel = this.selectedDate
        && i === this.selectedDate.getMonth()
        && year === this.selectedDate.getFullYear();
      let cls = 'dp-cell';
      if (isCur) cls += ' dp-current';
      if (isSel) cls += ' dp-selected';
      html += `<button type="button" class="${cls}" data-nav="pick-month" data-month="${i}">${m}</button>`;
    });

    html += `</div>`;
    this.dropdown.innerHTML = html;
  }

  // ── Year view ──────────────────────────────────────────────
  renderYears() {
    const start = this.yearRangeStart;
    const end   = start + 11;
    const curY  = this.currentDate.getFullYear();
    const selY  = this.selectedDate ? this.selectedDate.getFullYear() : null;

    let html = `
      <div class="date-header">
        <button type="button" class="date-nav-btn" data-nav="prev-decade"><i class="fa fa-chevron-left"></i></button>
        <span class="date-month-year">${start} – ${end}</span>
        <button type="button" class="date-nav-btn" data-nav="next-decade"><i class="fa fa-chevron-right"></i></button>
      </div>
      <div class="dp-grid-year">`;

    for (let y = start; y <= end; y++) {
      let cls = 'dp-cell';
      if (y === curY) cls += ' dp-current';
      if (y === selY) cls += ' dp-selected';
      html += `<button type="button" class="${cls}" data-nav="pick-year" data-year="${y}">${y}</button>`;
    }

    html += `</div>`;
    this.dropdown.innerHTML = html;
  }

  // ── Open / close ───────────────────────────────────────────
  attachEvents() {
    this.trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      // close other open pickers
      document.querySelectorAll('.date-dropdown.show').forEach(d => {
        if (d !== this.dropdown) d.classList.remove('show');
      });
      document.querySelectorAll('.date-trigger.open').forEach(t => {
        if (t !== this.trigger) t.classList.remove('open');
      });
      const opening = !this.dropdown.classList.contains('show');
      this.dropdown.classList.toggle('show');
      this.trigger.classList.toggle('open');
      if (opening) {
        this.view = 'day';
        this.render();
      }
    });

    document.addEventListener('click', (e) => {
      if (!this.container.contains(e.target)) {
        this.dropdown.classList.remove('show');
        this.trigger.classList.remove('open');
      }
    });
  }

  // ── All in-dropdown interactions ───────────────────────────
  attachDropdownEvents() {
    this.dropdown.querySelectorAll('[data-nav]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const nav = btn.dataset.nav;

        if (nav === 'prev') {
          this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
          this.view = 'day'; this.render();

        } else if (nav === 'next') {
          this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
          this.view = 'day'; this.render();

        } else if (nav === 'switch-month') {
          this.view = 'month'; this.render();

        } else if (nav === 'prev-year') {
          this.currentDate = new Date(this.currentDate.getFullYear() - 1, this.currentDate.getMonth(), 1);
          this.render();

        } else if (nav === 'next-year') {
          this.currentDate = new Date(this.currentDate.getFullYear() + 1, this.currentDate.getMonth(), 1);
          this.render();

        } else if (nav === 'switch-year') {
          this.yearRangeStart = Math.floor(this.currentDate.getFullYear() / 12) * 12;
          this.view = 'year'; this.render();

        } else if (nav === 'pick-month') {
          this.currentDate = new Date(this.currentDate.getFullYear(), parseInt(btn.dataset.month), 1);
          this.view = 'day'; this.render();

        } else if (nav === 'prev-decade') {
          this.yearRangeStart -= 12; this.render();

        } else if (nav === 'next-decade') {
          this.yearRangeStart += 12; this.render();

        } else if (nav === 'pick-year') {
          this.currentDate = new Date(parseInt(btn.dataset.year), this.currentDate.getMonth(), 1);
          this.view = 'month'; this.render();
        }
      });
    });

    // Day cell clicks
    this.dropdown.querySelectorAll('.date-day:not(.empty):not(.disabled)').forEach(cell => {
      cell.addEventListener('click', (e) => {
        e.stopPropagation();
        const date = new Date(
          parseInt(cell.dataset.year),
          parseInt(cell.dataset.month),
          parseInt(cell.dataset.day)
        );
        if (date <= this.maxDate) {
          this.selectedDate = date;
          this.hiddenInput.value = this.formatDate(date);
          this.updateTriggerDisplay();
          this.dropdown.classList.remove('show');
          this.trigger.classList.remove('open');
          this.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });

    // Quick buttons
    const todayBtn = this.dropdown.querySelector('[data-quick="today"]');
    const clearBtn = this.dropdown.querySelector('[data-quick="clear"]');
    if (todayBtn) {
      todayBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const today = new Date(); today.setHours(0,0,0,0);
        this.selectedDate = today;
        this.hiddenInput.value = this.formatDate(today);
        this.updateTriggerDisplay();
        this.dropdown.classList.remove('show');
        this.trigger.classList.remove('open');
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', (e) => {
        e.stopPropagation();
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

  // Inject month/year grid styles once
  const style = document.createElement('style');
  style.textContent = `
    .dp-header-btn {
      background: none; border: none; color: var(--gold-light);
      font-weight: 700; font-size: .85rem; cursor: pointer;
      padding: .2rem .5rem; border-radius: var(--radius);
      transition: background .15s;
    }
    .dp-header-btn:hover { background: rgba(59,130,246,.12); }
    .dp-grid-month, .dp-grid-year {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
      padding: .5rem 0;
    }
    .dp-cell {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius); padding: .55rem .2rem;
      font-size: .8rem; font-weight: 500; color: var(--text-soft);
      cursor: pointer; transition: all .15s; text-align: center;
    }
    .dp-cell:hover    { background: rgba(59,130,246,.15); color: var(--navy-light); border-color: var(--navy-light); }
    .dp-cell.dp-current  { border-color: var(--gold-light); color: var(--gold-light); }
    .dp-cell.dp-selected { background: var(--navy-light); border-color: var(--navy-light); color: #fff; font-weight: 700; }
  `;
  document.head.appendChild(style);
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