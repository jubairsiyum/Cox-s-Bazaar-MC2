@props(['action', 'message' => 'Are you sure you want to delete this record? This action cannot be undone.', 'method' => 'DELETE', 'confirmText' => 'Delete', 'label' => 'Delete'])

<span x-data="{ open: false }">
    <button type="button"
            @click="open = true"
            class="btn btn-danger btn-sm">
        {{ $label }}
    </button>

    <div class="modal-backdrop" x-show="open" x-cloak
         @keydown.escape.window="open = false">
        <div class="modal" style="max-width:420px" @click.stop>
            <div class="modal-header">
                <span class="modal-title">Confirm Action</span>
                <button type="button" @click="open=false"
                        style="background:transparent;border:none;cursor:pointer;color:var(--color-text-muted);">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size:.87rem;color:var(--color-text-secondary);margin:0;">{{ $message }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" @click="open=false" class="btn btn-secondary">Cancel</button>
                <form action="{{ $action }}" method="POST" style="display:inline;">
                    @csrf
                    @method($method)
                    <button type="submit" class="btn btn-danger">{{ $confirmText }}</button>
                </form>
            </div>
        </div>
    </div>
</span>
