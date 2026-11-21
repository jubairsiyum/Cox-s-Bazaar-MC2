<div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
    <div class="flex items-center justify-between pb-4 border-b border-line mb-6">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-ink">Change Password</h2>
            <p class="text-xs text-muted mt-0.5">Your login password. Your supplier contact email is separate and does not change your login.</p>
        </div>
        <span class="grid size-9 place-items-center rounded-2xl bg-soft text-brand-500">
            <x-icon name="key" class="size-4.5" />
        </span>
    </div>

    <form method="POST" action="{{ route('suppliers.my-profile.password') }}" class="space-y-4 max-w-2xl">
        @csrf
        @method('PATCH')

        <div>
            <label for="supplier_current_password" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                Current Password <span class="text-danger">*</span>
            </label>
            <input type="password" id="supplier_current_password" name="current_password" required autocomplete="current-password"
                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('current_password', 'updateSupplierPassword') border-danger @enderror">
            @error('current_password', 'updateSupplierPassword')
                <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="supplier_new_password" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    New Password <span class="text-danger">*</span>
                </label>
                <input type="password" id="supplier_new_password" name="password" required autocomplete="new-password"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('password', 'updateSupplierPassword') border-danger @enderror">
                @error('password', 'updateSupplierPassword')
                    <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="supplier_password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Confirm New Password <span class="text-danger">*</span>
                </label>
                <input type="password" id="supplier_password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all">
            </div>
        </div>

        <div class="pt-3 flex justify-end">
            <button type="submit"
                    class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                <x-icon name="lock" class="size-4" />
                <span>Update Password</span>
            </button>
        </div>
    </form>
</div>
