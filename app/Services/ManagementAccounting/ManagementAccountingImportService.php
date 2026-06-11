<?php

namespace App\Services\ManagementAccounting;

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
    ) {}

    public function importFromUpload(
        UploadedFile $file,
        ManagementBankAccount $bankAccount,
        User $importer,
    ): ManagementStatementImport {
        $parsed = $this->parser->parse($file->getRealPath() ?: $file->path());

        if ($parsed['account_number'] !== null
            && $parsed['account_number'] !== $bankAccount->account_number) {
            $matchedAccount = ManagementBankAccount::query()
                ->where('account_number', $parsed['account_number'])
                ->first();

            if ($matchedAccount !== null) {
                $bankAccount = $matchedAccount;
            }
        }

        return DB::transaction(function () use ($file, $bankAccount, $importer, $parsed): ManagementStatementImport {
            $import = ManagementStatementImport::query()->create([
                'bank_account_id' => $bankAccount->id,
                'format' => SberRegistryXlsxParser::FORMAT,
                'file_name' => $file->getClientOriginalName(),
                'imported_by' => $importer->id,
                'status' => 'draft',
            ]);

            $totalIn = 0.0;
            $totalOut = 0.0;
            $periodFrom = null;
            $periodTo = null;
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

            return $import->fresh(['bankAccount', 'importer']);
        });
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
