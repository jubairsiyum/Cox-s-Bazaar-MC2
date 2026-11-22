            {{-- Live Product Summary Card --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Live SKU Preview</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                          :class="status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20'">
                        <span class="size-1.5 rounded-full" :class="status === 'active' ? 'bg-success' : 'bg-danger'"></span>
                        <span x-text="status === 'active' ? 'Active' : 'Inactive'">Active</span>
                    </span>
                </div>

                <div class="rounded-2xl border border-line bg-canvas/40 p-4 space-y-3">
                    <div>
                        <p class="text-sm font-bold text-ink leading-snug" x-text="displayName()"></p>
                        <p class="text-[11px] text-muted mt-0.5" x-text="model.trim() ? 'Model: ' + model.trim() : 'PRD code auto-assigned on save'"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 pt-2 border-t border-line/70">
                        <div>
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Sale Price</span>
                            <span class="block text-base font-extrabold text-ink tabular-nums mt-0.5" x-text="formatMoney(salePrice)">৳0.00</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Opening Stock</span>
                            <span class="block text-sm font-bold text-brand-500 tabular-nums mt-0.5" x-text="(parseInt(quantity, 10) || 0) + ' ' + unit">0 Pcs</span>
                        </div>
                    </div>
                </div>

                {{-- Profit Margin Meter --}}
                <div class="rounded-2xl border border-line bg-soft/50 p-4 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted font-medium">Est. Unit Profit</span>
                        <span class="font-bold tabular-nums"
                              :class="profit() >= 0 ? 'text-success' : 'text-danger'"
                              x-text="formatMoney(profit())">৳0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted font-medium">Gross Margin</span>
                        <span class="font-bold tabular-nums"
                              :class="marginPct() >= 0 ? 'text-brand-500' : 'text-danger'"
                              x-text="marginPct().toFixed(1) + '%'">0.0%</span>
                    </div>
                </div>
            </div>
