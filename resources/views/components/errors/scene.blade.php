@props(['code' => 500, 'emblem' => 'alert', 'label' => 'Error'])

{{-- Original isometric scene. Colors come only from theme tokens via error-scene.css. --}}
<svg class="es-svg" viewBox="0 0 640 490" role="img" aria-label="{{ $label }}" data-emblem="{{ $emblem }}" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <radialGradient id="es-glow"><stop offset="0" class="es-glow-a"/><stop offset="1" class="es-glow-b"/></radialGradient>
    <linearGradient id="es-beam" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="es-beam-a"/><stop offset="1" class="es-beam-b"/></linearGradient>
    <text id="es-t" transform="matrix(0.866 -0.5 0 1 150.3 340)" x="0" y="0" font-size="142" font-weight="900" textLength="262" lengthAdjust="spacingAndGlyphs" style="font-family:var(--font-sans,ui-sans-serif,system-ui,sans-serif)">{{ $code }}</text>
    <symbol id="es-cube" overflow="visible">
      <polygon class="es-cl" points="-13.9,-8.0 0.0,0.0 0.0,-16.0 -13.9,-24.0"/>
      <polygon class="es-cr" points="0.0,0.0 13.9,-8.0 13.9,-24.0 0.0,-16.0"/>
      <polygon class="es-ct" points="0.0,-16.0 13.9,-24.0 0.0,-32.0 -13.9,-24.0"/>
    </symbol>
  </defs>

  <g>
    <g class="es-platform">
      <ellipse cx="320" cy="481" rx="216" ry="18" class="es-shadow"/>
      <polygon class="es-plat-l" points="50.0,320.0 320.0,455.0 320.0,469.0 50.0,334.0"/>
      <polygon class="es-plat-r" points="320.0,455.0 590.0,320.0 590.0,334.0 320.0,469.0"/>
      <polygon class="es-plat-top" points="320.0,185.0 590.0,320.0 320.0,455.0 50.0,320.0"/>
      <ellipse cx="320" cy="320" rx="167" ry="84" fill="url(#es-glow)"/>
      <path class="es-grid" d="M83.8 303.1L353.8 438.1M353.8 201.9L83.8 336.9M117.5 286.2L387.5 421.2M387.5 218.8L117.5 353.8M151.2 269.4L421.2 404.4M421.2 235.6L151.2 370.6M185 252.5L455 387.5M455 252.5L185 387.5M218.8 235.6L488.8 370.6M488.8 269.4L218.8 404.4M252.5 218.8L522.5 353.8M522.5 286.2L252.5 421.2M286.2 201.9L556.2 336.9M556.2 303.1L286.2 438.1"/>
    </g>
    <g class="es-traces">
      <path class="es-trace-base" d="M261.1 310 L359.8 367 L283.6 411 L352.9 451"/>
      <path class="es-trace" d="M261.1 310 L359.8 367 L283.6 411 L352.9 451"/>
      <circle class="es-node" cx="352.9" cy="451" r="4.5"/>
      <path class="es-trace-base" d="M311.3 281 L432.6 351 L493.2 316 L555.6 352"/>
      <path class="es-trace" d="M311.3 281 L432.6 351 L493.2 316 L555.6 352"/>
      <circle class="es-node" cx="555.6" cy="352" r="4.5"/>
    </g>
    <g aria-hidden="true"><ellipse class="es-ripple" style="--rd:0s" cx="266.3" cy="289" rx="150" ry="75"/><ellipse class="es-ripple" style="--rd:1.6s" cx="266.3" cy="289" rx="150" ry="75"/></g>
    <g class="es-slab">
      <polygon class="es-shadow es-shadow-pulse" points="167.6,364.0 396.2,232.0 436.0,255.0 207.4,387.0"/>
      <polygon class="es-slab-r" points="167.6,350.0 167.6,364.0 396.2,232.0 396.2,218.0"/>
      <polygon class="es-slab-l" points="132.9,330.0 132.9,344.0 167.6,364.0 167.6,350.0"/>
      <polygon class="es-slab-top" points="132.9,330.0 361.6,198.0 396.2,218.0 167.6,350.0"/>
    </g>
    <g class="es-beam-in"><polygon class="es-beam" fill="url(#es-beam)" points="142.0,188.0 210.0,188.0 268.0,356.0 84.0,356.0"/></g>
    <g class="es-digits"><g class="es-float">
        <use href="#es-t" class="es-layer es-back" style="--dx:-15.59px;--dy:-9.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-14.72px;--dy:-8.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-13.86px;--dy:-8.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-12.99px;--dy:-7.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-12.12px;--dy:-7.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-11.26px;--dy:-6.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-10.39px;--dy:-6.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-9.53px;--dy:-5.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-8.66px;--dy:-5.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-7.79px;--dy:-4.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-6.93px;--dy:-4.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-6.06px;--dy:-3.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-5.20px;--dy:-3.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-4.33px;--dy:-2.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-3.46px;--dy:-2.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-2.60px;--dy:-1.50px"/><use href="#es-t" class="es-layer es-back" style="--dx:-1.73px;--dy:-1.00px"/><use href="#es-t" class="es-layer es-back" style="--dx:-0.87px;--dy:-0.50px"/>
        <use href="#es-t" class="es-front es-digits-front"/>
      </g></g>
    <g class="es-cubes"><g transform="translate(505 352)"><g class="es-float2"><use href="#es-cube" class="es-cube-tone" transform="scale(1.5)"/></g></g><g transform="translate(92 334)"><g class="es-float3"><use href="#es-cube" class="es-cube-light" transform="scale(1.05)"/></g></g><g transform="translate(424 394)"><g class="es-float"><use href="#es-cube" class="es-cube-light" transform="scale(1.0)"/></g></g></g>
    <g aria-hidden="true"><g transform="translate(130 300)"><g class="es-particle-g" style="--pd:0s"><path class="es-particle" d="M0 -4 L4 0 L0 4 L-4 0Z"/></g></g><g transform="translate(470 300)"><g class="es-particle-g" style="--pd:1.1s"><path class="es-particle" d="M0 -4 L4 0 L0 4 L-4 0Z"/></g></g><g transform="translate(300 420)"><g class="es-particle-g" style="--pd:2.2s"><path class="es-particle" d="M0 -4 L4 0 L0 4 L-4 0Z"/></g></g><g transform="translate(380 250)"><g class="es-particle-g" style="--pd:3.3s"><path class="es-particle" d="M0 -4 L4 0 L0 4 L-4 0Z"/></g></g><g transform="translate(240 260)"><g class="es-particle-g" style="--pd:0.7s"><path class="es-particle" d="M0 -4 L4 0 L0 4 L-4 0Z"/></g></g><g transform="translate(520 330)"><g class="es-particle-g" style="--pd:2.8s"><path class="es-particle" d="M0 -4 L4 0 L0 4 L-4 0Z"/></g></g><g transform="translate(180 380)"><g class="es-particle-g" style="--pd:1.9s"><path class="es-particle" d="M0 -4 L4 0 L0 4 L-4 0Z"/></g></g></g>
    <g transform="translate(176 130)">
      <g class="es-emblem-in"><g class="es-float2">
        <g class="es-orbit">
          <circle cy="9" r="56" class="es-coin-side"/>
          <circle r="56" class="es-coin-face"/>
          <g class="es-spin-rev"><circle r="47" class="es-coin-ring"/></g>
          
        <g class="es-emb es-e-radar">
          <circle r="12" class="es-stroke" stroke-width="1.6" opacity=".4"/>
          <circle r="23" class="es-stroke" stroke-width="1.6" opacity=".4"/>
          <circle r="34" class="es-stroke" stroke-width="2.2" opacity=".7"/>
          <line x1="-34" y1="0" x2="34" y2="0" class="es-stroke" stroke-width="1" opacity=".3"/>
          <line x1="0" y1="-34" x2="0" y2="34" class="es-stroke" stroke-width="1" opacity=".3"/>
          <g class="es-spin"><circle r="34" fill="none"/><path class="es-wedge" d="M0 0 L34 0 A34 34 0 0 0 19.5 -27.9 Z" transform="scale(1,-1) rotate(0)"/><line x1="0" y1="0" x2="34" y2="0" class="es-stroke" stroke-width="2.4"/></g>
          <circle cx="15" cy="-13" r="3.6" class="es-fill"/><circle cx="15" cy="-13" r="3.6" class="es-fill es-blip"/>
        </g>
        <g class="es-emb es-e-lock">
          <path class="es-stroke es-shackle" stroke-width="7" d="M-12 -4 V-14 a12 12 0 0 1 24 0 V-4"/>
          <rect x="-22" y="-5" width="44" height="34" rx="8" class="es-fill"/>
          <circle cx="0" cy="9" r="5" class="es-face"/><rect x="-2.4" y="11" width="4.8" height="9" rx="2" class="es-face"/>
        </g>
        <g class="es-emb es-e-cog">
          <g class="es-spin"><path d="M24.3 -6 L32.7 -4.6 L32.7 4.6 L24.3 6 L21.4 12.9 L26.4 19.9 L19.9 26.4 L12.9 21.4 L6 24.3 L4.6 32.7 L-4.6 32.7 L-6 24.3 L-12.9 21.4 L-19.9 26.4 L-26.4 19.9 L-21.4 12.9 L-24.3 6 L-32.7 4.6 L-32.7 -4.6 L-24.3 -6 L-21.4 -12.9 L-26.4 -19.9 L-19.9 -26.4 L-12.9 -21.4 L-6 -24.3 L-4.6 -32.7 L4.6 -32.7 L6 -24.3 L12.9 -21.4 L19.9 -26.4 L26.4 -19.9 L21.4 -12.9Z" class="es-fill"/><circle r="11" class="es-face"/></g>
          <path d="M3 -9 L-5 2 H0 L-3 10 L6 -2 H1 Z" class="es-fill"/>
        </g>
        <g class="es-emb es-e-clock">
          <circle r="32" class="es-stroke" stroke-width="5"/>
          <line x1="0" y1="-26" x2="0" y2="-22" transform="rotate(0)" class="es-stroke" stroke-width="2.4"/><line x1="0" y1="-26" x2="0" y2="-22" transform="rotate(90)" class="es-stroke" stroke-width="2.4"/><line x1="0" y1="-26" x2="0" y2="-22" transform="rotate(180)" class="es-stroke" stroke-width="2.4"/><line x1="0" y1="-26" x2="0" y2="-22" transform="rotate(270)" class="es-stroke" stroke-width="2.4"/>
          <g class="es-spin-slow"><circle r="19" fill="none"/><line x1="0" y1="0" x2="0" y2="-18" class="es-stroke" stroke-width="4"/></g>
          <g class="es-spin" style="animation-duration:3s"><circle r="25" fill="none"/><line x1="0" y1="0" x2="0" y2="-25" class="es-stroke" stroke-width="2.4"/></g>
          <circle r="3.4" class="es-fill"/>
        </g>
        <g class="es-emb es-e-gauge">
          <path d="M-34 16 A34 34 0 0 1 34 16" pathLength="100" class="es-track" stroke-width="9"/>
          <path d="M-34 16 A34 34 0 0 1 34 16" pathLength="100" class="es-stroke" stroke-width="9" stroke-dasharray="72 100"/>
          <line x1="0" y1="16" x2="0" y2="-24" class="es-stroke es-needle" stroke-width="4"/>
          <circle cx="0" cy="16" r="6" class="es-fill"/>
        </g>
        <g class="es-emb es-e-tool">
          <g transform="rotate(-45)"><circle cy="-20" r="15" class="es-fill"/><rect x="-6.5" y="-38" width="13" height="16" rx="2" class="es-face"/><rect x="-5.5" y="-10" width="11" height="46" rx="5.5" class="es-fill"/></g>
          <rect x="-26" y="30" width="52" height="5" rx="2.5" class="es-track"/><rect x="-26" y="30" width="52" height="5" rx="2.5" class="es-fill es-prog-bar"/>
        </g>
        <g class="es-emb es-e-ban">
          <circle r="29" class="es-stroke" stroke-width="7"/>
          <line x1="-20" y1="-20" x2="20" y2="20" class="es-stroke" stroke-width="7"/>
        </g>
        <g class="es-emb es-e-alert">
          <path d="M0 -31 L31 23 H-31 Z" class="es-fill" stroke="var(--es-tone)" stroke-width="6" stroke-linejoin="round"/>
          <rect x="-3" y="-12" width="6" height="19" rx="3" class="es-face"/><circle cy="15" r="3.6" class="es-face"/>
        </g>
        </g>
        <g transform="rotate(-14)">
          <ellipse rx="92" ry="30" class="es-orbit-ring"/>
          <g class="es-ox" style="--od:0s"><g class="es-oy"><use href="#es-cube" class="es-cube-tone" transform="translate(0 9) scale(.55)"/></g></g>
          <g class="es-ox" style="--od:-2.8s"><g class="es-oy"><use href="#es-cube" class="es-cube-light" transform="translate(0 9) scale(.5)"/></g></g>
          <g class="es-ox" style="--od:-5.6s"><g class="es-oy"><use href="#es-cube" class="es-cube-tone" transform="translate(0 8) scale(.38)"/></g></g>
        </g>
      </g></g>
    </g>
  </g>
</svg>
