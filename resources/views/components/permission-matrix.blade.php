{{-- Permission matrix grouped by module with a "select all" toggle per group.
     Usage: <x-permission-matrix :permissions="$permissions" :selected="$selected" /> --}}
@props(['permissions', 'selected' => []])

@php
    $groups = $permissions->groupBy(fn ($p) => str($p->name)->before('.')->toString());
@endphp

<div class="grid-2" style="align-items:start;">
    @foreach($groups as $module => $items)
        <div class="card" x-data="{ all: {{ in_array($module, collect($selected)->map(fn ($n) => str($n)->before('.')->toString())->unique()->all()) ? 'true' : 'false' }} }">
            <div class="card-header" style="padding:12px 16px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" class="form-checkbox" x-model="all"
                           @change="document.querySelectorAll('[data-perm-group=\'{{ $module }}\']').forEach(b => b.checked = all)">
                    <span class="card-title" style="font-size:.84rem;">{{ ucwords(str_replace('-', ' ', $module)) }}</span>
                </label>
                <span class="badge badge-neutral">{{ $items->count() }}</span>
            </div>
            <div class="card-body" style="padding:12px 16px;">
                <div style="display:flex;flex-direction:column;gap:6px;">
                    @foreach($items as $permission)
                        <label style="display:flex;align-items:center;gap:8px;font-size:.83rem;cursor:pointer;">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                   class="form-checkbox" data-perm-group="{{ $module }}"
                                   @checked(in_array($permission->name, $selected))>
                            {{ str($permission->name)->after('.')->replace('-', ' ')->title() }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
