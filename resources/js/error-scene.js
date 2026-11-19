/*
 | Alpine component for error pages. Pure enhancement: the page is fully usable without it.
 |  - parallax tilt of the scene following the pointer (disabled for touch / reduced motion)
 |  - countdown + automatic reload for 429 / 502 / 503 / 504 (honours Retry-After)
 |  - loop guard: stops auto-reloading after MAX_AUTO attempts
 | Register once in app.js:  Alpine.data('errorScene', errorScene)
 */
const MAX_AUTO = 12;
const STORE = 'es-auto-retries';

const reduced = () => typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
const read = (k) => { try { return sessionStorage.getItem(k); } catch (e) { return null; } };
const write = (k, v) => { try { sessionStorage.setItem(k, v); } catch (e) {} };
const drop = (k) => { try { sessionStorage.removeItem(k); } catch (e) {} };

export default function errorScene(cfg = {}) {
  const ui = cfg.ui || {};
  const nf = new Intl.NumberFormat(cfg.locale === 'bn' ? 'bn-BD' : 'en-US');

  return {
    remaining: null,
    total: cfg.retry || 0,
    stalled: false,
    timer: null,
    frame: 0,
    onMove: null,
    onLeave: null,

    get text() {
      if (this.stalled) return ui.stalled || '';
      return (ui.retrying || '{s}').replace('{s}', nf.format(this.remaining ?? this.total));
    },
    get progress() {
      if (this.stalled || !this.total || this.remaining === null) return 0;
      return Math.min(1, Math.max(0, 1 - this.remaining / this.total));
    },

    init() {
      if (cfg.auto && cfg.retry) this.startCountdown();
      if (!reduced() && !(window.matchMedia && matchMedia('(pointer: coarse)').matches)) this.startTilt();
    },

    destroy() {
      clearInterval(this.timer);
      cancelAnimationFrame(this.frame);
      if (this.onMove) removeEventListener('pointermove', this.onMove);
      if (this.onLeave) document.removeEventListener('mouseleave', this.onLeave);
    },

    startCountdown() {
      const tries = Number(read(STORE) || 0) + 1;
      write(STORE, String(tries));
      if (tries > MAX_AUTO) { this.stalled = true; return; }

      const until = Date.now() + cfg.retry * 1000;
      this.remaining = cfg.retry;
      this.timer = setInterval(() => {
        this.remaining = Math.max(0, Math.ceil((until - Date.now()) / 1000));
        if (this.remaining <= 0) { clearInterval(this.timer); location.reload(); }
      }, 250);
    },

    reload() {
      drop(STORE);          // a manual retry resets the loop guard
      location.reload();
    },

    startTilt() {
      const el = this.$refs && this.$refs.tilt;
      if (!el) return;
      this.onMove = (e) => {
        if (this.frame) return;
        this.frame = requestAnimationFrame(() => {
          this.frame = 0;
          const nx = e.clientX / innerWidth - 0.5;
          const ny = e.clientY / innerHeight - 0.5;
          el.style.setProperty('--ry', (nx * 7).toFixed(2) + 'deg');
          el.style.setProperty('--rx', (-ny * 5).toFixed(2) + 'deg');
        });
      };
      this.onLeave = () => { el.style.setProperty('--ry', '0deg'); el.style.setProperty('--rx', '0deg'); };
      addEventListener('pointermove', this.onMove, { passive: true });
      document.addEventListener('mouseleave', this.onLeave);
    },
  };
}
