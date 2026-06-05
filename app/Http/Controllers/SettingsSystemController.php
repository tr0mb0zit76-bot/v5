<?php

namespace App\Http\Controllers;

use App\Enums\OrderNumberSegmentType;
use App\Enums\OrderNumberSequenceScope;
use App\Http\Requests\StoreOrderNumberingRuleRequest;
use App\Http\Requests\UpdateOrderNumberingRuleRequest;
use App\Models\Contractor;
use App\Models\OrderNumberingRule;
use App\Services\OrderNumberingService;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsSystemController extends Controller
{
    public function __construct(
        private readonly OrderNumberingService $orderNumbering,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        return Inertia::render('Settings/System/Index', [
            'sections' => [
                [
                    'key' => 'order-numbering',
                    'title' => 'Автонумератор',
                    'description' => 'Шаблоны номеров заявок по своей компании: префикс, тело, суффикс и шифр для мастера заказа.',
                    'href' => route('settings.system.order-numbering'),
                    'icon' => 'hash',
                    'accent' => 'slate',
                ],
            ],
        ]);
    }

    public function orderNumbering(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $rules = OrderNumberingRule::query()
            ->with('ownCompany:id,name')
            ->orderBy('cipher')
            ->get()
            ->map(fn (OrderNumberingRule $rule): array => $this->serializeRule($rule))
            ->all();

        $ownCompanies = Contractor::query()
            ->where('is_own_company', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Contractor $company): array => [
                'id' => $company->id,
                'name' => $company->name,
            ])
            ->all();

        return Inertia::render('Settings/System/OrderNumbering', [
            'rules' => $rules,
            'ownCompanies' => $ownCompanies,
            'segmentTypeOptions' => OrderNumberSegmentType::options(),
            'sequenceScopeOptions' => OrderNumberSequenceScope::options(),
        ]);
    }

    public function store(StoreOrderNumberingRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $rule = OrderNumberingRule::query()->create($validated);

        return redirect()
            ->route('settings.system.order-numbering')
            ->with('success', 'Правило «'.$rule->cipher.'» сохранено.');
    }

    public function update(UpdateOrderNumberingRuleRequest $request, OrderNumberingRule $orderNumberingRule): RedirectResponse
    {
        $orderNumberingRule->update($request->validated());

        return redirect()
            ->route('settings.system.order-numbering')
            ->with('success', 'Правило «'.$orderNumberingRule->cipher.'» обновлено.');
    }

    public function destroy(Request $request, OrderNumberingRule $orderNumberingRule): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $cipher = $orderNumberingRule->cipher;
        $orderNumberingRule->delete();

        return redirect()
            ->route('settings.system.order-numbering')
            ->with('success', 'Правило «'.$cipher.'» удалено.');
    }

    public function preview(Request $request): JsonResponse
    {
        abort_unless(RoleAccess::canAccessSettingsSystem($request->user()), 403);

        $validated = $request->validate([
            'separator' => ['required', 'string', 'max:3'],
            'prefix_type' => ['required', 'string'],
            'prefix_value' => ['nullable', 'string', 'max:64'],
            'body_type' => ['required', 'string'],
            'body_value' => ['nullable', 'string', 'max:64'],
            'suffix_type' => ['required', 'string'],
            'suffix_value' => ['nullable', 'string', 'max:64'],
            'sequence_pad' => ['required', 'integer', 'min:0', 'max:8'],
            'sequence_scope' => ['required', 'string'],
        ]);

        $rule = new OrderNumberingRule([
            ...$validated,
            'cipher' => 'preview',
            'own_company_id' => 0,
            'prefix_type' => $validated['prefix_type'],
            'body_type' => $validated['body_type'],
            'suffix_type' => $validated['suffix_type'],
            'sequence_scope' => $validated['sequence_scope'],
        ]);

        $rule->prefix_type = OrderNumberSegmentType::from($validated['prefix_type']);
        $rule->body_type = OrderNumberSegmentType::from($validated['body_type']);
        $rule->suffix_type = OrderNumberSegmentType::from($validated['suffix_type']);
        $rule->sequence_scope = OrderNumberSequenceScope::from($validated['sequence_scope']);

        $at = Carbon::now();
        $sampleSequence = 1;

        $manager = $request->user();

        return response()->json([
            'sample' => $this->orderNumbering->composeNumber($rule, $sampleSequence, $at, $manager),
            'sample_next' => $this->orderNumbering->composeNumber($rule, $sampleSequence + 1, $at, $manager),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRule(OrderNumberingRule $rule): array
    {
        return [
            'id' => $rule->id,
            'cipher' => $rule->cipher,
            'own_company_id' => $rule->own_company_id,
            'own_company_name' => $rule->ownCompany?->name,
            'separator' => $rule->separator,
            'prefix_type' => $rule->prefix_type->value,
            'prefix_value' => $rule->prefix_value,
            'body_type' => $rule->body_type->value,
            'body_value' => $rule->body_value,
            'suffix_type' => $rule->suffix_type->value,
            'suffix_value' => $rule->suffix_value,
            'sequence_pad' => $rule->sequence_pad,
            'sequence_scope' => $rule->sequence_scope->value,
        ];
    }
}
