@php
$themeOptions = [
    [
        'id' => 'swift',
        'name' => 'Swift',
        'badge' => 'Default',
        'tagline' => 'Electric Royal Blue',
        'description' => 'Crisp, high-contrast enterprise design engineered for maximum readability, focus, and rapid data scanning.',
        'primary' => '#2166ee',
        'primaryDark' => '#5b8dff',
        'canvas' => '#e2eaf7',
        'canvasDark' => '#0a0d1a',
        'shell' => '#f6f7fb',
        'shellDark' => '#10142a',
        'surface' => '#ffffff',
        'surfaceDark' => '#171c36',
        'soft' => '#e8f0fe',
        'softDark' => '#1f2b5c',
        'line' => '#eef0f6',
        'lineDark' => '#252b4d',
        'ink' => '#0f1b4d',
        'inkDark' => '#eef1ff',
        'charts' => ['#2166ee', '#ff5a36', '#16a34a', '#f59e0b'],
        'chartsDark' => ['#5b8dff', '#ff7a5c', '#34d399', '#fbbf24'],
    ],
    [
        'id' => 'ocean',
        'name' => 'Ocean',
        'badge' => 'Teal & Cyan',
        'tagline' => 'Maritime Cyan & Deep Teal',
        'description' => 'Cool maritime cyan paired with deep teal, creating a balanced, tranquil, and trustworthy interface.',
        'primary' => '#07739a',
        'primaryDark' => '#38c8ea',
        'canvas' => '#dcedf2',
        'canvasDark' => '#04121a',
        'shell' => '#f2f9fb',
        'shellDark' => '#081a24',
        'surface' => '#ffffff',
        'surfaceDark' => '#0d2634',
        'soft' => '#dff3f8',
        'softDark' => '#123a4d',
        'line' => '#e1edf1',
        'lineDark' => '#17384a',
        'ink' => '#08293a',
        'inkDark' => '#e6f8ff',
        'charts' => ['#07739a', '#ef5a3c', '#6d4be8', '#b7791f'],
        'chartsDark' => ['#38c8ea', '#ff8a6b', '#a78bfa', '#fbbf24'],
    ],
    [
        'id' => 'aurora',
        'name' => 'Aurora',
        'badge' => 'Violet SaaS',
        'tagline' => 'Celestial Violet & Purple',
        'description' => 'Vibrant violet and celestial purple tailored for modern analytics, creative energy, and premium SaaS feel.',
        'primary' => '#6334e0',
        'primaryDark' => '#a184ff',
        'canvas' => '#e9e6f7',
        'canvasDark' => '#0b0818',
        'shell' => '#f7f6fc',
        'shellDark' => '#110d24',
        'surface' => '#ffffff',
        'surfaceDark' => '#1a1535',
        'soft' => '#efe9fe',
        'softDark' => '#2a2260',
        'line' => '#ece9f6',
        'lineDark' => '#2b2552',
        'ink' => '#1b1440',
        'inkDark' => '#f1eeff',
        'charts' => ['#6334e0', '#ef5a3c', '#12a594', '#b7791f'],
        'chartsDark' => ['#a184ff', '#ff8a6b', '#2dd4bf', '#fbbf24'],
    ],
    [
        'id' => 'ember',
        'name' => 'Ember',
        'badge' => 'Warm Sunset',
        'tagline' => 'Terracotta & Sunset Orange',
        'description' => 'Rich terracotta and warm sunset orange bringing punch, confidence, and warmth to everyday operations.',
        'primary' => '#c2410c',
        'primaryDark' => '#ff8a3d',
        'canvas' => '#f4e8de',
        'canvasDark' => '#120a05',
        'shell' => '#fcf7f2',
        'shellDark' => '#1a0f08',
        'surface' => '#ffffff',
        'surfaceDark' => '#25170d',
        'soft' => '#fdeee0',
        'softDark' => '#3b2412',
        'line' => '#f3e8de',
        'lineDark' => '#3a2616',
        'ink' => '#2b1708',
        'inkDark' => '#fff1e6',
        'charts' => ['#c2410c', '#2563eb', '#16803c', '#b7791f'],
        'chartsDark' => ['#ff8a3d', '#60a5fa', '#4ade80', '#fbbf24'],
    ],
    [
        'id' => 'emerald',
        'name' => 'Emerald',
        'badge' => 'Growth & Finance',
        'tagline' => 'Natural Mint & Forest Green',
        'description' => 'Natural deep forest tones and fresh mint accents, ideal for inventory management and financial tracking.',
        'primary' => '#097852',
        'primaryDark' => '#34d399',
        'canvas' => '#e1f0ea',
        'canvasDark' => '#06110d',
        'shell' => '#f3faf6',
        'shellDark' => '#0a1914',
        'surface' => '#ffffff',
        'surfaceDark' => '#10241d',
        'soft' => '#dff4ea',
        'softDark' => '#17392d',
        'line' => '#e4efe9',
        'lineDark' => '#1d3a2f',
        'ink' => '#0a2a21',
        'inkDark' => '#e8fbf3',
        'charts' => ['#097852', '#ef5a3c', '#2f6fe4', '#b7791f'],
        'chartsDark' => ['#34d399', '#ff8a6b', '#60a5fa', '#fbbf24'],
    ],
    [
        'id' => 'graphite',
        'name' => 'Graphite',
        'badge' => 'Monochrome Minimal',
        'tagline' => 'Deep Carbon & High Contrast',
        'description' => 'Sleek, distraction-free monochrome styling inspired by Linear, Raycast, and luxury developer tooling.',
        'primary' => '#18181b',
        'primaryDark' => '#fafafa',
        'canvas' => '#e6e6e9',
        'canvasDark' => '#09090b',
        'shell' => '#f5f5f6',
        'shellDark' => '#0f0f12',
        'surface' => '#ffffff',
        'surfaceDark' => '#18181b',
        'soft' => '#ececee',
        'softDark' => '#27272a',
        'line' => '#ececef',
        'lineDark' => '#2a2a2f',
        'ink' => '#111113',
        'inkDark' => '#fafafa',
        'charts' => ['#27272a', '#c2410c', '#0f766e', '#8b8b94'],
        'chartsDark' => ['#fafafa', '#fbbf24', '#2dd4bf', '#a1a1aa'],
    ],
];
@endphp

