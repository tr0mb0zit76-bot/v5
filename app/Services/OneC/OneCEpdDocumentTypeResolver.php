<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\OneCEpdRegistryEntry;

/**
 * Классификация строк РеестрЭПД по типу ссылки / имени метаданных.
 */
final class OneCEpdDocumentTypeResolver
{
    /**
     * @return array{code: string, label: string}
     */
    public function resolve(?string $documentRefType, ?string $metadataFullName = null, ?string $metadataSynonym = null): array
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) $documentRefType,
            (string) $metadataFullName,
            (string) $metadataSynonym,
        ])));

        if ($haystack === '') {
            return [
                'code' => OneCEpdRegistryEntry::TYPE_UNKNOWN,
                'label' => 'Неизвестный ЭПД',
            ];
        }

        if (str_contains($haystack, 'электроннаятранспортнаянакладная')
            || str_contains($haystack, 'электронная транспортная накладная')) {
            return [
                'code' => OneCEpdRegistryEntry::TYPE_ETRN,
                'label' => 'ЭТрН',
            ];
        }

        if (str_contains($haystack, 'электронноепоручениеэкспедитору')
            || str_contains($haystack, 'электронное поручение экспедитору')
            || str_contains($haystack, 'on_porexp')) {
            return [
                'code' => OneCEpdRegistryEntry::TYPE_EXPEDITION_ORDER,
                'label' => 'Поручение экспедитору',
            ];
        }

        if (str_contains($haystack, 'электроннаяэкспедиторскаярасписка')
            || str_contains($haystack, 'электронная экспедиторская расписка')
            || str_contains($haystack, 'on_exprasp')) {
            return [
                'code' => OneCEpdRegistryEntry::TYPE_EXPEDITION_RECEIPT,
                'label' => 'Экспедиторская расписка',
            ];
        }

        if (str_contains($haystack, 'электронныйзаказзаявка')
            || str_contains($haystack, 'электронный заказ (заявка)')
            || str_contains($haystack, 'электронный заказ')) {
            return [
                'code' => OneCEpdRegistryEntry::TYPE_E_ORDER,
                'label' => 'Электронный заказ (заявка)',
            ];
        }

        if (str_contains($haystack, 'электронныйзаказнаряд')
            || str_contains($haystack, 'электронный заказ-наряд')
            || str_contains($haystack, 'электронный заказ наряд')) {
            return [
                'code' => OneCEpdRegistryEntry::TYPE_E_WORK_ORDER,
                'label' => 'Электронный заказ-наряд',
            ];
        }

        return [
            'code' => OneCEpdRegistryEntry::TYPE_UNKNOWN,
            'label' => $metadataSynonym !== null && trim($metadataSynonym) !== ''
                ? trim($metadataSynonym)
                : 'Неизвестный ЭПД',
        ];
    }

    /**
     * Типы, которые зеркалим в order_one_c_documents при «связать».
     *
     * @return list<string>
     */
    public function linkableOneCDocumentTypes(): array
    {
        return [
            OneCEpdRegistryEntry::TYPE_ETRN,
            OneCEpdRegistryEntry::TYPE_EXPEDITION_RECEIPT,
        ];
    }
}
