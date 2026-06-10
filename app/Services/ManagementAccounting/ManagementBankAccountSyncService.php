<?php

namespace App\Services\ManagementAccounting;

use App\Models\Contractor;
use App\Models\ManagementBankAccount;
use Illuminate\Support\Facades\Schema;

class ManagementBankAccountSyncService
{
    /**
     * Подтягивает р/с из карточек «своя компания» в справочник управленческого учёта.
     */
    public function syncFromOwnCompanies(): void
    {
        if (! Schema::hasTable('contractors')
            || ! Schema::hasColumn('contractors', 'is_own_company')
            || ! Schema::hasColumn('contractors', 'bank_accounts')) {
            return;
        }

        $query = Contractor::query()
            ->where('is_own_company', true)
            ->orderBy('name');

        if (Schema::hasColumn('contractors', 'is_active')) {
            $query->where('is_active', true);
        }

        $sortOrder = 10;

        foreach ($query->get(['id', 'name', 'bank_accounts']) as $company) {
            $accounts = $company->bank_accounts;
            if (! is_array($accounts) || $accounts === []) {
                continue;
            }

            $orderedAccounts = $this->orderAccountsPrimaryFirst($accounts);

            foreach ($orderedAccounts as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $accountNumber = preg_replace('/\D+/', '', (string) ($row['account_number'] ?? '')) ?? '';
                if ($accountNumber === '') {
                    continue;
                }

                $bankName = trim((string) ($row['bank_name'] ?? ''));
                if ($bankName === '') {
                    $bankName = 'Банк';
                }

                $currency = strtoupper(substr(trim((string) ($row['currency'] ?? 'RUB')), 0, 3));
                if ($currency === '') {
                    $currency = 'RUB';
                }

                ManagementBankAccount::query()->updateOrCreate(
                    ['account_number' => $accountNumber],
                    [
                        'bank_name' => $bankName,
                        'account_mask' => $this->maskForAccountNumber($accountNumber),
                        'currency' => $currency,
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ],
                );

                $sortOrder += 10;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @return list<array<string, mixed>>
     */
    private function orderAccountsPrimaryFirst(array $accounts): array
    {
        $normalized = array_values(array_filter($accounts, static fn (mixed $row): bool => is_array($row)));

        usort(
            $normalized,
            static function (array $left, array $right): int {
                $leftPrimary = filter_var($left['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $rightPrimary = filter_var($right['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return (int) $rightPrimary <=> (int) $leftPrimary;
            },
        );

        return $normalized;
    }

    private function maskForAccountNumber(string $accountNumber): string
    {
        $digits = preg_replace('/\D+/', '', $accountNumber) ?? '';

        if (strlen($digits) < 4) {
            return $digits !== '' ? '****'.$digits : '****';
        }

        return '****'.substr($digits, -4);
    }
}
