<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$db   = getDB();
$uid  = $_SESSION['user_id'];

// Mark all as read
$db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);

$notifs = $db->prepare('SELECT n.*, pa.application_number FROM notifications n
    LEFT JOIN passport_applications pa ON pa.id = n.application_id
    WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT 100');
$notifs->execute([$uid]);
$rows = $notifs->fetchAll();

$pageTitle = 'Notifications';
include __DIR__ . '/includes/header.php';
?>

<style>
/* ─────────────────────────────────────────────────────────────
   NOTIFICATIONS PAGE — Premium Edition (Dashboard Matching)
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
@keyframes notificationSlide {
  from { opacity: 0; transform: translateX(-20px); }
  to { opacity: 1; transform: translateX(0); }
}
@keyframes ring {
  0% { transform: rotate(0deg); }
  25% { transform: rotate(10deg); }
  75% { transform: rotate(-10deg); }
  100% { transform: rotate(0deg); }
}

.notif-animate { animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both }
.notif-animate-d1 { animation-delay:.06s }
.notif-animate-d2 { animation-delay:.12s }
.notif-animate-d3 { animation-delay:.18s }

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

/* ── Hero Section (matches dashboard) ────────────────────── */
.notif-hero {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 1.5rem;
  background: #060D1A;
  border: 1px solid rgba(59,130,246,.18);
  animation: fadeIn .6s ease both;
}
html[data-theme="light"] .notif-hero { background: #0B2545; border-color: rgba(59,130,246,.3); }

.notif-hero-mesh {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 0% 50%, rgba(29,78,216,.22) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at 100% 20%, rgba(200,145,26,.12) 0%, transparent 55%),
    radial-gradient(ellipse 50% 70% at 60% 100%, rgba(13,148,136,.10) 0%, transparent 55%);
}

.notif-hero-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.4) 30%, rgba(0,0,0,.4) 70%, transparent);
}

.notif-hero::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px; z-index: 3;
  background: linear-gradient(90deg, transparent 0%, var(--gold-light) 30%, #fff9 50%, var(--gold-light) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}

.notif-hero-inner {
  position: relative; z-index: 2;
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 1.2rem; padding: 1.75rem 2rem;
}

.notif-hero-left { display: flex; align-items: center; gap: 1.25rem; }

.notif-hero-icon {
  position: relative; width: 60px; height: 60px;
  border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(59,130,246,.25), rgba(59,130,246,.08));
  border: 1px solid rgba(59,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #93C5FD;
  box-shadow: 0 0 30px rgba(59,130,246,.2), inset 0 1px 0 rgba(255,255,255,.08);
  animation: float 4s ease-in-out infinite;
}
.notif-hero-icon::after {
  content: '';
  position: absolute; inset: -1px; border-radius: 17px;
  background: linear-gradient(135deg, rgba(59,130,246,.4), transparent 60%);
  opacity: .5; pointer-events: none;
}
.notif-hero-icon i {
  animation: ring 2s ease-in-out infinite;
  transform-origin: center;
}

.notif-hero-eyebrow {
  font-size: .67rem; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: rgba(255,255,255,.35);
  margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem;
}
.notif-hero-eyebrow::before {
  content: ''; width: 18px; height: 1.5px;
  background: var(--gold-light); border-radius: 2px; display: block;
}
.notif-hero-name {
  font-size: 1.45rem; font-weight: 800; color: #fff;
  letter-spacing: -.03em; line-height: 1.15;
}
.notif-hero-meta {
  display: flex; align-items: center; gap: 1rem;
  margin-top: .45rem; flex-wrap: wrap;
}
.notif-hero-meta-chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .73rem; color: rgba(255,255,255,.4);
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: .2rem .65rem;
}
.notif-hero-meta-chip i { font-size: .62rem; }

.notif-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* ── Stats Row (mini stats) ───────────────────────────────── */
.notif-stats-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.notif-stat-mini {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0.75rem 1.25rem;
  flex: 1;
  min-width: 100px;
  transition: all 0.2s;
  cursor: pointer;
}
.notif-stat-mini:hover {
  transform: translateY(-2px);
  border-color: rgba(59,130,246,0.3);
  background: rgba(59,130,246,.02);
}
.notif-stat-mini .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--gold-light);
  line-height: 1;
}
.notif-stat-mini .label {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
}

