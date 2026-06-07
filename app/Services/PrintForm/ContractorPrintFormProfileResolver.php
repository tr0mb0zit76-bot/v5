<?php

namespace App\Services\PrintForm;

use App\Models\Contractor;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use Illuminate\Support\Facades\Schema;

final class ContractorPrintFormProfileResolver
{
    public const MODE_INTERNAL_STANDARD = 'internal_standard';

    public const MODE_INTERNAL_CUSTOMIZED = 'internal_customized';

    public const MODE_CONTRACTOR_EXTERNAL = 'contractor_external';

    /**
     * @return array{
     *     mode: string,
     *     label: string,
     *     summary: string,
     *     customer: array{mode: string, label: string},
     *     carrier: array{mode: string, label: string}
     * }
     */
    public function resolve(Contractor $contractor): array
    {
        $customer = $this->resolveSide((int) $contractor->id, PrintFormBasicTerm::PARTY_CUSTOMER);
        $carrier = $this->resolveSide((int) $contractor->id, PrintFormBasicTerm::PARTY_CARRIER);

        return [
            'mode' => $this->aggregateMode($customer['mode'], $carrier['mode']),
            'label' => $this->aggregateLabel($customer, $carrier),
            'summary' => $this->aggregateSummary($customer, $carrier),
            'customer' => $customer,
            'carrier' => $carrier,
        ];
    }

    /**
     * @return array{mode: string, label: string}
     */
    private function resolveSide(int $contractorId, string $party): array
    {
        if ($this->hasExternalTemplate($contractorId, $party)) {
            return [
                'mode' => self::MODE_CONTRACTOR_EXTERNAL,
                'label' => 'Форма контрагента',
            ];
        }

        if ($this->hasCustomBasicTerms($contractorId, $party)) {
            return [
                'mode' => self::MODE_INTERNAL_CUSTOMIZED,
                'label' => 'Наша с правками',
            ];
        }

        return [
            'mode' => self::MODE_INTERNAL_STANDARD,
            'label' => 'Стандартная',
        ];
    }

    private function hasExternalTemplate(int $contractorId, string $party): bool
    {
        if (! Schema::hasTable('print_form_templates')) {
            return false;
        }

        return PrintFormTemplate::query()
            ->where('contractor_id', $contractorId)
            ->where('source_type', 'external_docx')
            ->where('is_active', true)
            ->where(function ($query) use ($party): void {
                $query->where('party', $party)
                    ->orWhere('party', 'internal');
            })
            ->exists();
    }

    private function hasCustomBasicTerms(int $contractorId, string $party): bool
    {
        if (! Schema::hasTable('print_form_basic_terms')) {
            return false;
        }

        return PrintFormBasicTerm::query()
            ->where('contractor_id', $contractorId)
            ->where('party', $party)
            ->exists();
    }

    private function aggregateMode(string $customerMode, string $carrierMode): string
    {
        $priority = [
            self::MODE_CONTRACTOR_EXTERNAL => 3,
            self::MODE_INTERNAL_CUSTOMIZED => 2,
            self::MODE_INTERNAL_STANDARD => 1,
        ];

        return ($priority[$customerMode] ?? 0) >= ($priority[$carrierMode] ?? 0)
            ? $customerMode
            : $carrierMode;
    }

    /**
     * @param  array{mode: string, label: string}  $customer
     * @param  array{mode: string, label: string}  $carrier
     */
    private function aggregateLabel(array $customer, array $carrier): string
    {
        if ($customer['mode'] === $carrier['mode']) {
            return $customer['label'];
        }

        return 'Заказчик: '.$customer['label'].'. Перевозчик: '.$carrier['label'].'.';
    }

    /**
     * @param  array{mode: string, label: string}  $customer
     * @param  array{mode: string, label: string}  $carrier
     */
    private function aggregateSummary(array $customer, array $carrier): string
    {
        if ($customer['mode'] === $carrier['mode']) {
            return match ($customer['mode']) {
                self::MODE_CONTRACTOR_EXTERNAL => 'Для контрагента загружен внешний DOCX-шаблон.',
                self::MODE_INTERNAL_CUSTOMIZED => 'В CRM сохранены индивидуальные базовые условия.',
                default => 'Используются стандартные формы и базовые условия CRM.',
            };
        }

        return 'Заказчик — '.$customer['label'].'. Перевозчик — '.$carrier['label'].'.';
    }
}
