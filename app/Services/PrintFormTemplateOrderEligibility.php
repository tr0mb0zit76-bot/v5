<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Support\PrintFormBasicTermsTableCloner;
use App\Support\PrintFormTemplateTransportScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Проверяет, можно ли применить шаблон печатной формы к заказу (контрагент, активность, файл, scope).
 */
final class PrintFormTemplateOrderEligibility
{
    /**
     * @return list<int>
     */
    public function contractorIdsForOrder(Order $order): array
    {
        $ids = collect([
            $order->customer_id,
            $order->carrier_id,
        ]);

        if ($order->relationLoaded('legs') && Schema::hasTable('leg_contractor_assignments')) {
            foreach ($order->legs as $leg) {
                $cid = $leg->contractorAssignment?->contractor_id;
                if ($cid !== null) {
                    $ids->push($cid);
                }

                if ($leg->relationLoaded('contractorAssignments')) {
                    foreach ($leg->contractorAssignments as $assignment) {
                        if ($assignment->contractor_id !== null) {
                            $ids->push($assignment->contractor_id);
                        }
                    }
                }
            }
        }

        $financialTerm = $order->relationLoaded('financialTerm')
            ? $order->financialTerm
            : null;

        if ($financialTerm !== null) {
            $costs = is_array($financialTerm->contractors_costs) ? $financialTerm->contractors_costs : [];
            foreach ($costs as $cost) {
                if (is_array($cost) && isset($cost['contractor_id'])) {
                    $ids->push($cost['contractor_id']);
                }
            }
        }

        return $this->normalizeContractorIds($ids->all());
    }

    /**
     * @param  list<int>  $contractorIds
     */
    public function isTemplateAvailableForContext(
        PrintFormTemplate $template,
        ?int $ownCompanyId,
        bool $isInternationalTransport,
        ?string $party,
        array $contractorIds = [],
    ): bool {
        if (! $template->is_active || blank($template->file_path) || $template->entity_type !== 'order') {
            return false;
        }

        $templateArray = $this->templateToArray($template);

        if (! $this->matchesPrintSlotParty($templateArray, $party)) {
            return false;
        }

        if (! $this->matchesOwnCompany($template->own_company_id, $ownCompanyId)) {
            return false;
        }

        if (! PrintFormTemplateTransportScope::matches($template->transport_scope, $isInternationalTransport)) {
            return false;
        }

        if ($template->contractor_id === null) {
            return true;
        }

        return in_array((int) $template->contractor_id, $contractorIds, true);
    }

    public function isTemplateAvailableForOrder(
        PrintFormTemplate $template,
        Order $order,
        ?string $party = null,
        ?bool $isInternationalTransport = null,
    ): bool {
        return $this->isTemplateAvailableForContext(
            $template,
            $order->own_company_id !== null ? (int) $order->own_company_id : null,
            $isInternationalTransport ?? $order->isInternationalTransportEffective(),
            $party,
            $this->contractorIdsForOrder($order),
        );
    }

    /**
     * @param  Collection<int, PrintFormTemplate>|Collection<int, array<string, mixed>>  $templates
     */
    public function resolveDefaultTemplate(
        Collection $templates,
        ?int $ownCompanyId,
        bool $isInternationalTransport,
        ?string $party,
        array $contractorIds = [],
    ): PrintFormTemplate|array|null {
        $available = $templates
            ->filter(function (PrintFormTemplate|array $template) use ($ownCompanyId, $isInternationalTransport, $party, $contractorIds): bool {
                if ($template instanceof PrintFormTemplate) {
                    return $this->isTemplateAvailableForContext(
                        $template,
                        $ownCompanyId,
                        $isInternationalTransport,
                        $party,
                        $contractorIds,
                    );
                }

                return $this->isArrayTemplateAvailableForContext(
                    $template,
                    $ownCompanyId,
                    $isInternationalTransport,
                    $party,
                    $contractorIds,
                );
            })
            ->sortByDesc(fn (PrintFormTemplate|array $template): int => $this->specificityScore(
                $template instanceof PrintFormTemplate ? $this->templateToArray($template) : $template,
                $ownCompanyId,
                $isInternationalTransport,
            ))
            ->values();

        if ($available->isEmpty()) {
            return null;
        }

        $defaultCandidates = $available->filter(function (PrintFormTemplate|array $template): bool {
            if ($template instanceof PrintFormTemplate) {
                return (bool) $template->is_default;
            }

            return (bool) ($template['is_default'] ?? false);
        });

        if ($defaultCandidates->isNotEmpty()) {
            return $defaultCandidates
                ->sortByDesc(fn (PrintFormTemplate|array $template): int => $this->specificityScore(
                    $template instanceof PrintFormTemplate ? $this->templateToArray($template) : $template,
                    $ownCompanyId,
                    $isInternationalTransport,
                ))
                ->first();
        }

        return $available->first();
    }

