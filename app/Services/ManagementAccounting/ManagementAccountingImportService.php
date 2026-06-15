<?php

namespace App\Services\ManagementAccounting;

use App\Exceptions\DuplicateStatementImportException;
use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ManagementAccountingImportService
{
    public function __construct(
        private readonly SberRegistryXlsxParser $parser,
        private readonly ManagementAccountingMatchingService $matching,
        private readonly ManagementAccountingAllocationService $allocationService,
    ) {}

    public function importFromUpload(
        UploadedFile $file,
        ?ManagementBankAccount $bankAccount,
        User $importer,
    ): ManagementStatementImport {
        $parsed = $this->parser->parse($file->getRealPath() ?: $file->path());
        $bankAccount = $this->resolveBankAccount($bankAccount, $parsed);

        return DB::transaction(function () use ($file, $bankAccount, $importer, $parsed): ManagementStatementImport {
            $import = ManagementStatementImport::query()->create([
                'bank_account_id' => $bankAccount->id,
                'format' => SberRegistryXlsxParser::BANK_REGISTRY_V1,
                'file_name' => $file->getClientOriginalName(),
                'imported_by' => $importer->id,
                'status' => 'draft',
            ]);

            $totalIn = 0.0;
            $totalOut = 0.0;
            $periodFrom = $parsed['period_from'] ?? null;
            $periodTo = $parsed['period_to'] ?? null;
            $created = 0;

            foreach ($parsed['lines'] as $row) {
                $hash = $this->lineHash(
                    $bankAccount->account_number,
                    $row['operation_date'],
                    $row['direction'],
                    $row['amount'],
                    $row['description'],
                );

                $exists = ManagementStatementLine::query()
                    ->where('bank_account_id', $bankAccount->id)
                    ->where('line_hash', $hash)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $line = ManagementStatementLine::query()->create([
                    'import_id' => $import->id,
                    'bank_account_id' => $bankAccount->id,
                    'line_hash' => $hash,
                    'row_number' => $row['row_number'],
                    'operation_date' => $row['operation_date'],
                    'direction' => $row['direction'],
                    'amount' => $row['amount'],
                    'currency' => $bankAccount->currency,
                    'description' => $row['description'],
                    'status' => 'pending',
                    'source' => 'import',
                ]);

                $suggestion = $this->matching->suggestForLine($line);
                unset($suggestion['suggested_candidates']);
                $line->fill($suggestion);
                $line->save();

                if ($row['direction'] === 'in') {
                    $totalIn += $row['amount'];
                } else {
                    $totalOut += $row['amount'];
                }

                $periodFrom = $periodFrom === null || $row['operation_date'] < $periodFrom
                    ? $row['operation_date']
                    : $periodFrom;
                $periodTo = $periodTo === null || $row['operation_date'] > $periodTo
                    ? $row['operation_date']
                    : $periodTo;

                $created++;
            }

            $import->update([
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'lines_count' => $created,
                'total_in' => round($totalIn, 2),
                'total_out' => round($totalOut, 2),
            ]);

            if ($created === 0) {
                $existingImport = ManagementStatementImport::query()
                    ->where('bank_account_id', $bankAccount->id)
                    ->where('id', '!=', $import->id)
                    ->where('lines_count', '>', 0)
                    ->when($periodFrom !== null, fn ($query) => $query->where('period_to', '>=', $periodFrom))
                    ->when($periodTo !== null, fn ($query) => $query->where('period_from', '<=', $periodTo))
                    ->orderByDesc('id')
                    ->first();

                $import->delete();

                if ($existingImport !== null) {
                    throw new DuplicateStatementImportException($existingImport);
                }

                throw new \InvalidArgumentException('В файле нет новых операций для загрузки.');
            }

            return $import->fresh(['bankAccount', 'importer']);
        });
    }

    public function destroyImport(ManagementStatementImport $import, User $actor): void
    {
        DB::transaction(function () use ($import, $actor): void {
            ManagementStatementLine::query()
                ->where('import_id', $import->id)
                ->where('status', 'allocated')
                ->orderBy('id')
                ->each(function (ManagementStatementLine $line) use ($actor): void {
                    $this->allocationService->deallocateLine($line, $actor, 'Удаление выписки');
                });

            ManagementStatementLine::query()
                ->where('import_id', $import->id)
                ->delete();

            $import->delete();
        });
    }

    /**
     * @param  array{account_number: ?string, period_from: ?string, period_to: ?string}  $parsed
     */
    private function resolveBankAccount(?ManagementBankAccount $bankAccount, array $parsed): ManagementBankAccount
    {
        if ($parsed['account_number'] !== null) {
            $matchedAccount = ManagementBankAccount::query()
                ->where('account_number', $parsed['account_number'])
                ->first();

            if ($matchedAccount !== null) {
                return $matchedAccount;
            }
        }

        if ($bankAccount !== null) {
            return $bankAccount;
        }

        return ManagementBankAccount::consolidated();
    }

    private function lineHash(
        string $accountNumber,
        string $operationDate,
        string $direction,
        float $amount,
        string $description,
    ): string {
        return hash('sha256', implode('|', [
            $accountNumber,
            $operationDate,
            $direction,
            number_format($amount, 2, '.', ''),
            mb_strtolower(trim($description)),
        ]));
    }
}
