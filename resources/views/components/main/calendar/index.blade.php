<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-line bg-surface  ']) }}>
    <div class="custom-calendar relative">
        <div id="calendar" class="gap-0!"></div>
    </div>

    <x-main.calendar.event-modal />
</div>
