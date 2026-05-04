<?php
    require_once __DIR__ . '/../includes/config.php';
    requireRole('officer');
    $db = getDB();
    $uid = $_SESSION['user_id'];
    $errors = [];
    $data = [];

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

        // Check if email already exists in users table
        $checkEmail = $db->prepare('SELECT id FROM users WHERE email = ?');
        $checkEmail->execute([$data['email']]);
        $existingUser = $checkEmail->fetch();
        
        $applicant_user_id = null;
        
        if ($existingUser) {
            $applicant_user_id = $existingUser['id'];
            // Check if this user already has an application with same national_id
            $checkApp = $db->prepare('SELECT id FROM passport_applications WHERE national_id = ? AND applicant_user_id = ?');
            $checkApp->execute([$data['national_id'], $applicant_user_id]);
            if ($checkApp->fetch()) {
                $errors['national_id'] = 'An application already exists for this National ID.';
            }
        } else {
            // Create new applicant account in users table (only login credentials)
            $username = strtolower(preg_replace('/[^a-z0-9]/', '_', $data['full_name'])) . '_' . rand(1000, 9999);
            $tempPassword = bin2hex(random_bytes(4)); // 8 character temporary password
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Check if username already exists
            $checkUser = $db->prepare('SELECT id FROM users WHERE username = ?');
            $checkUser->execute([$username]);
            while ($checkUser->fetch()) {
                $username = strtolower(preg_replace('/[^a-z0-9]/', '_', $data['full_name'])) . '_' . rand(1000, 9999);
                $checkUser->execute([$username]);
            }
            
            // Insert only into users table (no national_id, DOB, gender, address here)
            $createUser = $db->prepare('INSERT INTO users (full_name, username, email, password, role, phone, is_active, created_at) 
                                        VALUES (?, ?, ?, ?, "applicant", ?, 1, NOW())');
            $createUser->execute([
                $data['full_name'],
                $username,
                $data['email'],
                $hashedPassword,
                $data['phone']
            ]);
            $applicant_user_id = $db->lastInsertId();
            
            // Store temp credentials to display
            $_SESSION['temp_credentials'] = [
                'username' => $username,
                'password' => $tempPassword,
                'full_name' => $data['full_name']
            ];
        }

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
            
            // Insert into passport_applications (all applicant details go here)
            $stmt = $db->prepare('INSERT INTO passport_applications
                (application_number, applicant_user_id, officer_id, full_name, national_id, date_of_birth, gender, address, phone, email, passport_type, application_date, photo_path, current_stage, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $appNum,                    // 1. application_number
                $applicant_user_id,         // 2. applicant_user_id (can be NULL if user doesn't want account)
                $uid,                       // 3. officer_id
                $data['full_name'],         // 4. full_name
                $data['national_id'],       // 5. national_id
                $data['date_of_birth'],     // 6. date_of_birth
                $data['gender'],            // 7. gender
                $data['address'],           // 8. address
                $data['phone'],             // 9. phone
                $data['email'],             // 10. email
                $data['passport_type'],     // 11. passport_type
                $data['application_date'],  // 12. application_date
                $photoPath,                 // 13. photo_path
                'Application Submitted',    // 14. current_stage
                'Pending'                   // 15. status
            ]);
            $appId = $db->lastInsertId();

            // Insert processing stages
            $allStages = ['Application Submitted','Document Verification','Biometric Capture','Background Check','Passport Printing','Ready for Collection','Passport Released'];
            $ins = $db->prepare('INSERT INTO processing_stages (application_id, stage_name, status, officer_id) VALUES (?, ?, ?, ?)');
            foreach ($allStages as $s) {
                $stStatus = ($s === 'Application Submitted') ? 'Completed' : 'Pending';
                $ins->execute([$appId, $s, $stStatus, $uid]);
            }

            // Create notification for applicant if they have an account
            if ($applicant_user_id) {
                $notifMsg = "Your passport application (#{$appNum}) has been submitted successfully.";
                $notifStmt = $db->prepare('INSERT INTO notifications (user_id, application_id, message) VALUES (?, ?, ?)');
                $notifStmt->execute([$applicant_user_id, $appId, $notifMsg]);
            }

            logActivity($uid, 'NEW_APPLICATION', "Created application $appNum for {$data['full_name']}" . ($applicant_user_id ? " (User ID: $applicant_user_id)" : " (No account)"));
            flash('success', "Application $appNum created successfully!");
            redirect(APP_URL . '/officer/manage_application.php?id=' . $appId);
        }
    }

    $pageTitle = 'New Application';
    include __DIR__ . '/../includes/header.php';
    
    // Display temporary credentials if they exist (from new account creation)
    if (isset($_SESSION['temp_credentials'])) {
        $temp = $_SESSION['temp_credentials'];
        echo '<div class="alert alert-info animate animate-d1" style="background: linear-gradient(135deg, rgba(59,130,246,0.12), rgba(139,92,246,0.08)); border-left: 4px solid #3B82F6;">
            <i class="fa fa-info-circle" style="color: #3B82F6;"></i>
            <strong>Applicant Account Created!</strong><br>
            <strong>Name:</strong> ' . htmlspecialchars($temp['full_name']) . '<br>
            <strong>Username:</strong> <code style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px;">' . htmlspecialchars($temp['username']) . '</code><br>
            <strong>Temporary Password:</strong> <code style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px;">' . htmlspecialchars($temp['password']) . '</code><br>
            <small>Please provide these credentials to the applicant. They can log in and change their password.</small>
        </div>';
        unset($_SESSION['temp_credentials']);
    }
