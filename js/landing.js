// ── Spotlight card effects ────────────────────────────────
(function() {
  const STEP = 1.5, MAX_DIST = 260, RADIUS = 12;
  let mx = -9999, my = -9999;
  const particles = [];

  // Per-card color map based on class / icon color
  function getCardColor(card) {
    if (card.classList.contains('admin'))     return '248,113,113';
    if (card.classList.contains('officer'))   return '59,130,246';
    if (card.classList.contains('applicant')) return '52,211,153';
    const icon = card.querySelector('.bento-icon, .how-icon');
    if (icon) {
      if (icon.classList.contains('blue'))   return '59,130,246';
      if (icon.classList.contains('gold'))   return '200,145,26';
      if (icon.classList.contains('green'))  return '52,211,153';
      if (icon.classList.contains('teal'))   return '45,212,191';
      if (icon.classList.contains('red'))    return '248,113,113';
      if (icon.classList.contains('purple')) return '167,139,250';
    }
    return '99,130,246';
  }

  function sampleBorder(W, H, r) {
    const pts = [], PI = Math.PI;
    const push = (x, y) => pts.push({x, y});
    function line(x0,y0,x1,y1) {
      const n = Math.ceil(Math.hypot(x1-x0, y1-y0) / STEP);
      for (let i = 0; i < n; i++) { const t=i/n; push(x0+(x1-x0)*t, y0+(y1-y0)*t); }
    }
    function arc(cx, cy, a0, a1) {
      const n = Math.ceil(Math.abs(a1-a0)*r/STEP);
      for (let i = 0; i <= n; i++) { const a=a0+(a1-a0)*(i/n); push(cx+r*Math.cos(a), cy+r*Math.sin(a)); }
    }
    line(r,0,W-r,0); arc(W-r,r,-PI/2,0); line(W,r,W,H-r); arc(W-r,H-r,0,PI/2);
    line(W-r,H,r,H); arc(r,H-r,PI/2,PI); line(0,H-r,0,r); arc(r,r,PI,3*PI/2);
    return pts;
  }

  function nearestOnSeg(ax,ay,bx,by,px,py) {
    const dx=bx-ax, dy=by-ay, l2=dx*dx+dy*dy;
    if (l2===0) return {x:ax,y:ay};
    const t=Math.max(0,Math.min(1,((px-ax)*dx+(py-ay)*dy)/l2));
    return {x:ax+t*dx, y:ay+t*dy};
  }

  // ── Particle burst ──────────────────────────────────────
  const pCanvas = document.createElement('canvas');
  pCanvas.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:9999;';
  document.body.appendChild(pCanvas);
  const pCtx = pCanvas.getContext('2d');

  function resizeP() { pCanvas.width=window.innerWidth; pCanvas.height=window.innerHeight; }
  resizeP(); window.addEventListener('resize', resizeP);

  function spawnBurst(x, y, rgb) {
    for (let i = 0; i < 32; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = Math.random() * 4.5 + 1.2;
      particles.push({
        x, y, vx: Math.cos(angle)*speed, vy: Math.sin(angle)*speed,
        r: Math.random()*2+0.6, alpha:1,
        decay: Math.random()*0.022+0.013, rgb,
        trail: []
      });
    }
  }

  function drawParticles() {
    pCtx.clearRect(0, 0, pCanvas.width, pCanvas.height);
    for (let i = particles.length-1; i >= 0; i--) {
      const p = particles[i];
      p.trail.push({x:p.x, y:p.y});
      if (p.trail.length > 7) p.trail.shift();
      p.x += p.vx; p.y += p.vy;
      p.vy += 0.1; p.vx *= 0.97; p.vy *= 0.97;
      p.alpha -= p.decay;
      if (p.alpha <= 0) { particles.splice(i,1); continue; }
      for (let t = 0; t < p.trail.length-1; t++) {
        const ta = p.alpha * (t/p.trail.length) * 0.35;
        pCtx.strokeStyle = `rgba(${p.rgb},${ta.toFixed(3)})`;
        pCtx.lineWidth = p.r * (t/p.trail.length);
        pCtx.beginPath();
        pCtx.moveTo(p.trail[t].x, p.trail[t].y);
        pCtx.lineTo(p.trail[t+1].x, p.trail[t+1].y);
        pCtx.stroke();
      }
      pCtx.fillStyle = `rgba(${p.rgb},${p.alpha.toFixed(3)})`;
      pCtx.beginPath(); pCtx.arc(p.x, p.y, p.r, 0, Math.PI*2); pCtx.fill();
    }
  }

  // ── Per-card state ──────────────────────────────────────
  const allCards = [...document.querySelectorAll('.how-card, .bento-card, .role-card')];
  const tiltState = new Map();

  allCards.forEach(card => {
    const rgb = getCardColor(card);
    card.dataset.scColor = rgb;
    tiltState.set(card, {rx:0, ry:0, trx:0, try:0});

    card.addEventListener('mousemove', e => {
      const rect = card.getBoundingClientRect();
      const cx = rect.left + rect.width/2, cy = rect.top + rect.height/2;
      const dx = (e.clientX-cx)/(rect.width/2);
      const dy = (e.clientY-cy)/(rect.height/2);
      const s = tiltState.get(card);
      s.trx = -dy * 8; s.try = dx * 8;
    });
    card.addEventListener('mouseleave', () => {
      const s = tiltState.get(card);
      s.trx = 0; s.try = 0;
    });
    card.addEventListener('click', e => {
      spawnBurst(e.clientX, e.clientY, rgb);
    });
  });

  // ── Draw border glow ────────────────────────────────────
  function drawBorder(card, canvas) {
    const rect = card.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    const W = rect.width, H = rect.height;
    const cw = Math.round(W*dpr), ch = Math.round(H*dpr);
    if (canvas.width !== cw || canvas.height !== ch) {
      canvas.width = cw; canvas.height = ch;
      canvas.style.width = W+'px'; canvas.style.height = H+'px';
      card._pts = null;
    }
    if (!card._pts) card._pts = sampleBorder(W, H, RADIUS);

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, cw, ch); ctx.save(); ctx.scale(dpr, dpr);
    const lx = mx - rect.left, ly = my - rect.top;
    const rgb = card.dataset.scColor;
    const pts = card._pts;
    ctx.lineWidth = 1.5; ctx.lineCap = 'butt';

    for (let i = 0; i < pts.length-1; i++) {
      const ax=pts[i].x, ay=pts[i].y, bx=pts[i+1].x, by=pts[i+1].y;
      const n = nearestOnSeg(ax, ay, bx, by, lx, ly);
      const d = Math.hypot(lx-n.x, ly-n.y);
      if (d >= MAX_DIST) continue;
      const a = Math.pow(1 - d/MAX_DIST, 2);
      ctx.strokeStyle = `rgba(${rgb},${a.toFixed(3)})`;
      ctx.beginPath(); ctx.moveTo(ax, ay); ctx.lineTo(bx, by); ctx.stroke();
    }

    const sp = card.querySelector('.sc-spotlight');
    if (sp) {
      const inside = lx>=0 && lx<=W && ly>=0 && ly<=H;
      if (inside) {
        sp.style.background = `radial-gradient(circle at ${(lx/W*100).toFixed(1)}% ${(ly/H*100).toFixed(1)}%, rgba(${rgb},0.07) 0%, transparent 60%)`;
        sp.style.opacity = '1';
      } else {
        sp.style.opacity = '0';
      }
    }
    ctx.restore();
  }

  // ── Tilt update ─────────────────────────────────────────
  function updateTilts() {
    allCards.forEach(card => {
      const s = tiltState.get(card);
      s.rx += (s.trx - s.rx) * 0.1;
      s.ry += (s.try - s.ry) * 0.1;
      card.style.transform = `rotateX(${s.rx.toFixed(2)}deg) rotateY(${s.ry.toFixed(2)}deg)`;
    });
  }

  // ── RAF loop ────────────────────────────────────────────
  function tick() {
    updateTilts();
    drawParticles();
    allCards.forEach(card => {
      const canvas = card.querySelector('.sc-canvas');
      if (canvas) drawBorder(card, canvas);
    });
    requestAnimationFrame(tick);
  }

  document.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; });
  requestAnimationFrame(tick);
})();

