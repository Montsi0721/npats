<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();

// ── Date filter ─────────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Stats in range
$totalRange   = $db->prepare('SELECT COUNT(*) FROM passport_applications WHERE application_date BETWEEN ? AND ?');
$totalRange->execute([$from,$to]);
$totalRange = $totalRange->fetchColumn();

$byStatus = $db->prepare("SELECT status, COUNT(*) as cnt FROM passport_applications WHERE application_date BETWEEN ? AND ? GROUP BY status");
$byStatus->execute([$from,$to]);
$byStatus = $byStatus->fetchAll();

$byType = $db->prepare("SELECT passport_type, COUNT(*) as cnt FROM passport_applications WHERE application_date BETWEEN ? AND ? GROUP BY passport_type");
$byType->execute([$from,$to]);
$byType = $byType->fetchAll();

$byOfficer = $db->prepare("SELECT u.full_name, COUNT(pa.id) as cnt FROM passport_applications pa JOIN users u ON u.id=pa.officer_id WHERE pa.application_date BETWEEN ? AND ? GROUP BY pa.officer_id ORDER BY cnt DESC");
$byOfficer->execute([$from,$to]);
$byOfficer = $byOfficer->fetchAll();

$readyList = $db->query("SELECT pa.application_number, pa.full_name, pa.phone, pa.email, pa.application_date FROM passport_applications pa WHERE pa.current_stage='Ready for Collection' ORDER BY pa.application_date")->fetchAll();

$pageTitle = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<div class="hero animate">
  <div class="hero-mesh"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-icon"><i class="fa fa-chart-bar"></i></div>
      <div>
        <div class="hero-eyebrow">System Administrator</div>
        <div class="hero-name">Reports & Analytics</div>
        <div class="hero-meta">
          <span class="hero-meta-chip"><i class="fa fa-calendar-days"></i> <?= date('l, d F Y') ?></span>
          <span class="hero-meta-chip"><i class="fa fa-clock"></i> <?= date('H:i') ?></span>
          <span class="hero-meta-chip" style="color:rgba(200,145,26,.7);border-color:rgba(200,145,26,.25);background:rgba(200,145,26,.06);">
            <i class="fa fa-chart-line"></i> Performance Overview
          </span>
        </div>
      </div>
    </div>
    <div class="hero-right no-print">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline">
        <i class="fa fa-arrow-left"></i> Dashboard
      </a>
      <button class="btn btn-primary" onclick="window.print()">
        <i class="fa fa-print"></i> Print Report
      </button>
    </div>
  </div>
</div>

<!-- Mini Stats Row -->
<div class="stats-row animate animate-d1">
  <div class="stat-mini hover-card">
    <div class="value"><?= $totalRange ?></div>
    <div class="label">Applications in Period</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #34D399;"><?= count($readyList) ?></div>
    <div class="label">Ready for Collection</div>
  </div>
  <div class="stat-mini hover-card">
    <div class="value" style="color: #60A5FA;"><?= count($byOfficer) ?></div>
    <div class="label">Active Officers</div>
  </div>
</div>

<!-- Date Filter Card with Custom Date Pickers -->
<div class="card animate animate-d2 hover-card no-print" style="padding: 20px;">
  <form method="GET" class="form-card" id="reportForm">
    <div class="group">
      <label><i class="fa fa-calendar-alt"></i> From Date</label>
      <div class="date-picker-custom" id="fromDatePicker">
        <div class="date-trigger" id="fromDateTrigger">
          <span class="date-value" id="fromDateDisplay"><?= date('F d, Y', strtotime($from)) ?></span>
          <i class="fa fa-calendar-alt"></i>
        </div>
        <input type="hidden" name="from" id="fromDateHidden" value="<?= e($from) ?>">
        <div class="date-dropdown" id="fromCalendar"></div>
      </div>
    </div>
    <div class="group">
      <label><i class="fa fa-calendar-alt"></i> To Date</label>
      <div class="date-picker-custom" id="toDatePicker">
        <div class="date-trigger" id="toDateTrigger">
          <span class="date-value" id="toDateDisplay"><?= date('F d, Y', strtotime($to)) ?></span>
          <i class="fa fa-calendar-alt"></i>
        </div>
        <input type="hidden" name="to" id="toDateHidden" value="<?= e($to) ?>">
        <div class="date-dropdown" id="toCalendar"></div>
      </div>
    </div>
    <div class="actions">
      <button type="submit" class="btn btn-primary">
        <i class="fa fa-filter"></i> Apply Filter
      </button>
      <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline">
        <i class="fa fa-times"></i> Reset
      </a>
    </div>
  </form>
  <div class="date-quick-buttons">
    <button type="button" class="date-quick-btn" data-range="today">Today</button>
    <button type="button" class="date-quick-btn" data-range="yesterday">Yesterday</button>
    <button type="button" class="date-quick-btn" data-range="week">This Week</button>
    <button type="button" class="date-quick-btn" data-range="month">This Month</button>
    <button type="button" class="date-quick-btn" data-range="quarter">Last 90 Days</button>
    <button type="button" class="date-quick-btn" data-range="year">This Year</button>
  </div>
