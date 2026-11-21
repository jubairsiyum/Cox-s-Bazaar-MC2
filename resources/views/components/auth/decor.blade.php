{{--
  Page decoration. Every shape is a REAL element (div / svg), not a background image, so it follows the
  active theme + dark mode and can be animated or removed individually. Purely visual: aria-hidden.
  Parallax: `.au-par` + --dx/--dy (px at full pointer deflection). Idle drift: `.au-fl`.
--}}
<div class="au-decor" aria-hidden="true">
    {{-- big sweeping shapes --}}
    <div class="au-par" style="--dx:-6;--dy:-4;right:-14vmax;top:-26vmax;width:70vmax;height:70vmax"><div class="au-sweep" style="width:100%;height:100%"></div></div>
    <div class="au-par" style="--dx:8;--dy:6;right:-30vmax;bottom:-42vmax;width:84vmax;height:84vmax"><div class="au-sweep au-sweep-b" style="width:100%;height:100%"></div></div>
    <div class="au-par" style="--dx:10;--dy:8;left:-4vmax;bottom:-5vmax;width:min(22vmax,230px);height:min(22vmax,230px)"><div class="au-sweep au-fl au-fl-b" style="width:100%;height:100%;opacity:.3"></div></div>

    {{-- dot grids --}}
    <div class="au-par" style="--dx:-8;--dy:-6;left:3.5vw;top:12vh">
        <svg class="au-dotgrid" width="270" height="190" viewBox="0 0 270 190"><defs><pattern id="au-dots-a" width="25" height="25" patternUnits="userSpaceOnUse"><circle class="au-dots" cx="3" cy="3" r="3" style="fill:var(--au-on)"/></pattern></defs><rect width="270" height="190" fill="url(#au-dots-a)"/></svg>
    </div>
    <div class="au-par hidden md:block" style="--dx:8;--dy:6;right:3vw;bottom:4vh">
        <svg class="au-dotgrid" width="270" height="130" viewBox="0 0 270 130"><defs><pattern id="au-dots-b" width="25" height="25" patternUnits="userSpaceOnUse"><circle cx="3" cy="3" r="3" style="fill:var(--au-on)"/></pattern></defs><rect width="270" height="130" fill="url(#au-dots-b)"/></svg>
    </div>
    <div class="au-par hidden lg:block" style="--dx:6;--dy:-8;right:2.6vw;top:56vh">
        <svg class="au-dotgrid" width="110" height="210" viewBox="0 0 110 210"><defs><pattern id="au-dots-c" width="25" height="25" patternUnits="userSpaceOnUse"><circle cx="3" cy="3" r="3" style="fill:var(--au-on)"/></pattern></defs><rect width="110" height="210" fill="url(#au-dots-c)"/></svg>
    </div>

    {{-- accent triangles + bars --}}
    <div class="au-par" style="--dx:-14;--dy:-10;left:3.4vw;top:3vh"><svg class="au-fl" width="34" height="38" viewBox="0 0 34 38"><path class="au-tri" d="M2 2l30 17L2 36z"/></svg></div>
    <div class="au-par hidden sm:block" style="--dx:12;--dy:10;left:30vw;bottom:2.5vh"><svg class="au-fl au-fl-b" style="--d:-3s" width="34" height="38" viewBox="0 0 34 38"><path class="au-tri" d="M2 2l30 17L2 36z"/></svg></div>
    <div class="au-par hidden sm:block" style="--dx:-10;--dy:6;left:27.2vw;bottom:6vh"><svg class="au-spin" width="60" height="60" viewBox="0 0 60 60"><rect class="au-dash" x="8" y="8" width="44" height="44"/></svg></div>
    <div class="au-par hidden md:block" style="--dx:-12;--dy:8;left:2vw;top:41vh"><svg class="au-fl" style="--d:-2s" width="90" height="46" viewBox="0 0 90 46"><path class="au-bar" d="M10 2h78L78 22 2 44z" style="opacity:.95"/></svg></div>
    <div class="au-par hidden md:block" style="--dx:10;--dy:-6;right:0;top:57vh"><svg class="au-fl au-fl-b" style="--d:-5s" width="86" height="44" viewBox="0 0 86 44"><path class="au-bar" d="M2 20l66-18 16 20-72 20z"/></svg></div>

    {{-- balls --}}
    <div class="au-par" style="--dx:-10;--dy:-8;left:53vw;top:5vh"><div class="au-ball au-fl" style="width:32px;height:32px;opacity:.35"></div></div>
    <div class="au-par hidden sm:block" style="--dx:10;--dy:4;right:1.6vw;top:41vh"><div class="au-ball" style="width:14px;height:14px"></div></div>
    <div class="au-par" style="--dx:12;--dy:10;right:7vw;bottom:3vh"><div class="au-ball au-fl au-fl-b" style="width:30px;height:30px;--d:-4s"></div></div>
</div>
