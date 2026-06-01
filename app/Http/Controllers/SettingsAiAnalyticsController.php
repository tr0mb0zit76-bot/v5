<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiUsageAnalyticsService;
use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsAiAnalyticsController extends Controller
{
    public function __construct(
        private readonly AiUsageAnalyticsService $analytics,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_unless(RoleAccess::canViewAiAnalytics($request->user()), 403);

        $days = max(1, min(365, (int) $request->integer('days', (int) config('ai.analytics.insights_default_days', 30))));

        return Inertia::render('Settings/AiAnalytics', [
            'days' => $days,
            'insights' => $this->analytics->insights($days, 25, 20),
            'periodOptions' => [
                ['value' => 7, 'label' => '7 дней'],
                ['value' => 30, 'label' => '30 дней'],
                ['value' => 90, 'label' => '90 дней'],
            ],
            'salesBookUrl' => route('sales-assistant.book'),
        ]);
    }
}
