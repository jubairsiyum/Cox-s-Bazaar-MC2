const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const onReady = (fn) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn, { once: true }) : fn();

export function initReveal(root = document) {
  root.querySelectorAll('[data-stagger]').forEach((p) =>
    [...p.children].forEach((c, i) => { if (!c.style.getPropertyValue('--i')) c.style.setProperty('--i', i); }));
  const els = root.querySelectorAll('.rv:not(.in)');
  if (reduced() || !('IntersectionObserver' in window)) {
    els.forEach((e) => e.classList.add('in'));
  } else {
    const pending = new Set(els);
    const show = (el) => { el.classList.add('in'); pending.delete(el); io.unobserve(el); };
    const io = new IntersectionObserver((entries) => {
      for (const e of entries) if (e.isIntersecting || e.boundingClientRect.bottom < 0) show(e.target);
    }, { threshold: 0.1, rootMargin: '0px 0px -6% 0px' });
    els.forEach((e) => io.observe(e));

    // IO only reports state *changes*: a jump-scroll (End key, anchor, restored scroll) can skip elements.
    // Sweep: anything fully above the viewport is revealed (no one is watching it animate).
    let queued = false;
    const sweep = () => {
      queued = false;
      for (const el of pending) if (el.getBoundingClientRect().bottom < 0) show(el);
      if (!pending.size) removeEventListener('scroll', onScroll);
    };
    const onScroll = () => { if (!queued) { queued = true; requestAnimationFrame(sweep); } };
    addEventListener('scroll', onScroll, { passive: true });
  }
  document.documentElement.classList.add('motion-ready');
}

/* Theme/dark switch: pass the function that changes the class/attribute (must change DOM synchronously) */
let t;
export function themeSwitch(apply) {
  const root = document.documentElement;
  if (reduced()) return apply();
  if (document.startViewTransition) return document.startViewTransition(apply);
  root.classList.add('theme-fade'); apply();
  clearTimeout(t); t = setTimeout(() => root.classList.remove('theme-fade'), 450);
}

export function registerMotion(Alpine) {
  Alpine.directive('count', (el, { expression }, { evaluate, cleanup }) => {
    const to = Number(evaluate(expression));
    if (!Number.isFinite(to)) return;
    const dec = Number(el.dataset.decimals || 0);
    const lang = el.dataset.locale || (document.documentElement.lang === 'bn' ? 'bn-BD' : undefined);
    const fmt = new Intl.NumberFormat(lang, { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const pre = el.dataset.prefix || '', suf = el.dataset.suffix || '', dur = Number(el.dataset.duration || 1400);
    let last = '';
    const set = (v) => { const s = pre + fmt.format(v) + suf; if (s !== last) { el.textContent = s; last = s; } };
    if (reduced()) return set(to);
    set(0);
    let raf = 0, timer = 0, io;
    const run = () => {
      const host = el.closest('.rv');
      const i = host ? parseFloat(getComputedStyle(host).getPropertyValue('--i')) || 0 : 0;
      timer = setTimeout(() => {
        const t0 = performance.now();
        const tick = (now) => { const p = Math.min((now - t0) / dur, 1); set(to * (p === 1 ? 1 : 1 - 2 ** (-10 * p))); if (p < 1) raf = requestAnimationFrame(tick); };
        raf = requestAnimationFrame(tick);
      }, 250 + Math.min(i, 10) * 70);
    };
    if ('IntersectionObserver' in window) { io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { io.disconnect(); run(); } }, { threshold: 0.2 }); io.observe(el); } else run();
    cleanup(() => { cancelAnimationFrame(raf); clearTimeout(timer); io && io.disconnect(); });
  });
}
export const startMotion = () => onReady(() => initReveal());
