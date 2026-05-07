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

  const mouse = { x: -9999, y: -9999, vx: 0, vy: 0, lastX: -9999, lastY: -9999 };
  let   scrollY = 0;
  let   mouseTrail = [];
  const MAX_TRAIL = 15;

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
    /* particle count scales with viewport area, capped at 250 */
    const count = Math.min(Math.floor((W * H) / 7000), 250);

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
        r:    Math.random() * 1.8 + 0.3,
        vx:   (Math.random() - 0.5) * 0.25,
        vy:   (Math.random() - 0.5) * 0.25,
        base,
        alpha: Math.random() * 0.65 + 0.1,
        rotation: Math.random() * Math.PI * 2,
        rotSpeed: (Math.random() - 0.5) * 0.02,
      };
    });

    /* 24 larger glowing nodes for the constellation */
    nodes = Array.from({ length: 24 }, () => ({
      x:     Math.random() * W,
      y:     Math.random() * H,
      ox:    0,
      oy:    0,
      z:     Math.random() * 1.8 + 0.6,
      size:  Math.random() * 4   + 2,
      vx:    (Math.random() - 0.5) * 0.12,
      vy:    (Math.random() - 0.5) * 0.12,
      phase: Math.random() * Math.PI * 2,
      speed: 0.005 + Math.random() * 0.008,
      attraction: Math.random() * 0.5 + 0.3,
    }));
  }

  /* ── Update mouse velocity for smoother reactions ────────── */
  function updateMouseVelocity(e) {
    if (mouse.lastX !== -9999) {
      mouse.vx = (e.clientX - mouse.lastX) * 0.3;
      mouse.vy = (e.clientY - mouse.lastY) * 0.3;
      
      /* Add to trail */
      mouseTrail.unshift({ x: e.clientX, y: e.clientY });
      if (mouseTrail.length > MAX_TRAIL) mouseTrail.pop();
    }
    mouse.lastX = e.clientX;
    mouse.lastY = e.clientY;
  }

  /* ── Main draw loop ──────────────────────────────────────── */
  function draw () {
    if (paused) { rafId = requestAnimationFrame(draw); return; }

    ctx.clearRect(0, 0, W, H);

    const light = isLight();

    /* ─── Particles with enhanced mouse interaction ────────── */
    for (const p of particles) {
      /* Natural drift */
      p.x += p.vx;
      p.y += p.vy;

      /* Rotation for visual variety */
      p.rotation += p.rotSpeed;

      /* wrap edges */
      if (p.x < -20) p.x = W + 20; 
      else if (p.x > W + 20) p.x = -20;
      if (p.y < -20) p.y = H + 20; 
      else if (p.y > H + 20) p.y = -20;

      /* parallax: deeper particles scroll more */
      const baseY = p.y - scrollY * p.z * 0.06;

      /* Enhanced mouse repulsion force */
      const dx = p.x - mouse.x;
      const dy = baseY - mouse.y;
      const d2 = dx * dx + dy * dy;
      const mouseForceRadius = 180 / p.z;
      const rr = mouseForceRadius ** 2;

      if (d2 < rr && d2 > 0) {
        const d    = Math.sqrt(d2);
        const strength = (1 - d / mouseForceRadius) * 4.5;
        const angle = Math.atan2(dy, dx);
        const forceX = Math.cos(angle) * strength;
        const forceY = Math.sin(angle) * strength;
        
        p.ox += (forceX - p.ox) * 0.25;
        p.oy += (forceY - p.oy) * 0.25;
      } else {
        p.ox += (0 - p.ox) * 0.08;
        p.oy += (0 - p.oy) * 0.08;
      }
      
      /* Add mouse velocity influence (mouse drag effect) */
      if (Math.abs(mouse.vx) > 0.1 || Math.abs(mouse.vy) > 0.1) {
        const distToMouse = Math.sqrt(d2);
        if (distToMouse < 250) {
          const influence = (1 - distToMouse / 250) * 0.8;
          p.ox += mouse.vx * influence * 0.02;
          p.oy += mouse.vy * influence * 0.02;
        }
      }

      const drawX = p.x   + p.ox;
      const drawY = baseY + p.oy;

      /* cull off-screen */
      if (drawY < -10 || drawY > H + 10) continue;

      const [r, g, b] = p.base;
      /* light theme: darken stars so they're visible on white */
      const [fr, fg, fb] = light ? [r * 0.35, g * 0.35, b * 0.35] : [r, g, b];
      const fa = light ? p.alpha * 0.7 : p.alpha * Math.min(p.z, 1) * (0.8 + Math.sin(p.rotation) * 0.2);

      /* Draw particle with optional glow */
      ctx.save();
      ctx.translate(drawX, drawY);
      ctx.rotate(p.rotation);
      ctx.beginPath();
      ctx.arc(0, 0, p.r * p.z * 0.7, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${fr},${fg},${fb},${fa})`;
      ctx.fill();
      
      /* Add small glow for larger particles */
      if (p.r > 0.8) {
        ctx.beginPath();
        ctx.arc(0, 0, p.r * p.z * 1.2, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(${fr},${fg},${fb},${fa * 0.3})`;
        ctx.fill();
      }
      ctx.restore();
    }

    /* ─── Constellation nodes with enhanced reactivity ─────── */
    for (const n of nodes) {
      n.x += n.vx;
      n.y += n.vy;
      if (n.x < -30) n.x = W + 30; 
      else if (n.x > W + 30) n.x = -30;
      if (n.y < -30) n.y = H + 30; 
      else if (n.y > H + 30) n.y = -30;

      n.phase += n.speed;

      const baseY = n.y - scrollY * n.z * 0.07;

      /* Enhanced mouse repulsion for nodes */
      const dx = n.x - mouse.x;
      const dy = baseY - mouse.y;
      const d2 = dx * dx + dy * dy;
      const nodeForceRadius = 200;
      const rr = nodeForceRadius ** 2;

      if (d2 < rr && d2 > 0) {
        const d = Math.sqrt(d2);
        const strength = (1 - d / nodeForceRadius) * 6.0 * n.attraction;
        const angle = Math.atan2(dy, dx);
        const forceX = Math.cos(angle) * strength;
        const forceY = Math.sin(angle) * strength;
        
        n.ox += (forceX - n.ox) * 0.18;
        n.oy += (forceY - n.oy) * 0.18;
      } else {
        n.ox += (0 - n.ox) * 0.06;
        n.oy += (0 - n.oy) * 0.06;
      }
      
      /* Mouse velocity influence on nodes */
      if (Math.abs(mouse.vx) > 0.1 || Math.abs(mouse.vy) > 0.1) {
        const distToMouse = Math.sqrt(d2);
        if (distToMouse < 300) {
          const influence = (1 - distToMouse / 300) * 1.2;
          n.ox += mouse.vx * influence * 0.015;
          n.oy += mouse.vy * influence * 0.015;
        }
      }

      const nx = n.x   + n.ox;
      const ny = baseY + n.oy;
      if (ny < -80 || ny > H + 80) continue;

      const pulse  = 0.5 + 0.5 * Math.sin(n.phase);
      const gSize  = n.size * 6 * n.z;
      const zCap   = Math.min(n.z, 1);
      const aScale = light ? 0.25 : 1;

      /* Enhanced glow halo with pulsing effect */
      const g = ctx.createRadialGradient(nx, ny, 0, nx, ny, gSize);
      const intensity = 0.28 * pulse * zCap * aScale;
      g.addColorStop(0, `rgba(59,130,246,${intensity * 1.5})`);
      g.addColorStop(0.4, `rgba(59,130,246,${intensity})`);
      g.addColorStop(1, 'rgba(59,130,246,0)');
      ctx.beginPath();
      ctx.arc(nx, ny, gSize, 0, Math.PI * 2);
      ctx.fillStyle = g;
      ctx.fill();

      /* Core dot with pulse */
      ctx.beginPath();
      ctx.arc(nx, ny, n.size * n.z * 0.65, 0, Math.PI * 2);
      ctx.fillStyle = light
        ? `rgba(29,90,200,${0.65 * pulse})`
        : `rgba(100,165,255,${0.85 * pulse})`;
      ctx.fill();
      
      /* Inner bright core */
      ctx.beginPath();
      ctx.arc(nx, ny, n.size * n.z * 0.3, 0, Math.PI * 2);
      ctx.fillStyle = light
        ? `rgba(59,130,246,${0.8})`
        : `rgba(200,220,255,${0.9 * pulse})`;
      ctx.fill();
    }

    /* ─── Enhanced connection lines with mouse influence ───── */
    const MAX_DIST   = 280;
    const MAX_DIST_2 = MAX_DIST * MAX_DIST;
    const lineAlpha  = light ? 0.1 : 0.18;

    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const a  = nodes[i], b = nodes[j];
        const ax = a.x + a.ox, ay = (a.y - scrollY * a.z * 0.07) + a.oy;
        const bx = b.x + b.ox, by = (b.y - scrollY * b.z * 0.07) + b.oy;
        let dx = ax - bx, dy = ay - by;
        let d2 = dx * dx + dy * dy;

        /* Check if mouse is near either node for highlighted connections */
        let mouseInfluence = 1;
        const distToMouseA = Math.hypot(ax - mouse.x, ay - mouse.y);
        const distToMouseB = Math.hypot(bx - mouse.x, by - mouse.y);
        if (distToMouseA < 150 || distToMouseB < 150) {
          mouseInfluence = 1.5;
        }

        if (d2 < MAX_DIST_2) {
          const t = 1 - Math.sqrt(d2) / MAX_DIST;
          const finalAlpha = t * lineAlpha * mouseInfluence;
          
          ctx.beginPath();
          ctx.moveTo(ax, ay);
          ctx.lineTo(bx, by);
          ctx.strokeStyle = `rgba(59,130,246,${finalAlpha})`;
          ctx.lineWidth   = mouseInfluence > 1 ? 1.2 : 0.8;
          ctx.stroke();
          
          /* Add glow effect to connections near mouse */
          if (mouseInfluence > 1) {
            ctx.beginPath();
            ctx.moveTo(ax, ay);
            ctx.lineTo(bx, by);
            ctx.strokeStyle = `rgba(100,165,255,${finalAlpha * 0.5})`;
            ctx.lineWidth   = 2.5;
            ctx.stroke();
          }
        }
      }
    }

    /* ─── Draw mouse trail effect ──────────────────────────── */
    if (!light && mouseTrail.length > 1) {
      for (let i = 0; i < mouseTrail.length - 1; i++) {
        const alpha = (1 - i / mouseTrail.length) * 0.15;
        ctx.beginPath();
        ctx.moveTo(mouseTrail[i].x, mouseTrail[i].y);
        ctx.lineTo(mouseTrail[i + 1].x, mouseTrail[i + 1].y);
        ctx.strokeStyle = `rgba(59,130,246,${alpha})`;
        ctx.lineWidth = 3 - (i / mouseTrail.length) * 2;
        ctx.stroke();
      }
    }

    /* ─── Enhanced cursor glow on canvas ───────────────────── */
    if (mouse.x > 0 && !light) {
      const mg = ctx.createRadialGradient(
        mouse.x, mouse.y, 0,
        mouse.x, mouse.y, 280
      );
      mg.addColorStop(0, 'rgba(59,130,246,0.08)');
      mg.addColorStop(0.4, 'rgba(59,130,246,0.04)');
      mg.addColorStop(1, 'rgba(59,130,246,0)');
      ctx.fillStyle = mg;
      ctx.fillRect(0, 0, W, H);
      
      /* Secondary smaller glow */
      const mg2 = ctx.createRadialGradient(
        mouse.x, mouse.y, 0,
        mouse.x, mouse.y, 120
      );
      mg2.addColorStop(0, 'rgba(200,145,26,0.06)');
      mg2.addColorStop(1, 'rgba(200,145,26,0)');
      ctx.fillStyle = mg2;
      ctx.fillRect(0, 0, W, H);
    }

    rafId = requestAnimationFrame(draw);
  }

  /* ── Event listeners ─────────────────────────────────────── */
  window.addEventListener('mousemove', e => {
    updateMouseVelocity(e);
    mouse.x = e.clientX;
    mouse.y = e.clientY;
  }, { passive: true });

  window.addEventListener('mouseleave', () => {
    mouse.x = -9999;
    mouse.y = -9999;
    mouse.lastX = -9999;
    mouse.lastY = -9999;
    mouse.vx = 0;
    mouse.vy = 0;
    mouseTrail = [];
  });

  window.addEventListener('scroll', () => {
    scrollY = window.scrollY;
  }, { passive: true });

  /* Pause when tab is hidden — saves CPU */
  document.addEventListener('visibilitychange', () => {
    paused = document.hidden;
  });

  /* Re-build when theme toggles */
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