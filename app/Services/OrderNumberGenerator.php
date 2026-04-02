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

        $base = Str::upper(Str::substr(Str::slug($ownCompany->name ?? '', ''), 0, 4));

        if ($base !== '') {
            return $base;
        }

        if (filled($ownCompany->inn)) {
            return 'C'.Str::substr((string) $ownCompany->inn, -3);
        }

        return 'ORD';
    }
}