    /**
     * Если для «нашей» компании есть привязанные шаблоны — универсальные (без own_company_id) не показываем.
     *
     * @param  Collection<int, array<string, mixed>>  $templates
     * @return Collection<int, array<string, mixed>>
     */
    public function preferOwnCompanySpecificTemplates(Collection $templates, ?int $ownCompanyId): Collection
    {
        if ($ownCompanyId === null) {
            return $templates;
        }

        $specific = $templates->filter(
            fn (array $template): bool => isset($template['own_company_id'])
                && (int) $template['own_company_id'] === $ownCompanyId,
        );

        if ($specific->isEmpty()) {
            return $templates;
        }

        return $specific->values();
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  list<int>  $contractorIds
     */
    public function isArrayTemplateAvailableForContext(
        array $template,
        ?int $ownCompanyId,
        bool $isInternationalTransport,
        ?string $party,
        array $contractorIds = [],
    ): bool {
        if (($template['entity_type'] ?? 'order') !== 'order') {
            return false;
        }

        if (($template['is_active'] ?? true) !== true || blank($template['file_path'] ?? null)) {
            return false;
        }

        if (! $this->matchesPrintSlotParty($template, $party)) {
            return false;
        }

        if (! $this->matchesOwnCompany(
            isset($template['own_company_id']) ? (int) $template['own_company_id'] : null,
            $ownCompanyId,
        )) {
            return false;
        }

        if (! PrintFormTemplateTransportScope::matches($template['transport_scope'] ?? null, $isInternationalTransport)) {
            return false;
        }

        $contractorId = $template['contractor_id'] ?? null;
        if ($contractorId === null) {
            return true;
        }

        return in_array((int) $contractorId, $contractorIds, true);
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public function specificityScore(array $template, ?int $ownCompanyId, bool $isInternationalTransport): int
    {
        $score = (bool) ($template['is_default'] ?? false) ? 1000 : 0;

        $templateOwnCompanyId = isset($template['own_company_id']) ? (int) $template['own_company_id'] : null;
        if ($templateOwnCompanyId !== null && $ownCompanyId !== null && $templateOwnCompanyId === $ownCompanyId) {
            $score += 200;
        } elseif ($templateOwnCompanyId === null) {
            $score += 20;
        }

        $scope = trim((string) ($template['transport_scope'] ?? PrintFormTemplateTransportScope::ANY));
        if ($scope === PrintFormTemplateTransportScope::DOMESTIC && ! $isInternationalTransport) {
            $score += 100;
        } elseif ($scope === PrintFormTemplateTransportScope::INTERNATIONAL && $isInternationalTransport) {
            $score += 100;
        } elseif ($scope === PrintFormTemplateTransportScope::ANY) {
            $score += 10;
        }

        if (($template['contractor_id'] ?? null) !== null) {
            $score += 50;
        }

        return $score;
    }

    /**
     * @return array<string, mixed>
     */
    public function templateToArray(PrintFormTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'code' => $template->code,
            'entity_type' => $template->entity_type,
            'document_type' => $template->document_type,
            'party' => $template->party,
            'contractor_id' => $template->contractor_id,
            'contractor_name' => $template->contractor?->name,
            'own_company_id' => $template->own_company_id,
            'own_company_name' => $template->ownCompany?->name,
            'transport_scope' => $template->transport_scope ?? PrintFormTemplateTransportScope::ANY,
            'transport_scope_label' => PrintFormTemplateTransportScope::label($template->transport_scope),
            'is_default' => (bool) $template->is_default,
            'is_active' => (bool) $template->is_active,
            'file_path' => $template->file_path,
            'has_customer_basic_terms' => $this->templateHasBasicTermsForParty($template, PrintFormBasicTerm::PARTY_CUSTOMER),
            'has_carrier_basic_terms' => $this->templateHasBasicTermsForParty($template, PrintFormBasicTerm::PARTY_CARRIER),
        ];
    }

    public function templateHasBasicTermsForParty(PrintFormTemplate $template, string $party): bool
    {
        $help = PrintFormBasicTermsTableCloner::placeholderHelpForParty($party);

        if ($help === []) {
            return false;
        }

        $anchor = (string) ($help['anchor'] ?? '');

        if ($anchor === '') {
            return false;
        }

        $variables = collect($template->settings['variables'] ?? [])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): string => strtolower($value))
            ->values()
            ->all();