</div>

<!-- Report Cards Grid -->
<div class="grid">
  <!-- By Status Card -->
  <div class="card animate animate-d3 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-chart-pie"></i> By Status</span>
    </div>
    <div class="card-body">
      <div class="total-stat">
        <div class="total-number"><?= $totalRange ?></div>
        <div class="total-label">Total Applications</div>
      </div>
      <?php 
      $statusColors = [
        'Pending' => '#F59E0B',
        'In-Progress' => '#60A5FA',
        'Completed' => '#34D399',
        'Rejected' => '#F87171'
      ];
      foreach ($byStatus as $s): 
        $color = $statusColors[$s['status']] ?? '#6B7280';
        $percent = $totalRange > 0 ? round(($s['cnt'] / $totalRange) * 100) : 0;
      ?>
      <div class="stat-list-item">
        <div class="stat-list-label">
          <span class="stat-list-badge" style="background:<?= $color ?>20; color:<?= $color ?>;"><?= e($s['status']) ?></span>
        </div>
        <div class="stat-list-value"><?= $s['cnt'] ?> (<?= $percent ?>%)</div>
      </div>
      <div class="stat-progress">
        <div class="stat-progress-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byStatus)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-chart-simple"></i></div>
        <h4>No data available</h4>
        <p>No applications in selected period</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- By Type Card -->
  <div class="card animate animate-d3 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-passport"></i> By Passport Type</span>
    </div>
    <div class="card-body">
      <?php 
      $typeColors = [
        'Normal' => '#3B82F6',
        'Express' => '#F59E0B'
      ];
      foreach ($byType as $t): 
        $color = $typeColors[$t['passport_type']] ?? '#6B7280';
        $percent = $totalRange > 0 ? round(($t['cnt'] / $totalRange) * 100) : 0;
      ?>
      <div class="stat-list-item">
        <div class="stat-list-label">
          <i class="fa fa-passport" style="color: <?= $color ?>;"></i>
          <span><?= e($t['passport_type']) ?></span>
        </div>
        <div class="stat-list-value"><?= $t['cnt'] ?> (<?= $percent ?>%)</div>
      </div>
      <div class="stat-progress">
        <div class="stat-progress-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byType)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-passport"></i></div>
        <h4>No data available</h4>
        <p>No applications in selected period</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- By Officer Card -->
  <div class="card animate animate-d3 hover-card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-user-tie"></i> By Officer</span>
    </div>
    <div class="card-body">
      <?php 
      $maxCnt = !empty($byOfficer) ? max(array_column($byOfficer, 'cnt')) : 1;
      $colors = ['#60A5FA', '#34D399', '#F59E0B', '#F87171', '#A78BFA'];
      foreach ($byOfficer as $idx => $o): 
        $percent = round(($o['cnt'] / $maxCnt) * 100);
        $color = $colors[$idx % count($colors)];
      ?>
      <div class="stat-list-item">
        <div class="stat-list-label">
          <i class="fa fa-user-circle" style="color: <?= $color ?>;"></i>
          <span><?= e($o['full_name']) ?></span>
        </div>
        <div class="stat-list-value"><?= $o['cnt'] ?></div>
      </div>
      <div class="stat-progress">
        <div class="stat-progress-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byOfficer)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-users"></i></div>
        <h4>No data available</h4>
        <p>No applications processed in selected period</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Ready for Collection Table Card -->
