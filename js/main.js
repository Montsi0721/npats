// ============================================================
// NPATS — main.js
// ============================================================

// ── Theme toggle ────────────────────────────────────────────
// Dark is default. User preference is saved in localStorage.
// Apply theme BEFORE paint to avoid flash (also done inline in header).
(function applyTheme() {
  const saved = localStorage.getItem('npats_theme');
  if (saved === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
})();

document.addEventListener('DOMContentLoaded', () => {

  // ── Theme button ─────────────────────────────────────────
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
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

  // ── Mobile nav ───────────────────────────────────────────
  const hamburger = document.getElementById('hamburger');
  const navLinks  = document.getElementById('navLinks');
  if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => navLinks.classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
        navLinks.classList.remove('open');
      }
    });
  }

  // ── Auto-dismiss alerts ──────────────────────────────────
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s, transform .5s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 500);
    }, 5000);
  });

  // ── Modal helpers ────────────────────────────────────────
  document.querySelectorAll('[data-modal-open]').forEach(btn =>
    btn.addEventListener('click', () => openModal(btn.dataset.modalOpen)));

  document.querySelectorAll('[data-modal-close]').forEach(btn =>
    btn.addEventListener('click', () => closeModal(btn.dataset.modalClose)));

  document.querySelectorAll('.modal-overlay').forEach(overlay =>
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.remove('open');
    }));

  // ── Confirm dialogs ──────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el =>
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    }));

  // ── Photo preview ─────────────────────────────────────────
  const photoInput   = document.getElementById('photo_upload');
  const photoPreview = document.getElementById('photo_preview');
  const photoPH      = document.getElementById('photo_placeholder');
  if (photoInput && photoPreview) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = e => {
          photoPreview.src = e.target.result;
          photoPreview.style.display = 'block';
          if (photoPH) photoPH.style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // ── Password eye toggles ──────────────────────────────────
  document.querySelectorAll('.eye-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.input-wrap')?.querySelector('input');
      const icon  = btn.querySelector('i');
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'fa fa-eye-slash';
      } else {
        input.type = 'password';
        if (icon) icon.className = 'fa fa-eye';
      }
    });
  });

  // ── Application form validation ───────────────────────────
  const appForm = document.getElementById('applicationForm');
  if (appForm) {
    appForm.addEventListener('submit', e => {
      let valid = true;

      appForm.querySelectorAll('[required]').forEach(field => {
        const err = document.getElementById(field.id + '_err');
        if (!field.value.trim()) {
          field.classList.add('error');
          if (err) { err.textContent = 'This field is required.'; err.classList.add('show'); }
          valid = false;
        } else {
          field.classList.remove('error');
          if (err) err.classList.remove('show');
        }
      });

      const emailField = document.getElementById('email');
      if (emailField?.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value)) {
        emailField.classList.add('error');
        const err = document.getElementById('email_err');
        if (err) { err.textContent = 'Enter a valid email address.'; err.classList.add('show'); }
        valid = false;
      }

      const phoneField = document.getElementById('phone');
      if (phoneField?.value && !/^[+\d\s\-()]{7,20}$/.test(phoneField.value)) {
        phoneField.classList.add('error');
        const err = document.getElementById('phone_err');
        if (err) { err.textContent = 'Enter a valid phone number.'; err.classList.add('show'); }
        valid = false;
      }

      const dobField = document.getElementById('date_of_birth');
      if (dobField?.value && new Date(dobField.value) >= new Date()) {
        dobField.classList.add('error');
        const err = document.getElementById('date_of_birth_err');
        if (err) { err.textContent = 'Date of birth must be in the past.'; err.classList.add('show'); }
        valid = false;
      }

      if (!valid) { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    });

    appForm.querySelectorAll('input, select, textarea').forEach(field => {
      field.addEventListener('input', () => {
        field.classList.remove('error');
        const err = document.getElementById(field.id + '_err');
        if (err) err.classList.remove('show');
      });
    });
  }

  // ── Dynamic table search ──────────────────────────────────
  const tableSearch = document.getElementById('tableSearch');
  if (tableSearch) {
    tableSearch.addEventListener('input', () => {
      const q = tableSearch.value.toLowerCase();
      document.querySelectorAll('tbody tr:not(.no-data)').forEach(row =>
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none');
    });
  }

  // ── Print trigger ─────────────────────────────────────────
  document.querySelectorAll('[data-print]').forEach(btn =>
    btn.addEventListener('click', () => window.print()));

}); // end DOMContentLoaded

// Exposed globally for inline onclick handlers in PHP templates
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
