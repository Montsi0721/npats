<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

// ── Quick-create user from dashboard ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_create'])) {
    $name  = trim($_POST['full_name'] ?? '');
    $uname = trim($_POST['username']  ?? '');
    $email = trim($_POST['email']     ?? '');
    $phone = trim($_POST['phone']     ?? '');
    $role  = $_POST['role']            ?? '';
    $pass  = $_POST['password']        ?? '';

    if (!in_array($role, ['admin','officer'])) {
        flash('error', 'Invalid role.');
    } elseif (!$name || !$uname || !$email || !$pass) {
        flash('error', 'All fields are required.');
    } elseif (strlen($pass) < 8) {
        flash('error', 'Password must be at least 8 characters.');
    } else {
        try {
            $hashed = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare('INSERT INTO users (full_name,username,email,password,role,phone) VALUES (?,?,?,?,?,?)')
               ->execute([$name, $uname, $email, $hashed, $role, $phone]);
            logActivity($_SESSION['user_id'], 'ADD_USER', 'Created '.ucfirst($role).": $uname");
            flash('success', ucfirst($role)." account '$uname' created successfully.");
        } catch (PDOException) {
            flash('error', 'Username or email already exists.');
        }
    }
    redirect(APP_URL . '/admin/dashboard.php');
}

// ── Stats ────────────────────────────────────────────────────
$totalApps     = (int)$db->query('SELECT COUNT(*) FROM passport_applications')->fetchColumn();
$pending       = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='Pending'")->fetchColumn();
$inProgress    = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='In-Progress'")->fetchColumn();
$completed     = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='Completed'")->fetchColumn();
$rejected      = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE status='Rejected'")->fetchColumn();
$readyColl     = (int)$db->query("SELECT COUNT(*) FROM passport_applications WHERE current_stage='Ready for Collection'")->fetchColumn();
$totalOfficers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='officer' AND is_active=1")->fetchColumn();
$totalAdmins   = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'  AND is_active=1")->fetchColumn();

