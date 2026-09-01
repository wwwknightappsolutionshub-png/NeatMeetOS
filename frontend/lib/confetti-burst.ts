/**
 * Lightweight canvas confetti burst for celebratory moments (no extra deps).
 */
export function fireConfettiBurst(durationMs = 3200): void {
  if (typeof window === 'undefined') return;

  const canvas = document.createElement('canvas');
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText =
    'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:200;';
  document.body.appendChild(canvas);

  const ctx = canvas.getContext('2d');
  if (!ctx) {
    canvas.remove();
    return;
  }

  const resize = () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  };
  resize();
  window.addEventListener('resize', resize);

  const colors = ['#3d5a45', '#5a8f6e', '#d4a853', '#e8c468', '#ffffff', '#c9e4d4'];
  const particleCount = 120;
  const particles = Array.from({ length: particleCount }, () => {
    const angle = Math.random() * Math.PI * 2;
    const speed = 4 + Math.random() * 10;
    return {
      x: canvas.width * (0.35 + Math.random() * 0.3),
      y: canvas.height * 0.28,
      vx: Math.cos(angle) * speed,
      vy: Math.sin(angle) * speed - 6,
      w: 6 + Math.random() * 6,
      h: 4 + Math.random() * 5,
      rot: Math.random() * Math.PI,
      spin: (Math.random() - 0.5) * 0.3,
      color: colors[Math.floor(Math.random() * colors.length)] ?? colors[0],
      life: 1,
      decay: 0.008 + Math.random() * 0.01,
    };
  });

  const started = performance.now();
  let raf = 0;

  const frame = (now: number) => {
    const elapsed = now - started;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (const p of particles) {
      p.x += p.vx;
      p.y += p.vy;
      p.vy += 0.22;
      p.vx *= 0.99;
      p.rot += p.spin;
      p.life -= p.decay;

      if (p.life <= 0) continue;

      ctx.save();
      ctx.globalAlpha = Math.max(0, p.life);
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      ctx.restore();
    }

    if (elapsed < durationMs) {
      raf = window.requestAnimationFrame(frame);
      return;
    }

    window.cancelAnimationFrame(raf);
    window.removeEventListener('resize', resize);
    canvas.remove();
  };

  raf = window.requestAnimationFrame(frame);
}
