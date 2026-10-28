import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

Alpine.plugin(persist);

// ── Global helpers available in Alpine expressions ──────────
window.formatBDT = (amount) => {
    const num = parseFloat(amount) || 0;
    return '৳' + num.toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

// ── Flash message auto-dismiss ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const flashes = document.querySelectorAll('[data-flash-dismiss]');
    flashes.forEach(el => {
        const delay = parseInt(el.dataset.flashDismiss) || 4000;
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, delay);
    });
});

// ── Offline / Online detection ──────────────────────────────
const offlineBanner = document.getElementById('offline-banner');
if (offlineBanner) {
    const update = () => {
        offlineBanner.style.display = navigator.onLine ? 'none' : 'block';
    };
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
    update();
}

// ── Service Worker registration ─────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .catch(err => console.warn('SW registration failed:', err));
    });
}

// Start Alpine
window.Alpine = Alpine;
Alpine.start();
