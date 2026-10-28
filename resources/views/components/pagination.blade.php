@props(['paginator', 'total' => null])

<div class="pagination-wrap">
    <div>
        Showing
        <strong>{{ $paginator->firstItem() ?? 0 }}</strong>–<strong>{{ $paginator->lastItem() ?? 0 }}</strong>
        of <strong>{{ $total ?? $paginator->total() }}</strong> records
    </div>
    {{ $paginator->links() }}
</div>
