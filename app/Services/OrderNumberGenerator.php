<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Order;
use Illuminate\Support\Str;

class OrderNumberGenerator
{
    public function generate(?Contractor $ownCompany = null): array
    {
        $companyCode = $this->resolveCompanyCode($ownCompany);
        $prefix = $companyCode.'-'.now()->format('ym');

        $lastNumber = Order::query()
            ->where('company_code', $companyCode)
            ->where('order_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('order_number');

        $lastSequence = (int) Str::of((string) $lastNumber)->afterLast('-')->value();
        $nextSequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        return [
            'company_code' => $companyCode,
            'order_number' => $prefix.'-'.$nextSequence,
        ];
    }

    private function resolveCompanyCode(?Contractor $ownCompany): string
    {
        if ($ownCompany === null) {
            return 'ORD';
        }

        $explicit = data_get($ownCompany->metadata, 'order_company_code');
        if (is_string($explicit) && filled(trim($explicit))) {
            $code = $this->sanitizeCompanyCodeString($explicit);
            if ($code !== '') {
                return $code;
            }
        }

        $abbrev = $this->abbreviateLegalEntityName($ownCompany->name ?? '');
        if ($abbrev !== '') {
            return $abbrev;
        }

        $slugBase = Str::upper(Str::substr(Str::slug($ownCompany->name ?? '', ''), 0, 4));
        if ($slugBase !== '') {
            return $slugBase;
        }

        if (filled($ownCompany->inn)) {
            return 'C'.Str::substr((string) $ownCompany->inn, -3);
        }

        return 'ORD';
    }

    /**
     * Код из явного поля в metadata контрагента (нашей компании): латиница/кириллица/цифры, до 10 символов.
     */
    private function sanitizeCompanyCodeString(string $value): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $value);

        return Str::upper(Str::substr((string) $clean, 0, 10));
    }

    /**
     * Аббревиатура по наименованию: убираем типовые префиксы (ООО, ЗАО, …), берём первые буквы слов (кириллица/латиница), макс. 4 символа.
     * Пример: «ООО Альфа-Плюс Перевозки» → «АПП», «ООО Логистика России» → «ЛР».
     */
    private function abbreviateLegalEntityName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $stripped = preg_replace(
            '/^\s*(?:ооо|ooo|зао|пао|ао|ип|нко)\s*[«"]?/iu',
            '',
            $trimmed,
        );
        $stripped = (string) $stripped;
        $stripped = preg_replace('/^[«"\']+\s*/u', '', $stripped);
        $stripped = preg_replace('/[»"\']+\s*$/u', '', $stripped);
        $stripped = trim($stripped);

        if ($stripped === '') {
            $stripped = $trimmed;
        }

        $segments = preg_split('/[\s\-–—,.]+/u', $stripped, -1, PREG_SPLIT_NO_EMPTY);
        if ($segments === false) {
            return '';
        }

        $letters = '';
        foreach ($segments as $segment) {
            if (mb_strlen($letters) >= 4) {
                break;
            }

            if (preg_match('/\p{L}/u', $segment, $firstLetter) !== 1) {
                continue;
            }

            $letters .= mb_strtoupper($firstLetter[0], 'UTF-8');
        }

        return mb_substr($letters, 0, 4, 'UTF-8');
    }
}
