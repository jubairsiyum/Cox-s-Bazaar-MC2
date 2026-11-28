import { createPopper } from "@popperjs/core";
import Alpine from "alpinejs";
import persist from "@alpinejs/persist";
import "./bootstrap";
import initCharts from "./components/chart";
import registerUi from "./ui";
import { initThemedSelects } from "./ui/themed-select";
import { registerMotion, startMotion, themeSwitch } from "./motion";
import errorScene from "./error-scene";
import { loginForm, authStage } from "./auth";

Alpine.plugin(persist);

window.Alpine = Alpine;
window.createPopper = createPopper;
window.loadFlatpickr = async () => {
    const [{ default: flatpickr }] = await Promise.all([
        import("flatpickr"),
        import("flatpickr/dist/flatpickr.min.css"),
    ]);
    window.flatpickr = flatpickr;
    return flatpickr;
};

// ── Global helpers available in Alpine expressions ──────────
window.formatBDT = (amount) => {
    const raw = String(amount ?? "0").replace(/[৳,\s]/g, "");
    const num = Number.isFinite(Number(raw)) ? Number(raw) : 0;
    const formatted = Math.abs(num).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        useGrouping: true,
    });
    return `${num < 0 ? '-' : ''}৳${formatted}`;
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

// Toast notification store
Alpine.store("toast", {
    items: [],
    show(message, type = "success", duration = 4000) {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });

        if (duration) {
            setTimeout(() => this.remove(id), duration);
        }
    },
    remove(id) {
        this.items = this.items.filter((toast) => toast.id !== id);
    },
});

// Toast host component: seeds the store from server flash data.
Alpine.data("toastHost", () => ({
    init() {
        (window.flashToasts || []).forEach((toast) => {
            Alpine.store("toast").show(toast.message, toast.type, toast.duration);
        });
    },
}));

// Barcode field: receives scans from hardware scanners and fills the input.
Alpine.data("barcodeField", () => ({
    lastScan: "",
    scanning: false,

    init() {
        // Focus the input so a USB/Bluetooth keyboard-wedge scanner types into it.
        this.$nextTick(() => this.focus());
    },

    startScan() {
        this.scanning = true;
        this.focus();
    },

    applyScan(code) {
        if (!code) {
            return;
        }
        if (this.$refs.input) {
            this.$refs.input.value = code;
        }
        this.lastScan = code;
        this.scanning = false;
        Alpine.store("toast")?.show("Barcode scanned: " + code, "success", 2500);
    },

    focus() {
        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
    },
}));

// Theme store (Multi-theme + Dark mode)
const THEMES = ['swift', 'ocean', 'aurora', 'ember', 'emerald', 'graphite'];
const DEFAULT_THEME = 'swift';
const read  = (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } };
const write = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
const notify = () => window.dispatchEvent(new CustomEvent('theme-changed'));

Alpine.store('theme', {
  dark: document.documentElement.classList.contains('dark'),
  toggle() {
    themeSwitch(() => {
      this.dark = !this.dark;
      document.documentElement.classList.toggle('dark', this.dark);
      write('theme', this.dark ? 'dark' : 'light');
      notify();
    });
  },
  setDark(isDark) {
    if (this.dark === isDark) return;
    themeSwitch(() => {
      this.dark = isDark;
      document.documentElement.classList.toggle('dark', isDark);
      write('theme', isDark ? 'dark' : 'light');
      notify();
    });
  },
  themes: THEMES,
  name: document.documentElement.dataset.theme || DEFAULT_THEME,
  setTheme(n) {
    if (!THEMES.includes(n)) return;
    themeSwitch(() => {
      this.name = n;
      document.documentElement.dataset.theme = n;
      write('color-theme', n);
      notify();
    });
  },
});

// Collapsible desktop sidebar + mobile drawer store
const syncSidebarAttr = (expanded) => {
  document.documentElement.setAttribute('data-sidebar', expanded ? 'expanded' : 'collapsed');
};