/* ── Notifications Card (matches dashboard) ───────────────── */
.notif-card {
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
}
.notif-card::before {
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
.notif-card:hover::before { opacity: 1; }
.notif-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.3);
}

.notif-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--surface), var(--bg-alt));
}
.notif-card-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.notif-card-title i {
  color: var(--gold);
  font-size: .78rem;
}
.notif-card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}
.notif-card-badge.unread {
  background: rgba(59,130,246,.15);
  border-color: rgba(59,130,246,.3);
  color: #60A5FA;
}

/* Notification List */
.notif-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.2rem 1.5rem;
  border-bottom: 1px solid var(--border);
  transition: all 0.2s;
  animation: notificationSlide 0.4s cubic-bezier(.22,1,.36,1) both;
  position: relative;
}
.notif-item:hover {
  background: rgba(59,130,246,.04);
}
.notif-item:last-child {
  border-bottom: none;
}
.notif-icon {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}
.notif-icon.info {
  background: rgba(59,130,246,.12);
  color: #60A5FA;
}
.notif-icon.success {
  background: rgba(52,211,153,.12);
  color: #34D399;
}
.notif-icon.warning {
  background: rgba(245,158,11,.12);
  color: #F59E0B;
}
.notif-icon.danger {
  background: rgba(239,68,68,.12);
  color: #F87171;
}
.notif-content {
  flex: 1;
}
.notif-message {
  font-size: .88rem;
  color: var(--text);
  line-height: 1.45;
  margin-bottom: 0.3rem;
}
.notif-message strong {
  color: var(--gold-light);
  font-weight: 600;
}
.notif-app-link {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: .72rem;
  color: var(--navy-light);
  background: rgba(59,130,246,.08);
  padding: 0.2rem 0.7rem;
  border-radius: 20px;
  text-decoration: none;
  transition: all 0.2s;
  margin-top: 0.3rem;
}
.notif-app-link:hover {
  background: var(--navy-light);
  color: #fff;
  text-decoration: none;
  transform: translateX(2px);
}
.notif-time {
  font-size: .68rem;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 0.3rem;
  margin-top: 0.4rem;
}
.notif-time i {
  font-size: .6rem;
}
.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #60A5FA;
  position: absolute;
  left: 1rem;
  top: 1.4rem;
  animation: pulse-ring 1.5s infinite;
}
@keyframes pulse-ring {
  0% { box-shadow: 0 0 0 0 rgba(96,165,250,.4); }
  70% { box-shadow: 0 0 0 6px rgba(96,165,250,0); }
  100% { box-shadow: 0 0 0 0 rgba(96,165,250,0); }
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
}
.empty-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 1.2rem;
  background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(200,145,26,.05));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: var(--muted);
}
.empty-state h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 0.3rem;
}
.empty-state p {
  color: var(--muted);
  font-size: .8rem;
  margin-bottom: 0;
}

/* Mark All Read Button */
.mark-read-btn {
  padding: 0.4rem 1rem;
  font-size: .75rem;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  color: var(--text-soft);
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}
.mark-read-btn:hover {
  background: var(--navy-light);
  border-color: var(--navy-light);
  color: #fff;
  transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 768px) {
  .notif-hero-inner { flex-direction: column; text-align: center; }
  .notif-stats-row { flex-wrap: wrap; }
  .notif-item { padding: 1rem; }
  .notif-icon { width: 36px; height: 36px; font-size: .85rem; }
}
</style>

<!-- Hero Section (matching dashboard) -->
<div class="notif-hero notif-animate">
  <div class="notif-hero-mesh"></div>
  <div class="notif-hero-grid"></div>
  <div class="notif-hero-inner">
    <div class="notif-hero-left">
      <div class="notif-hero-icon"><i class="fa fa-bell"></i></div>
      <div>
        <div class="notif-hero-eyebrow">Stay Updated</div>
        <div class="notif-hero-name">Notifications</div>
        <div class="notif-hero-meta">
          <span class="notif-hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="notif-hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="notif-hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-bell"></i> All updates
          </span>
        </div>
      </div>
    </div>
    <div class="notif-hero-right">
      <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="notif-stats-row notif-animate notif-animate-d1">
  <div class="notif-stat-mini hover-card">
    <div class="value"><?= count($rows) ?></div>
    <div class="label">Total Notifications</div>
  </div>
  <div class="notif-stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= count(array_filter($rows, fn($n) => !$n['is_read'])) ?></div>
    <div class="label">Unread</div>
  </div>
