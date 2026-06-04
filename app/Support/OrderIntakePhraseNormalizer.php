<?php

namespace App\Support;

use App\Models\User;
use App\Services\OrderIntakeLearnedPhrasesService;

final class OrderIntakePhraseNormalizer
{
    public static function normalizeInstruction(string $text, ?User $user = null): string
    {
        $normalized = trim($text);

        if ($user !== null) {
            $normalized = app(OrderIntakeLearnedPhrasesService::class)->applyLearnedPhrases($user, $normalized);
        }

        foreach (self::paymentReplacements() as $pattern => $replacement) {
            $normalized = (string) preg_replace($pattern, $replacement, $normalized);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array<string, mixed>
     */
    public static function normalizeExtracted(array $extracted, ?User $user = null): array
    {
        $commercial = is_array($extracted['commercial'] ?? null) ? $extracted['commercial'] : [];

        foreach (['customer_payment_terms', 'carrier_payment_terms'] as $key) {
            if (! isset($commercial[$key]) || $commercial[$key] === null) {
                continue;
            }

            $commercial[$key] = self::normalizePaymentTermsText((string) $commercial[$key], $user);
        }

        $extracted['commercial'] = $commercial;

        $ownCompany = is_array($extracted['own_company'] ?? null) ? $extracted['own_company'] : [];
        $name = isset($ownCompany['name']) ? trim((string) $ownCompany['name']) : '';

        if ($name !== '') {
            $extracted['own_company']['name'] = $name;
        }

        return $extracted;
    }

    public static function normalizePaymentTermsText(string $value, ?User $user = null): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        if ($user !== null) {
            $normalized = app(OrderIntakeLearnedPhrasesService::class)->applyLearnedPhrases($user, $normalized);
        }

        foreach (self::paymentReplacements() as $pattern => $replacement) {
            $normalized = (string) preg_replace($pattern, $replacement, $normalized);
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private static function paymentReplacements(): array
    {
        $fromConfig = config('order_intake.payment_term_replacements', []);

        return is_array($fromConfig) ? $fromConfig : [];
    }
}