?>

<!-- Hero Section (rest remains the same as your original) -->
<div class="hero animate">
    <div class="hero-mesh"></div>
    <div class="hero-grid"></div>
    <div class="hero-inner">
        <div class="hero-left">
            <div class="hero-icon"><i class="fa fa-file-circle-plus"></i></div>
            <div>
                <div class="hero-eyebrow">Passport Application</div>
                <div class="hero-name">New Application</div>
                <div class="hero-meta">
                    <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
                    <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
                    <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
                        <i class="fa fa-passport"></i> Capture applicant details
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

<?php if (!empty($errors)): ?>
<div class="alert animate animate-d1" style="background: linear-gradient(135deg, rgba(220,38,38,0.08), rgba(220,38,38,0.04)); border-left: 4px solid #dc2626;">
    <i class="fa fa-exclamation-circle" style="color: #dc2626;"></i> 
    <strong>Please correct the following errors:</strong>
    <ul style="margin: 8px 0 0 20px; color: #dc2626;">
        <?php foreach ($errors as $field => $error): ?>
            <li><strong><?= ucfirst(str_replace('_', ' ', $field)) ?>:</strong> <?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- main form card -->
<div class="card animate animate-d1" id="formCard">
    <div class="card-header">
        <div class="card-title">
            <i class="fa fa-user-plus"></i> Applicant Information
        </div>
        <div class="card-badge">required *</div>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="applicationForm">
            <div class="form-grid">
                <!-- Full Name -->
                <div class="form-group">
                    <label><i class="fa fa-user"></i> Full Name *</label>
                    <div class="input-wrap">
                        <input type="text" name="full_name" value="<?= e($data['full_name'] ?? '') ?>" class="<?= isset($errors['full_name'])?'error':'' ?>">
                    </div>
                    <span class="field-error <?= isset($errors['full_name'])?'show':'' ?>"><?= e($errors['full_name'] ?? '') ?></span>
                </div>
                
                <!-- National ID -->
                <div class="form-group">
                    <label><i class="fa fa-id-card"></i> National ID Number *</label>
                    <div class="input-wrap">
                        <input type="text" name="national_id" value="<?= e($data['national_id'] ?? '') ?>" class="<?= isset($errors['national_id'])?'error':'' ?>">
                    </div>
                    <span class="field-error <?= isset($errors['national_id'])?'show':'' ?>"><?= e($errors['national_id'] ?? '') ?></span>
                </div>
                
                <!-- Date of Birth -->
                <div class="form-group">
                    <label><i class="fa fa-birthday-cake"></i> Date of Birth *</label>
                    <div class="date-picker-custom" id="dobPicker">
                        <div class="date-trigger" id="dobTrigger">
                            <span class="date-value" id="date_of_birth_display"><?= !empty($data['date_of_birth']) ? date('F d, Y', strtotime($data['date_of_birth'])) : 'Select date of birth' ?></span>
                            <i class="fa fa-calendar-alt"></i>
                        </div>
                        <input type="hidden" name="date_of_birth" id="date_of_birth" value="<?= e($data['date_of_birth'] ?? '') ?>">
                        <div class="date-dropdown" id="calendarDropdown"></div>
                    </div>
                    <span class="field-error <?= isset($errors['date_of_birth'])?'show':'' ?>"><?= e($errors['date_of_birth'] ?? '') ?></span>
                </div>
                
                <!-- Gender -->
                <div class="form-group">
                    <label><i class="fa fa-venus-mars"></i> Gender *</label>
                    <div class="custom-select" id="genderSelect">
                        <div class="custom-select-trigger">
                            <?= e($data['gender'] ?? 'Select Gender') ?>
                        </div>
                        <div class="custom-select-dropdown">
                            <div class="custom-select-option" data-value="Male">Male</div>
                            <div class="custom-select-option" data-value="Female">Female</div>
                            <div class="custom-select-option" data-value="Other">Other</div>
                        </div>
                        <input type="hidden" name="gender" id="genderInput" value="<?= e($data['gender'] ?? '') ?>">
                    </div>
                    <span class="field-error <?= isset($errors['gender'])?'show':'' ?>"><?= e($errors['gender'] ?? '') ?></span>
                </div>
                
                <!-- Phone -->
                <div class="form-group">
                    <label><i class="fa fa-phone"></i> Phone Number *</label>
                    <div class="input-wrap">
                        <input type="tel" name="phone" value="<?= e($data['phone'] ?? '') ?>" placeholder="+266..." class="<?= isset($errors['phone'])?'error':'' ?>">
                    </div>
                    <span class="field-error <?= isset($errors['phone'])?'show':'' ?>"><?= e($errors['phone'] ?? '') ?></span>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label><i class="fa fa-envelope"></i> Email Address *</label>
                    <div class="input-wrap">
                        <input type="email" name="email" value="<?= e($data['email'] ?? '') ?>" class="<?= isset($errors['email'])?'error':'' ?>">
                    </div>
                    <span class="field-error <?= isset($errors['email'])?'show':'' ?>"><?= e($errors['email'] ?? '') ?></span>
                </div>
                
                <!-- Passport Type -->
                <div class="form-group">
                    <label><i class="fa fa-passport"></i> Passport Type *</label>
                    <div class="custom-select" id="passportTypeSelect">
                        <div class="custom-select-trigger">
                            <?= e($data['passport_type'] ?? 'Normal') ?>
                        </div>
                        <div class="custom-select-dropdown">
                            <div class="custom-select-option" data-value="Normal">Normal (Standard Processing - 2-3 weeks)</div>
                            <div class="custom-select-option" data-value="Express">Express (Priority Processing - 3-5 days)</div>
                        </div>
                        <input type="hidden" name="passport_type" id="passportTypeInput" value="<?= e($data['passport_type'] ?? 'Normal') ?>">
                    </div>
                </div>
                
                <!-- Address - full width -->
                <div class="form-group full">
                    <label><i class="fa fa-map-marker-alt"></i> Residential Address *</label>
                    <div class="input-wrap">
                        <textarea name="address" rows="3" class="<?= isset($errors['address'])?'error':'' ?>"><?= e($data['address'] ?? '') ?></textarea>
                    </div>
                    <span class="field-error <?= isset($errors['address'])?'show':'' ?>"><?= e($errors['address'] ?? '') ?></span>
                </div>
                
                <!-- Photo Upload -->
                <div class="form-group full">
                    <label><i class="fa fa-camera"></i> Applicant Photo <span style="font-weight: normal; font-size: 0.75rem;">(optional but recommended)</span></label>
                    <div class="photo-upload">
                        <div class="photo-box" id="photoDropzone">
                            <div class="photo-placeholder">
                                <i class="fa fa-cloud-upload-alt"></i>
                                <span>Click or drag photo here</span>
                                <span class="small">JPG, PNG up to 2MB</span>
                            </div>
                        </div>
                        <input type="file" id="photo_upload" name="photo_upload" accept="image/jpeg,image/png" style="display: none;">
                        <div class="photo-preview" id="photoPreview" style="display: none;">
                            <img id="previewImg" src="" alt="Preview">
                            <button type="button" class="remove-photo" id="removePhotoBtn" onclick="removePhoto()"><i class="fa fa-times"></i></button>
                        </div>
                        <span class="field-error <?= isset($errors['photo_upload'])?'show':'' ?>"><?= e($errors['photo_upload'] ?? '') ?></span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-outline" onclick="window.history.back()">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-primary" id="submitBtn">
                    <i class="fa fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Spotlight effect for card (from original dashboard)
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