$recent    = $db->query('SELECT pa.*, u.full_name AS officer_name FROM passport_applications pa
    JOIN users u ON u.id=pa.officer_id ORDER BY pa.created_at DESC LIMIT 7')->fetchAll();
$typeStats = $db->query('SELECT passport_type, COUNT(*) cnt FROM passport_applications GROUP BY passport_type')->fetchAll();
$stageStats= $db->query('SELECT current_stage, COUNT(*) cnt FROM passport_applications GROUP BY current_stage ORDER BY cnt DESC')->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../css/partials/dashboard.css">

<div class="admin-hero admin-animate">
  <div class="admin-hero-mesh"></div>
  <div class="admin-hero-grid"></div>
  <div class="admin-hero-inner">
    <div class="admin-hero-left">
      <div class="admin-hero-icon"><i class="fa fa-gauge"></i></div>
      <div>
        <div class="admin-hero-eyebrow">System Administrator</div>
        <div class="admin-hero-name">Admin Dashboard</div>
        <div class="admin-hero-meta">
          <span class="admin-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="admin-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="admin-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-chart-line"></i> System Overview
          </span>
        </div>
      </div>
    </div>
    <div class="admin-hero-right">
      <div class="btn-group">
        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline btn-sm">
          <i class="fa fa-chart-bar"></i> Reports
        </a>
        <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline btn-sm">
          <i class="fa fa-list"></i> Applications
        </a>
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline btn-sm">
          <i class="fa fa-users"></i> Users
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Quick Action Cards -->
<div class="quick-actions-grid admin-animate admin-animate-d1">
  <button class="quick-action-card" data-modal-open="modalAddOfficer">
    <div class="qa-icon" style="background:rgba(59,130,246,.12);color:#60A5FA;">
      <i class="fa fa-id-badge"></i>
    </div>
    <div class="qa-body">
      <div class="qa-title">Add Passport Officer</div>
      <div class="qa-sub">Create an officer account with application management access</div>
    </div>
    <i class="fa fa-plus qa-arrow"></i>
  </button>

  <button class="quick-action-card" data-modal-open="modalAddAdmin">
    <div class="qa-icon" style="background:rgba(245,158,11,.12);color:#F59E0B;">
      <i class="fa fa-user-shield"></i>
    </div>
    <div class="qa-body">
      <div class="qa-title">Add Administrator</div>
      <div class="qa-sub">Create an admin account with full system privileges</div>
    </div>
    <i class="fa fa-plus qa-arrow"></i>
  </button>
</div>

<!-- Stats Grid -->
<div class="stats-grid admin-animate admin-animate-d2">
  <div class="stat-card blue hover-card">
    <div class="stat-icon"><i class="fa fa-file-lines"></i></div>
    <div><div class="stat-num"><?= $totalApps ?></div><div class="stat-label">Total Applications</div></div>
  </div>
  <div class="stat-card gold hover-card">
    <div class="stat-icon"><i class="fa fa-clock"></i></div>
    <div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
  </div>
  <div class="stat-card teal hover-card">
    <div class="stat-icon"><i class="fa fa-rotate"></i></div>
    <div><div class="stat-num"><?= $inProgress ?></div><div class="stat-label">In Progress</div></div>
  </div>
  <div class="stat-card green hover-card">
    <div class="stat-icon"><i class="fa fa-circle-check"></i></div>
    <div><div class="stat-num"><?= $completed ?></div><div class="stat-label">Completed</div></div>
  </div>
  <div class="stat-card red hover-card">
    <div class="stat-icon"><i class="fa fa-circle-xmark"></i></div>
    <div><div class="stat-num"><?= $rejected ?></div><div class="stat-label">Rejected</div></div>
  </div>
  <div class="stat-card gold hover-card">
    <div class="stat-icon"><i class="fa fa-box-open"></i></div>
    <div><div class="stat-num"><?= $readyColl ?></div><div class="stat-label">Ready for Collection</div></div>
  </div>
  <div class="stat-card blue hover-card">
    <div class="stat-icon"><i class="fa fa-id-badge"></i></div>
    <div><div class="stat-num"><?= $totalOfficers ?></div><div class="stat-label">Active Officers</div></div>
  </div>
  <div class="stat-card gold hover-card">
    <div class="stat-icon"><i class="fa fa-user-shield"></i></div>
    <div><div class="stat-num"><?= $totalAdmins ?></div><div class="stat-label">Administrators</div></div>
  </div>
</div>

<!-- Main Content Grid -->
<div class="dash-main">
  <!-- Recent Applications Card -->
  <div class="admin-card admin-animate admin-animate-d3 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-clock-rotate-left"></i> Recent Applications</span>
      <a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-ghost btn-sm">
        View all <i class="fa fa-arrow-right"></i>
      </a>
    </div>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr><th>App Number</th><th>Applicant</th><th>Type</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr class="no-data"><td colspan="5"><i class="fa fa-inbox"></i> No applications yet.</td></tr>
        <?php else: foreach ($recent as $r): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $r['id'] ?>"
                   style="font-weight:500;color:var(--navy-light);"><?= e($r['application_number']) ?></a></td>
            <td><strong><?= e($r['full_name']) ?></strong></td>
            <td><span class="app-type-badge"><?= e($r['passport_type']) ?></span></td>
            <td><?= statusBadge($r['status']) ?></td>
            <td style="font-size:.78rem;color:var(--muted);"><?= e($r['application_date']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Column Stats -->
  <div>
    <!-- By Type Card -->
    <div class="admin-card admin-animate admin-animate-d4 hover-card" style="margin-bottom:1rem;">
      <div class="card-header"><span class="card-title"><i class="fa fa-chart-pie"></i> By Passport Type</span></div>
      <?php if (empty($typeStats)): ?>
        <div class="stat-list-item"><span class="stat-list-label">No data yet</span></div>
      <?php else: foreach ($typeStats as $ts): ?>
        <div class="stat-list-item">
          <span class="stat-list-label"><?= e($ts['passport_type']) ?></span>
          <span class="stat-list-value"><?= $ts['cnt'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- By Stage Card -->
    <div class="admin-card admin-animate admin-animate-d5 hover-card">
      <div class="card-header"><span class="card-title"><i class="fa fa-layer-group"></i> By Processing Stage</span></div>
      <?php if (empty($stageStats)): ?>
        <div class="stat-list-item"><span class="stat-list-label">No data yet</span></div>
      <?php else: foreach ($stageStats as $st): ?>
        <div class="stat-list-item">
          <span class="stat-list-label"><?= e($st['current_stage']) ?></span>
          <span class="stat-list-value"><?= $st['cnt'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- MODAL: Add Officer -->
<div class="modal-overlay" id="modalAddOfficer">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-id-badge"></i> Add Passport Officer</span>
      <button class="modal-close" data-modal-close="modalAddOfficer">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="quick_create" value="1">
      <input type="hidden" name="role" value="officer">
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="fa fa-info-circle"></i>
          Officers can capture applications, update processing stages, and release passports.
        </div>
        <div class="form-grid">
          <div class="form-group full">
            <label><i class="fa fa-user"></i> Full Name *</label>
            <div class="input-wrap">
              <input type="text" name="full_name" required placeholder="e.g. Jane Mokoena">
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa fa-at"></i> Username *</label>
            <div class="input-wrap">
              <input type="text" name="username" required placeholder="Unique username">
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa fa-envelope"></i> Email *</label>
            <div class="input-wrap">
              <input type="email" name="email" required placeholder="officer@npats.gov.ls">
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa fa-phone"></i> Phone</label>
            <div class="input-wrap">
              <input type="tel" name="phone" placeholder="+266 …">
            </div>
          </div>
          <div class="form-group full">
            <label><i class="fa fa-lock"></i> Password * <span class="text-muted">(min. 8 chars)</span></label>
            <div class="input-wrap">
              <input type="password" name="password" required placeholder="Strong password" style="padding-right: 2.8rem;">
              <button type="button" class="eye-btn"><i class="fa fa-eye"></i></button>
            </div>
            <div class="strength-bar">
              <div class="strength-fill"></div>
            </div>
            <div class="strength-text"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modalAddOfficer">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-user-plus"></i> Create Officer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Add Administrator -->
<div class="modal-overlay" id="modalAddAdmin">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-user-shield"></i> Add Administrator</span>
      <button class="modal-close" data-modal-close="modalAddAdmin">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="quick_create" value="1">
      <input type="hidden" name="role" value="admin">
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="fa fa-triangle-exclamation"></i>
          Admins have <strong>full system access</strong> — user management, all applications, reports and audit logs.
        </div>
        <div class="form-grid">
          <div class="form-group full">
            <label><i class="fa fa-user"></i> Full Name *</label>
            <div class="input-wrap">
              <input type="text" name="full_name" required placeholder="e.g. Lesedi Thamae">
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa fa-at"></i> Username *</label>
            <div class="input-wrap">
              <input type="text" name="username" required placeholder="Unique username">
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa fa-envelope"></i> Email *</label>
            <div class="input-wrap">
              <input type="email" name="email" required placeholder="admin@npats.gov.ls">
            </div>
          </div>
          <div class="form-group">
            <label><i class="fa fa-phone"></i> Phone</label>
            <div class="input-wrap">
              <input type="tel" name="phone" placeholder="+266 …">
            </div>
          </div>
          <div class="form-group full">
            <label><i class="fa fa-lock"></i> Password * <span class="text-muted">(min. 8 chars)</span></label>
            <div class="input-wrap">
              <input type="password" name="password" required placeholder="Strong password" style="padding-right: 2.8rem;">
              <button type="button" class="eye-btn"><i class="fa fa-eye"></i></button>
            </div>
            <div class="strength-bar">
              <div class="strength-fill"></div>
            </div>
            <div class="strength-text"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close="modalAddAdmin">Cancel</button>
        <button type="submit" class="btn btn-gold" onclick="return confirm('Grant full administrator privileges to this user?')">
          <i class="fa fa-user-shield"></i> Create Administrator
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    const R = 16;
    const DURATION = 500;

    function ease(t) { return t < 0.5 ? 2*t*t : -1+(4-2*t)*t; }

    function buildPts(w, h, r) {
      const pts = [];
      const steps = 12;
      function corner(cx, cy, a0, a1) {
        for (let i = 0; i <= steps; i++) {
          const a = a0 + (a1 - a0) * i / steps;
          pts.push([cx + r * Math.cos(a), cy + r * Math.sin(a)]);
        }
      }
      pts.push([r, 0]); pts.push([w-r, 0]);
      corner(w-r, r,   -Math.PI/2, 0);
      pts.push([w, r]); pts.push([w, h-r]);
      corner(w-r, h-r, 0, Math.PI/2);
      pts.push([w-r, h]); pts.push([r, h]);
      corner(r, h-r,   Math.PI/2, Math.PI);
      pts.push([0, h-r]); pts.push([0, r]);
      corner(r, r,     Math.PI, 3*Math.PI/2);
      pts.push([r, 0]);
      return pts;
    }

    function buildLens(pts) {
      const l = [0];
      for (let i = 1; i < pts.length; i++) {
        const dx = pts[i][0]-pts[i-1][0], dy = pts[i][1]-pts[i-1][1];
        l.push(l[i-1] + Math.sqrt(dx*dx+dy*dy));
      }
      return l;
    }

    function closestT(pts, lens, mx, my) {
      let best = Infinity, bestT = 0;
      for (let i = 1; i < pts.length; i++) {
        const [x1,y1]=pts[i-1],[x2,y2]=pts[i];
        const dx=x2-x1, dy=y2-y1, l2=dx*dx+dy*dy;
        let t = l2<1e-9 ? 0 : Math.max(0,Math.min(1,((mx-x1)*dx+(my-y1)*dy)/l2));
        const px=x1+t*dx, py=y1+t*dy;
        const d=(mx-px)**2+(my-py)**2;
        if (d<best){best=d; bestT=lens[i-1]+t*(lens[i]-lens[i-1]);}
      }
      return bestT;
    }

    function strokeArc(ctx, pts, lens, total, from, to) {
      let f = ((from%total)+total)%total;
      let t2 = f+(to-from);
      ctx.beginPath();
      let started = false;
      for (let pass = 0; pass <= 1; pass++) {
        const off = pass*total;
        for (let i = 1; i < pts.length; i++) {
          const s0=lens[i-1]+off, s1=lens[i]+off, sl=s1-s0;
          if (sl<1e-9||s1<f||s0>t2) continue;
          const ta=Math.max(0,(f-s0)/sl), tb=Math.min(1,(t2-s0)/sl);
          const [x1,y1]=pts[i-1],[x2,y2]=pts[i];
          const ax=x1+ta*(x2-x1), ay=y1+ta*(y2-y1);
          const bx=x1+tb*(x2-x1), by=y1+tb*(y2-y1);
          if (!started){ctx.moveTo(ax,ay);started=true;}
          else ctx.lineTo(ax,ay);
          ctx.lineTo(bx,by);
        }
      }
      ctx.stroke();
    }

    function initCard(card) {
      const dpr = window.devicePixelRatio || 1;
      const cvs = document.createElement('canvas');
      cvs.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;';
      card.appendChild(cvs);

      // read accent color from CSS variable
      const accent = getComputedStyle(card).getPropertyValue('--card-accent').trim() || '#888';

      let pts, lens, total;
      let drawFrom=0, drawTo=0, state='idle', animId=null, animTs=null;

      function setup() {
        const w=card.offsetWidth, h=card.offsetHeight;
        cvs.width=w*dpr; cvs.height=h*dpr;
        pts=buildPts(w,h,R); lens=buildLens(pts);
        total=lens[lens.length-1];
      }

      function render(accent) {
        const ctx=cvs.getContext('2d');
        ctx.clearRect(0,0,cvs.width,cvs.height);
        if (drawTo-drawFrom < 0.5) return;
        ctx.save(); ctx.scale(dpr,dpr);
        ctx.strokeStyle=accent; ctx.lineWidth=1.5;
        ctx.lineCap='round'; ctx.lineJoin='round';
        strokeArc(ctx,pts,lens,total,drawFrom,drawTo);
        ctx.restore();
      }

      function cancel() { if(animId){cancelAnimationFrame(animId);animId=null;animTs=null;} }

      function animate(fs,ts,ft,tt,done) {
        cancel(); animTs=null;
        const accent = getComputedStyle(card).getPropertyValue('--card-accent').trim() || '#888';
        animId=requestAnimationFrame(function tick(now){
          if(!animTs) animTs=now;
          const e=ease(Math.min((now-animTs)/DURATION,1));
          drawFrom=fs+(ft-fs)*e; drawTo=ts+(tt-ts)*e;
          render(accent);
          if((now-animTs)<DURATION) animId=requestAnimationFrame(tick);
          else { drawFrom=ft; drawTo=tt; animId=null; done&&done(); }
        });
      }

      card.addEventListener('mouseenter', e => {
        setup();
        const accent = getComputedStyle(card).getPropertyValue('--card-accent').trim() || '#888';
        const br=card.getBoundingClientRect();
        const entryT=closestT(pts,lens,e.clientX-br.left,e.clientY-br.top);
        cancel();
        if (state==='idle') {
          drawFrom=entryT; drawTo=entryT;
          animate(entryT,entryT,entryT,entryT+total,()=>{ state='full'; });
          state='drawing';
        } else if (state==='erasing') {
          animate(drawFrom,drawTo,drawFrom,drawFrom+total,()=>{ state='full'; });
          state='drawing';
        }
        card.addEventListener('mousemove', onMove);
      });

      function onMove(e) {
        const br=card.getBoundingClientRect();
        card.style.setProperty('--x', (e.clientX-br.left)+'px');
        card.style.setProperty('--y', (e.clientY-br.top)+'px');
      }

      card.addEventListener('mouseleave', e => {
        setup();
        const br=card.getBoundingClientRect();
        let exitT=closestT(pts,lens,e.clientX-br.left,e.clientY-br.top);
        let absExit=exitT;
        while(absExit<drawFrom) absExit+=total;
        while(absExit>drawFrom+total) absExit-=total;
        absExit=Math.max(drawFrom,Math.min(drawTo,absExit));
        cancel();
        state='erasing';
        animate(drawFrom,drawTo,absExit,absExit,()=>{ state='idle'; });
        card.removeEventListener('mousemove', onMove);
      });
    }

    document.querySelectorAll('.stat-card').forEach(initCard);
  })();
  
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .quick-action-card, .stat-card');
  
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

// Modal functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Open modals from buttons
document.querySelectorAll('[data-modal-open]').forEach(btn => {
  btn.addEventListener('click', () => {
    const modalId = btn.getAttribute('data-modal-open');
    openModal(modalId);
  });
});

// Close modals with close buttons
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

// Toggle password visibility
document.querySelectorAll('.eye-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const wrap  = btn.closest('.input-wrap');
    const input = document.querySelector('input[type="password"], input[type="text"]');
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  });
});

