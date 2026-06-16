<?php

namespace App\Support;

use App\Models\Contractor;
use Illuminate\Support\Facades\Schema;

/**
 * Правила области «шаблон по умолчанию» (своя компания + тип перевозки + сторона).
 */
final class PrintFormTemplateDefaultScope
{
    public static function multipleOwnCompaniesExist(): bool
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'is_own_company')) {
            return false;
        }

        return Contractor::query()->where('is_own_company', true)->count() > 1;
    }

    public static function defaultRequiresOwnCompany(): bool
    {
        return self::multipleOwnCompaniesExist();
    }
}