// Double confirmation on submit
document.getElementById('submitBtn')?.addEventListener('click', function(e) {
    if (!confirm('Please verify all applicant information is correct.\n\nSubmitting this application will create an account for the applicant.\n\nContinue with submission?')) {
        e.preventDefault();
        return false;
    }
    document.getElementById('applicationForm').submit();
});

// ============================================================
// PREMIUM CUSTOM CALENDAR DATE PICKER
// Three-view: Day → click header → Month → click header → Year
// ============================================================
class PremiumDatePicker {
    constructor(displayElementId, hiddenInputId, containerId, options = {}) {
        this.displayElement = document.getElementById(displayElementId);
        this.hiddenInput    = document.getElementById(hiddenInputId);
        this.container      = document.getElementById(containerId);
        this.currentDate    = new Date();
        this.view           = 'day'; // 'day' | 'month' | 'year'
        this.yearRangeStart = Math.floor(this.currentDate.getFullYear() / 12) * 12;

        if (this.hiddenInput && this.hiddenInput.value) {
            this.selectedDate = new Date(this.hiddenInput.value + 'T00:00:00');
            this.currentDate  = new Date(this.selectedDate);
        } else {
            this.selectedDate = null;
        }

        this.maxDate = options.maxDate ? new Date(options.maxDate) : null;
        this.minDate = options.minDate ? new Date(options.minDate) : null;

        this.init();
    }

