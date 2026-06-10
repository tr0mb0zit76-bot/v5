<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKpiDeductionRuleRequest;
use App\Http\Requests\StoreSalaryCoefficientRequest;
use App\Http\Requests\StoreSalaryPayoutRequest;
use App\Http\Requests\StoreSalaryPeriodRequest;
use App\Http\Requests\StoreSalaryUnscopedAdvanceRequest;
use App\Http\Requests\UpdateKpiDeductionRuleRequest;
use App\Http\Requests\UpdateKpiSettingsRequest;
use App\Http\Requests\UpdateSalaryCoefficientRequest;
use App\Models\KpiDeductionRule;
use App\Models\SalaryCoefficient;
use App\Models\SalaryPeriod;
use App\Models\User;
use App\Services\KpiConfigurationService;
use App\Services\SalaryPayrollService;
use App\Support\KpiDeductionCarrierRule;
use App\Support\KpiDeductionRuleAmount;
use App\Support\KpiDeductionRuleDescription;
use App\Support\PaymentFormDictionary;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SettingsKpiController extends Controller
{
    public function __construct(
        private readonly KpiConfigurationService $kpiConfigurationService,
        private readonly SalaryPayrollService $salaryPayrollService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsMotivation($request->user()), 403);

        return Inertia::render('Settings/Kpi', [
            'bonusMultiplier' => $this->kpiConfigurationService->getBonusMultiplier(),
            'insuranceMultiplier' => $this->kpiConfigurationService->getInsuranceMultiplier(),
            'customRulesCutoffDate' => KpiDeductionRule::CUSTOM_RULES_CUTOFF_DATE,
            'paymentFormOptions' => PaymentFormDictionary::options(),
            'carrierRuleOptions' => collect(KpiDeductionCarrierRule::values())
                ->map(fn (string $value): array => [
                    'value' => $value,
                    'label' => KpiDeductionCarrierRule::label($value),
                ])
                ->values()
                ->all(),
            'deductionRules' => $this->deductionRulesPayload(),
        ]);
    }

    public function salaryIndex(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsMotivation($request->user()), 403);

        return Inertia::render('Settings/MotivationSalary', [
            ...$this->salaryPagePayload($request),
            'salary_module' => 'settings',
        ]);
    }

    public function financeSalaryIndex(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessFinanceSalary($request->user()), 403);

        return Inertia::render('Settings/MotivationSalary', [
            ...$this->salaryPagePayload($request),
            'salary_module' => 'finance',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function salaryPagePayload(Request $request): array
    {
        $prunedPeriodsCount = $this->salaryPayrollService->pruneDuplicateDraftPeriods();
        $periods = $this->salaryPayrollService->periods();
        $activePeriod = $periods->firstWhere('id', (int) $request->integer('salary_period_id')) ?? $periods->first();
        $selectedSalaryUserId = $request->filled('salary_user_id')
            ? (int) $request->integer('salary_user_id')
            : null;

        return [
            'employees' => $this->employeesPayload(),
            'salaryCoefficients' => $this->salaryCoefficientsPayload(),
            'salaryPeriods' => $periods->map(fn (SalaryPeriod $period): array => [
                'id' => $period->id,
                'period_start' => optional($period->period_start)?->toDateString(),
                'period_end' => optional($period->period_end)?->toDateString(),
                'period_type' => $period->period_type,
                'status' => $period->status,
                'notes' => $period->notes,
                'can_delete' => $this->salaryPayrollService->canDeletePeriod($period),
            ])->values(),
            'salaryPeriodsPrunedCount' => $prunedPeriodsCount > 0 ? $prunedPeriodsCount : null,
            'activeSalaryPeriodId' => $activePeriod?->id,
            'activeSalaryUserId' => $selectedSalaryUserId,
            'salaryPeriodUsers' => $this->salaryPayrollService->userSummariesForPeriod($activePeriod, $selectedSalaryUserId),
            'salaryPeriodOrderRows' => $this->salaryPayrollService->orderRowsForPeriod($activePeriod, $selectedSalaryUserId),
        ];
    }

    public function update(UpdateKpiSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->kpiConfigurationService->saveBonusMultiplier((float) $validated['bonus_multiplier']);
        $this->kpiConfigurationService->saveInsuranceMultiplier((float) $validated['insurance_multiplier']);

        return to_route('settings.motivation.kpi');
    }

    public function storeDeductionRule(StoreKpiDeductionRuleRequest $request): RedirectResponse
    {
        KpiDeductionRule::query()->create($this->normalizeDeductionRuleInput($request->validated()));

        return to_route('settings.motivation.kpi');
    }

    public function updateDeductionRule(
        UpdateKpiDeductionRuleRequest $request,
        KpiDeductionRule $kpiDeductionRule,
    ): RedirectResponse {
        $kpiDeductionRule->update($this->normalizeDeductionRuleInput($request->validated()));

        return to_route('settings.motivation.kpi');
    }

    public function destroyDeductionRule(KpiDeductionRule $kpiDeductionRule): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsMotivation(request()->user()), 403);

        $kpiDeductionRule->delete();

        return to_route('settings.motivation.kpi');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeDeductionRuleInput(array $input): array
    {
        $input['customer_payment_form'] = filled($input['customer_payment_form'] ?? null)
            ? (string) $input['customer_payment_form']
            : null;
        $input['customer_positive_vat_required'] = (bool) ($input['customer_positive_vat_required'] ?? false);
        $input['is_active'] = (bool) ($input['is_active'] ?? true);
        $input['carrier_payment_forms'] = collect($input['carrier_payment_forms'] ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->values()
            ->all();

        if ($input['carrier_payment_forms'] === []) {
            $input['carrier_payment_forms'] = null;
        }

        foreach ([
            'customer_vat_rate_percent',
            'carrier_vat_rate_percent',
            'deduction_secondary_percent',
            'margin_supplement_percent',
            'margin_supplement_carrier_vat_percent',
            'effective_to',
        ] as $nullableField) {
            if (! filled($input[$nullableField] ?? null)) {
                $input[$nullableField] = null;
            }
        }

        return $input;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deductionRulesPayload(): array
    {
        if (! Schema::hasTable('kpi_deduction_rules')) {
            return [];
        }

        return KpiDeductionRule::query()
            ->orderByDesc('priority')
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->get()
            ->map(fn (KpiDeductionRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'priority' => $rule->priority,
                'customer_payment_form' => $rule->customer_payment_form,
                'customer_positive_vat_required' => $rule->customer_positive_vat_required,
                'customer_vat_rate_percent' => $rule->customer_vat_rate_percent !== null
                    ? (float) $rule->customer_vat_rate_percent
                    : null,
                'carrier_rule' => $rule->carrier_rule,
                'carrier_payment_forms' => $rule->carrier_payment_forms ?? [],
                'carrier_vat_rate_percent' => $rule->carrier_vat_rate_percent !== null
                    ? (float) $rule->carrier_vat_rate_percent
                    : null,
                'deduction_primary_percent' => (float) $rule->deduction_primary_percent,
                'deduction_secondary_percent' => $rule->deduction_secondary_percent !== null
                    ? (float) $rule->deduction_secondary_percent
                    : null,
                'margin_supplement_percent' => $rule->margin_supplement_percent !== null
                    ? (float) $rule->margin_supplement_percent
                    : null,
                'margin_supplement_carrier_vat_percent' => $rule->margin_supplement_carrier_vat_percent !== null
                    ? (float) $rule->margin_supplement_carrier_vat_percent
                    : null,
                'effective_from' => optional($rule->effective_from)?->toDateString(),
                'effective_to' => optional($rule->effective_to)?->toDateString(),
                'is_active' => $rule->is_active,
                'description' => KpiDeductionRuleDescription::build($rule),
                'deduction_label' => KpiDeductionRuleAmount::ratesLabel($rule),
            ])
            ->values()
            ->all();
    }

    public function storeSalaryCoefficient(StoreSalaryCoefficientRequest $request): RedirectResponse
    {
        SalaryCoefficient::query()->create($request->validated());

        return $this->salaryRedirect($request);
    }

    public function updateSalaryCoefficient(
        UpdateSalaryCoefficientRequest $request,
        SalaryCoefficient $salaryCoefficient
    ): RedirectResponse {
        $salaryCoefficient->update($request->validated());

        return $this->salaryRedirect($request);
    }

    public function destroySalaryCoefficient(Request $request, SalaryCoefficient $salaryCoefficient): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);

        $salaryCoefficient->delete();

        return $this->salaryRedirect($request);
    }

    public function storeSalaryPeriod(StoreSalaryPeriodRequest $request): RedirectResponse
    {
        $period = $this->salaryPayrollService->createPeriod($request->validated(), $request->user()?->id);
        $this->salaryPayrollService->recalculatePeriod($period);

        return $this->salaryRedirect($request, ['salary_period_id' => $period->id]);
    }

    public function recalculateSalaryPeriod(Request $request, SalaryPeriod $salaryPeriod): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);
        $this->salaryPayrollService->recalculatePeriod($salaryPeriod);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    public function approveSalaryPeriod(Request $request, SalaryPeriod $salaryPeriod): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);
        $this->salaryPayrollService->approvePeriod($salaryPeriod, $request->user()?->id);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    public function closeSalaryPeriod(Request $request, SalaryPeriod $salaryPeriod): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);
        $this->salaryPayrollService->closePeriod($salaryPeriod, $request->user()?->id);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    public function destroySalaryPeriod(Request $request, SalaryPeriod $salaryPeriod): RedirectResponse
    {
        $this->assertSalaryModuleAccess($request);

        try {
            $this->salaryPayrollService->deletePeriod($salaryPeriod);
        } catch (\RuntimeException $exception) {
            return $this->salaryRedirect($request, [
                'salary_period_id' => $request->integer('salary_period_id') ?: null,
            ])->withErrors(['period' => $exception->getMessage()]);
        }

        return $this->salaryRedirect($request)->with('success', 'Зарплатный период удалён.');
    }

    public function storeSalaryPayout(
        StoreSalaryPayoutRequest $request,
        SalaryPeriod $salaryPeriod
    ): RedirectResponse {
        $this->salaryPayrollService->createPayout($salaryPeriod, $request->validated(), $request->user()?->id);

        return $this->salaryRedirect($request, ['salary_period_id' => $salaryPeriod->id]);
    }

    public function storeSalaryAdvanceWithoutPeriod(StoreSalaryUnscopedAdvanceRequest $request): RedirectResponse
    {
        $this->salaryPayrollService->createUnscopedAdvancePayout($request->validated(), $request->user()?->id);

        return $this->salaryRedirect($request, []);
    }

    private function assertSalaryModuleAccess(Request $request): void
    {
        if ($request->routeIs('finance.salary.*')) {
            abort_unless(RoleAccess::canAccessFinanceSalary($request->user()), 403);

            return;
        }

        abort_unless(RoleAccess::canAccessSettingsMotivation($request->user()), 403);
    }

    /**
     * @param  array<string, scalar|null>  $parameters
     */
    private function salaryRedirect(Request $request, array $parameters = []): RedirectResponse
    {
        $routeName = $request->routeIs('finance.salary.*')
            ? 'finance.salary.index'
            : 'settings.motivation.salary';

        $salaryUserId = $request->filled('salary_user_id')
            ? (int) $request->integer('salary_user_id')
            : null;

        if ($salaryUserId !== null && ! array_key_exists('salary_user_id', $parameters)) {
            $parameters['salary_user_id'] = $salaryUserId;
        }

        return to_route($routeName, $parameters);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeesPayload(): array
    {
        return User::query()
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'roles.name as role_name',
            ])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_name' => $user->role_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function salaryCoefficientsPayload(): array
    {
        return SalaryCoefficient::query()
            ->with('manager:id,name,email')
            ->orderByDesc('effective_from')
            ->orderBy('manager_id')
            ->get()
            ->map(fn (SalaryCoefficient $coefficient): array => [
                'id' => $coefficient->id,
                'manager_id' => $coefficient->manager_id,
                'manager_name' => $coefficient->manager?->name,
                'base_salary' => $coefficient->base_salary,
                'bonus_percent' => $coefficient->bonus_percent,
                'effective_from' => optional($coefficient->effective_from)?->toDateString(),
                'effective_to' => optional($coefficient->effective_to)?->toDateString(),
                'is_active' => $coefficient->is_active,
            ])
            ->values()
            ->all();
    }
}
