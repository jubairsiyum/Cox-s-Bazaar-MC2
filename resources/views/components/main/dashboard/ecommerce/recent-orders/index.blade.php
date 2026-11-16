@php
    $orders = include resource_path('views/components/main/dashboard/ecommerce/recent-orders/data.php');
@endphp
<div class="overflow-hidden rounded-2xl border border-line bg-surface px-4 pt-4 pb-3 sm:px-6">
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-ink">
                {{ __('ecommerce.recentOrders.title') }}
            </h3>
        </div>

        <div class="flex items-center gap-3">
            <x-ui.button variant="outline">
                <x-svg.horizontal-slide />
                {{ __('common.filter') }}
            </x-ui.button>
            <x-ui.button variant="outline">{{ __('common.seeAll') }}</x-ui.button>
        </div>
    </div>

    <div class="max-w-full overflow-x-auto">
        <table class="min-w-full">
            <!-- Table Header -->
            <thead class="border-y border-line">
                <tr>
                    @foreach (['products', 'category', 'price', 'status'] as $column)
                        <th class="py-3 text-start text-xs font-medium text-muted">
                            {{ __('ecommerce.recentOrders.' . $column) }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <!-- Table Body -->
            <tbody class="divide-y divide-line">
                @foreach ($orders as $order)
                    <x-main.dashboard.ecommerce.recent-orders.order-row :order="$order" />
                @endforeach
            </tbody>
        </table>
    </div>
</div>