    init() {
        this.render();
        this.attachEvents();
        if (this.selectedDate) {
            this.updateDisplayValue(this.formatDisplayDate(this.selectedDate));
        }
    }

    updateDisplayValue(value) {
        if (!this.displayElement) return;
        if (this.displayElement.tagName === 'INPUT') {
            this.displayElement.value = value;
        } else {
            this.displayElement.textContent = value || 'Select date of birth';
        }
    }

    // ── Render dispatcher ──────────────────────────────────
    render() {
        if (!this.container) return;
        if (this.view === 'day')   this.renderDays();
        if (this.view === 'month') this.renderMonths();
        if (this.view === 'year')  this.renderYears();
    }

    // ── Day view ───────────────────────────────────────────
    renderDays() {
        const year  = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const firstDay    = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const prevLast    = new Date(year, month, 0).getDate();
        const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const DAYS   = ['SUN','MON','TUE','WED','THU','FRI','SAT'];

        let html = `
            <div class="date-header">
                <button type="button" class="date-nav-btn" data-action="prev"><i class="fa fa-chevron-left"></i></button>
                <button type="button" class="date-month-year dp-header-btn" data-action="switch-month" title="Pick month &amp; year">
                    ${MONTHS[month]} ${year} <i class="fa fa-caret-down" style="font-size:.6rem;margin-left:.2rem;opacity:.6;"></i>
                </button>
                <button type="button" class="date-nav-btn" data-action="next"><i class="fa fa-chevron-right"></i></button>
            </div>
            <div class="date-weekdays">${DAYS.map(d => `<div>${d}</div>`).join('')}</div>
            <div class="date-days">`;

        for (let i = 0; i < firstDay; i++) {
            const d = prevLast - firstDay + i + 1;
            html += `<div class="date-day other-month">${d}</div>`;
        }
        for (let d = 1; d <= daysInMonth; d++) {
            const dateObj = new Date(year, month, d);
            const dateStr = this.formatDate(dateObj);
            const sel  = this.selectedDate && this.formatDate(this.selectedDate) === dateStr;
            const tod  = this.formatDate(new Date()) === dateStr;
            const dis  = this.isDateDisabled(dateObj);
            let cls = 'date-day';
            if (sel) cls += ' selected';
            if (tod) cls += ' today';
            if (dis) cls += ' disabled';
            html += `<div class="${cls}" data-date="${dateStr}">${d}</div>`;
        }
        const filled = firstDay + daysInMonth;
        for (let d = 1; d <= (42 - filled); d++) {
            html += `<div class="date-day other-month">${d}</div>`;
        }

        html += `</div>
            <div class="date-quick-buttons">
                <button type="button" class="date-quick-btn" data-action="clear"><i class="fa fa-times-circle"></i> Clear</button>
                <button type="button" class="date-quick-btn" data-action="today"><i class="fa fa-calendar-day"></i> Today</button>
            </div>`;

        this.container.innerHTML = html;
    }

