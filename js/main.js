// ─────────────────────────────────────────
// THEME (runs immediately)
// ─────────────────────────────────────────
(function applyTheme() {
  const saved = localStorage.getItem('npats_theme');
  if (saved === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
})();

// ─────────────────────────────────────────
// MAIN INIT
// ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

  initThemeToggle();
  initMobileNav();
  initAlerts();
  initModals();
  initConfirmDialogs();
  initPhotoPreview();
  initPasswordToggles();
  initFormValidation();
  initTableSearch();
  initPrint();
  initCustomSelects();

});

// ─────────────────────────────────────────
// THEME TOGGLE
// ─────────────────────────────────────────
function initThemeToggle() {
  const btn = document.getElementById('themeToggle');
  if (!btn) return;

  btn.addEventListener('click', () => {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    if (isLight) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('npats_theme', 'dark');
    } else {
      document.documentElement.setAttribute('data-theme', 'light');
      localStorage.setItem('npats_theme', 'light');
    }
  });
}

// ─────────────────────────────────────────
// MOBILE NAV
// ─────────────────────────────────────────
function initMobileNav() {
  const hamburger = document.getElementById('hamburger');
  const navLinks  = document.getElementById('navLinks');
  if (!hamburger || !navLinks) return;

  hamburger.addEventListener('click', () => {
    navLinks.classList.toggle('open');
  });

  document.addEventListener('click', e => {
    if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
      navLinks.classList.remove('open');
    }
  });
}

// ─────────────────────────────────────────
// ALERTS
// ─────────────────────────────────────────
function initAlerts() {
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 500);
    }, 5000);
  });
}

// ─────────────────────────────────────────
// MODALS
// ─────────────────────────────────────────
function initModals() {
  document.querySelectorAll('[data-modal-open]').forEach(btn =>
    btn.addEventListener('click', () => openModal(btn.dataset.modalOpen))
  );

  document.querySelectorAll('[data-modal-close]').forEach(btn =>
    btn.addEventListener('click', () => closeModal(btn.dataset.modalClose))
  );

  document.querySelectorAll('.modal-overlay').forEach(overlay =>
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.remove('open');
    })
  );
}

// ─────────────────────────────────────────
// CONFIRM DIALOGS
// ─────────────────────────────────────────
function initConfirmDialogs() {
  document.querySelectorAll('[data-confirm]').forEach(el =>
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    })
  );
}

// ─────────────────────────────────────────
// PHOTO PREVIEW
// ─────────────────────────────────────────
function initPhotoPreview() {
  const input = document.getElementById('photo_upload');
  const preview = document.getElementById('photo_preview');
  const placeholder = document.getElementById('photo_placeholder');

  if (!input || !preview) return;

  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
      preview.src = e.target.result;
      preview.style.display = 'block';
      if (placeholder) placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });
}

// ─────────────────────────────────────────
// PASSWORD TOGGLE
// ─────────────────────────────────────────
function initPasswordToggles() {
  document.querySelectorAll('.eye-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.input-wrap')?.querySelector('input');
      const icon  = btn.querySelector('i');
      if (!input) return;

      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      if (icon) icon.className = isHidden ? 'fa fa-eye-slash' : 'fa fa-eye';
    });
  });
}

// ─────────────────────────────────────────
// FORM VALIDATION
// ─────────────────────────────────────────
function initFormValidation() {
  const form = document.getElementById('applicationForm');
  if (!form) return;

  form.addEventListener('submit', e => {
    let valid = true;

    form.querySelectorAll('[required]').forEach(field => {
      const err = document.getElementById(field.id + '_err');
      if (!field.value.trim()) {
        field.classList.add('error');
        if (err) { err.textContent = 'This field is required.'; err.classList.add('show'); }
        valid = false;
      }
    });

    const email = document.getElementById('email');
    if (email?.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      setError(email, 'Enter a valid email address.');
      valid = false;
    }

    const phone = document.getElementById('phone');
    if (phone?.value && !/^[+\d\s\-()]{7,20}$/.test(phone.value)) {
      setError(phone, 'Enter a valid phone number.');
      valid = false;
    }

    const dob = document.getElementById('date_of_birth');
    if (dob?.value && new Date(dob.value) >= new Date()) {
      setError(dob, 'Date of birth must be in the past.');
      valid = false;
    }

    if (!valid) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });

  form.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('input', () => {
      field.classList.remove('error');
      const err = document.getElementById(field.id + '_err');
      if (err) err.classList.remove('show');
    });
  });
}

function setError(field, message) {
  field.classList.add('error');
  const err = document.getElementById(field.id + '_err');
  if (err) {
    err.textContent = message;
    err.classList.add('show');
  }
}

// ─────────────────────────────────────────
// TABLE SEARCH
// ─────────────────────────────────────────
function initTableSearch() {
  const search = document.getElementById('tableSearch');
  if (!search) return;

  search.addEventListener('input', () => {
    const q = search.value.toLowerCase();
    document.querySelectorAll('tbody tr:not(.no-data)').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ─────────────────────────────────────────
// PRINT
// ─────────────────────────────────────────
function initPrint() {
  document.querySelectorAll('[data-print]').forEach(btn =>
    btn.addEventListener('click', () => window.print())
  );
}

// ─────────────────────────────────────────
// CUSTOM SELECT (SINGLE SOURCE OF TRUTH)
// ─────────────────────────────────────────
function initCustomSelects() {
  document.querySelectorAll('.select-custom').forEach(select => {
    const display  = select.querySelector('.select-display');
    const dropdown = select.querySelector('.select-dropdown');
    const input    = select.querySelector('input[type="hidden"]');

    if (!display || !dropdown || !input) return;

    display.addEventListener('click', () => {
      dropdown.classList.toggle('open');
    });

    dropdown.querySelectorAll('[data-value]').forEach(option => {
      option.addEventListener('click', () => {
        display.textContent = option.textContent;
        input.value = option.dataset.value;
        dropdown.classList.remove('open');
      });
    });

    document.addEventListener('click', e => {
      if (!select.contains(e.target)) {
        dropdown.classList.remove('open');
      }
    });
  });
}

// ─────────────────────────────────────────
// MODAL HELPERS (GLOBAL)
// ─────────────────────────────────────────
function openModal(id)  {
  document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
}

// ─────────────────────────────────────────
// STAGE MODAL (UPDATED FOR CUSTOM SELECT)
// ─────────────────────────────────────────
function openStageModal(stageName, currentStatus, comments) {
  document.getElementById('modalStageName').value = stageName;
  document.getElementById('modalStageLabel').textContent = stageName;

  const input = document.getElementById('modalStageStatus');
  const display = document.querySelector('#stageStatusSelect .select-display');

  if (input) input.value = currentStatus;
  if (display) display.textContent = currentStatus;

  document.getElementById('modalComments').value = comments;

  openModal('stageModal');
}