            {{-- Section 1: Core Identity & Classification --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="smartphone" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Product Identity &amp; Classification</h2>
                        <p class="text-xs text-muted mt-0.5">Core naming, manufacturer brand, category, and model number.</p>
                    </div>
                </div>

                <div class="p-6 grid gap-5 sm:grid-cols-2">
                    {{-- Product Name --}}
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Product Name<span class="text-danger ml-0.5">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               x-model="name"
                               value="{{ old('name') }}"
                               required
                               placeholder="e.g. iPhone 15 Pro Max, Galaxy S24 Ultra"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                        @error('name')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Product Photo --}}
                    <div class="sm:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <label for="photo" class="block text-xs font-semibold uppercase tracking-wider text-ink">
                                Photo
                            </label>
                            <span class="text-[11px] text-muted">Optional — JPG, PNG, WEBP or SVG, max 2 MB</span>
                        </div>
                        <input type="file"
                               id="photo"
                               name="photo"
                               accept=".jpg,.jpeg,.png,.webp,.svg"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2 text-xs sm:text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink hover:file:bg-line cursor-pointer shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('photo') border-danger @enderror">
                        @error('photo')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Brand --}}
                    <div>
                        <label for="brand_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Brand
                        </label>
                        <select id="brand_id" name="brand_id"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('brand_id') border-danger @enderror">
                            <option value="">Select Brand</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                        @error('brand_id')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Category --}}
                    <div>
                        <label for="category_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Category
                        </label>
                        <select id="category_id" name="category_id"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('category_id') border-danger @enderror">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Model --}}
                    <div>
                        <label for="model" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Model
                        </label>
                        <input type="text"
                               id="model"
                               name="model"
                               x-model="model"
                               value="{{ old('model') }}"
                               placeholder="e.g. A2890, SM-S928B"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('model') border-danger @enderror">
                        @error('model')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Status
                        </label>
                        <select id="status" name="status" x-model="status"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('status') border-danger @enderror">
                            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        </select>
                        @error('status')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Section 2: Variant Specs & Barcode Scanner --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                        <x-icon name="sparkles" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Variant Specifications &amp; Barcode / IMEI</h2>
                        <p class="text-xs text-muted mt-0.5">Define storage/color variants and scan or enter a unique IMEI or EAN barcode.</p>
                    </div>
                </div>

                <div class="p-6 grid gap-5 sm:grid-cols-2">
                    {{-- Storage --}}
                    <div>
                        <label for="storage" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Storage
                        </label>
                        <input type="text"
                               id="storage"
                               name="storage"
                               x-model="storage"
                               value="{{ old('storage') }}"
                               placeholder="e.g. 256GB, 512GB, 8GB/256GB"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('storage') border-danger @enderror">
                        @error('storage')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Color --}}
                    <div>
                        <label for="color" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Color
                        </label>
                        <input type="text"
                               id="color"
                               name="color"
                               x-model="color"
                               value="{{ old('color') }}"
                               placeholder="e.g. Black, Blue, Natural Titanium"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('color') border-danger @enderror">
                        @error('color')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Barcode / IMEI Scanner Field --}}
                    <div class="sm:col-span-2" x-data="barcodeField()" @barcode-scanned.window="applyScan($event.detail.code)">
                        <label for="barcode" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Barcode / IMEI
                        </label>
                        <div class="flex items-center gap-2.5">
                            <input type="text"
                                   id="barcode"
                                   name="barcode"
                                   x-ref="input"
                                   value="{{ old('barcode') }}"
                                   placeholder="Scan barcode or type IMEI / EAN"
                                   @keydown.enter.prevent
                                   class="flex-1 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm font-mono text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('barcode') border-danger @enderror">
                            <button type="button"
                                    @click="startScan()"
                                    :class="scanning ? 'bg-warning text-on-brand' : 'bg-brand-500 hover:bg-brand-600 text-on-brand'"
                                    class="btn-scale inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs sm:text-sm font-semibold shadow-sm transition-colors shrink-0 cursor-pointer">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <path d="M4 5v14M8 5v14M12 5v14M16 5v14M20 5v14"/>
                                </svg>
                                <span x-text="scanning ? 'Scanning…' : 'Scan'">Scan</span>
                            </button>
                        </div>
                        <p x-show="scanning && !lastScan" x-cloak class="mt-1.5 text-xs font-medium text-brand-500">
                            Listening for barcode — scan with your USB/Bluetooth scanner…
                        </p>
                        <p x-show="lastScan" x-cloak class="mt-1.5 text-xs font-medium text-success">
                            Last scanned: <span x-text="lastScan" class="font-mono font-semibold"></span>
                        </p>
                        @error('barcode')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Section 3: Pricing, Opening Stock & Notes --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                        <x-icon name="taka" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Pricing, Stock &amp; Notes</h2>
                        <p class="text-xs text-muted mt-0.5">Set BDT purchase &amp; retail prices alongside opening stock quantity.</p>
                    </div>
                </div>

                <div class="p-6 grid gap-5 sm:grid-cols-2">
                    {{-- Purchase Price --}}
                    <div>
                        <label for="purchase_price" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Purchase Price (৳)
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-muted">৳</span>
                            <input type="number"
                                   id="purchase_price"
                                   name="purchase_price"
                                   x-model="purchasePrice"
                                   value="{{ old('purchase_price') }}"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   class="w-full rounded-xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-sm text-ink placeholder:text-muted tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('purchase_price') border-danger @enderror">
                        </div>
                        @error('purchase_price')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Sale Price --}}
                    <div>
                        <label for="sale_price" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Sale Price (৳)
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-muted">৳</span>
                            <input type="number"
                                   id="sale_price"
                                   name="sale_price"
                                   x-model="salePrice"
                                   value="{{ old('sale_price') }}"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   class="w-full rounded-xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-sm text-ink placeholder:text-muted tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('sale_price') border-danger @enderror">
                        </div>
                        @error('sale_price')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Opening Quantity --}}
                    <div>
                        <label for="quantity" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Opening Quantity
                        </label>
                        <input type="number"
                               id="quantity"
                               name="quantity"
                               x-model="quantity"
                               value="{{ old('quantity') }}"
                               step="1"
                               min="0"
                               placeholder="0"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('quantity') border-danger @enderror">
                        @error('quantity')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Unit --}}
                    <div>
                        <label for="unit" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Unit
                        </label>
                        <select id="unit" name="unit" x-model="unit"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('unit') border-danger @enderror">
                            @foreach(\App\Models\Unit::UNITS as $unitOption)
                                <option value="{{ $unitOption }}" @selected(old('unit', 'Pcs') === $unitOption)>{{ $unitOption }}</option>
                            @endforeach
                        </select>
                        @error('unit')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Note --}}
                    <div class="sm:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink">
                                Note
                            </label>
                            <span class="text-[11px] text-muted">Optional</span>
                        </div>
                        <textarea id="note"
                                  name="note"
                                  rows="3"
                                  placeholder="Additional product details, warranty info, or packaging notes..."
                                  class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-y @error('note') border-danger @enderror">{{ old('note') }}</textarea>
                        @error('note')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Form Footer Action Bar --}}
                <div class="border-t border-line px-6 py-4 bg-canvas/30 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ $cancelUrl }}"
                       class="btn-scale inline-flex items-center justify-center rounded-xl border border-line bg-surface px-5 py-2.5 text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>{{ $submitLabel }}</span>
                    </button>
                </div>
            </div>
