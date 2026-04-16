<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardMetricsService $dashboardMetricsService): Response
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'] ?? now()->startOfMonth()->toDateString())->toDateString();
        $dateTo = Carbon::parse($validated['date_to'] ?? now()->endOfMonth()->toDateString())->toDateString();

        return Inertia::render('Dashboard', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'metrics' => $request->user() === null
                ? [
                    'total_orders' => 0,
                    'direct_orders' => 0,
                    'direct_share_percent' => 0,
                    'period_delta' => 0,
                    'weekly_client_returns' => 0,
                    'tasks_today' => 0,
                    'tasks_overdue' => 0,
                    'plan_completion_percent' => 0.0,
                    'tasks_on_time_percent' => 0.0,
                    'tasks_sla_breached_open' => 0,
                    'margin_rank' => '—',
                ]
                : $dashboardMetricsService->forManager($request->user()->id, $dateFrom, $dateTo),
        ]);
    }
}