// Password strength meter
document.querySelectorAll('input[name="password"]').forEach(input => {
  const formGroup = input.closest('.form-group');
  if (!formGroup) return;
  const fill = formGroup.querySelector('.strength-fill');
  const text = formGroup.querySelector('.strength-text');
  if (!fill || !text) return;

  input.addEventListener('input', () => {
    const val = input.value;
    let score = 0;
    if (val.length >= 8)               score++;
    if (val.length >= 12)              score++;
    if (/[A-Z]/.test(val))             score++;
    if (/[0-9]/.test(val))             score++;
    if (/[^A-Za-z0-9]/.test(val))      score++;

    const levels = [
      { pct: '0%',   color: '',          label: '' },
      { pct: '25%',  color: '#F87171',   label: 'Weak' },
      { pct: '50%',  color: '#FBBF24',   label: 'Fair' },
      { pct: '75%',  color: '#60A5FA',   label: 'Good' },
      { pct: '90%',  color: '#34D399',   label: 'Strong' },
      { pct: '100%', color: '#34D399',   label: 'Very Strong' },
    ];

    const lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 5)];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    text.textContent      = lvl.label;
    text.style.color      = lvl.color;
  });
});
</script>

<style>
.app-type-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .7rem;
  background: rgba(200,145,26,.12);
  border: 1px solid rgba(200,145,26,.2);
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 500;
  color: var(--gold-light);
}
.btn-group {
  display: flex;
  gap: 0.5rem;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>