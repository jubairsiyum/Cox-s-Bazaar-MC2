<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign In') — {{ config('app.name') }}</title>
    <meta name="theme-color" content="#004EFC">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style nonce="{{ Vite::cspNonce() }}">
        :root {
            --auth-brand: #004EFC;
            --auth-brand-dark: #0039c9;
            --auth-brand-light: #2f6bff;
            --auth-brand-subtle: #eaf0ff;
            --auth-page: #f4f6f8;
            --auth-surface: #ffffff;
            --auth-text: #0b0d12;
            --auth-muted: #667085;
            --auth-line: #e2e8f0;
            --auth-tile: #eaf0ff;
            --auth-card: #ffffff;
            --auth-shadow: 0 24px 60px rgba(11, 13, 18, .16);
            --auth-ring: rgba(0, 78, 252, .14);
        }

        .auth-body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--auth-page);
            color: var(--auth-text);
            font-family: 'Outfit', system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .auth-frame {
            position: relative;
            width: 100%;
            max-width: 1080px;
            border-radius: 44px;
            overflow: hidden;
            box-shadow: var(--auth-shadow);
            background: linear-gradient(to bottom, var(--auth-brand) 0 52%, var(--auth-surface) 52% 100%);
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 24px;
            padding: 56px 52px;
        }

        .auth-frame::before {
            content: '';
            position: absolute;
            top: -180px;
            right: -120px;
            width: 460px;
            height: 460px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, .16), transparent 65%);
            pointer-events: none;
        }

        .auth-left {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 36px;
            min-width: 0;
            position: relative;
            z-index: 1;
        }

        .auth-hero {
            color: #fff;
            position: relative;
            padding-right: 170px;
            animation: authFadeUp .55s cubic-bezier(.22, 1, .36, 1) both;
        }

        .auth-logo {
            width: 68px;
            height: 68px;
            display: block;
            margin-bottom: 18px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 16px;
            padding: 6px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .18);
        }

        .auth-hero h1 {
            font-size: 28px;
            font-weight: 600;
            margin: 0 0 12px;
            letter-spacing: -.01em;
            line-height: 1.25;
        }

        .auth-hero p {
            font-size: 13px;
            line-height: 1.7;
            margin: 0 0 10px;
            max-width: 410px;
            opacity: .95;
        }

        .auth-art {
            position: absolute;
            right: -8px;
            top: 70px;
            width: 200px;
            filter: drop-shadow(0 18px 18px rgba(0, 0, 0, .28));
        }

        .auth-sub {
            font-size: 13px;
            font-weight: 500;
            margin: 0 0 10px;
            color: var(--auth-text);
        }

        .auth-mods {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            max-width: 470px;
            animation: authFadeUp .55s cubic-bezier(.22, 1, .36, 1) .16s both;
        }

        .auth-mod {
            background: var(--auth-tile);
            border-radius: 12px;
            padding: 16px 4px 12px;
            text-align: center;
            font-size: 11px;
            color: var(--auth-muted);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .auth-mod:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 13, 18, .08);
        }

        .auth-mod svg {
            width: 26px;
            height: 26px;
            stroke: var(--auth-brand);
            fill: none;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
            display: block;
            margin: 0 auto 8px;
        }

        .auth-card {
            align-self: center;
            background: var(--auth-card);
            border-radius: 32px;
            padding: 34px 34px 30px;
            box-shadow: var(--auth-shadow);
            animation: authFadeUp .55s cubic-bezier(.22, 1, .36, 1) .08s both;
        }

        .auth-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 13px;
            color: var(--auth-text);
        }

        .auth-top b {
            color: var(--auth-brand);
            font-weight: 600;
        }

        .auth-top small {
            font-size: 11px;
            color: var(--auth-muted);
            text-align: right;
            line-height: 1.5;
        }

        .auth-top small a {
            color: var(--auth-brand);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-top small a:hover {
            text-decoration: underline;
        }

        .auth-card h2 {
            font-size: 28px;
            font-weight: 600;
            margin: 2px 0 10px;
            letter-spacing: -.01em;
            color: var(--auth-text);
        }

        .auth-desc {
            font-size: 13px;
            line-height: 1.6;
            color: var(--auth-muted);
            margin: 0 0 22px;
        }

        .auth-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin: 0 0 8px;
            color: var(--auth-text);
        }

        .auth-field {
            position: relative;
            margin-bottom: 18px;
        }

        .auth-input {
            width: 100%;
            height: 46px;
            border: 1px solid var(--auth-line);
            border-radius: 10px;
            padding: 0 14px;
            font: inherit;
            font-size: 13.5px;
            background: transparent;
            color: var(--auth-text);
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .auth-input::placeholder {
            color: var(--auth-muted);
        }

        .auth-input:focus-visible {
            outline: none;
            border-color: var(--auth-brand);
            box-shadow: 0 0 0 4px var(--auth-ring);
        }

        .auth-input.is-invalid {
            border-color: #d92d20;
        }

        .auth-input.is-invalid:focus-visible {
            box-shadow: 0 0 0 4px rgba(217, 45, 32, .14);
        }

        .auth-error {
            font-size: 11.5px;
            color: #b42318;
            margin-top: 6px;
        }

        .auth-eye {
            position: absolute;
            right: 8px;
            top: 36px;
            border: 0;
            background: none;
            color: var(--auth-muted);
            font: inherit;
            font-size: 11.5px;
            cursor: pointer;
            padding: 6px;
        }

        .auth-eye:hover {
            color: var(--auth-text);
        }

        .auth-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin: -4px 0 20px;
            color: var(--auth-muted);
        }

        .auth-row a {
            color: var(--auth-brand);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-row a:hover {
            text-decoration: underline;
        }

        .auth-row label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
        }

        .auth-row input {
            width: auto;
            height: auto;
            accent-color: var(--auth-brand);
            margin: 0;
        }

        .auth-btn,
        .auth-btn-secondary {
            width: 100%;
            height: 48px;
            border-radius: 10px;
            font: inherit;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
        }

        .auth-btn {
            border: 0;
            background: linear-gradient(180deg, var(--auth-brand-light), var(--auth-brand));
            color: #fff;
            box-shadow: 0 8px 18px rgba(0, 78, 252, .35);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }

        .auth-btn:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(0, 78, 252, .4);
        }

        .auth-btn:active {
            transform: translateY(0);
            box-shadow: 0 6px 14px rgba(0, 78, 252, .35);
        }

        .auth-btn-secondary {
            border: 1px solid var(--auth-line);
            background: transparent;
            color: var(--auth-text);
            transition: background .15s ease, border-color .15s ease;
        }

        .auth-btn-secondary:hover {
            background: var(--auth-page);
            border-color: var(--auth-line);
        }

        .auth-btn:focus-visible,
        .auth-btn-secondary:focus-visible,
        .auth-eye:focus-visible {
            outline: 2px solid var(--auth-text);
            outline-offset: 2px;
        }

        .auth-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .auth-alert {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            border-radius: 10px;
            padding: 11px 13px;
            font-size: 12.5px;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .auth-alert-success {
            background: #ecfdf3;
            color: #027a48;
            border: 1px solid #a6f4c5;
        }

        .auth-foot {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid var(--auth-line);
            font-size: 11.5px;
            color: var(--auth-muted);
            text-align: center;
        }

        @keyframes authFadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .auth-hero,
            .auth-mods,
            .auth-card {
                animation: none;
            }
        }

        @media (max-width: 860px) {
            .auth-body {
                padding: 12px;
            }

            .auth-frame {
                grid-template-columns: 1fr;
                padding: 32px 20px;
                border-radius: 30px;
                background: var(--auth-surface);
            }

            .auth-frame::before {
                display: none;
            }

            .auth-hero {
                background: var(--auth-brand);
                margin: -32px -20px 0;
                padding: 32px 20px 40px;
                border-radius: 0 0 30px 30px;
                padding-right: 20px;
            }

            .auth-art {
                display: none;
            }

            .auth-mods {
                display: none;
            }

            .auth-card {
                box-shadow: none;
                border: 1px solid var(--auth-line);
                padding: 26px 22px;
            }

            .auth-left {
                gap: 0;
            }
        }
    </style>