const readSidebarExpanded = () => {
  const saved = read('sidebarExpanded');
  return saved === null ? true : saved === 'true';
};

Alpine.store('sidebar', {
  isExpanded: window.innerWidth >= 1024 ? readSidebarExpanded() : false,
  isMobileOpen: false,
  isHovered: false,
  _hoverTimer: null,

  init() {
    this.syncFromViewport();
    window.addEventListener('resize', () => this.syncFromViewport());
  },

  syncFromViewport() {
    if (window.innerWidth >= 1024) {
      this.isMobileOpen = false;
      this.isExpanded = readSidebarExpanded();
      syncSidebarAttr(this.isExpanded);
    } else {
      this.isHovered = false;
      this.isExpanded = false;
      syncSidebarAttr(false);
    }
  },

  toggleExpanded() {
    if (window.innerWidth < 1024) {
      this.toggleMobileOpen();
      return;
    }
    clearTimeout(this._hoverTimer);
    this.isExpanded = !this.isExpanded;
    this.isHovered = false;
    this.isMobileOpen = false;
    write('sidebarExpanded', this.isExpanded ? 'true' : 'false');
    syncSidebarAttr(this.isExpanded);
  },

  setExpanded(val) {
    clearTimeout(this._hoverTimer);
    this.isExpanded = !!val;
    this.isHovered = false;
    this.isMobileOpen = false;
    if (window.innerWidth >= 1024) {
      write('sidebarExpanded', this.isExpanded ? 'true' : 'false');
      syncSidebarAttr(this.isExpanded);
    }
  },

  toggleMobileOpen() {
    this.isMobileOpen = !this.isMobileOpen;
  },

  setMobileOpen(val) {
    this.isMobileOpen = !!val;
  },

  setHovered(val) {
    if (window.innerWidth < 1024 || this.isExpanded) {
      this.isHovered = false;
      return;
    }
    clearTimeout(this._hoverTimer);
    if (val) {
      this.isHovered = true;
    } else {
      this._hoverTimer = setTimeout(() => {
        this.isHovered = false;
      }, 60);
    }
  },
});

registerMotion(Alpine);
startMotion();

Alpine.data("errorScene", errorScene);
Alpine.data("loginForm", loginForm);
Alpine.data("authStage", authStage);

// Start Alpine
Alpine.start();

// Global keyboard-wedge barcode scanner capture. USB and Bluetooth scanners
// used in Bangladesh behave like a keyboard: they type the code in a fast burst
// and end with Enter. This listener detects that burst and routes it to the
// product barcode field regardless of focus.
function initBarcodeScanner() {
    let buffer = "";
    let lastKeyTime = 0;
    let flushTimer = null;

    const flush = () => {
        const code = buffer;
        buffer = "";
        if (code.length >= 4) {
            window.dispatchEvent(new CustomEvent("barcode-scanned", { detail: { code } }));
        }
    };

    document.addEventListener("keydown", (event) => {
        const isBarcodeInput = event.target && event.target.id === "barcode";

        if (event.key === "Enter") {
            if (buffer.length >= 4) {
                event.preventDefault();
                event.stopPropagation();
                flush();
            }
            return;
        }

        // Only printable characters without modifier keys.
        if (event.key.length !== 1 || event.ctrlKey || event.altKey || event.metaKey) {
            buffer = "";
            return;
        }

        const now = Date.now();
        const rapid = now - lastKeyTime <= 40;

        if (!rapid) {
            buffer = "";
        }

        buffer += event.key;
        lastKeyTime = now;

        // Keep a detected scan from leaking into a non-barcode field. When the
        // barcode input itself is focused, let the keys flow through naturally.
        if (buffer.length >= 2 && !isBarcodeInput) {
            event.preventDefault();
            event.stopPropagation();
        }

        clearTimeout(flushTimer);
        flushTimer = setTimeout(flush, 100);
    }, true);
}

// Initialize components on DOM ready
document.addEventListener("DOMContentLoaded", () => {
    initThemedSelects();
    initBarcodeScanner();

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

    // Chart imports
    initCharts();

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
