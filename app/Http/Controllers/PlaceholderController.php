<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaceholderController extends Controller
{
    /**
     * Render a "module coming soon" page for modules that are planned but not
     * yet implemented. Replaced module-by-module as they are built.
     */
    public function __invoke(Request $request, string $module): View
    {
        return view('placeholder', [
            'module' => $module,
        ]);
    }
}
