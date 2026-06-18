<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

final class KodTnVedPageParser
{
    /**
     * @return array{label: string|null, duty_percent: float|null, vat_percent: float|null}|null
     */
    public function parse(string $html): ?array
    {
        if ($html === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $duty = $this->extractPercentAfter($text, 'Импортная пошлина');
        $vat = $this->extractPercentAfter($text, 'Ввозной НДС');
        $label = $this->extractLabel($html);

        if ($duty === null && $vat === null && $label === null) {
            return null;
        }

        return [
            'label' => $label,
            'duty_percent' => $duty,
            'vat_percent' => $vat,
        ];
    }

    private function extractPercentAfter(string $text, string $anchor): ?float
    {
        $pattern = '/'.preg_quote($anchor, '/').'[\s\S]{0,120}?([\d]+(?:[.,][\d]+)?)\s*%/u';

        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }

        $value = (float) str_replace(',', '.', $matches[1]);

        return max(0.0, min(100.0, round($value, 4)));
    }

    private function extractLabel(string $html): ?string
    {
        $code = null;

        if (preg_match('/<h1[^>]*>\s*Код ТН ВЭД\s+(\d{10})\s*<\/h1>/iu', $html, $codeMatch)) {
            $code = $codeMatch[1];
        } elseif (preg_match('/Код ТН ВЭД\s+(\d{10})/u', $html, $codeMatch)) {
            $code = $codeMatch[1];
        }

        if ($code === null) {
            return null;
        }

        if (preg_match('/##\s*Описание\s*[\s\S]*?'.$code.'\s*-\s*([^<\n]+)/iu', $html, $labelMatch)) {
            $label = trim($labelMatch[1]);

            return $label !== '' ? $label : null;
        }

        if (preg_match('/'.$code.'\s*-\s*([A-ZА-ЯЁ][^<\n]{3,200})/u', $html, $labelMatch)) {
            $label = trim($labelMatch[1]);

            return $label !== '' ? $label : null;
        }

        return null;
    }
}