    // ── Month view ─────────────────────────────────────────
    renderMonths() {
        const year = this.currentDate.getFullYear();
        const MONTHS_SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        let html = `
            <div class="date-header">
                <button type="button" class="date-nav-btn" data-action="prev-year"><i class="fa fa-chevron-left"></i></button>
                <button type="button" class="date-month-year dp-header-btn" data-action="switch-year" title="Pick year">
                    ${year} <i class="fa fa-caret-down" style="font-size:.6rem;margin-left:.2rem;opacity:.6;"></i>
                </button>
                <button type="button" class="date-nav-btn" data-action="next-year"><i class="fa fa-chevron-right"></i></button>
            </div>
            <div class="dp-grid-month">`;

        MONTHS_SHORT.forEach((m, i) => {
            const isCur = i === this.currentDate.getMonth();
            const isSel = this.selectedDate && i === this.selectedDate.getMonth() && year === this.selectedDate.getFullYear();
            let cls = 'dp-cell';
            if (isCur) cls += ' dp-current';
            if (isSel) cls += ' dp-selected';
            html += `<button type="button" class="${cls}" data-action="pick-month" data-month="${i}">${m}</button>`;
        });

        html += `</div>`;
        this.container.innerHTML = html;
    }

    // ── Year view ──────────────────────────────────────────
    renderYears() {
        const start = this.yearRangeStart;
        const end   = start + 11;
        const curY  = this.currentDate.getFullYear();
        const selY  = this.selectedDate ? this.selectedDate.getFullYear() : null;

        let html = `
            <div class="date-header">
                <button type="button" class="date-nav-btn" data-action="prev-decade"><i class="fa fa-chevron-left"></i></button>
                <span class="date-month-year">${start} – ${end}</span>
                <button type="button" class="date-nav-btn" data-action="next-decade"><i class="fa fa-chevron-right"></i></button>
            </div>
            <div class="dp-grid-year">`;

        for (let y = start; y <= end; y++) {
            let cls = 'dp-cell';
            if (y === curY) cls += ' dp-current';
            if (y === selY) cls += ' dp-selected';
            html += `<button type="button" class="${cls}" data-action="pick-year" data-year="${y}">${y}</button>`;
        }

        html += `</div>`;
        this.container.innerHTML = html;
    }

