<script>
function productFormPreview(initial) {
    return {
        name: initial.name,
        storage: initial.storage,
        color: initial.color,
        model: initial.model,
        purchasePrice: initial.purchasePrice,
        salePrice: initial.salePrice,
        quantity: initial.quantity,
        unit: initial.unit,
        status: initial.status,

        displayName() {
            const base = this.name.trim() || 'New Product Name';
            const parts = [this.storage.trim(), this.color.trim()].filter(Boolean);
            return parts.length ? `${base} (${parts.join(' / ')})` : base;
        },

        profit() {
            const sale = parseFloat(this.salePrice) || 0;
            const purchase = parseFloat(this.purchasePrice) || 0;
            return sale - purchase;
        },

        marginPct() {
            const sale = parseFloat(this.salePrice) || 0;
            if (sale <= 0) return 0;
            return (this.profit() / sale) * 100;
        },

        formatMoney(value) {
            const num = parseFloat(value) || 0;
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(num);
            }
            const sign = num < 0 ? '-' : '';
            return `${sign}৳${Math.abs(num).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
    };
}
</script>
