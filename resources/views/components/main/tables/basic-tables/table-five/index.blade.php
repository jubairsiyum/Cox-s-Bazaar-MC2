@props(['data' => null])
@php
    $orders = $data ?? (include resource_path('views/components/main/tables/basic-tables/table-five/data.php'))['recentOrdersData'];
    $categoryMap = ['uiKits' => 'UI Kits', 'templates' => 'Templates', 'saas' => 'SaaS'];
@endphp
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-line bg-surface px-4 pt-4 pb-3 sm:px-6  ']) }}>
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-ink">Recent Orders</h3>
        </div>

        <div class="flex items-center gap-3">
            <x-ui.button variant="outline">
                <x-svg.horizontal-slide />
                Filter
            </x-ui.button>
            <x-ui.button variant="outline">See all</x-ui.button>
        </div>
    </div>

    <div class="-mx-4 overflow-x-auto sm:-mx-6">
        <table class="min-w-full">
            <!-- Table Header -->
            <thead class="border-y border-line">
                <tr>
                    <th class="py-3 ps-4 text-start text-xs font-medium text-muted sm:ps-6">Products</th>
                    <th class="py-3 text-start text-xs font-medium text-muted">Category</th>
                    <th class="py-3 text-start text-xs font-medium text-muted">Country</th>
                    <th class="py-3 text-start text-xs font-medium text-muted">CR</th>
                    <th class="py-3 pe-4 text-start text-xs font-medium text-muted sm:pe-6">Value</th>
                </tr>
            </thead>

            <!-- Table Body -->
            <tbody class="divide-y divide-line">
                @foreach ($orders as $order)
                    <tr>
                        <td class="py-3 ps-4 text-start text-sm font-medium text-ink sm:ps-6">{{ $order['name'] }}</td>
                        <td class="py-3 text-start text-sm text-muted">{{ $categoryMap[$order['category']] ?? $order['category'] }}</td>
                        <td class="py-3 text-start text-sm text-muted">
                            <div class="h-5 w-5">
                                <img src="{{ $order['country'] }}" class="h-5 w-5 rounded-sm object-fill" alt="{{ $order['name'] }}" width="20" height="20" />
                            </div>
                        </td>
                        <td class="py-3 text-start text-sm text-muted">{{ $order['cr'] }}</td>
                        <td class="py-3 pe-4 text-start text-sm font-medium text-success sm:pe-6">${{ $order['value'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