    // ── Helpers ────────────────────────────────────────────
    formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    formatDisplayDate(date) {
        if (!date) return '';
        return date.toLocaleDateString(undefined, { year:'numeric', month:'long', day:'numeric' });
    }

    isDateDisabled(date) {
        if (this.maxDate) {
            const max = new Date(this.maxDate); max.setHours(23,59,59,999);
            if (date > max) return true;
        }
        if (this.minDate) {
            const min = new Date(this.minDate); min.setHours(0,0,0,0);
            if (date < min) return true;
        }
        return false;
    }

    selectDate(date) {
        if (this.isDateDisabled(date)) return;
        this.selectedDate = date;
        if (this.hiddenInput) this.hiddenInput.value = this.formatDate(date);
        this.updateDisplayValue(this.formatDisplayDate(date));
        this.view = 'day';
        this.render();
        this.container.classList.remove('show');
    }

    // ── Events ─────────────────────────────────────────────
    attachEvents() {
        // Open/close on trigger click
        const triggerDiv = this.displayElement
            ? (this.displayElement.closest('.date-trigger') || this.displayElement)
            : null;

        if (triggerDiv) {
            triggerDiv.style.cursor = 'pointer';
            triggerDiv.addEventListener('click', (e) => {
                e.stopPropagation();
                if (this.container.classList.contains('show')) {
                    this.container.classList.remove('show');
                } else {
                    this.view = 'day';
                    this.render();
                    this.container.classList.add('show');
                }
            });
        }

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target) && (!triggerDiv || !triggerDiv.contains(e.target))) {
                this.container.classList.remove('show');
            }
        });

        // All interactions via delegation
        this.container.addEventListener('click', (e) => {
            e.stopPropagation();
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;

            // ── Day view actions ──
            if (action === 'prev') {
                this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
                this.render();
            } else if (action === 'next') {
                this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
                this.render();
            } else if (action === 'switch-month') {
                this.view = 'month';
                this.render();
            } else if (action === 'clear') {
                this.selectedDate = null;
                if (this.hiddenInput) this.hiddenInput.value = '';
                this.updateDisplayValue('');
                this.view = 'day';
                this.render();
                this.container.classList.remove('show');
            } else if (action === 'today') {
                const today = new Date();
                if (!this.isDateDisabled(today)) {
                    this.currentDate = new Date(today);
                    this.selectDate(today);
                }

            // ── Month view actions ──
            } else if (action === 'prev-year') {
                this.currentDate = new Date(this.currentDate.getFullYear() - 1, this.currentDate.getMonth(), 1);
                this.render();
            } else if (action === 'next-year') {
                this.currentDate = new Date(this.currentDate.getFullYear() + 1, this.currentDate.getMonth(), 1);
                this.render();
            } else if (action === 'switch-year') {
                this.yearRangeStart = Math.floor(this.currentDate.getFullYear() / 12) * 12;
                this.view = 'year';
                this.render();
            } else if (action === 'pick-month') {
                this.currentDate = new Date(this.currentDate.getFullYear(), parseInt(btn.dataset.month), 1);
                this.view = 'day';
                this.render();

            // ── Year view actions ──
            } else if (action === 'prev-decade') {
                this.yearRangeStart -= 12;
                this.render();
            } else if (action === 'next-decade') {
                this.yearRangeStart += 12;
                this.render();
            } else if (action === 'pick-year') {
                this.currentDate = new Date(parseInt(btn.dataset.year), this.currentDate.getMonth(), 1);
                this.view = 'month';
                this.render();

            // ── Day cell click ──
            } else {
                const dayDiv = e.target.closest('.date-day');
                if (dayDiv && !dayDiv.classList.contains('disabled') && !dayDiv.classList.contains('other-month') && dayDiv.dataset.date) {
                    const [y, m, d] = dayDiv.dataset.date.split('-').map(Number);
                    this.selectDate(new Date(y, m - 1, d));
                }
            }
        });

        // Day cell clicks don't carry data-action, handle separately
        this.container.addEventListener('click', (e) => {
            const dayDiv = e.target.closest('.date-day');
            if (dayDiv && !dayDiv.classList.contains('disabled') && !dayDiv.classList.contains('other-month') && dayDiv.dataset.date) {
                const [y, m, d] = dayDiv.dataset.date.split('-').map(Number);
                this.selectDate(new Date(y, m - 1, d));
            }
        });
    }
}

