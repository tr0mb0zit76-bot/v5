<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiUsageAnalyticsService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsAiAnalyticsController extends Controller
{
    public function __construct(
        private readonly AiUsageAnalyticsService $analytics,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canViewAiAnalytics($request->user()), 403);

        $days = $this->resolveDays($request);

        return Inertia::render('Settings/AiAnalytics', [
            'days' => $days,
            'insights' => $this->analytics->insights($days, 25, 20),
            'periodOptions' => $this->periodOptions(),
            'salesBookUrl' => route('sales-assistant.book'),
        ]);
    }

    public function dismissSalesBookGap(Request $request, int $event): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RoleAccess::canViewAiAnalytics($user), 403);
        abort_unless($this->analytics->dismissSalesBookGap($event, $user), 404);

        return redirect()
            ->route('settings.ai-analytics', ['days' => $this->resolveDays($request)])
            ->with('flash', [
                'type' => 'success',
                'message' => 'Запрос убран из списка пробелов Книги продаж.',
            ]);
    }

    private function resolveDays(Request $request): int
    {
        return max(1, min(365, (int) $request->integer('days', (int) config('ai.analytics.insights_default_days', 30))));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function periodOptions(): array
    {
        return [
            ['value' => 7, 'label' => '7 дней'],
            ['value' => 30, 'label' => '30 дней'],
            ['value' => 90, 'label' => '90 дней'],
        ];
    }
}
