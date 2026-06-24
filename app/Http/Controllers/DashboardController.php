<?php

namespace App\Http\Controllers;

use App\Services\Commercial\LeadAttentionQueueService;
use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardMetricsService $dashboardMetricsService,
        LeadAttentionQueueService $leadAttentionQueue,
    ): Response {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFromCarbon = Carbon::parse($validated['date_from'] ?? now()->startOfYear()->toDateString())->startOfDay();
        $dateToCarbon = Carbon::parse($validated['date_to'] ?? now()->endOfYear()->toDateString())->endOfDay();

        if ($dateFromCarbon->gt($dateToCarbon)) {
            $dateToCarbon = $dateFromCarbon->copy()->endOfYear();
        }

        $dateFrom = $dateFromCarbon->toDateString();
        $dateTo = $dateToCarbon->toDateString();

        return Inertia::render('Dashboard', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'metrics' => $request->user() === null
                ? [
                    'total_orders' => 0,
                    'period_delta' => 0,
                    'weekly_client_returns' => 0,
                    'weekly_client_returns_overdue' => 0,
                    'tasks_today' => 0,
                    'tasks_overdue' => 0,
                    'plan_completion_percent' => 0.0,
                    'tasks_on_time_percent' => 0.0,
                    'tasks_sla_breached_open' => 0,
                    'margin_rank' => '—',
                    'finance_chart' => [],
                    'finance_flow_mode' => 'hidden',
                    'show_dual_metrics' => false,
                    'metrics_scope' => 'own',
                    'metrics_own' => null,
                ]
                : $dashboardMetricsService->forDashboard($request->user(), $dateFrom, $dateTo),
            'leadAttentionQueue' => $leadAttentionQueue->queueForUser(
                $request->user(),
                (int) config('commercial_nudges.attention_queue_limit', 15),
            ),
        ]);
    }
}