<div class="space-y-8">
    {{-- Theme Header & Mode Bar --}}
    <div class="card p-6 border border-line bg-surface/90 shadow-card">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-1.5">
                <div class="inline-flex items-center gap-2 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-brand-500">
                    <x-icon name="palette" class="size-3.5" />
                    <span>Workspace Customization</span>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">Choose Workspace Theme</h2>
                <p class="text-sm text-muted max-w-2xl">
                    Select a color palette that suits your personal workflow. Themes apply instantly across all dashboards, charts, tables, and reports, and persist across your device sessions.
                </p>
            </div>

            {{-- Mode Switcher & Active Indicator --}}
            <div class="flex flex-wrap items-center gap-3">
                {{-- Active Theme Pill --}}
                <div class="inline-flex items-center gap-2 rounded-xl bg-soft px-3 py-2 border border-line text-xs font-medium text-ink">
                    <span class="size-2.5 rounded-full bg-brand-500 animate-pulse"></span>
                    <span class="text-muted">Active:</span>
                    <span class="font-semibold capitalize text-brand-500" x-text="$store.theme.name"></span>
                </div>

                {{-- Light / Dark Segmented Toggle --}}
                <div class="inline-flex items-center rounded-xl bg-soft p-1 border border-line" role="group" aria-label="Color scheme selection">
                    <button type="button"
                            @click="$store.theme.setDark(false)"
                            :class="!$store.theme.dark ? 'bg-surface text-ink shadow-sm font-semibold' : 'text-muted hover:text-ink'"
                            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition-all cursor-pointer"
                            aria-label="Light mode">
                        <x-icon name="sun" class="size-3.5 text-warning" />
                        <span>Light</span>
                    </button>
                    <button type="button"
                            @click="$store.theme.setDark(true)"
                            :class="$store.theme.dark ? 'bg-surface text-ink shadow-sm font-semibold' : 'text-muted hover:text-ink'"
                            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition-all cursor-pointer"
                            aria-label="Dark mode">
                        <x-icon name="moon" class="size-3.5" />
                        <span>Dark</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- 6 Theme Cards Grid --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-ink">Color Palette Options</h3>
                <p class="text-xs text-muted">Click any theme card to apply immediately</p>
            </div>
            <button type="button"
                    @click="$store.theme.setTheme('swift'); $store.toast?.show('Reset to default Swift theme', 'success', 2000)"
                    x-show="$store.theme.name !== 'swift'"
                    x-cloak
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-brand-500 transition-colors cursor-pointer">
                <span>Reset to Default (Swift)</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($themeOptions as $theme)
                <div class="group relative rounded-2xl border-2 transition-all duration-300 cursor-pointer overflow-hidden flex flex-col justify-between"
                     :class="$store.theme.name === '{{ $theme['id'] }}' 
                            ? 'border-brand-500 bg-surface shadow-xl ring-4 ring-brand-500/15 translate-y-[-2px]' 
                            : 'border-line bg-surface/90 hover:border-brand-500/40 hover:shadow-lg hover:translate-y-[-1px]'"
                     @click="$store.theme.setTheme('{{ $theme['id'] }}'); $store.toast?.show('Theme changed to {{ $theme['name'] }}', 'success', 2200)"
                     tabindex="0"
                     role="button"
                     aria-label="Select {{ $theme['name'] }} theme"
                     @keydown.enter.prevent="$store.theme.setTheme('{{ $theme['id'] }}')"
                     @keydown.space.prevent="$store.theme.setTheme('{{ $theme['id'] }}')">

                    <div class="p-5 space-y-4">
                        {{-- Top Metadata Row --}}
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="size-3 rounded-full"
                                      :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['primaryDark'] }}' : '{{ $theme['primary'] }}' }"></span>
                                <h4 class="text-base font-bold text-ink tracking-tight">{{ $theme['name'] }}</h4>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                      :style="{ 
                                          backgroundColor: $store.theme.dark ? '{{ $theme['softDark'] }}' : '{{ $theme['soft'] }}',
                                          color: $store.theme.dark ? '{{ $theme['primaryDark'] }}' : '{{ $theme['primary'] }}'
                                      }">
                                    {{ $theme['badge'] }}
                                </span>

                                {{-- Active Check Pill --}}
                                <span x-show="$store.theme.name === '{{ $theme['id'] }}'"
                                      x-cloak
                                      class="inline-flex items-center gap-1 rounded-full bg-brand-500 text-on-brand px-2 py-0.5 text-[11px] font-bold shadow-sm animate-in fade-in zoom-in-95 duration-200">
                                    <x-icon name="check" class="size-3 stroke-[2.5]" />
                                    <span>Active</span>
                                </span>
                            </div>
                        </div>

                        {{-- Simulated Miniature Application Window Mockup --}}
                        <div class="rounded-xl border overflow-hidden shadow-inner transition-transform duration-300 group-hover:scale-[1.01]"
                             :style="{
                                 borderColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}',
                                 backgroundColor: $store.theme.dark ? '{{ $theme['canvasDark'] }}' : '{{ $theme['canvas'] }}'
                             }">
                            {{-- Window Title Bar --}}
                            <div class="flex items-center justify-between px-3 py-1.5 border-b"
                                 :style="{
                                     backgroundColor: $store.theme.dark ? '{{ $theme['surfaceDark'] }}' : '{{ $theme['surface'] }}',
                                     borderColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}'
                                 }">
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full bg-red-400"></span>
                                    <span class="size-2 rounded-full bg-amber-400"></span>
                                    <span class="size-2 rounded-full bg-emerald-400"></span>
                                </div>
                                <span class="text-[9px] font-mono opacity-60 text-muted">{{ $theme['id'] }}-erp.preview</span>
                                <div class="w-8"></div>
                            </div>

                            {{-- Window Inner Frame (Sidebar + Content) --}}
                            <div class="flex h-36 p-1.5 gap-1.5">
                                {{-- Mini Sidebar --}}
                                <div class="w-14 rounded-lg p-1.5 flex flex-col gap-1.5 border"
                                     :style="{
                                         backgroundColor: $store.theme.dark ? '{{ $theme['surfaceDark'] }}' : '{{ $theme['surface'] }}',
                                         borderColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}'
                                     }">
                                    <div class="size-3.5 rounded flex items-center justify-center text-[7px] font-bold text-white shadow-xs"
                                         :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['primaryDark'] }}' : '{{ $theme['primary'] }}' }">
                                        {{ substr($theme['name'], 0, 1) }}
                                    </div>
                                    <div class="h-1.5 w-full rounded"
                                         :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['primaryDark'] }}' : '{{ $theme['primary'] }}' }"></div>
                                    <div class="h-1.5 w-3/4 rounded opacity-40"
                                         :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}' }"></div>
                                    <div class="h-1.5 w-4/5 rounded opacity-40"
                                         :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}' }"></div>
                                </div>

                                {{-- Mini Main Stage --}}
                                <div class="flex-1 flex flex-col gap-1.5 overflow-hidden">
                                    {{-- Mini Topbar --}}
                                    <div class="h-5 rounded-md px-2 flex items-center justify-between border"
                                         :style="{
                                             backgroundColor: $store.theme.dark ? '{{ $theme['surfaceDark'] }}' : '{{ $theme['surface'] }}',
                                             borderColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}'
                                         }">
                                        <div class="h-1.5 w-12 rounded"
                                             :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['softDark'] }}' : '{{ $theme['soft'] }}' }"></div>
                                        <div class="size-2.5 rounded-full"
                                             :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['primaryDark'] }}' : '{{ $theme['primary'] }}' }"></div>
                                    </div>

                                    {{-- Mini Dashboard Cards Grid --}}
                                    <div class="flex-1 grid grid-cols-2 gap-1.5">
                                        {{-- Mini Stat Card --}}
                                        <div class="rounded-md p-1.5 flex flex-col justify-between border"
                                             :style="{
                                                 backgroundColor: $store.theme.dark ? '{{ $theme['surfaceDark'] }}' : '{{ $theme['surface'] }}',
                                                 borderColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}'
                                             }">
                                            <div class="flex items-center justify-between">
                                                <div class="size-2.5 rounded flex items-center justify-center"
                                                     :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['softDark'] }}' : '{{ $theme['soft'] }}' }">
                                                    <span class="size-1 rounded-full"
                                                          :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['primaryDark'] }}' : '{{ $theme['primary'] }}' }"></span>
                                                </div>
                                                <span class="text-[7px] font-bold text-emerald-500">+12%</span>
                                            </div>
                                            <div>
                                                <div class="h-1 w-6 rounded opacity-50"
                                                     :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['inkDark'] }}' : '{{ $theme['ink'] }}' }"></div>
                                                <div class="h-1.5 w-10 rounded mt-0.5"
                                                     :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['inkDark'] }}' : '{{ $theme['ink'] }}' }"></div>
                                            </div>
                                        </div>

                                        {{-- Mini Chart Preview Card --}}
                                        <div class="rounded-md p-1.5 flex flex-col justify-end border"
                                             :style="{
                                                 backgroundColor: $store.theme.dark ? '{{ $theme['surfaceDark'] }}' : '{{ $theme['surface'] }}',
                                                 borderColor: $store.theme.dark ? '{{ $theme['lineDark'] }}' : '{{ $theme['line'] }}'
                                             }">
                                            <div class="flex items-end justify-between gap-1 h-12 pt-1 px-1">
                                                <div class="w-1.5 rounded-t"
                                                     :style="{ 
                                                         height: '55%',
                                                         backgroundColor: $store.theme.dark ? '{{ $theme['chartsDark'][0] }}' : '{{ $theme['charts'][0] }}'
                                                     }"></div>
                                                <div class="w-1.5 rounded-t"
                                                     :style="{ 
                                                         height: '85%',
                                                         backgroundColor: $store.theme.dark ? '{{ $theme['chartsDark'][1] }}' : '{{ $theme['charts'][1] }}'
                                                     }"></div>
                                                <div class="w-1.5 rounded-t"
                                                     :style="{ 
                                                         height: '40%',
                                                         backgroundColor: $store.theme.dark ? '{{ $theme['chartsDark'][2] }}' : '{{ $theme['charts'][2] }}'
                                                     }"></div>
                                                <div class="w-1.5 rounded-t"
                                                     :style="{ 
                                                         height: '95%',
                                                         backgroundColor: $store.theme.dark ? '{{ $theme['chartsDark'][3] }}' : '{{ $theme['charts'][3] }}'
                                                     }"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Theme Tagline & Description --}}
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-ink">{{ $theme['tagline'] }}</p>
                            <p class="text-xs text-muted leading-relaxed line-clamp-2">{{ $theme['description'] }}</p>
                        </div>
                    </div>

                    {{-- Bottom Footer with Palette Swatches & CTA --}}
                    <div class="p-4 pt-3 border-t border-line bg-canvas/30 flex items-center justify-between">
                        {{-- Swatches row --}}
                        <div class="flex items-center gap-1.5">
                            <span class="size-4 rounded-full border border-black/10 shadow-xs cursor-help"
                                  title="Primary Accent"
                                  :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['primaryDark'] }}' : '{{ $theme['primary'] }}' }"></span>
                            <span class="size-4 rounded-full border border-black/10 shadow-xs cursor-help"
                                  title="Canvas Background"
                                  :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['canvasDark'] }}' : '{{ $theme['canvas'] }}' }"></span>
                            <span class="size-4 rounded-full border border-black/10 shadow-xs cursor-help"
                                  title="Surface Card Background"
                                  :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['surfaceDark'] }}' : '{{ $theme['surface'] }}' }"></span>
                            <span class="size-4 rounded-full border border-black/10 shadow-xs cursor-help"
                                  title="Soft Badge / Pill Accent"
                                  :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['softDark'] }}' : '{{ $theme['soft'] }}' }"></span>
                            <span class="size-4 rounded-full border border-black/10 shadow-xs cursor-help"
                                  title="Chart Series Accent"
                                  :style="{ backgroundColor: $store.theme.dark ? '{{ $theme['chartsDark'][1] }}' : '{{ $theme['charts'][1] }}' }"></span>
                        </div>

                        {{-- Action Button --}}
                        <div>
                            <template x-if="$store.theme.name === '{{ $theme['id'] }}'">
                                <span class="inline-flex items-center gap-1 rounded-xl bg-brand-500/10 text-brand-500 px-3 py-1.5 text-xs font-semibold">
                                    <x-icon name="check" class="size-3.5 stroke-[2.5]" />
                                    <span>Current</span>
                                </span>
                            </template>
                            <template x-if="$store.theme.name !== '{{ $theme['id'] }}'">
                                <span class="inline-flex items-center gap-1 rounded-xl bg-soft text-ink px-3 py-1.5 text-xs font-semibold group-hover:bg-brand-500 group-hover:text-on-brand transition-colors duration-200">
                                    <span>Select</span>
                                    <x-icon name="arrow-up-right" class="size-3.5" />
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Live Component Playground / Preview Sandbox --}}
    <div class="card p-6 border border-line bg-surface/90 shadow-card space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-line pb-4">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <x-icon name="sparkles" class="size-4 text-brand-500" />
                    <h3 class="text-base font-bold text-ink">Live Component Sandbox</h3>
                </div>
                <p class="text-xs text-muted">Preview how your active theme styles real production UI elements in real time</p>
            </div>
            <div class="text-xs text-muted font-mono bg-soft px-3 py-1 rounded-lg border border-line">
                data-theme="<span class="text-brand-500 font-bold" x-text="$store.theme.name"></span>"
            </div>
        </div>

        {{-- Live Components Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            {{-- KPI Stat Card --}}
            <div class="rounded-2xl border border-line bg-surface p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted">Daily Net Sales</span>
                    <div class="size-8 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center">
                        <x-icon name="currency-taka" class="size-4" />
                    </div>
                </div>
                <div>
                    <div class="text-xl font-extrabold tracking-tight text-ink">৳ 3,45,280.00</div>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="inline-flex items-center gap-0.5 text-[11px] font-bold text-success bg-success/10 px-1.5 py-0.5 rounded-md">
                            ↑ +18.4%
                        </span>
                        <span class="text-[11px] text-muted">vs previous week</span>
                    </div>
                </div>
            </div>

            {{-- Button Variants Showcase --}}
            <div class="rounded-2xl border border-line bg-surface p-4 shadow-sm space-y-3">
                <span class="text-xs font-medium text-muted block">Button Variants</span>
                <div class="flex flex-col gap-2">
                    <button type="button"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 text-on-brand px-3 py-2 text-xs font-semibold shadow-xs hover:bg-brand-600 transition-colors">
                        <x-icon name="plus" class="size-3.5" />
                        <span>Brand Primary Button</span>
                    </button>
                    <button type="button"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-soft text-brand-500 px-3 py-2 text-xs font-semibold hover:bg-brand-100 transition-colors">
                        <span>Soft Highlight Button</span>
                    </button>
                </div>
            </div>

            {{-- Status Badges Showcase --}}
            <div class="rounded-2xl border border-line bg-surface p-4 shadow-sm space-y-3">
                <span class="text-xs font-medium text-muted block">Status & Alert Badges</span>
                <div class="flex flex-wrap gap-2 pt-1">
                    <span class="inline-flex items-center gap-1 rounded-full bg-success/15 text-success px-2.5 py-1 text-xs font-semibold">
                        <span class="size-1.5 rounded-full bg-success"></span>
                        Delivered
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-500/15 text-brand-500 px-2.5 py-1 text-xs font-semibold">
                        <span class="size-1.5 rounded-full bg-brand-500"></span>
                        Processing
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-warning/15 text-warning px-2.5 py-1 text-xs font-semibold">
                        <span class="size-1.5 rounded-full bg-warning"></span>
                        Pending
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-danger/15 text-danger px-2.5 py-1 text-xs font-semibold">
                        <span class="size-1.5 rounded-full bg-danger"></span>
                        Cancelled
                    </span>
                </div>
            </div>

            {{-- Form Control & Focus Ring --}}
            <div class="rounded-2xl border border-line bg-surface p-4 shadow-sm space-y-3">
                <span class="text-xs font-medium text-muted block">Input & Focus Ring</span>
                <div class="space-y-2">
                    <div class="relative">
                        <input type="text"
                               placeholder="Type to test focus..."
                               value="Swift Track ERP"
                               class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs text-ink placeholder:text-muted outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all">
                    </div>
                    <div class="flex items-center justify-between text-xs text-muted pt-1">
                        <span>Interactive Toggle:</span>
                        <label class="switch">
                            <input type="checkbox" checked @change="$store.theme.toggle()">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Token Palette Bar --}}
        <div class="pt-4 border-t border-line">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Semantic Theme CSS Tokens</span>
                <span class="text-xs text-muted">Synchronized across document root</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 text-center text-xs">
                <div class="rounded-xl border border-line p-2.5 bg-canvas">
                    <div class="font-mono text-[10px] text-muted">--canvas</div>
                    <div class="text-[11px] font-semibold text-ink mt-0.5">Canvas</div>
                </div>
                <div class="rounded-xl border border-line p-2.5 bg-shell">
                    <div class="font-mono text-[10px] text-muted">--shell</div>
                    <div class="text-[11px] font-semibold text-ink mt-0.5">Shell</div>
                </div>
                <div class="rounded-xl border border-line p-2.5 bg-surface">
                    <div class="font-mono text-[10px] text-muted">--surface</div>
                    <div class="text-[11px] font-semibold text-ink mt-0.5">Surface</div>
                </div>
                <div class="rounded-xl border border-line p-2.5 bg-soft">
                    <div class="font-mono text-[10px] text-muted">--soft</div>
                    <div class="text-[11px] font-semibold text-brand-500 mt-0.5">Soft Accent</div>
                </div>
                <div class="rounded-xl border border-line p-2.5 bg-line">
                    <div class="font-mono text-[10px] text-muted">--line</div>
                    <div class="text-[11px] font-semibold text-ink mt-0.5">Border</div>
                </div>
                <div class="rounded-xl border border-line p-2.5 bg-brand-500 text-on-brand">
                    <div class="font-mono text-[10px] opacity-80">--brand-500</div>
                    <div class="text-[11px] font-semibold mt-0.5">Brand Main</div>
                </div>
                <div class="rounded-xl border border-line p-2.5 bg-brand-600 text-on-brand">
                    <div class="font-mono text-[10px] opacity-80">--brand-600</div>
                    <div class="text-[11px] font-semibold mt-0.5">Brand Hover</div>
                </div>
            </div>
        </div>
    </div>
</div>