</div>

<!-- Notifications Card -->
<div class="notif-card notif-animate notif-animate-d2 hover-card" id="notifCard">
  <div class="notif-card-header">
    <div class="notif-card-title">
      <i class="fa fa-bell"></i> Notification Center
      <span class="notif-card-badge"><?= count($rows) ?> messages</span>
    </div>
    <?php if (!empty($rows)): ?>
    <button class="mark-read-btn" onclick="markAllRead()">
      <i class="fa fa-check-double"></i> Mark all as read
    </button>
    <?php endif; ?>
  </div>
  
  <?php if (empty($rows)): ?>
    <div class="empty-state">
      <div class="empty-icon">
        <i class="fa fa-bell-slash"></i>
      </div>
      <h3>No notifications yet</h3>
      <p>When you receive updates about your applications, they'll appear here.</p>
    </div>
  <?php else: ?>
    <ul class="notif-list">
    <?php foreach ($rows as $idx => $n): ?>
      <li class="notif-item" style="animation-delay: <?= $idx * 0.03 ?>s">
        <?php if (!$n['is_read']): ?>
        <div class="notif-dot"></div>
        <?php endif; ?>
        
        <?php
          // Determine icon based on message content
          $iconClass = 'info';
          $icon = 'fa-info-circle';
          if (strpos($n['message'], 'approved') !== false || strpos($n['message'], 'Approved') !== false) {
            $iconClass = 'success';
            $icon = 'fa-check-circle';
          } elseif (strpos($n['message'], 'rejected') !== false || strpos($n['message'], 'Rejected') !== false) {
            $iconClass = 'danger';
            $icon = 'fa-exclamation-circle';
          } elseif (strpos($n['message'], 'released') !== false || strpos($n['message'], 'Released') !== false) {
            $iconClass = 'success';
            $icon = 'fa-passport';
          } elseif (strpos($n['message'], 'ready') !== false || strpos($n['message'], 'Ready') !== false) {
            $iconClass = 'warning';
            $icon = 'fa-clock';
          }
        ?>
        <div class="notif-icon <?= $iconClass ?>">
          <i class="fa <?= $icon ?>"></i>
        </div>
        <div class="notif-content">
          <div class="notif-message"><?= e($n['message']) ?></div>
          <?php if ($n['application_number']): ?>
          <a href="<?= APP_URL ?>/public_track.php?app_num=<?= urlencode($n['application_number']) ?>" class="notif-app-link" target="_blank">
            <i class="fa fa-passport"></i> Track Application #<?= e($n['application_number']) ?>
            <i class="fa fa-external-link-alt"></i>
          </a>
          <?php endif; ?>
          <div class="notif-time">
            <i class="fa fa-clock"></i> <?= date('M d, Y · h:i A', strtotime($n['created_at'])) ?>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
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

// Mark all notifications as read via AJAX
function markAllRead() {
  fetch('<?= APP_URL ?>/ajax/mark_notifications_read.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove all unread dots
      document.querySelectorAll('.notif-dot').forEach(dot => dot.remove());
      // Update badge
      const badge = document.querySelector('.notif-card-badge');
      if (badge) {
        const total = <?= count($rows) ?>;
        badge.textContent = total + ' messages';
      }
      // Show toast or feedback
      const btn = document.querySelector('.mark-read-btn');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fa fa-check"></i> All marked!';
      btn.style.opacity = '0.7';
      setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
      }, 2000);
    }
  })
  .catch(error => {
    console.error('Error:', error);
  });
}

// Auto-refresh notifications every 30 seconds (optional)
let autoRefreshInterval = null;

function startAutoRefresh() {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);
  autoRefreshInterval = setInterval(() => {
    fetch('<?= APP_URL ?>/ajax/get_notifications_count.php')
      .then(response => response.json())
      .then(data => {
        if (data.unread > 0) {
          // Optional: update badge or show indicator
          const badge = document.querySelector('.notif-card-badge');
          if (badge && !badge.textContent.includes('new')) {
            badge.textContent = badge.textContent + ' · ' + data.unread + ' new';
          }
        }
      })
      .catch(err => console.log('Auto-refresh error:', err));
  }, 30000);
}

// Uncomment to enable auto-refresh
// startAutoRefresh();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>