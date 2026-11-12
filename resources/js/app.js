import { createPopper } from "@popperjs/core";
import Alpine from "alpinejs";
import persist from "@alpinejs/persist";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.min.css";
import "./bootstrap";
import initCharts from "./components/chart";
import registerUi from "./ui";

Alpine.plugin(persist);

window.Alpine = Alpine;
window.createPopper = createPopper;
window.flatpickr = flatpickr;

// ── Global helpers available in Alpine expressions ──────────
window.formatBDT = (amount) => {
    const num = parseFloat(amount) || 0;
    return '৳' + num.toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

// Register Alpine.js components before initializing
Alpine.data("dropdown", () => ({
    open: false,
    toggle() {
        this.open = !this.open;
        if (this.open) this.position();
    },
    position() {
        this.$nextTick(() => {
            const button = this.$el;
            const dropdown = this.$refs.dropdown;
            if (!button || !dropdown) return;
            const rect = button.getBoundingClientRect();

            // Apply initial styles to ensure measurement is possible
            dropdown.style.position = "fixed";
            dropdown.style.zIndex = "999";

            const isRtl = document.documentElement.getAttribute("dir") === "rtl";
            if (isRtl) {
                dropdown.style.left = `${rect.left}px`;
                dropdown.style.right = "auto";
            } else {
                dropdown.style.right = `${window.innerWidth - rect.right - 13}px`;
                dropdown.style.left = "auto";
            }

            const dropdownHeight = Math.max(dropdown.offsetHeight, 0);
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;

            if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                dropdown.style.top = `${rect.top - dropdownHeight}px`;
            } else {
                dropdown.style.top = `${rect.bottom}px`;
            }
        });
    },
    init() {
        this.$watch("open", (value) => {
            if (value) this.position();
        });
    },
}));

registerUi(Alpine);

// Language & direction switcher store
Alpine.store("direction", {
    languages: [
        { id: "en", name: "English", shortName: "English", flag: "flag-us.svg" },
        { id: "ar", name: "Arabic (Saudi)", shortName: "Arabic", flag: "flag-sa.svg", badge: "RTL" },
        { id: "es", name: "Español", shortName: "Español", flag: "flag-es.svg" },
        { id: "de", name: "Deutsch", shortName: "Deutsch", flag: "flag-de.svg" },
    ],
    locale: localStorage.getItem("dir") === "rtl" ? "ar" : "en",
    get current() {
        return this.languages.find((l) => l.id === this.locale) || this.languages[0];
    },
    select(id) {
        this.locale = id;
        const dir = id === "ar" ? "rtl" : "ltr";
        localStorage.setItem("dir", dir);
        document.documentElement.setAttribute("dir", dir);
    },
});

// Start Alpine
Alpine.start();

// Initialize components on DOM ready
document.addEventListener("DOMContentLoaded", () => {
    // ── Flash message auto-dismiss ──────────────────────────
    const flashes = document.querySelectorAll('[data-flash-dismiss]');
    flashes.forEach(el => {
        const delay = parseInt(el.dataset.flashDismiss) || 4000;
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, delay);
    });

    // ── Offline / Online detection ──────────────────────────
    const offlineBanner = document.getElementById('offline-banner');
    if (offlineBanner) {
        const update = () => {
            offlineBanner.style.display = navigator.onLine ? 'none' : 'block';
        };
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        update();
    }

    // ── Service Worker registration ─────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .catch(err => console.warn('SW registration failed:', err));
        });
    }

    // Map imports
    if (document.querySelector("#mapOne")) {
        import("./components/maps/world-map").then((module) => module.initMap());
    }

    // Chart imports
    initCharts();

    // Calendar init
    if (document.querySelector("#calendar")) {
        import("./components/calendar/init").then((module) => module.calendarInit());
    }

    // Copy button helper
    const copyInput = document.getElementById("copy-input");
    if (copyInput) {
        const copyButton = document.getElementById("copy-button");
        const copyText = document.getElementById("copy-text");
        const websiteInput = document.getElementById("website-input");

        if (copyButton && copyText && websiteInput) {
            copyButton.addEventListener("click", () => {
                navigator.clipboard.writeText(websiteInput.value).then(() => {
                    copyText.textContent = "Copied";
                    setTimeout(() => {
                        copyText.textContent = "Copy";
                    }, 2000);
                });
            });
        }
    }
});
