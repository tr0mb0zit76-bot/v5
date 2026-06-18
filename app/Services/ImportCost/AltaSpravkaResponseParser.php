<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use SimpleXMLElement;

final class AltaSpravkaResponseParser
{
    /**
     * @return array{
     *     code: string|null,
     *     label: string|null,
     *     duty_percent: float|null,
     *     vat_percent: float|null,
     *     error_code: string|null,
     *     error_message: string|null
     * }|null
     */
    public function parse(string $xml): ?array
    {
        $xml = trim($xml);

        if ($xml === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $document instanceof SimpleXMLElement) {
            return null;
        }

        $root = $document->getName();

        if ($root === 'Error') {
            return [
                'code' => null,
                'label' => null,
                'duty_percent' => null,
                'vat_percent' => null,
                'error_code' => $this->text($document->ErrorCode),
                'error_message' => $this->text($document->ErrorDescr),
            ];
        }

        if ($root !== 'GoodInfo') {
            return null;
        }

        return [
            'code' => $this->text($document->Code),
            'label' => $this->text($document->Name),
            'duty_percent' => $this->extractDutyPercent($document),
            'vat_percent' => $this->extractVatPercent($document),
            'error_code' => null,
            'error_message' => null,
        ];
    }

    private function extractDutyPercent(SimpleXMLElement $document): ?float
    {
        $import = $this->firstChild($document->Importlist ?? null, 'Import');

        if (! $import instanceof SimpleXMLElement) {
            return null;
        }

        if (isset($import->ValueDetail->ValueCount) && $this->isPercentUnit($import->ValueDetail->ValueUnit)) {
            return $this->normalizePercent((string) $import->ValueDetail->ValueCount);
        }

        return $this->parsePercentText($this->text($import->Value));
    }

    private function extractVatPercent(SimpleXMLElement $document): ?float
    {
        $vat = $this->firstChild($document->VATlist ?? null, 'VAT');

        if (! $vat instanceof SimpleXMLElement) {
            return null;
        }

        if (isset($vat->ValueDetail->ValueCount) && $this->isPercentUnit($vat->ValueDetail->ValueUnit)) {
            return $this->normalizePercent((string) $vat->ValueDetail->ValueCount);
        }

        return $this->parsePercentText($this->text($vat->Value));
    }

    private function isPercentUnit(SimpleXMLElement|string|null $unit): bool
    {
        $value = $this->text($unit);

        return $value === '%' || str_contains(mb_strtolower($value), 'проц');
    }

    private function parsePercentText(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $lower = mb_strtolower($value);

        if (str_contains($lower, 'беспошлин') || str_contains($lower, 'не облагается')) {
            return 0.0;
        }

        if (preg_match('/([\d]+(?:[.,][\d]+)?)\s*%/u', $value, $matches) === 1) {
            return $this->normalizePercent($matches[1]);
        }

        return null;
    }

    private function normalizePercent(string $value): ?float
    {
        $normalized = (float) str_replace(',', '.', trim($value));

        return max(0.0, min(100.0, round($normalized, 4)));
    }

    private function firstChild(?SimpleXMLElement $parent, string $name): ?SimpleXMLElement
    {
        if (! $parent instanceof SimpleXMLElement || ! isset($parent->{$name})) {
            return null;
        }

        foreach ($parent->{$name} as $child) {
            return $child;
        }

        return null;
    }

    private function text(SimpleXMLElement|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SimpleXMLElement) {
            $text = trim((string) $value);

            return $text !== '' ? $text : null;
        }

        $text = trim($value);

        return $text !== '' ? $text : null;
    }
}