<div class="card animate animate-d4 hover-card">
  <div class="card-header">
    <span class="card-title"><i class="fa fa-box-open"></i> Passports Ready for Collection</span>
    <span class="card-badge" style="background: rgba(52,211,153,.15); color: #34D399; padding: 0.2rem 0.7rem; border-radius: 20px; font-size: 0.7rem;">
      <?= count($readyList) ?> pending
    </span>
  </div>
  <?php if (empty($readyList)): ?>
    <div class="empty-state" style="padding: 2rem;">
      <div class="empty-icon"><i class="fa fa-check-circle" style="color: #34D399;"></i></div>
      <h4>No passports ready for collection</h4>
      <p>All clear! Applications ready for pickup will appear here.</p>
    </div>
  <?php else: ?>
  <div class="ready-table-wrapper">
    <table class="ready-table">
      <thead>
        <tr>
          <th>App Number</th>
          <th>Applicant</th>
          <th>Phone</th>
          <th>Email</th>
          <th>App Date</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($readyList as $r): ?>
      <tr>
        <td><a href="<?= APP_URL ?>/admin/view_application.php?id=<?= $r['id'] ?? '' ?>" class="app-number-link"><?= e($r['application_number']) ?></a></td>
        <td><span class="ready-applicant"><?= e($r['full_name']) ?></span></td>
        <td><?= e($r['phone']) ?></td>
        <td><?= e($r['email']) ?></td>
        <td><span style="font-size: .75rem; color: var(--muted);"><i class="fa fa-calendar"></i> <?= e($r['application_date']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
// Spotlight effect for hover-card elements
(function() {
  const spotlightElements = document.querySelectorAll('.hover-card, .card, .card');
  
  spotlightElements.forEach(el => {
    let spotlight = el.querySelector('.sc-spotlight');
    if (!spotlight) {
      spotlight = document.createElement('div');
      spotlight.className = 'sc-spotlight';
      el.style.position = 'relative';
      el.style.overflow = 'visible';
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

// ============================================================
// PREMIUM CUSTOM CALENDAR DATE PICKER
// ============================================================
class PremiumDatePicker {
  constructor(containerId, hiddenInputId, options = {}) {
    this.container = document.getElementById(containerId);
    this.hiddenInput = document.getElementById(hiddenInputId);
    this.trigger = this.container.querySelector('.date-trigger');
    this.displaySpan = this.trigger.querySelector('.date-value');
    this.dropdown = this.container.querySelector('.date-dropdown');
    
    this.currentDate = new Date();
    this.selectedDate = this.hiddenInput.value ? new Date(this.hiddenInput.value) : null;
    this.maxDate = options.maxDate ? new Date(options.maxDate) : null;
    this.minDate = options.minDate ? new Date(options.minDate) : null;
    this.onSelect = options.onSelect || null;
    
    this.init();
  }
  
  init() {
    this.updateDisplay();
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
    
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const weekdays = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
    
    let calendarHtml = `
      <div class="date-header">
        <button type="button" class="date-nav-btn" data-action="prev"><i class="fa fa-chevron-left"></i></button>
        <div class="date-month-year">${monthNames[month]} ${year}</div>
        <button type="button" class="date-nav-btn" data-action="next"><i class="fa fa-chevron-right"></i></button>
      </div>
      <div class="date-weekdays">
        ${weekdays.map(day => `<div class="date-weekday">${day}</div>`).join('')}
      </div>
      <div class="date-days">
    `;
    
    // Previous month days
    for (let i = 0; i < startDayOfWeek; i++) {
      const prevDate = prevMonthLastDay - startDayOfWeek + i + 1;
      calendarHtml += `<div class="date-day empty" data-date="${year}-${month}-${prevDate}">${prevDate}</div>`;
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
      const dateObj = new Date(year, month, day);
      const dateStr = this.formatDate(dateObj);
      const isSelected = this.selectedDate && this.formatDate(this.selectedDate) === dateStr;
      const isToday = this.formatDate(new Date()) === dateStr;
      const isDisabled = this.isDateDisabled(dateObj);
      
      let classes = 'date-day';
      if (isSelected) classes += ' selected';
      if (isToday) classes += ' today';
      if (isDisabled) classes += ' disabled';
      
      calendarHtml += `<div class="${classes}" data-date="${dateStr}" data-year="${year}" data-month="${month}" data-day="${day}">${day}</div>`;
    }
    
    // Next month days (fill to 42 cells)
    const totalCells = 42;
    const currentCells = startDayOfWeek + daysInMonth;
    const nextMonthDays = totalCells - currentCells;
    
    for (let day = 1; day <= nextMonthDays; day++) {
      calendarHtml += `<div class="date-day empty" data-date="${year}-${month + 1}-${day}">${day}</div>`;
    }
    
    calendarHtml += `
      </div>
      <div class="date-quick-buttons">
        <button type="button" class="date-quick-btn" data-quick="clear">Clear</button>
        <button type="button" class="date-quick-btn" data-quick="today">Today</button>
      </div>
    `;
    
    this.dropdown.innerHTML = calendarHtml;
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
  
  updateDisplay() {
    if (this.selectedDate) {
      this.displaySpan.textContent = this.formatDisplayDate(this.selectedDate);
      this.displaySpan.classList.remove('date-placeholder');
    } else {
      this.displaySpan.textContent = 'Select a date';
      this.displaySpan.classList.add('date-placeholder');
    }
  }
  
  selectDate(date) {
    if (!date) {
      this.selectedDate = null;
      this.hiddenInput.value = '';
      this.updateDisplay();
      this.renderCalendar();
      this.closeDropdown();
      if (this.onSelect) this.onSelect(null);
      return;
    }
    
    if (this.isDateDisabled(date)) return;
    this.selectedDate = date;
    this.hiddenInput.value = this.formatDate(date);
    this.updateDisplay();
    this.renderCalendar();
    this.closeDropdown();
    if (this.onSelect) this.onSelect(date);
  }
  
  openDropdown() {
    // Close other dropdowns
    document.querySelectorAll('.date-dropdown.show').forEach(drop => {
      if (drop !== this.dropdown) drop.classList.remove('show');
    });
    document.querySelectorAll('.date-trigger.open').forEach(trigger => {
      if (trigger !== this.trigger) trigger.classList.remove('open');
    });
    
    this.dropdown.classList.add('show');
    this.trigger.classList.add('open');
    this.renderCalendar();
  }
  
  closeDropdown() {
    this.dropdown.classList.remove('show');
    this.trigger.classList.remove('open');
  }
  
  attachEvents() {
    // Toggle dropdown on trigger click
    this.trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (this.dropdown.classList.contains('show')) {
        this.closeDropdown();
      } else {
        this.openDropdown();
      }
    });
    
    // Close when clicking outside
    document.addEventListener('click', (e) => {
      if (!this.container.contains(e.target)) {
        this.closeDropdown();
      }
    });
    
    // Handle calendar interactions (event delegation)
    this.dropdown.addEventListener('click', (e) => {
      const dayDiv = e.target.closest('.date-day');
      const navBtn = e.target.closest('.date-nav-btn');
      const quickBtn = e.target.closest('.date-quick-btn');
      
      if (dayDiv && !dayDiv.classList.contains('empty') && !dayDiv.classList.contains('disabled')) {
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
      
      if (quickBtn) {
        const action = quickBtn.dataset.quick;
        if (action === 'clear') {
          this.selectDate(null);
        } else if (action === 'today') {
          const today = new Date();
          if (!this.isDateDisabled(today)) {
            this.selectDate(today);
          }
        }
      }
    });
  }
}

// Initialize date pickers
const fromPicker = new PremiumDatePicker('fromDatePicker', 'fromDateHidden', {
  onSelect: (date) => {
    // Optional callback when from date changes
  }
});

const toPicker = new PremiumDatePicker('toDatePicker', 'toDateHidden', {
  onSelect: (date) => {
    // Optional callback when to date changes
  }
});

// Quick date range buttons
document.querySelectorAll('.quick-date-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const range = btn.dataset.range;
    const today = new Date();
    let fromDate = new Date();
    let toDate = new Date();
    
    switch(range) {
      case 'today':
        fromDate = new Date(today);
        toDate = new Date(today);
        break;
      case 'yesterday':
        fromDate = new Date(today);
        fromDate.setDate(today.getDate() - 1);
        toDate = new Date(fromDate);
        break;
      case 'week':
        fromDate = new Date(today);
        const dayOfWeek = today.getDay();
        fromDate.setDate(today.getDate() - dayOfWeek);
        toDate = new Date(today);
        break;
      case 'month':
        fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
        toDate = new Date(today);
        break;
      case 'quarter':
        fromDate = new Date(today);
        fromDate.setDate(today.getDate() - 90);
        toDate = new Date(today);
        break;
      case 'year':
        fromDate = new Date(today.getFullYear(), 0, 1);
        toDate = new Date(today);
        break;
    }
    
    fromPicker.selectDate(fromDate);
    toPicker.selectDate(toDate);
    
    // Submit the form
    document.getElementById('reportForm').submit();
  });
});

// Animate progress bars on load
document.addEventListener('DOMContentLoaded', function() {
  const progressBars = document.querySelectorAll('.stat-progress-fill');
  progressBars.forEach(bar => {
    const width = bar.style.width;
    bar.style.width = '0';
    setTimeout(() => {
      bar.style.width = width;
    }, 100);
  });
});
</script>

<style>
.card-badge {
  font-size: .67rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .12rem .52rem;
  color: var(--muted);
}
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
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>