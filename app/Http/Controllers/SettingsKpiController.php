<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsKpiController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Settings/Kpi', [
            'thresholdPreview' => [
                [
                    'range' => '0.00 - 0.24',
                    'direct' => 3,
                    'indirect' => 7,
                ],
                [
                    'range' => '0.25 - 0.49',
                    'direct' => 4,
                    'indirect' => 8,
                ],
                [
                    'range' => '0.50 - 1.00',
                    'direct' => 5,
                    'indirect' => 9,
                ],
            ],
        ]);
    }
}