        foreach ($variables as $variable) {
            if ($variable === strtolower($anchor) || str_starts_with($variable, strtolower($anchor).'#')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Сторона шаблона для подбора в слот печати и области «по умолчанию».
     *
     * @return 'customer'|'carrier'|'internal'|'dual'
     */
    public function effectivePrintParty(array $template): string
    {
        $party = trim((string) ($template['party'] ?? 'internal'));

        if (in_array($party, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
            return $party;
        }

        if ($this->isDualOrientedTemplate($template)) {
            return 'dual';
        }

        if ($this->isCustomerOrientedTemplate($template)) {
            return PrintFormBasicTerm::PARTY_CUSTOMER;
        }

        if ($this->isCarrierOrientedTemplate($template)) {
            return PrintFormBasicTerm::PARTY_CARRIER;
        }

        return 'internal';
    }

    /**
     * Можно ли предлагать шаблон в слоте печати заказчика или перевозчика.
     */
    public function matchesPrintSlotParty(array $template, ?string $slotParty): bool
    {
        if ($slotParty === null || $slotParty === '') {
            return true;
        }

        $effective = $this->effectivePrintParty($template);

        if ($effective === 'dual') {
            return false;
        }

        if ($slotParty === PrintFormBasicTerm::PARTY_CUSTOMER) {
            return in_array($effective, [PrintFormBasicTerm::PARTY_CUSTOMER, 'internal'], true);
        }

        if ($slotParty === PrintFormBasicTerm::PARTY_CARRIER) {
            return in_array($effective, [PrintFormBasicTerm::PARTY_CARRIER, 'internal'], true);
        }

        return true;
    }

    public function templatesShareDefaultScope(PrintFormTemplate $left, PrintFormTemplate $right): bool
    {
        if ($left->entity_type !== $right->entity_type || $left->document_type !== $right->document_type) {
            return false;
        }

        if ($left->own_company_id !== $right->own_company_id) {
            return false;
        }

        $leftScope = $left->transport_scope ?? PrintFormTemplateTransportScope::ANY;
        $rightScope = $right->transport_scope ?? PrintFormTemplateTransportScope::ANY;

        if ($leftScope !== $rightScope) {
            return false;
        }

        return $this->effectivePrintParty($this->templateToArray($left))
            === $this->effectivePrintParty($this->templateToArray($right));
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public function isDualOrientedTemplate(array $template): bool
    {
        $party = trim((string) ($template['party'] ?? 'internal'));

        if (in_array($party, [PrintFormBasicTerm::PARTY_CUSTOMER, PrintFormBasicTerm::PARTY_CARRIER], true)) {
            return false;
        }

        $hasCarrier = array_key_exists('has_carrier_basic_terms', $template)
            ? (bool) $template['has_carrier_basic_terms']
            : null;
        $hasCustomer = array_key_exists('has_customer_basic_terms', $template)
            ? (bool) $template['has_customer_basic_terms']
            : null;

        if ($hasCarrier === null || $hasCustomer === null) {
            return false;
        }

        return $hasCustomer && $hasCarrier;
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public function isCarrierOrientedTemplate(array $template): bool
    {
        $party = trim((string) ($template['party'] ?? 'internal'));

        if ($party === 'carrier') {
            return true;
        }

        if ($party === 'customer') {
            return false;
        }

        $hasCarrier = (bool) ($template['has_carrier_basic_terms'] ?? false);
        $hasCustomer = (bool) ($template['has_customer_basic_terms'] ?? false);

        return $hasCarrier && ! $hasCustomer;
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public function isCustomerOrientedTemplate(array $template): bool
    {
        $party = trim((string) ($template['party'] ?? 'internal'));

        if ($party === 'customer') {
            return true;
        }

        if ($party === 'carrier') {
            return false;
        }

        $hasCarrier = (bool) ($template['has_carrier_basic_terms'] ?? false);
        $hasCustomer = (bool) ($template['has_customer_basic_terms'] ?? false);

        return $hasCustomer && ! $hasCarrier;
    }

    private function matchesOwnCompany(?int $templateOwnCompanyId, ?int $orderOwnCompanyId): bool
    {
        if ($templateOwnCompanyId === null) {
            return true;
        }

        return $orderOwnCompanyId !== null && $templateOwnCompanyId === $orderOwnCompanyId;
    }

    /**
     * @param  list<int|mixed>  $ids
     * @return list<int>
     */
    private function normalizeContractorIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn (mixed $value): bool => is_int($value) || ctype_digit((string) $value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
