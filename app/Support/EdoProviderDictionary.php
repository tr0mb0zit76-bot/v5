<?php

declare(strict_types=1);

namespace App\Support;

final class EdoProviderDictionary
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'diadoc', 'label' => 'Контур.Диадок'],
            ['value' => 'sbis', 'label' => 'СБИС'],
            ['value' => 'taxcom', 'label' => 'Такском'],
            ['value' => 'astral', 'label' => 'Калуга Астрал'],
            ['value' => 'kontur', 'label' => 'СКБ Контур'],
            ['value' => 'roseltorg', 'label' => 'Росэлторг'],
            ['value' => 'other', 'label' => 'Другое'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::options(), 'value');
    }

    public static function label(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        foreach (self::options() as $option) {
            if ($option['value'] === $code) {
                return $option['label'];
            }
        }

        return $code;
    }
}
