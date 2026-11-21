<div class="grid gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="chat" class="size-5" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Send Payment Request</h2>
                <p class="text-xs text-muted">
                    @if($isSupplierPortal)
                        Ask a branch that owes you money to settle its dues. The branch gets an in-app message with a direct action link.
                    @else
                        On {{ $supplier->name }}'s behalf — the targeted branch gets an in-app message with a direct action link.
                    @endif
                </p>
            </div>
        </div>

        @can('requestPayment', $supplier)
            @if($requestBranches->isEmpty())
                <x-empty-state message="No branch currently owes {{ $supplier->name }} money, so there is nothing to request." />
            @else
                @if($errors->any())
                    <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4 mb-4">
                        <ul class="list-disc list-inside space-y-0.5 text-xs text-ink/90">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('suppliers.profile.payment-requests.store', $supplier) }}" class="space-y-4"
                      x-data="{
                          branches: @js($requestBranches),
                          branchId: @js(old('location_id', '')),
                          orderId: @js(old('purchase_order_id', '')),
                          get branch() { return this.branches.find(b => String(b.id) === String(this.branchId)) ?? null; },
                          get orders() { return this.branch ? this.branch.orders : []; },
                          get maxAmount() {
                              if (! this.branch) { return null; }
                              const order = this.orders.find(o => String(o.id) === String(this.orderId));
                              return order ? order.due : this.branch.due;
                          },
                      }">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="request_location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Branch <span class="text-danger">*</span>
                            </label>
                            <select id="request_location_id" name="location_id" required x-model="branchId" x-on:change="orderId = ''"
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer">
                                <option value="">Please Select</option>
                                @foreach($requestBranches as $branch)
                                    <option value="{{ $branch['id'] }}">{{ $branch['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="request_purchase_order_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Purchase Invoice
                            </label>
                            <select id="request_purchase_order_id" name="purchase_order_id" x-model="orderId" :disabled="! branch"
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer disabled:opacity-60">
                                <option value="">Whole branch due</option>
                                <template x-for="order in orders" :key="order.id">
                                    <option :value="order.id" x-text="order.label" :selected="String(order.id) === String(orderId)"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label for="request_amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Amount ({{ \App\Models\Setting::get('currency_code', 'BDT') }})
                            </label>
                            <input type="number" step="0.01" min="0.01" id="request_amount" name="amount" value="{{ old('amount') }}"
                                   x-bind:max="maxAmount"
                                   placeholder="Optional"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                            <p class="mt-1.5 text-[11px] text-muted" x-show="maxAmount !== null" x-cloak>
                                Up to <span class="font-semibold text-ink tabular-nums" x-text="Number(maxAmount).toFixed(2)"></span> outstanding.
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="request_message" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Message <span class="text-danger">*</span>
                            </label>
                            <textarea id="request_message" name="message" rows="3" required
                                      placeholder="Please settle the outstanding dues for this month."
                                      class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none">{{ old('message') }}</textarea>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                            <x-icon name="chat" class="size-4" />
                            <span>Send Payment Request</span>
                        </button>
                    </div>
                </form>
            @endif
        @else
            <x-empty-state message="You do not have permission to send payment requests on the supplier's behalf." />
        @endcan
    </div>

    <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="enquiries" class="size-5" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Payment Requests</h2>
                <p class="text-xs text-muted">Requests sent to branches and their current state. Open requests can be {{ $isSupplierPortal ? 'edited or cancelled' : 'cancelled' }}.</p>
            </div>
        </div>

        @if($paymentRequests->isEmpty())
            <x-empty-state message="No payment requests sent yet." />
        @else
            <div class="space-y-3">
                @foreach($paymentRequests as $paymentRequest)
                    @php
                        $editBag = 'updatePaymentRequest-'.$paymentRequest->id;
                        $isEditing = $errors->getBag($editBag)->any();
                        $editBranch = $requestBranches->firstWhere('id', $paymentRequest->location_id);
                        $canEditRequest = $isSupplierPortal && auth()->user()->can('editPaymentRequest', [$supplier, $paymentRequest]);
                    @endphp
                    <div class="rounded-2xl border border-line bg-soft/40 px-4 py-3"
                         x-data="{
                             editing: {{ $isEditing ? 'true' : 'false' }},
                             orders: @js($editBranch['orders'] ?? []),
                             branchDue: @js((float) ($editBranch['due'] ?? 0)),
                             orderId: @js((string) ($isEditing ? old('purchase_order_id', $paymentRequest->purchase_order_id) : $paymentRequest->purchase_order_id)),
                             get maxAmount() {
                                 const order = this.orders.find(o => String(o.id) === String(this.orderId));
                                 return order ? order.due : this.branchDue;
                             },
                         }">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="text-sm font-semibold text-ink">{{ $paymentRequest->location?->name ?? 'Unknown branch' }}</span>
                            @if($paymentRequest->purchaseOrder)
                                @if($isSupplierPortal)
                                    <span class="text-[11px] font-semibold text-ink tabular-nums">{{ $paymentRequest->purchaseOrder->reference_no }}</span>
                                @else
                                    <a href="{{ route('purchase-invoices.show', $paymentRequest->purchaseOrder) }}" class="text-[11px] font-semibold text-brand-500 hover:underline tabular-nums">
                                        {{ $paymentRequest->purchaseOrder->reference_no }}
                                    </a>
                                @endif
                            @endif
                            @if($paymentRequest->amount !== null)
                                <span class="text-xs font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($paymentRequest->amount) }}</span>
                            @endif
                            @php
                                $requestStatusKey = match ($paymentRequest->status->value) {
                                    'open' => 'due',
                                    'actioned' => 'paid',
                                    default => 'cancelled',
                                };
                            @endphp
                            <x-status-badge :status="$requestStatusKey" :label="$paymentRequest->status->label()" />
                            <span class="ml-auto text-[11px] text-muted whitespace-nowrap">{{ $paymentRequest->created_at->format('d M Y') }}</span>
                        </div>
                        <p class="text-xs text-muted mt-1.5">{{ $paymentRequest->message }}</p>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-[11px] text-muted">
                            <span>Requested by <strong class="text-ink">{{ $paymentRequest->creator?->name ?? 'Unknown user' }}</strong></span>
                            @if($paymentRequest->status === \App\Enums\SupplierPaymentRequestStatus::Actioned)
                                <span>· actioned by <strong class="text-ink">{{ $paymentRequest->actioner?->name ?? 'Unknown user' }}</strong></span>
                                @if($paymentRequest->payment)
                                    @unless($isSupplierPortal)
                                        <a href="{{ route('supplier-payments.show', $paymentRequest->payment) }}" class="text-brand-500 hover:underline">View payment</a>
                                    @endunless
                                @endif
                            @endif
                            @if($paymentRequest->isOpen())
                                @if($canEditRequest)
                                    <button type="button" x-on:click="editing = ! editing" class="text-brand-500 font-semibold hover:underline cursor-pointer" x-text="editing ? 'Close Edit' : 'Edit'">Edit</button>
                                @endif
                                @can('requestPayment', $supplier)
                                    <form method="POST" action="{{ route('suppliers.profile.payment-requests.cancel', [$supplier, $paymentRequest]) }}" class="inline-block ml-auto">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-danger font-semibold hover:underline cursor-pointer">Cancel Request</button>
                                    </form>
                                @endcan
                                @if($canRecordPayments)
                                    <a href="{{ route('supplier-payments.create', array_filter([
                                        'supplier_id' => $supplier->id,
                                        'purchase_order_id' => $paymentRequest->purchase_order_id,
                                        'amount' => $paymentRequest->amount,
                                        'payment_request_id' => $paymentRequest->id,
                                    ])) }}" class="text-brand-500 font-semibold hover:underline ml-auto">Record Payment</a>
                                @endif
                            @endif
                        </div>
                        @if($canEditRequest)
                            <form method="POST" action="{{ route('suppliers.my-profile.requests.update', $paymentRequest) }}" x-show="editing" x-cloak
                                  class="mt-3 space-y-3 rounded-2xl border border-line bg-surface p-4">
                                @csrf
                                @method('PATCH')

                                @if($isEditing)
                                    <ul class="list-disc list-inside space-y-0.5 text-xs text-danger">
                                        @foreach ($errors->getBag($editBag)->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="edit_purchase_order_{{ $paymentRequest->id }}" class="block text-[11px] font-semibold uppercase tracking-wider text-ink mb-1">
                                            Purchase Invoice
                                        </label>
                                        <select id="edit_purchase_order_{{ $paymentRequest->id }}" name="purchase_order_id" x-model="orderId"
                                                class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer">
                                            <option value="">Whole branch due</option>
                                            <template x-for="order in orders" :key="order.id">
                                                <option :value="order.id" x-text="order.label" :selected="String(order.id) === String(orderId)"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="edit_amount_{{ $paymentRequest->id }}" class="block text-[11px] font-semibold uppercase tracking-wider text-ink mb-1">
                                            Amount ({{ \App\Models\Setting::get('currency_code', 'BDT') }})
                                        </label>
                                        <input type="number" step="0.01" min="0.01" id="edit_amount_{{ $paymentRequest->id }}" name="amount"
                                               value="{{ $isEditing ? old('amount') : $paymentRequest->amount }}" x-bind:max="maxAmount" placeholder="Optional"
                                               class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="edit_message_{{ $paymentRequest->id }}" class="block text-[11px] font-semibold uppercase tracking-wider text-ink mb-1">
                                            Message <span class="text-danger">*</span>
                                        </label>
                                        <textarea id="edit_message_{{ $paymentRequest->id }}" name="message" rows="2" required
                                                  class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none">{{ $isEditing ? old('message') : $paymentRequest->message }}</textarea>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" x-on:click="editing = false"
                                            class="inline-flex items-center rounded-2xl border border-line bg-surface hover:bg-soft px-4 py-2 text-xs font-semibold text-ink transition-colors cursor-pointer">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                                        <span>Save Changes</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
