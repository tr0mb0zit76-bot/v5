<?php

namespace App\Support;

final class RussianPositionInflector
{
    public static function toGenitive(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $parts = preg_split('/(\s+)/u', $trimmed, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (! is_array($parts)) {
            return $trimmed;
        }

        $result = array_map(static function (string $chunk): string {
            if (preg_match('/^\s+$/u', $chunk) === 1) {
                return $chunk;
            }

            return self::inflectToken($chunk);
        }, $parts);

        return implode('', $result);
    }

    private static function inflectToken(string $token): string
    {
        if ($token === '') {
            return $token;
        }

        if (preg_match('/^[\p{Lu}\d\.\-\/]+$/u', $token) === 1) {
            return $token;
        }

        $leading = '';
        $core = $token;
        $trailing = '';

        if (preg_match('/^([^\p{L}]*)([\p{L}\-]+)([^\p{L}]*)$/u', $token, $m) === 1) {
            $leading = $m[1];
            $core = $m[2];
            $trailing = $m[3];
        }

        $inflected = self::inflectWord($core);

        return $leading.$inflected.$trailing;
    }

    private static function inflectWord(string $word): string
    {
        $lower = mb_strtolower($word, 'UTF-8');

        $irregular = [
            'директор' => 'директора',
            'бухгалтер' => 'бухгалтера',
            'менеджер' => 'менеджера',
            'логист' => 'логиста',
            'экспедитор' => 'экспедитора',
            'заместитель' => 'заместителя',
            'представитель' => 'представителя',
            'руководитель' => 'руководителя',
            'начальник' => 'начальника',
            'водитель' => 'водителя',
            'оператор' => 'оператора',
            'специалист' => 'специалиста',
            'координатор' => 'координатора',
        ];

        if (isset($irregular[$lower])) {
            return self::preserveCase($word, $irregular[$lower]);
        }

        $rules = [
            '/(ый)$/u' => 'ого',
            '/(ий)$/u' => 'его',
            '/(ой)$/u' => 'ого',
            '/(ая)$/u' => 'ой',
            '/(яя)$/u' => 'ей',
            '/(ое)$/u' => 'ого',
            '/(ее)$/u' => 'его',
            '/(ые)$/u' => 'ых',
            '/(ие)$/u' => 'их',
            '/(тель)$/u' => 'теля',
            '/(ец)$/u' => 'ца',
            '/(ия)$/u' => 'ии',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $lower) === 1) {
                $base = preg_replace($pattern, $replacement, $lower);

                return self::preserveCase($word, is_string($base) ? $base : $lower);
            }
        }

        if (preg_match('/а$/u', $lower) === 1) {
            $before = mb_substr($lower, -2, 1, 'UTF-8');
            $ending = in_array($before, ['г', 'к', 'х', 'ж', 'ч', 'ш', 'щ', 'ц'], true) ? 'и' : 'ы';
            $base = mb_substr($lower, 0, mb_strlen($lower, 'UTF-8') - 1, 'UTF-8').$ending;

            return self::preserveCase($word, $base);
        }

        if (preg_match('/я$/u', $lower) === 1) {
            $base = mb_substr($lower, 0, mb_strlen($lower, 'UTF-8') - 1, 'UTF-8').'и';

            return self::preserveCase($word, $base);
        }

        if (preg_match('/[йь]$/u', $lower) === 1) {
            $base = mb_substr($lower, 0, mb_strlen($lower, 'UTF-8') - 1, 'UTF-8').'я';

            return self::preserveCase($word, $base);
        }

        if (preg_match('/[бвгджзклмнпрстфхцчшщ]$/u', $lower) === 1) {
            return self::preserveCase($word, $lower.'а');
        }

        return $word;
    }

    private static function preserveCase(string $source, string $target): string
    {
        $first = mb_substr($source, 0, 1, 'UTF-8');
        if ($first !== '' && mb_strtoupper($first, 'UTF-8') === $first) {
            return mb_strtoupper(mb_substr($target, 0, 1, 'UTF-8'), 'UTF-8')
                .mb_substr($target, 1, null, 'UTF-8');
        }

        return $target;
    }
}