// ── Reactive stars ────────────────────────────────────────
(function() {
  const canvas = document.getElementById('star-canvas');
  const ctx = canvas.getContext('2d');
  let W, H, stars = [];
  let mx = -9999, my = -9999, tx = -9999, ty = -9999;

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
    initStars();
  }

  function initStars() {
    stars = Array.from({ length: 160 }, () => ({
      ox: Math.random() * W, oy: Math.random() * H,
      x: 0, y: 0, vx: 0, vy: 0,
      r: Math.random() * 0.9 + 0.25,
      baseAlpha: Math.random() * 0.5 + 0.1,
      alpha: 0,
      twSpd: Math.random() * 0.0008 + 0.0003,
      twPh:  Math.random() * Math.PI * 2,
    }));
    stars.forEach(s => { s.x = s.ox; s.y = s.oy; });
  }

  function draw(ts) {
    ctx.clearRect(0, 0, W, H);
    tx += (mx - tx) * 0.1;
    ty += (my - ty) * 0.1;

    for (const s of stars) {
      const dx = tx - s.x, dy = ty - s.y;
      const dist = Math.hypot(dx, dy);

      if (dist < 130 && tx > 0) {
        const force = (1 - dist / 130) * 0.045 * 2.5;
        const nx = dx / dist || 0, ny = dy / dist || 0;
        s.vx -= nx * force * (130 - dist);
        s.vy -= ny * force * (130 - dist);
      }

      s.vx += (s.ox - s.x) * 0.06;
      s.vy += (s.oy - s.y) * 0.06;
      s.vx *= 0.78; s.vy *= 0.78;
      s.x  += s.vx;  s.y  += s.vy;

      const cd    = Math.hypot(mx - s.x, my - s.y);
      const boost = cd < 190 ? Math.pow(1 - cd / 190, 1.5) * 0.65 : 0;
      const twink = s.baseAlpha * (0.45 + 0.55 * Math.sin(ts * s.twSpd + s.twPh));
      s.alpha = Math.min(1, twink + boost);

      ctx.fillStyle = `rgba(255,255,255,${s.alpha.toFixed(3)})`;
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r + boost * 1.4, 0, Math.PI * 2);
      ctx.fill();
    }

    requestAnimationFrame(draw);
  }

  window.addEventListener('resize', resize);
  document.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; });
  resize();
  requestAnimationFrame(draw);
})();

// ── Scroll-triggered fade-up ──────────────────────────────
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.fade-up').forEach((el, i) => {
  // Stagger cards in a grid
  const parent = el.closest('.how-grid, .bento-grid, .roles-grid');
  if (parent) {
    const siblings = [...parent.querySelectorAll('.fade-up')];
    const idx = siblings.indexOf(el);
    el.style.transitionDelay = (idx * 0.07) + 's';
  }
  observer.observe(el);
});

// ── Navbar scroll tint ────────────────────────────────────
const nav = document.querySelector('.nav');
window.addEventListener('scroll', () => {
  nav.style.background = window.scrollY > 40
    ? 'rgba(8,14,26,.97)'
    : 'rgba(8,14,26,.85)';
}, { passive: true });

const year = new Date().getFullYear();

document.getElementById("footer-legal").innerHTML = `
  &copy; ${year} Ministry of Home Affairs<br>
  All rights reserved
`;