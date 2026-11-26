@php
    $profileUrl = route('suppliers.profile', $supplier);
    $sections = [
        'details' => ['label' => 'Details', 'viewBox' => '0 0 576 512', 'path' => 'M64 32C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zm80 256h64c44.2 0 80 35.8 80 80c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16c0-44.2 35.8-80 80-80zm-32-96a64 64 0 1 1 128 0 64 64 0 1 1 -128 0zm256-32H496c8.8 0 16 7.2 16 16s-7.2 16-16 16H368c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64H496c8.8 0 16 7.2 16 16s-7.2 16-16 16H368c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64H496c8.8 0 16 7.2 16 16s-7.2 16-16 16H368c-8.8 0-16-7.2-16-16s7.2-16 16-16z'],
        'dues' => ['label' => 'Branch Dues', 'viewBox' => '0 0 512 512', 'path' => 'M243.4 2.6l-224 96c-14 6-21.8 21-18.7 35.8S16.8 160 32 160v8c0 13.3 10.7 24 24 24H456c13.3 0 24-10.7 24-24v-8c15.2 0 28.3-10.7 31.3-25.6s-4.8-29.9-18.7-35.8l-224-96c-8-3.4-17.2-3.4-25.2 0zM128 224H64V420.3c-.6 .3-1.2 .7-1.8 1.1l-48 32c-11.7 7.8-17 22.4-12.9 35.9S17.9 512 32 512H480c14.1 0 26.5-9.2 30.6-22.7s-1.1-28.1-12.9-35.9l-48-32c-.6-.4-1.2-.7-1.8-1.1V224H384V416H344V224H280V416H232V224H168V416H128V224zM256 64a32 32 0 1 1 0 64 32 32 0 1 1 0-64z'],
        'orders' => ['label' => 'Purchase Orders', 'viewBox' => '0 0 576 512', 'path' => 'M0 24C0 10.7 10.7 0 24 0H69.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5L77.4 54.5c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24zM128 464a48 48 0 1 1 96 0 48 48 0 1 1 -96 0zm336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96z'],
        'payments' => ['label' => 'Payments', 'viewBox' => '0 0 576 512', 'path' => 'M0 112.5V422.3c0 18 10.1 35 27 41.3c87 32.5 174 10.3 261-11.9c79.8-20.3 159.6-40.7 239.3-18.9c23 6.3 48.7-9.5 48.7-33.4V89.7c0-18-10.1-35-27-41.3C462 15.9 375 38.1 288 60.3C208.2 80.6 128.4 100.9 48.7 79.1C25.6 72.8 0 88.6 0 112.5zM288 352c-44.2 0-80-43-80-96s35.8-96 80-96s80 43 80 96s-35.8 96-80 96zM64 352c35.3 0 64 28.7 64 64H64V352zm64-208c0 35.3-28.7 64-64 64V144h64zM512 304v64H448c0-35.3 28.7-64 64-64zM448 96h64v64c-35.3 0-64-28.7-64-64z'],
        'returns' => ['label' => 'Returns', 'viewBox' => '0 0 512 512', 'path' => 'M48.5 224H40c-13.3 0-24-10.7-24-24V72c0-9.7 5.8-18.5 14.8-22.2s19.3-1.7 26.2 5.2L98.6 96.6c87.6-86.5 228.7-86.2 315.8 1c87.5 87.5 87.5 229.3 0 316.8s-229.3 87.5-316.8 0c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0c62.5 62.5 163.8 62.5 226.3 0s62.5-163.8 0-226.3c-62.2-62.2-162.7-62.5-225.3-1L185 183c6.9 6.9 8.9 17.2 5.2 26.2s-12.5 14.8-22.2 14.8H48.5z'],
        'requests' => ['label' => 'Payment Requests', 'viewBox' => '0 0 512 512', 'path' => 'M498.1 5.6c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z'],
        'activity' => ['label' => 'Activity', 'viewBox' => '0 0 512 512', 'path' => 'M75 75L41 41C25.9 25.9 0 36.6 0 57.9V168c0 13.3 10.7 24 24 24H134.1c21.4 0 32.1-25.9 17-41l-30.8-30.8C155 85.5 203 64 256 64c106 0 192 86 192 192s-86 192-192 192c-40.8 0-78.6-12.7-109.7-34.4c-14.5-10.1-34.4-6.6-44.6 7.9s-6.6 34.4 7.9 44.6C151.2 495 201.7 512 256 512c141.4 0 256-114.6 256-256S397.4 0 256 0C185.3 0 121.3 28.7 75 75zm181 53c-13.3 0-24 10.7-24 24V256c0 6.4 2.5 12.5 7 17l72 72c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-65-65V152c0-13.3-10.7-24-24-24z'],
    ];
@endphp

@foreach($sections as $anchor => $section)
    <li>
        <a href="{{ $profileUrl }}#{{ $anchor }}"
           data-profile-section="{{ $anchor }}"
           :title="!isRailVisible() ? '{{ $section['label'] }}' : ''"
           @click="typeof sidebar !== 'undefined' ? (sidebar = false) : $store.sidebar.setMobileOpen(false)"
           :class="isRailVisible() ? 'w-full justify-between gap-3 rounded-full px-3 py-2.5' : 'w-full lg:size-11 lg:mx-auto lg:justify-center lg:px-0 lg:rounded-2xl'"
           class="flex items-center text-sm font-medium text-muted transition-all duration-200 hover:bg-soft/60 hover:text-ink">
            <span class="flex items-center min-w-0" :class="isRailVisible() ? 'gap-3 truncate' : 'lg:justify-center'">
                <span class="size-5 shrink-0 flex items-center justify-center">
                    <svg viewBox="{{ $section['viewBox'] }}" class="size-4 fill-current" aria-hidden="true" focusable="false">
                        <path d="{{ $section['path'] }}"/>
                    </svg>
                </span>
                <span x-show="isRailVisible()" class="erp-sidebar-label truncate whitespace-nowrap">{{ $section['label'] }}</span>
            </span>
        </a>
    </li>
@endforeach

<script>
window.addEventListener('load', function () {
    var links = document.querySelectorAll('[data-profile-section]');
    var activeClasses = ['bg-soft', 'text-brand-500', 'font-semibold'];
    var inactiveClasses = ['text-muted', 'hover:bg-soft/60', 'hover:text-ink'];

    if (links.length === 0 || document.getElementById('details') === null) {
        return;
    }

    function applyState(link, isActive) {
        link.classList.remove(...(isActive ? inactiveClasses : activeClasses));
        link.classList.add(...(isActive ? activeClasses : inactiveClasses));
        link.setAttribute('aria-current', isActive ? 'true' : 'false');
    }

    function setActive(key) {
        links.forEach(function (link) {
            applyState(link, link.dataset.profileSection === key);
        });
    }

    function sectionFromHash() {
        var key = window.location.hash.replace('#', '');

        return Array.prototype.some.call(links, function (link) {
            return link.dataset.profileSection === key;
        }) ? key : 'details';
    }

    links.forEach(function (link) {
        link.addEventListener('click', function () {
            setActive(link.dataset.profileSection);
        });
    });

    window.addEventListener('hashchange', function () {
        setActive(sectionFromHash());
    });

    setActive(sectionFromHash());

    if (!('IntersectionObserver' in window)) {
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                setActive(entry.target.id);
            }
        });
    }, { rootMargin: '-35% 0px -55% 0px' });

    links.forEach(function (link) {
        var target = document.getElementById(link.dataset.profileSection);

        if (target) {
            observer.observe(target);
        }
    });
});
</script>
