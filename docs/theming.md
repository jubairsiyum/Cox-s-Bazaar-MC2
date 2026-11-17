# Multi-Theme Architecture & Guide

This application uses a centralized, token-based multi-theme system built with **Tailwind CSS v4** (CSS-first configuration) and **Alpine.js v3**.

Every color in the UI is derived from semantic CSS tokens. Light mode, dark mode, and custom color themes are completely decoupled from individual templates.

---

## Token Contract

Every theme must define the following tokens for both Light and Dark modes:

| Category | Token | Description |
|---|---|---|
| **Surfaces** | `--canvas` | Background behind the app |
| | `--shell` | Outer container background |
| | `--surface` | Cards, panels, dropdowns, modals |
| | `--soft` | Subtle highlight (active pills, chips, muted tracks) |
| | `--line` | Borders, dividers, subtle outlines |
| **Text** | `--ink` | Primary text and headings |
| | `--muted` | Secondary text, icons, placeholders |
| | `--on-brand` | Text/icons displayed on top of brand backgrounds |
| **Brand** | `--brand-50` / `--brand-100` | Soft brand tints |
| | `--brand-400` | Secondary / chart accent |
| | `--brand-500` | Primary action / button / active link color |
| | `--brand-600` | Hover / pressed primary color |
| **Status** | `--danger` | Negative, error, decrease alerts |
| | `--success` | Positive, complete, increase status |
| | `--warning` | Caution, attention, pending status |
| **Effects** | `--overlay` | Modal & mobile backdrop tint |
| | `--shadow-rgb` | R G B integers for card box-shadow |
| **Charts** | `--chart-1` .. `--chart-4` | Dynamic chart series colors |

---

## How to Add a New Theme

Adding a new theme requires **zero template changes**. Simply follow these steps:

### 1. Create the Theme CSS File
Copy `resources/css/themes/swift.css` to `resources/css/themes/<name>.css` (e.g., `emerald.css`).

### 2. Update Selectors
Replace `swift` with your theme name in the selectors and **remove** `:root:not([data-theme])` (only the default theme keeps it):

```css
/* Theme: emerald */
[data-theme="emerald"] {
  --canvas:  #ecfdf5;
  --shell:   #f0fdf4;
  --surface: #ffffff;
  --soft:    #d1fae5;
  --line:    #e2e8f0;

  --ink:      #064e3b;
  --muted:    #047857;
  --on-brand: #ffffff;

  --brand-50:  #ecfdf5;
  --brand-100: #d1fae5;
  --brand-400: #34d399;
  --brand-500: #059669;
  --brand-600: #047857;

  --danger:  #ff5a36;
  --success: #10b981;
  --warning: #f59e0b;

  --overlay:    rgb(6 78 59 / 0.45);
  --shadow-rgb: 6 78 59;

  --chart-1: #059669;
  --chart-2: #34d399;
  --chart-3: #10b981;
  --chart-4: #f59e0b;
}

[data-theme="emerald"].dark {
  --canvas:  #061712;
  --shell:   #0b231b;
  --surface: #133328;
  --soft:    #184234;
  --line:    #1c4738;

  --ink:      #ecfdf5;
  --muted:    #86efac;
  --on-brand: #022c22;

  --brand-50:  #133328;
  --brand-100: #184234;
  --brand-400: #34d399;
  --brand-500: #10b981;
  --brand-600: #059669;

  --danger:  #ff7a5c;
  --success: #34d399;
  --warning: #fbbf24;

  --overlay:    rgb(0 0 0 / 0.6);
  --shadow-rgb: 0 0 0;

  --chart-1: #10b981;
  --chart-2: #34d399;
  --chart-3: #86efac;
  --chart-4: #fbbf24;
}
```

### 3. Register Import in `app.css`
Add one line to `resources/css/app.css`:
```css
@import "./themes/swift.css";
@import "./themes/emerald.css";
```

### 4. Register Theme in `app.js` and Head Script
In `resources/js/app.js`:
```javascript
const THEMES = ['swift', 'emerald'];
```

In the `<head>` script of layouts (`app.blade.php`, `auth.blade.php`, etc.):
```javascript
var allowed = ['swift', 'emerald'];
```

### 5. Verify Accessibility & Contrast
Ensure color combinations meet WCAG standards:
- Normal text (`text-ink`) vs. card background (`bg-surface`): Contrast ratio **≥ 4.5:1**.
- Brand link/button text (`text-brand-500`) on soft background (`bg-soft`): **≥ 4.5:1**.
- Button text (`text-on-brand`) on primary brand button (`bg-brand-500`): **≥ 3:1** for bold/large text.

---

## Developer Golden Rules

1. **Never use color literals outside `themes/*.css`**: No `#hex`, `rgb()`, `hsl()`, or `oklch()` in templates, JS, or CSS.
2. **Never use raw Tailwind palette classes**: Do not write `bg-blue-600`, `text-gray-500`, `border-slate-200`, etc. Always use semantic token utilities: `bg-brand-500`, `text-muted`, `border-line`.
3. **Do not duplicate tokens with `dark:` variants**: Semantic tokens automatically switch their value when `.dark` is present on the root `<html>`. Do not write `bg-white dark:bg-gray-800` — write `bg-surface`.
4. **If a new semantic color is required**: Add the token variable to **every** theme file in both light and dark, and map it into the `@theme inline` block in `resources/css/app.css`.