</head>
<body class="auth-body">
<main class="auth-frame">
    <section class="auth-left">
        <div class="auth-hero">
            <img class="auth-logo" src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ config('app.name') }} logo">
            <h1>{{ config('app.name') }}</h1>
            <p>A complete management system for your mobile phone business — sales, stock, purchasing and finance in one workspace.</p>
            <p>From IMEI-level inventory and POS billing to supplier payments and monthly reports, your mobile center runs on the same live numbers.</p>
            <svg class="auth-art" viewBox="0 0 200 240" aria-hidden="true">
                <g transform="rotate(8 100 120)">
                    <rect x="40" y="26" width="120" height="180" rx="16" fill="#0039c9"/>
                    <rect x="48" y="34" width="104" height="164" rx="12" fill="#004EFC"/>
                    <line x1="66" y1="64" x2="134" y2="64" stroke="#dbe7ff" stroke-width="9" stroke-linecap="round"/>
                    <line x1="66" y1="90" x2="118" y2="90" stroke="#dbe7ff" stroke-width="9" stroke-linecap="round" opacity=".7"/>
                    <line x1="66" y1="116" x2="126" y2="116" stroke="#dbe7ff" stroke-width="9" stroke-linecap="round" opacity=".7"/>
                    <line x1="66" y1="142" x2="104" y2="142" stroke="#dbe7ff" stroke-width="9" stroke-linecap="round" opacity=".5"/>
                </g>
                <g transform="rotate(-6 60 80)">
                    <rect x="24" y="50" width="88" height="88" rx="14" fill="#0b0d12"/>
                    <path d="M24 66h88M68 50v88" stroke="#1a2130" stroke-width="6"/>
                </g>
                <circle cx="152" cy="192" r="26" fill="#ffffff"/>
                <path d="M140 192l8 8 16-16" stroke="#004EFC" stroke-width="6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <div>
            <p class="auth-sub">Key modules</p>
            <div class="auth-mods">
                <div class="auth-mod">
                    <svg viewBox="0 0 24 24"><path d="M4 7h16l-1.5 8.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 7z"/><path d="M8 11V6a4 4 0 018 0v5"/></svg>
                    Sales
                </div>
                <div class="auth-mod">
                    <svg viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8"/></svg>
                    Inventory
                </div>
                <div class="auth-mod">
                    <svg viewBox="0 0 24 24"><path d="M9 3h6a1 1 0 011 1v1h2a1 1 0 011 1v14a1 1 0 01-1 1H6a1 1 0 01-1-1V6a1 1 0 011-1h2V4a1 1 0 011-1z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                    Purchasing
                </div>
                <div class="auth-mod">
                    <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.8a3.5 3.5 0 010 6.4M18 14.4c2 .8 3.5 2.6 3.5 5.6"/></svg>
                    Customers
                </div>
                <div class="auth-mod">
                    <svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                    Finance
                </div>
            </div>
        </div>
    </section>

    <section class="auth-card">
        @yield('content')

        <div class="auth-foot">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </section>
</main>

<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.getAttribute('data-password-toggle'));
                if (!input) {
                    return;
                }
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Hide' : 'Show';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        });
    })();
</script>

@stack('scripts')
</body>
</html>
