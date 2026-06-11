<?php

namespace App\Services\Mcp;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementReconcileRule;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingAllocationService;
use App\Services\ManagementAccounting\ManagementAccountingAnalyticsService;
use App\Services\ManagementAccounting\ManagementAccountingMatchingService;
use App\Services\ManagementAccounting\ManagementReconcileRuleService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly ManagementAccountingAnalyticsService $analytics,
        private readonly ManagementAccountingMatchingService $matching,
        private readonly ManagementAccountingAllocationService $allocation,
        private readonly ManagementReconcileRuleService $reconcileRules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function context(User $user): array
    {
        $this->access->requireManagementAccounting($user);

        return [
            'can_management_accounting' => true,
            'categories_count' => ManagementExpenseCategory::query()->where('is_active', true)->count(),
            'pending_imports' => ManagementStatementImport::query()
                ->when(! $user->isAdmin(), fn ($q) => $q->where('imported_by', $user->id))
                ->whereColumn('lines_allocated', '<', 'lines_count')
                ->count(),
            'active_reconcile_rules' => Schema::hasTable('management_reconcile_rules')
                ? ManagementReconcileRule::query()->where('is_active', true)->count()
                : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listImports(User $user, int $limit = 20): array
    {
        $this->access->requireManagementAccounting($user);

        return ManagementStatementImport::query()
            ->with(['bankAccount:id,bank_name,account_mask', 'importer:id,name'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('imported_by', $user->id))
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(static fn (ManagementStatementImport $import): array => [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'status' => $import->status,
                'period_from' => $import->period_from?->toDateString(),
                'period_to' => $import->period_to?->toDateString(),
                'lines_count' => $import->lines_count,
                'lines_allocated' => $import->lines_allocated,
                'total_in' => (float) $import->total_in,
                'total_out' => (float) $import->total_out,
                'bank_account' => $import->bankAccount === null ? null : [
                    'bank_name' => $import->bankAccount->bank_name,
                    'account_mask' => $import->bankAccount->account_mask,
                ],
                'importer_name' => $import->importer?->name,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLines(User $user, int $importId, ?string $status = null, int $limit = 50): array
    {
        $import = $this->access->findAccessibleImport($user, $importId);

        $query = ManagementStatementLine::query()
            ->where('import_id', $import->id)
            ->with([
                'suggestedOrder:id,order_number',
                'suggestedPaymentSchedule:id,party,amount',
                'suggestedCategory:id,name,code',
                'suggestedUser:id,name',
            ])
            ->orderBy('operation_date')
            ->orderBy('row_number');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (ManagementStatementLine $line): array => $this->linePayload($line))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestLine(User $user, int $lineId): array
    {
        $line = $this->access->findAccessibleLine($user, $lineId);

        if ($line->status === 'allocated') {
            return [
                'line' => $this->linePayload($line),
                'already_allocated' => true,
            ];
        }

        $suggestion = $this->matching->suggestForLine($line);
        $candidates = $suggestion['suggested_candidates'] ?? [];

        return [
            'line' => $this->linePayload($line),
            'suggestion' => $suggestion,
            'candidates' => $candidates,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function allocateLine(User $user, int $lineId, array $payload): array
    {
        $line = $this->access->findAccessibleLine($user, $lineId);

        if ($line->status === 'allocated') {
            throw new AuthenticationException('Строка уже разнесена.');
        }

        $allocated = $this->allocation->allocateLine($line, [
            'allocation_type' => (string) ($payload['allocation_type'] ?? 'category'),
            'category_id' => $payload['category_id'] ?? null,
            'payment_schedule_id' => $payload['payment_schedule_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ], $user);

        $rule = null;
        $rememberKeyword = isset($payload['remember_keyword']) ? trim((string) $payload['remember_keyword']) : '';
        if ($rememberKeyword !== '' && Schema::hasTable('management_reconcile_rules')) {
            $rule = $this->reconcileRules->rememberFromAllocatedLine(
                $user,
                $allocated,
                $rememberKeyword,
                isset($payload['remember_notes']) ? (string) $payload['remember_notes'] : null,
            );
        }

        return [
            'line' => $this->linePayload($allocated),
            'remembered_rule' => $rule === null ? null : [
                'id' => $rule->id,
                'keyword' => $rule->keyword,
                'allocation_type' => $rule->allocation_type,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(User $user, string $periodType, ?string $periodAnchor = null): array
    {
        $this->access->requireManagementAccounting($user);

        return $this->analytics->build($periodType, $periodAnchor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCategories(User $user): array
    {
        $this->access->requireManagementAccounting($user);

        return ManagementExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'kind', 'is_system'])
            ->map(static fn (ManagementExpenseCategory $category): array => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'kind' => $category->kind,
                'is_system' => $category->is_system,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function rememberRule(User $user, array $payload): ManagementReconcileRule
    {
        $this->access->requireManagementAccounting($user);

        return $this->reconcileRules->remember($user, [
            'keyword' => (string) $payload['keyword'],
            'direction' => $payload['direction'] ?? null,
            'allocation_type' => (string) $payload['allocation_type'],
            'category_id' => $payload['category_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'payment_schedule_id' => $payload['payment_schedule_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'priority' => $payload['priority'] ?? null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRules(User $user, int $limit = 30): array
    {
        $this->access->requireManagementAccounting($user);

        if (! Schema::hasTable('management_reconcile_rules')) {
            return [];
        }

        return ManagementReconcileRule::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('times_applied')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(static fn (ManagementReconcileRule $rule): array => [
                'id' => $rule->id,
                'keyword' => $rule->keyword,
                'direction' => $rule->direction,
                'allocation_type' => $rule->allocation_type,
                'category_id' => $rule->category_id,
                'user_id' => $rule->user_id,
                'order_number' => $rule->order_number,
                'payment_schedule_id' => $rule->payment_schedule_id,
                'notes' => $rule->notes,
                'priority' => $rule->priority,
                'times_applied' => $rule->times_applied,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function linePayload(ManagementStatementLine $line): array
    {
        return [
            'id' => $line->id,
            'import_id' => $line->import_id,
            'operation_date' => $line->operation_date?->toDateString(),
            'direction' => $line->direction,
            'amount' => (float) $line->amount,
            'currency' => $line->currency,
            'description' => $line->description,
            'status' => $line->status,
            'match_type' => $line->match_type,
            'match_confidence' => $line->match_confidence,
            'match_notes' => $line->match_notes,
            'suggested_order' => $line->suggestedOrder === null ? null : [
                'id' => $line->suggestedOrder->id,
                'order_number' => $line->suggestedOrder->order_number,
            ],
            'suggested_payment_schedule' => $line->suggestedPaymentSchedule === null ? null : [
                'id' => $line->suggestedPaymentSchedule->id,
                'party' => $line->suggestedPaymentSchedule->party,
                'amount' => (float) $line->suggestedPaymentSchedule->amount,
            ],
            'suggested_category' => $line->suggestedCategory === null ? null : [
                'id' => $line->suggestedCategory->id,
                'name' => $line->suggestedCategory->name,
                'code' => $line->suggestedCategory->code,
            ],
            'suggested_user' => $line->suggestedUser === null ? null : [
                'id' => $line->suggestedUser->id,
                'name' => $line->suggestedUser->name,
            ],
        ];
    }
}
