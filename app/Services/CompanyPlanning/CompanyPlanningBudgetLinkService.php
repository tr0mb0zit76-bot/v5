<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Support\CompanyPlanningCatalog;
use Illuminate\Support\Facades\Schema;

final class CompanyPlanningBudgetLinkService
{
    /**
     * @return list<array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     status_label: string,
     *     management_expense_category_id: int|null,
     *     expense_category_name: string|null,
     *     planned_budget_amount: float|null,
     *     budget_currency: string,
     *     show_url: string
     * }>
     */
    public function initiativesLinkedToCategories(): array
    {
        if (! Schema::hasTable('company_initiatives')) {
            return [];
        }

        $statusLabels = CompanyPlanningCatalog::initiativeStatusLabels();

        return CompanyInitiative::query()
            ->with('managementExpenseCategory:id,name')
            ->whereNotNull('management_expense_category_id')
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('title')
            ->get()
            ->map(fn (CompanyInitiative $initiative): array => [
                'id' => (int) $initiative->id,
                'title' => (string) $initiative->title,
                'status' => (string) $initiative->status,
                'status_label' => $statusLabels[$initiative->status] ?? $initiative->status,
                'management_expense_category_id' => $initiative->management_expense_category_id !== null
                    ? (int) $initiative->management_expense_category_id
                    : null,
                'expense_category_name' => $initiative->managementExpenseCategory?->name,
                'planned_budget_amount' => $initiative->planned_budget_amount !== null
                    ? (float) $initiative->planned_budget_amount
                    : null,
                'budget_currency' => strtoupper((string) ($initiative->budget_currency ?? 'RUB')),
                'show_url' => route('company-planning.show', $initiative),
            ])
            ->values()
            ->all();
    }
}
