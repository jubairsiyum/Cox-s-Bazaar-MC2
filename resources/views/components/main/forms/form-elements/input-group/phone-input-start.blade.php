<x-ui.field>
    <x-ui.field.label>Phone</x-ui.field.label>
    <x-ui.input-group class="overflow-hidden">
        <x-ui.input-group.addon align="inline-start" class="relative self-stretch border-e border-line p-0">
            <div class="relative flex h-full items-center">
                <select class="h-full cursor-pointer appearance-none border-0 border-none bg-transparent py-0 ps-3.5 pe-8 text-sm font-medium text-ink shadow-none outline-hidden focus:border-0 focus:border-none focus:outline-hidden focus:ring-0 dark:focus:text-on-brand" @click.stop>
                    <option value="US" class="text-ink">US</option>
                    <option value="GB" class="text-ink">GB</option>
                    <option value="CA" class="text-ink">CA</option>
                    <option value="AU" class="text-ink">AU</option>
                </select>
                <x-svg.chevron-down class="pointer-events-none absolute end-2.5 size-4 text-muted" />
            </div>
        </x-ui.input-group.addon>
        <x-ui.input-group.input type="tel" placeholder="+1 (555) 000-0000" />
    </x-ui.input-group>
</x-ui.field>
