<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementLine;
use App\Models\OrderOneCDocument;

/**
 * Сводка здоровья моста CRM ↔ 1С по публикациям.
 */
final class OneCBridgeHealthService
{
    public function __construct(
        private readonly OneCPublicationCatalog $catalog,
        private readonly OneCBpClient $oneC,
    ) {}

    /**
     * @return array{
     *     status: 'ok'|'attention'|'error',
     *     summary_ru: string,
     *     companies: list<array{
     *         code: string,
     *         label: string,
     *         odata_ok: bool,
     *         last_error: ?string,
     *         bank_account_id: ?int,
     *         pending_count: int,
     *         docs_gap_count: int,
     *         issues: list<string>,
     *         needs_escalation: bool
     *     }>
     * }
     */
    public function evaluate(?string $companyCode = null): array
    {
        $pubs = $companyCode !== null && $companyCode !== ''
            ? [$this->catalog->get($companyCode)]
            : $this->catalog->all();

        $docsGap = OrderOneCDocument::query()
            ->whereIn('status', [
                OrderOneCDocument::STATUS_FAILED,
                OrderOneCDocument::STATUS_PENDING,
            ])
            ->count();

        $companies = [];
        $hasError = false;
        $hasAttention = false;

        foreach ($pubs as $pub) {
            $ping = $this->oneC->ping($pub['base_url']);
            $bank = ManagementBankAccount::query()
                ->where('account_number', $pub['bank_account_number'])
                ->first();

            $pending = 0;
            if ($bank !== null) {
                $pending = ManagementStatementLine::query()
                    ->where('bank_account_id', $bank->id)
                    ->where('status', 'pending')
                    ->count();
            }

            $issues = [];
            if (! $ping['ok']) {
                $issues[] = 'OData недоступна: '.($ping['error'] ?? 'ошибка');
                $hasError = true;
            }
            if ($bank === null) {
                $issues[] = 'В CRM нет счёта '.$pub['bank_account_number'];
                $hasAttention = true;
            }
            // Pending — только инфо для бухгалтера; эскалацию не поднимает.
            if ($pending > 0) {
                $issues[] = "Неразнесённых платежей: {$pending}";
            }
            $companyDocsGap = $pub['code'] === OneCPublicationCatalog::CODE_AUTALLIANCE ? $docsGap : 0;
            if ($companyDocsGap > 0) {
                $issues[] = "Документы 1С с ошибкой/ожиданием: {$companyDocsGap}";
                $hasAttention = true;
            }

            $companies[] = [
                'code' => $pub['code'],
                'label' => $pub['label'],
                'odata_ok' => $ping['ok'],
                'last_error' => $ping['error'],
                'bank_account_id' => $bank?->id,
                'pending_count' => $pending,
                'docs_gap_count' => $companyDocsGap,
                'issues' => $issues,
                'needs_escalation' => ! $ping['ok'] || $bank === null || $companyDocsGap > 0,
            ];
        }

        $status = $hasError ? 'error' : ($hasAttention ? 'attention' : 'ok');
        $pendingTotal = array_sum(array_column($companies, 'pending_count'));
        $summary = match (true) {
            $status === 'error' => 'Мост 1С недоступен или сломан — нужна проверка OData.',
            $status === 'attention' => 'Есть замечания по мосту 1С — проверьте OData или документы.',
            $pendingTotal > 0 => "Мост доступен. Неразнесённых платежей: {$pendingTotal}.",
            default => 'Всё в порядке: мост доступен, платежи разнесены, документы на месте.',
        };

        return [
            'status' => $status,
            'summary_ru' => $summary,
            'companies' => $companies,
        ];
    }
}