// Add grid styles for month/year views inline so no extra CSS file is needed
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .dp-header-btn {
            background: none; border: none; color: var(--gold-light);
            font-weight: 700; font-size: .85rem; cursor: pointer;
            padding: .2rem .5rem; border-radius: var(--radius);
            transition: background .15s;
        }
        .dp-header-btn:hover { background: rgba(59,130,246,.12); }
        .dp-grid-month {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
            padding: .5rem 0;
        }
        .dp-grid-year {
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
        
        .remove-photo {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #dc2626;
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.2s;
        }
        .remove-photo:hover {
            background: #b91c1c;
            transform: scale(1.1);
        }
        .photo-preview {
            position: relative;
            display: inline-block;
        }
    `;
    document.head.appendChild(style);
})();

// Initialize — max date = today (no future DOB)
new PremiumDatePicker('date_of_birth_display', 'date_of_birth', 'calendarDropdown', {
    maxDate: new Date()
});

// ============================================================
// CUSTOM SELECT (forms.css .custom-select)
// ============================================================
function initCustomSelect(wrapperId, inputId) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    const trigger = wrapper.querySelector('.custom-select-trigger');
    const dropdown = wrapper.querySelector('.custom-select-dropdown');
    const hiddenInput = document.getElementById(inputId);
    const options = wrapper.querySelectorAll('.custom-select-option');
    
    if (trigger) {
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            trigger.classList.toggle('open');
        });
    }
    
    options.forEach(opt => {
        opt.addEventListener('click', () => {
            const val = opt.dataset.value;
            const text = opt.innerText;
            trigger.innerHTML = text;
            if (hiddenInput) hiddenInput.value = val;
            dropdown.classList.remove('show');
            trigger.classList.remove('open');
        });
    });
    
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            dropdown.classList.remove('show');
            if (trigger) trigger.classList.remove('open');
        }
    });
}

initCustomSelect('genderSelect', 'genderInput');
initCustomSelect('passportTypeSelect', 'passportTypeInput');

// ============================================================
// PHOTO UPLOAD: drag & drop + preview (in harmony with photo-box)
// ============================================================
const photoInput = document.getElementById('photo_upload');
const dropzone = document.getElementById('photoDropzone');
const previewDiv = document.getElementById('photoPreview');
const previewImg = document.getElementById('previewImg');

function removePhoto() {
    photoInput.value = '';
    previewDiv.style.display = 'none';
    dropzone.style.display = 'flex';
    previewImg.src = '';
}

if (dropzone) {
    dropzone.addEventListener('click', () => photoInput.click());
    
    photoInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            // Validate file size and type
            const file = this.files[0];
            if (!file.type.match('image/jpeg') && !file.type.match('image/png')) {
                alert('Only JPG and PNG images are allowed.');
                this.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be under 2MB.');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(ev) {
                previewImg.src = ev.target.result;
                previewDiv.style.display = 'flex';
                dropzone.style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Drag & drop
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
}

// Real-time validation feedback
const inputs = document.querySelectorAll('input, select, textarea');
inputs.forEach(input => {
    input.addEventListener('blur', function() {
        if (this.value.trim() === '' && this.hasAttribute('required')) {
            this.classList.add('error');
        } else {
            this.classList.remove('error');
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>