@php
    $eventColors = ['Primary', 'Danger', 'Success', 'Warning', 'Info'];
@endphp

<div
    x-data="{
        eventTitle: '',
        eventStartDate: '',
        eventEndDate: '',
        eventLevel: 'Primary',
        isEditing: false,
        startPicker: null,
        endPicker: null,
        initPickers() {
            const prevArrow = '<svg class=\'stroke-current\' width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M12.5 15L7.5 10L12.5 5\' stroke=\'\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>';
            const nextArrow = '<svg class=\'stroke-current\' width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M7.5 15L12.5 10L7.5 5\' stroke=\'\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>';
            const make = (el, value, onChange) => flatpickr(el, { dateFormat: 'Y-m-d', defaultDate: value || undefined, monthSelectorType: 'static', static: true, clickOpens: true, prevArrow, nextArrow, onChange: (_d, str) => onChange(str) });
            this.destroyPickers();
            if (this.$refs.startInput) this.startPicker = make(this.$refs.startInput, this.eventStartDate, (s) => (this.eventStartDate = s));
            if (this.$refs.endInput) this.endPicker = make(this.$refs.endInput, this.eventEndDate, (s) => (this.eventEndDate = s));
        },
        destroyPickers() {
            if (this.startPicker) this.startPicker.destroy();
            if (this.endPicker) this.endPicker.destroy();
            this.startPicker = null;
            this.endPicker = null;
        },
        save() {
            window.dispatchEvent(new CustomEvent('calendar-save', { detail: { title: this.eventTitle, start: this.eventStartDate, end: this.eventEndDate, level: this.eventLevel } }));
        },
    }"
    class="contents"
>
    <x-ui.dialog
        @calendar-open.window="
            const d = $event.detail;
            isEditing = !!d.event;
            eventTitle = d.event ? d.event.title : '';
            eventStartDate = d.start;
            eventEndDate = d.end || d.start;
            eventLevel = d.event ? d.event.level : 'Primary';
            show();
            $nextTick(() => setTimeout(() => initPickers(), 50));
        "
    >
        <x-ui.dialog.content :show-close-button="false" class="overflow-visible border-none bg-transparent p-0 shadow-none sm:max-w-175">
            <x-ui.card class="relative w-full rounded-3xl border border-line bg-surface p-6 shadow-card sm:p-11 ">
                <!-- Top-end circular close button -->
                <x-ui.dialog.close as-child>
                    <x-ui.button
                        variant="ghost"
                        size="icon-lg"
                        class="absolute end-6 top-6 hidden size-11 rounded-full bg-soft text-muted hover:bg-line hover:text-ink sm:flex"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        <span class="sr-only">Close</span>
                    </x-ui.button>
                </x-ui.dialog.close>

                <!-- Title and subtitle -->
                <div class="flex flex-col gap-2">
                    <x-ui.dialog.title class="text-title-lg leading-8 font-semibold text-ink sm:text-2xl">
                        Add / Edit Event
                    </x-ui.dialog.title>
                    <x-ui.dialog.description class="text-sm leading-5 font-normal text-muted">
                        Plan your next big moment: schedule or edit an event to stay on track
                    </x-ui.dialog.description>
                </div>

                <!-- Form fields -->
                <form class="mt-6 space-y-5 sm:mt-8 sm:space-y-6" @submit.prevent="save(); close()">
                    <!-- Event Title -->
                    <div>
                        <x-ui.label for="event-title" class="mb-1.5 block text-sm font-medium text-ink">
                            Event Title
                        </x-ui.label>
                        <x-ui.input id="event-title" x-model="eventTitle" class="h-11 rounded-[10px]" />
                    </div>

                    <!-- Event Color -->
                    <div>
                        <x-ui.label class="mb-3 block text-sm font-medium text-ink sm:mb-4">
                            Event Color
                        </x-ui.label>
                        <x-ui.radio-group
                            default-value="Primary"
                            class="flex flex-wrap items-center gap-4 sm:gap-5"
                            x-init="$watch('value', (v) => (eventLevel = v)); $watch('eventLevel', (l) => (value = l))"
                        >
                            @foreach ($eventColors as $color)
                                <div class="flex items-center gap-2">
                                    <x-ui.radio-group.item value="{{ $color }}" id="modal-color-{{ strtolower($color) }}" />
                                    <x-ui.label for="modal-color-{{ strtolower($color) }}" class="cursor-pointer text-sm font-normal text-ink">
                                        {{ $color }}
                                    </x-ui.label>
                                </div>
                            @endforeach
                        </x-ui.radio-group>
                    </div>

                    <!-- Enter Start Date -->
                    <div>
                        <x-ui.label for="event-start-date" class="mb-1.5 block text-sm font-medium text-ink">
                            Enter Start Date
                        </x-ui.label>
                        <x-ui.input x-ref="startInput" id="event-start-date" placeholder="dd/mm/yyyy" x-model="eventStartDate" class="h-11 rounded-[10px]" />
                    </div>

                    <!-- Enter End Date -->
                    <div>
                        <x-ui.label for="event-end-date" class="mb-1.5 block text-sm font-medium text-ink">
                            Enter End Date
                        </x-ui.label>
                        <x-ui.input x-ref="endInput" id="event-end-date" placeholder="dd/mm/yyyy" x-model="eventEndDate" class="h-11 rounded-[10px]" />
                    </div>

                    <!-- Action buttons -->
                    <div class="grid grid-cols-2 gap-3 pt-2 sm:flex sm:justify-end sm:pt-4">
                        <x-ui.button type="button" variant="outline" class="h-11 w-full rounded-[10px] px-5 py-3 sm:w-auto" @click="close()">
                            Close
                        </x-ui.button>
                        <x-ui.button type="submit" class="h-11 w-full rounded-[10px] px-5 py-3 sm:w-auto">
                            <span x-text="isEditing ? 'Save Changes' : 'Add Event'">Add Event</span>
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </x-ui.dialog.content>
    </x-ui.dialog>
</div>
