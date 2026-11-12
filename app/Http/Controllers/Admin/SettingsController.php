<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * The settings grouped by tab, in display order.
     *
     * @var array<string, string>
     */
    private const GROUPS = [
        'company' => 'Company',
        'numbering' => 'Document Numbering',
        'system' => 'System',
    ];

    /**
     * Display the settings grouped by tab.
     */
    public function index(): View
    {
        $groups = $this->groupedSettings();

        return view('admin.settings.index', [
            'groups' => $groups,
            'tabs' => self::GROUPS,
        ]);
    }

    /**
     * Update the settings in storage.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ($validated['settings'] as $key => $value) {
            Setting::set($key, $value, $request->user()->id);
        }

        AuditService::log('updated', null, [], $validated['settings'], 'Application settings updated.');

        return back()->with('success', 'Settings updated successfully.');
    }

    /**
     * Load all settings grouped by their tab key.
     *
     * @return Collection<string, Collection<int, Setting>>
     */
    private function groupedSettings(): Collection
    {
        return Setting::all()
            ->groupBy('group')
            ->sortBy(fn ($items, $group) => array_search($group, array_keys(self::GROUPS), true));
    }
}
