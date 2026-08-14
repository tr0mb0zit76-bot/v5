<?php

declare(strict_types=1);

namespace App\Services\ManagementAccounting;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\User;
use App\Services\OneC\OneCBpClient;
use App\Services\OneC\OneCPublicationCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Тянет банк из 1С OData → management_statement_lines (+ опционально авторазнесение).
 */
final class ManagementAccountingOneCPullService
{
    public const FORMAT = 'one_c_odata_v1';

    public function __construct(
        private readonly OneCBpClient $oneC,
        private readonly ManagementAccountingMatchingService $matching,
        private readonly ManagementAccountingAutoAllocateService $autoAllocate,
        private readonly ManagementAccountingStatementDuplicateService $duplicates,
    ) {}

    /**
     * @return array{
     *     import: ManagementStatementImport,
     *     fetched: int,
     *     created: int,
     *     skipped: int,
     *     allocated: int,
     *     pending: int,
     *     allocation_errors: list<string>
     * }
     */
    public function pullAndImport(
        string $dateFrom,
        string $dateTo,
        User $actor,
        bool $allocate = true,
        int $minConfidence = 55,
        ?ManagementBankAccount $bankAccount = null,
        ?string $publicationCode = null,
    ): array {
        $publication = null;
        if ($publicationCode !== null && $publicationCode !== '') {
            $publication = app(OneCPublicationCatalog::class)->get($publicationCode);
            $bankAccount ??= $this->resolveBankAccount($publication['bank_account_number']);
            $movements = $this->oneC->listBankMovements($dateFrom, $dateTo, [
                'base_url' => $publication['base_url'],
                'organization_ref' => $publication['organization_ref'],
                'date_filter_mode' => $publication['date_filter_mode'],
            ]);
        } else {
            $bankAccount ??= $this->resolveBankAccount();
            $movements = $this->oneC->listBankMovements($dateFrom, $dateTo);
        }

        return DB::transaction(function () use (
            $movements,
            $bankAccount,
            $actor,
            $allocate,
            $minConfidence,
            $dateFrom,
            $dateTo,
        ): array {
            $import = ManagementStatementImport::query()->create([
                'bank_account_id' => $bankAccount->id,
                'format' => self::FORMAT,
                'file_name' => '1C OData '.$dateFrom.'…'.$dateTo,
                'imported_by' => $actor->id,
                'status' => 'draft',
                'period_from' => $dateFrom,
                'period_to' => $dateTo,
            ]);

            $totalIn = 0.0;
            $totalOut = 0.0;
            $created = 0;
            $skipped = 0;
            $rowNumber = 0;
            $createdLines = [];

            foreach ($movements as $movement) {
                $rowNumber++;
                $hash = $this->externalRefHash($bankAccount->account_number, $movement['ref']);

                $exists = ManagementStatementLine::query()
                    ->where('bank_account_id', $bankAccount->id)
                    ->where('line_hash', $hash)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $description = $this->formatDescription($movement);
                if ($this->duplicates->existsTwin(
                    (int) $bankAccount->id,
                    (string) $movement['date'],
                    (string) $movement['direction'],
                    (float) $movement['amount'],
                    $description,
                    'one_c_odata',
                )) {
                    $skipped++;

                    continue;
                }
                $line = ManagementStatementLine::query()->create([
                    'import_id' => $import->id,
                    'bank_account_id' => $bankAccount->id,
                    'line_hash' => $hash,
                    'row_number' => $rowNumber,
                    'operation_date' => $movement['date'],
                    'direction' => $movement['direction'],
                    'amount' => $movement['amount'],
                    'currency' => $bankAccount->currency,
                    'description' => $description,
                    'status' => 'pending',
                    'source' => 'one_c_odata',
                ]);

                $suggestion = $this->matching->suggestForLine($line);
                unset($suggestion['suggested_candidates']);
                $line->fill($suggestion);
                $line->save();

                if ($movement['direction'] === 'in') {
                    $totalIn += $movement['amount'];
                } else {
                    $totalOut += $movement['amount'];
                }

                $created++;
                $createdLines[] = $line;
            }

            if ($created === 0) {
                $import->delete();

                throw new InvalidArgumentException(
                    $skipped > 0
                        ? "Новых операций нет (пропущено дублей: {$skipped})."
                        : 'В 1С за период нет банковских документов.'
                );
            }

            $import->update([
                'lines_count' => $created,
                'total_in' => round($totalIn, 2),
                'total_out' => round($totalOut, 2),
            ]);

            $allocated = 0;
            $errors = [];

            if ($allocate) {
                foreach ($createdLines as $line) {
                    $line->refresh();
                    try {
                        if ($this->autoAllocate->tryAutoAllocate($line, $actor, $minConfidence)) {
                            $allocated++;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = '#'.$line->id.' '.$e->getMessage();
                    }
                }
            }

            $import = $import->fresh(['bankAccount', 'importer']);
            $pending = ManagementStatementLine::query()
                ->where('import_id', $import->id)
                ->where('status', 'pending')
                ->count();

            return [
                'import' => $import,
                'fetched' => count($movements),
                'created' => $created,
                'skipped' => $skipped,
                'allocated' => $allocated,
                'pending' => $pending,
                'allocation_errors' => $errors,
            ];
        });
    }

    /**
     * @param  array{
     *     ref: string,
     *     date: string,
     *     direction: string,
     *     amount: float,
     *     number: ?string,
     *     operation: ?string,
     *     counterparty: ?string,
     *     purpose: ?string,
     *     comment: ?string
     * }  $movement
     */
    private function formatDescription(array $movement): string
    {
        $name = trim((string) ($movement['counterparty'] ?? ''));
        $purpose = trim((string) ($movement['purpose'] ?? ''));
        if ($name !== '' && $purpose !== '') {
            return $name.' / '.$purpose;
        }

        if ($purpose !== '') {
            return $purpose;
        }

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($movement['comment'] ?? '1С '.$movement['number']));
    }

    private function externalRefHash(string $accountNumber, string $ref): string
    {
        return hash('sha256', implode('|', ['one_c_odata', $accountNumber, $ref]));
    }

    private function resolveBankAccount(?string $accountNumber = null): ManagementBankAccount
    {
        $accountNumber ??= (string) config('one_c.bank_statement.account_number', '');
        if ($accountNumber !== '') {
            $account = ManagementBankAccount::query()
                ->where('account_number', $accountNumber)
                ->first();
            if ($account !== null) {
                return $account;
            }
        }

        $account = ManagementBankAccount::query()
            ->where('account_number', '40702810959710001997')
            ->first();

        if ($account !== null) {
            return $account;
        }

        return ManagementBankAccount::consolidated();
    }
}
