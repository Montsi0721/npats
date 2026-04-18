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

    // Validation
    foreach (['full_name','national_id','date_of_birth','gender','address','phone','email'] as $f) {
        if (empty($data[$f])) $errors[$f] = 'This field is required.';
    }
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email address.';
    if (!empty($data['date_of_birth']) && strtotime($data['date_of_birth']) >= time()) $errors['date_of_birth'] = 'Date of birth must be in the past.';
    if (!empty($data['phone']) && !preg_match('/^[+\d\s\-()]{7,20}$/', $data['phone'])) $errors['phone'] = 'Invalid phone number.';

    // Photo upload
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
        if (!confirm_dialog()) {} // JS handles confirm
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

        // Create all stage records as Pending
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
<div class="page-header">
  <h1 class="page-title"><i class="fa fa-file-plus"></i> New Passport Application</h1>
  <a href="<?= APP_URL ?>/officer/dashboard.php" class="btn btn-outline"><i class="fa fa-arrow-left"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error"><i class="fa fa-exclamation-circle"></i> Please correct the highlighted errors below.</div>
<?php endif; ?>

<div class="card">
  <form method="POST" enctype="multipart/form-data" id="applicationForm">
    <div class="form-grid">
      <!-- Full Name -->
      <div class="form-group">
        <label for="full_name">Full Name *</label>
        <input type="text" id="full_name" name="full_name" required value="<?= e($data['full_name'] ?? '') ?>" class="<?= isset($errors['full_name'])?'error':'' ?>">
        <span class="field-error <?= isset($errors['full_name'])?'show':'' ?>" id="full_name_err"><?= e($errors['full_name'] ?? '') ?></span>
      </div>
      <!-- National ID -->
      <div class="form-group">
        <label for="national_id">National ID Number *</label>
        <input type="text" id="national_id" name="national_id" required value="<?= e($data['national_id'] ?? '') ?>" class="<?= isset($errors['national_id'])?'error':'' ?>">
        <span class="field-error <?= isset($errors['national_id'])?'show':'' ?>" id="national_id_err"><?= e($errors['national_id'] ?? '') ?></span>
      </div>
      <!-- DOB -->
      <div class="form-group">
        <label for="date_of_birth">Date of Birth *</label>
        <input type="date" id="date_of_birth" name="date_of_birth" required value="<?= e($data['date_of_birth'] ?? '') ?>" class="<?= isset($errors['date_of_birth'])?'error':'' ?>">
        <span class="field-error <?= isset($errors['date_of_birth'])?'show':'' ?>" id="date_of_birth_err"><?= e($errors['date_of_birth'] ?? '') ?></span>
      </div>
      <!-- Gender -->
      <div class="form-group">
        <label for="gender">Gender *</label>
        <select id="gender" name="gender" required class="<?= isset($errors['gender'])?'error':'' ?>">
          <option value="">-- Select --</option>
          <?php $g = $data['gender'] ?? ''; ?>
          <option value="Male" <?= $g === 'Male' ? 'selected' : '' ?>>Male</option>
          <option value="Female" <?= $g === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
        <span class="field-error <?= isset($errors['gender'])?'show':'' ?>" id="gender_err"><?= e($errors['gender'] ?? '') ?></span>
      </div>
      <!-- Phone -->
      <div class="form-group">
        <label for="phone">Phone Number *</label>
        <input type="text" id="phone" name="phone" required value="<?= e($data['phone'] ?? '') ?>" class="<?= isset($errors['phone'])?'error':'' ?>" placeholder="+266…">
        <span class="field-error <?= isset($errors['phone'])?'show':'' ?>" id="phone_err"><?= e($errors['phone'] ?? '') ?></span>
      </div>
      <!-- Email -->
      <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" required value="<?= e($data['email'] ?? '') ?>" class="<?= isset($errors['email'])?'error':'' ?>">
        <span class="field-error <?= isset($errors['email'])?'show':'' ?>" id="email_err"><?= e($errors['email'] ?? '') ?></span>
      </div>
      <!-- Passport Type -->
      <div class="form-group">
        <label for="passport_type">Passport Type *</label>
        <select id="passport_type" name="passport_type">
          <option value="Normal" <?= ($data['passport_type']??'Normal')==='Normal'?'selected':'' ?>>Normal</option>
          <option value="Express" <?= ($data['passport_type']??'')==='Express'?'selected':'' ?>>Express</option>
        </select>
      </div>
      <!-- Photo -->
      <div class="form-group">
        <label for="photo_upload">Applicant Photo (JPG/PNG, max 2MB)</label>
        <input type="file" id="photo_upload" name="photo_upload" accept="image/jpeg,image/png" class="<?= isset($errors['photo_upload'])?'error':'' ?>">
        <span class="field-error <?= isset($errors['photo_upload'])?'show':'' ?>"><?= e($errors['photo_upload'] ?? '') ?></span>
        <div class="photo-placeholder" id="photo_placeholder"><i class="fa fa-user"></i></div>
        <img id="photo_preview" class="photo-preview" style="display:none;" src="" alt="Preview">
      </div>
      <!-- Address -->
      <div class="form-group full">
        <label for="address">Address *</label>
        <textarea id="address" name="address" required class="<?= isset($errors['address'])?'error':'' ?>"><?= e($data['address'] ?? '') ?></textarea>
        <span class="field-error <?= isset($errors['address'])?'show':'' ?>" id="address_err"><?= e($errors['address'] ?? '') ?></span>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary" onclick="return confirm('Submit this passport application?')">
        <i class="fa fa-paper-plane"></i> Submit Application
      </button>
      <a href="<?= APP_URL ?>/officer/dashboard.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<script>
// show/hide photo placeholder
const photoInput = document.getElementById('photo_upload');
const placeholder = document.getElementById('photo_placeholder');
if(photoInput){
  photoInput.addEventListener('change', function(){
    if(this.files[0]) placeholder.style.display='none';
    else { placeholder.style.display='flex'; }
  });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php
function confirm_dialog(): bool { return true; }
?>
