@extends('layouts.app')

@section('title', 'Purchase Order '.$order->reference_no)
@section('page_title', 'Purchase Invoices')

@section('content')
<style>
    @media print {
        header, aside, nav, .no-print { display: none !important; }
        main { padding: 0 !important; }
    }
</style>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3 no-print">
    <div>
        <div class="flex items-baseline gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Purchase Invoices</h1>
            <span class="text-sm text-gray-500 dark:text-gray-400">View Purchase Order {{ $order->reference_no }}</span>
        </div>
    </div>
    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
        <a href="{{ route('dashboard') }}" class="hover:text-brand-500 flex items-center gap-1">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Home
        </a>
        <span>&rsaquo;</span>
        <a href="{{ route('purchase-invoices.index') }}" class="hover:text-brand-500">Purchase Invoices List</a>
        <span>&rsaquo;</span>
        <span class="text-gray-700 dark:text-gray-300 font-medium">View Purchase Order</span>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
            Purchase Order {{ $order->reference_no }}
            <x-status-badge :status="$order->purchase_status->value" :label="$order->purchase_status->label()" />
            <x-status-badge :status="$order->payment_status->value" :label="$order->payment_status->label()" />
        </h2>
        <div class="flex items-center gap-2 no-print">
            @can('update', $order)
                <a href="{{ route('purchase-invoices.edit', $order) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-500/60 px-4 py-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 transition hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
            @endcan
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
            <a href="{{ route('purchase-invoices.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-medium text-white shadow-sm cursor-pointer" style="background-color: #f39c12;">
                Close
            </a>
        </div>
    </div>

    <div class="card-body p-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Supplier</div>
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $order->supplier?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Business Location</div>
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $order->location?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Date</div>
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $order->order_date->format('d/m/Y H:i') }}</div>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Added By</div>
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $order->creator?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Reference No</div>
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $order->reference_no }}</div>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Attach Document</div>
                @if($order->document)
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->document) }}" target="_blank"
                       class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">View document</a>
                @else
                    <div class="font-medium text-gray-900 dark:text-gray-100">—</div>
                @endif
            </div>
        </div>

        <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md mt-6">
            <table class="data-table w-full text-left">
                <thead>
                    <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">#</th>
                        <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Product</th>
                        <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Quantity</th>
                        <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Unit Price</th>
                        <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                    @forelse($order->items as $index => $item)
                        <tr class="odd:bg-gray-50 dark:odd:bg-gray-800/40">
                            <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $index + 1 }}</td>
                            <td class="p-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->unit?->displayName() ?? 'Unknown product' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->unit?->unit }}</div>
                            </td>
                            <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $item->quantity }}</td>
                            <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ \App\Helpers\Money::format($item->unit_price) }}</td>
                            <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($item->subtotal) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500 dark:text-gray-400 text-sm font-medium">
                                No products on this purchase order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-white dark:bg-gray-900">
                        <td colspan="4" class="p-3 text-sm text-right font-semibold text-gray-600 dark:text-gray-300">Subtotal</td>
                        <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($order->subtotal) }}</td>
                    </tr>
                    <tr class="bg-white dark:bg-gray-900">
                        <td colspan="4" class="p-3 text-sm text-right font-semibold text-gray-600 dark:text-gray-300">Tax ({{ rtrim(rtrim(number_format((float) $order->tax_rate, 2), '0'), '.') }}%)</td>
                        <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($order->tax_amount) }}</td>
                    </tr>
                    <tr class="bg-white dark:bg-gray-900">
                        <td colspan="4" class="p-3 text-sm text-right font-bold text-gray-800 dark:text-gray-100">Grand Total</td>
                        <td class="p-3 text-sm font-bold text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($order->grand_total) }}</td>
                    </tr>
                    <tr class="bg-white dark:bg-gray-900">
                        <td colspan="4" class="p-3 text-sm text-right font-semibold text-gray-600 dark:text-gray-300">Amount Paid</td>
                        <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($order->paid_amount) }}</td>
                    </tr>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <td colspan="4" class="p-3 text-sm text-right font-bold text-gray-800 dark:text-gray-100">Payment Due</td>
                        <td class="p-3 text-sm font-bold text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($order->payment_due) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@if(request('print'))
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
@endif
@endsection
