/**
 * scene-bg.js — Universal 3D particle background
 * ─────────────────────────────────────────────────────────────
 * Drop ONE <script src="scene-bg.js"></script> before </body>
 * on any page. The script self-initialises, creates its own
 * <canvas id="scene-bg-canvas">, and tears itself down cleanly.
 *
 * Features
 * ────────
 * • Layered star-field with depth (z 0.2 – 3.0)
 * • Mouse-reactive repulsion — particles & nodes flee cursor
 * • Constellation nodes with glow halos + connection lines
 * • Parallax scroll — deeper layers move faster
 * • Cursor glow on the canvas layer
 * • Theme-aware colours (dark / light via data-theme="light")
 * • Pauses when tab is hidden (Page Visibility API)
 * • Debounced resize handler — rebuilds only once per burst
 */

(function () {
  'use strict';

  /* ── Guard: don't double-initialise ─────────────────────── */
  if (document.getElementById('scene-bg-canvas')) return;

  /* ── Create & insert canvas ──────────────────────────────── */
  const canvas = document.createElement('canvas');
  canvas.id = 'scene-bg-canvas';
  canvas.setAttribute('aria-hidden', 'true');
  document.body.insertBefore(canvas, document.body.firstChild);

  const ctx = canvas.getContext('2d');

  /* ── State ───────────────────────────────────────────────── */
  let W, H;
  let particles = [];
  let nodes     = [];
  let rafId     = null;
  let paused    = false;

  const mouse = { x: -9999, y: -9999 };
  let   scrollY = 0;

  /* ── Theme detection ─────────────────────────────────────── */
  function isLight () {
    return document.documentElement.getAttribute('data-theme') === 'light';
  }

  /* ── Resize ──────────────────────────────────────────────── */
  function resize () {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
    build();
  }

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(resize, 120);
  });

  /* ── Build particle & node arrays ────────────────────────── */
  function build () {
    /* particle count scales with viewport area, capped at 200 */
    const count = Math.min(Math.floor((W * H) / 9000), 200);

    particles = Array.from({ length: count }, () => {
      const roll = Math.random();
      /* colour buckets: 14% gold · 36% blue · 50% white-ish */
      const base = roll > 0.86
        ? [200, 145, 26]    /* gold  */
        : roll > 0.50
          ? [59,  130, 246] /* blue  */
          : [180, 200, 240];/* star  */

      return {
        x:    Math.random() * W,
        y:    Math.random() * H,
        ox:   0,              /* mouse offset X (lerped) */
        oy:   0,              /* mouse offset Y (lerped) */
        z:    Math.random() * 2.5 + 0.3,
        r:    Math.random() * 1.5 + 0.3,
        vx:   (Math.random() - 0.5) * 0.18,
        vy:   (Math.random() - 0.5) * 0.18,
        base,
        alpha: Math.random() * 0.55 + 0.1,
      };
    });

    /* 18 larger glowing nodes for the constellation */
    nodes = Array.from({ length: 18 }, () => ({
      x:     Math.random() * W,
      y:     Math.random() * H,
      ox:    0,
      oy:    0,
      z:     Math.random() * 1.5 + 0.6,
      size:  Math.random() * 3   + 1.8,
      vx:    (Math.random() - 0.5) * 0.07,
      vy:    (Math.random() - 0.5) * 0.07,
      phase: Math.random() * Math.PI * 2,
      speed: 0.003 + Math.random() * 0.004,
    }));
  }

  /* ── Main draw loop ──────────────────────────────────────── */
  function draw () {
    if (paused) { rafId = requestAnimationFrame(draw); return; }

    ctx.clearRect(0, 0, W, H);

    const light = isLight();

    /* ─── Particles ────────────────────────────────────────── */
    for (const p of particles) {
      p.x += p.vx;
      p.y += p.vy;

      /* wrap edges */
      if (p.x < 0) p.x = W; else if (p.x > W) p.x = 0;
      if (p.y < 0) p.y = H; else if (p.y > H) p.y = 0;

      /* parallax: deeper particles scroll more */
      const baseY = p.y - scrollY * p.z * 0.04;

      /* mouse repulsion */
      const dx = p.x - mouse.x;
      const dy = baseY - mouse.y;
      const d2 = dx * dx + dy * dy;
      const rr = (110 / p.z) ** 2;

      if (d2 < rr && d2 > 0) {
        const d    = Math.sqrt(d2);
        const f    = (1 - d / Math.sqrt(rr)) * 2.8;
        p.ox += ((dx / d) * f - p.ox) * 0.14;
        p.oy += ((dy / d) * f - p.oy) * 0.14;
      } else {
        p.ox += (0 - p.ox) * 0.06;
        p.oy += (0 - p.oy) * 0.06;
      }

      const drawX = p.x   + p.ox;
      const drawY = baseY + p.oy;

      /* cull off-screen */
      if (drawY < -4 || drawY > H + 4) continue;

      const [r, g, b] = p.base;
      /* light theme: darken stars so they're visible on white */
      const [fr, fg, fb] = light ? [r * 0.35, g * 0.35, b * 0.35] : [r, g, b];
      const fa = light ? p.alpha * 0.7 : p.alpha * Math.min(p.z, 1);

      ctx.beginPath();
      ctx.arc(drawX, drawY, p.r * p.z * 0.65, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${fr},${fg},${fb},${fa})`;
      ctx.fill();
    }

    /* ─── Constellation nodes ──────────────────────────────── */
    for (const n of nodes) {
      n.x += n.vx;
      n.y += n.vy;
      if (n.x < 0) n.x = W; else if (n.x > W) n.x = 0;
      if (n.y < 0) n.y = H; else if (n.y > H) n.y = 0;

      n.phase += n.speed;

      const baseY = n.y - scrollY * n.z * 0.055;

      /* mouse repulsion — nodes react slightly stronger */
      const dx = n.x - mouse.x;
      const dy = baseY - mouse.y;
      const d2 = dx * dx + dy * dy;
      const rr = 160 * 160;

      if (d2 < rr && d2 > 0) {
        const d = Math.sqrt(d2);
        const f = (1 - d / 160) * 4.0;
        n.ox += ((dx / d) * f - n.ox) * 0.10;
        n.oy += ((dy / d) * f - n.oy) * 0.10;
      } else {
        n.ox += (0 - n.ox) * 0.05;
        n.oy += (0 - n.oy) * 0.05;
      }

      const nx = n.x   + n.ox;
      const ny = baseY + n.oy;
      if (ny < -50 || ny > H + 50) continue;

      const pulse  = 0.5 + 0.5 * Math.sin(n.phase);
      const gSize  = n.size * 5 * n.z;
      const zCap   = Math.min(n.z, 1);
      const aScale = light ? 0.25 : 1;

      /* glow halo */
      const g = ctx.createRadialGradient(nx, ny, 0, nx, ny, gSize);
      g.addColorStop(0, `rgba(59,130,246,${0.22 * pulse * zCap * aScale})`);
      g.addColorStop(1, 'rgba(59,130,246,0)');
      ctx.beginPath();
      ctx.arc(nx, ny, gSize, 0, Math.PI * 2);
      ctx.fillStyle = g;
      ctx.fill();

      /* core dot */
      ctx.beginPath();
      ctx.arc(nx, ny, n.size * n.z * 0.55, 0, Math.PI * 2);
      ctx.fillStyle = light
        ? `rgba(29,90,200,${0.55 * pulse})`
        : `rgba(100,165,255,${0.75 * pulse})`;
      ctx.fill();
    }

    /* ─── Connection lines between close nodes ─────────────── */
    const MAX_DIST   = 240;
    const MAX_DIST_2 = MAX_DIST * MAX_DIST;
    const lineAlpha  = light ? 0.07 : 0.13;

    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const a  = nodes[i], b = nodes[j];
        const ax = a.x + a.ox, ay = (a.y - scrollY * a.z * 0.055) + a.oy;
        const bx = b.x + b.ox, by = (b.y - scrollY * b.z * 0.055) + b.oy;
        const dx = ax - bx, dy = ay - by;
        const d2 = dx * dx + dy * dy;

        if (d2 < MAX_DIST_2) {
          const t = 1 - Math.sqrt(d2) / MAX_DIST;
          ctx.beginPath();
          ctx.moveTo(ax, ay);
          ctx.lineTo(bx, by);
          ctx.strokeStyle = `rgba(59,130,246,${t * lineAlpha})`;
          ctx.lineWidth   = 0.7;
          ctx.stroke();
        }
      }
    }

    /* ─── Cursor glow on canvas ────────────────────────────── */
    if (mouse.x > 0 && !light) {
      const mg = ctx.createRadialGradient(
        mouse.x, mouse.y, 0,
        mouse.x, mouse.y, 210
      );
      mg.addColorStop(0, 'rgba(59,130,246,0.045)');
      mg.addColorStop(1, 'rgba(59,130,246,0)');
      ctx.fillStyle = mg;
      ctx.fillRect(0, 0, W, H);
    }

    rafId = requestAnimationFrame(draw);
  }

  /* ── Event listeners ─────────────────────────────────────── */
  window.addEventListener('mousemove', e => {
    mouse.x = e.clientX;
    mouse.y = e.clientY;
  }, { passive: true });

  window.addEventListener('mouseleave', () => {
    mouse.x = -9999;
    mouse.y = -9999;
  });

  window.addEventListener('scroll', () => {
    scrollY = window.scrollY;
  }, { passive: true });

  /* Pause when tab is hidden — saves CPU */
  document.addEventListener('visibilitychange', () => {
    paused = document.hidden;
  });

  /* Re-build when theme toggles (theme-toggle button flips data-theme) */
  const themeObserver = new MutationObserver(() => {
    /* no rebuild needed — draw() reads isLight() live */
  });
  themeObserver.observe(document.documentElement, {
    attributes: true, attributeFilter: ['data-theme']
  });

  /* ── Boot ────────────────────────────────────────────────── */
  resize();         /* sets W, H, builds arrays, starts loop */
  rafId = requestAnimationFrame(draw);

})();