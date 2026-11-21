/*
 | Alpine components for the login page. Register in resources/js/app.js:
 |   import { loginForm, authStage } from './auth';
 |   Alpine.data('loginForm', loginForm);
 |   Alpine.data('authStage', authStage);
 | Both are progressive enhancement: without JS the form still posts normally.
 */

/** Form: double-submit guard, native validation, Caps-Lock hint (used by <x-auth.field type="password">). */
export function loginForm() {
    return {
        busy: false,
        caps: false,
        init() {
            // Back/forward cache restores a "busy" page: reset it.
            window.addEventListener('pageshow', () => { this.busy = false; });
        },
        submit(e) {
            if (this.busy) { e.preventDefault(); return; }
            const form = e.target;
            if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
            this.busy = true;
        },
        capsCheck(e) {
            this.caps = !!(e.getModifierState && e.getModifierState('CapsLock'));
        },
    };
}

/** <body>: subtle pointer parallax for the decoration layers (.au-par). Off for reduced-motion / touch. */
export function authStage() {
    return {
        init() {
            const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
            const fine = matchMedia('(hover: hover) and (pointer: fine)').matches;
            if (reduce || !fine) return;
            let raf = 0, nx = 0, ny = 0;
            const apply = () => {
                raf = 0;
                this.$el.style.setProperty('--px', nx.toFixed(3));
                this.$el.style.setProperty('--py', ny.toFixed(3));
            };
            this._move = (e) => {
                nx = (e.clientX / innerWidth) * 2 - 1;
                ny = (e.clientY / innerHeight) * 2 - 1;
                if (!raf) raf = requestAnimationFrame(apply);
            };
            window.addEventListener('pointermove', this._move, { passive: true });
        },
        destroy() { if (this._move) window.removeEventListener('pointermove', this._move); },
    };
}
