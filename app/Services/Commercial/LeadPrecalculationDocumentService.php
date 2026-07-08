<?php

declare(strict_types=1);

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Models\Order;
use App\Services\LeadPrecalculationService;
use App\Support\OrderLeadPrecalculationSnapshotResolver;
use Illuminate\Support\Str;

final class LeadPrecalculationDocumentService
{
    public function __construct(
        private readonly LeadPrecalculationService $precalculationService,
        private readonly LeadProposalPdfService $pdfService,
    ) {}

    /**
     * @return array{html: string, file_name: string, precalculation: array<string, mixed>}
     */
    public function render(Lead $lead): array
    {
        $precalculation = $this->precalculationService->calculate(
            is_array($lead->precalculation) ? $lead->precalculation : [],
        );

        $lead->loadMissing('counterparty');

        $html = $this->buildHtml($lead, $precalculation);
        $fileName = 'precalculation-'.Str::slug((string) ($lead->number ?: 'lead-'.$lead->id)).'.html';

        return [
            'html' => $html,
            'file_name' => $fileName,
            'precalculation' => $precalculation,
        ];
    }

    public function renderPdf(Lead $lead): ?string
    {
        $document = $this->render($lead);

        return $this->pdfService->convertHtmlToPdf(
            $document['html'],
            str_replace('.html', '.pdf', $document['file_name']),
        );
    }

    /**
     * @return array{html: string, file_name: string, precalculation: array<string, mixed>}
     */
    public function renderForOrderSnapshot(Order $order): array
    {
        $raw = OrderLeadPrecalculationSnapshotResolver::rawSnapshot($order);
        abort_if($raw === null, 404);

        $precalculation = $this->precalculationService->calculate($raw);

        $order->loadMissing(['client:id,name', 'lead:id,number,title,counterparty_id', 'lead.counterparty:id,name']);

        $html = $this->buildHtmlForOrderSnapshot($order, $precalculation);
        $slug = Str::slug((string) ($order->order_number ?: 'order-'.$order->id));
        $fileName = 'precalculation-'.$slug.'.html';

        return [
            'html' => $html,
            'file_name' => $fileName,
            'precalculation' => $precalculation,
        ];
    }

    public function renderPdfForOrderSnapshot(Order $order): ?string
    {
        $document = $this->renderForOrderSnapshot($order);

        return $this->pdfService->convertHtmlToPdf(
            $document['html'],
            str_replace('.html', '.pdf', $document['file_name']),
        );
    }

    /**
     * @param  array<string, mixed>  $precalculation
     */
    private function buildHtmlForOrderSnapshot(Order $order, array $precalculation): string
    {
        $lead = $order->lead;
        $counterpartyName = $order->client?->name
            ?? $lead?->counterparty?->name
            ?? '—';
        $title = $lead?->title ?? 'Предрасчёт с лида';
        $leadNumber = $lead?->number ?? '';
        $orderNumber = (string) ($order->order_number ?: '#'.$order->id);

        $body = $this->buildDocumentBody(
            precalculation: $precalculation,
            counterpartyName: $counterpartyName,
            title: $title,
            referenceLabel: 'Заказ',
            referenceNumber: $orderNumber,
            secondaryLabel: filled($leadNumber) ? 'Лид' : null,
            secondaryNumber: filled($leadNumber) ? $leadNumber : null,
        );

        return $body;
    }

    /**
     * @param  array<string, mixed>  $precalculation
     */
    private function buildHtml(Lead $lead, array $precalculation): string
    {
        $lead->loadMissing('counterparty');

        return $this->buildDocumentBody(
            precalculation: $precalculation,
            counterpartyName: (string) ($lead->counterparty?->name ?? '—'),
            title: (string) ($lead->title ?? 'Предрасчёт'),
            referenceLabel: 'Лид',
            referenceNumber: (string) ($lead->number ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $precalculation
     */
    private function buildDocumentBody(
        array $precalculation,
        string $counterpartyName,
        string $title,
        string $referenceLabel,
        string $referenceNumber,
        ?string $secondaryLabel = null,
        ?string $secondaryNumber = null,
    ): string {
        $computed = is_array($precalculation['computed'] ?? null) ? $precalculation['computed'] : [];
        $goodsLines = is_array($precalculation['goods_lines'] ?? null) ? $precalculation['goods_lines'] : [];
        $serviceLines = is_array($precalculation['service_lines'] ?? null) ? $precalculation['service_lines'] : [];
        $statusLabel = match ($precalculation['status'] ?? 'draft') {
            'ready' => 'Готов',
            'archived' => 'Архив',
            default => 'Черновик',
        };

        $goodsRows = '';
        foreach ($goodsLines as $index => $line) {
            $lineResult = collect($computed['goods_lines'] ?? [])
                ->first(fn (array $row): bool => ($row['line_id'] ?? '') === ($line['id'] ?? ''));
            $lineTotal = $lineResult['summary']['total_landed'] ?? null;

            $goodsRows .= '<tr>'
                .'<td>'.e((string) ($index + 1)).'</td>'
                .'<td>'.e((string) ($line['description'] ?: '—')).'</td>'
                .'<td>'.e((string) ($line['tn_ved_code'] ?: '—')).'</td>'
                .'<td style="text-align:right">'.e($this->money($line['invoice_amount'])).'</td>'
                .'<td>'.e((string) ($line['currency'] ?? 'RUB')).'</td>'
                .'<td style="text-align:right">'.e($this->money($lineTotal)).'</td>'
                .'</tr>';
        }

        $serviceRows = '';
        foreach ($serviceLines as $line) {
            $serviceRows .= '<tr>'
                .'<td>'.e((string) ($line['title'] ?? 'Услуга')).'</td>'
                .'<td style="text-align:right">'.e($this->money($line['amount'] ?? null)).'</td>'
                .'</tr>';
        }

        $counterparty = e($counterpartyName);
        $titleEscaped = e($title);
        $secondaryLine = $secondaryLabel !== null && $secondaryNumber !== null
            ? '<div><strong>'.e($secondaryLabel).':</strong> '.e($secondaryNumber).'</div>'
            : '';
        $disclaimer = e((string) data_get($computed, 'goods_lines.0.summary.disclaimer', config('import_cost_calculator.disclaimer', '')));
        $referenceNumberEscaped = e($referenceNumber);

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Предрасчёт {$referenceNumberEscaped}</title>
<style>
body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
h1 { font-size: 22px; margin-bottom: 4px; }
.meta { color: #555; margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin: 16px 0; }
th, td { border: 1px solid #ddd; padding: 8px 10px; font-size: 13px; }
th { background: #f5f5f5; text-align: left; }
.totals { margin-top: 20px; width: 360px; margin-left: auto; }
.totals td { border: none; padding: 6px 0; }
.totals .label { color: #555; }
.totals .value { text-align: right; font-weight: 600; }
.grand { font-size: 18px; color: #0f766e; }
.note { margin-top: 24px; font-size: 12px; color: #666; line-height: 1.5; }
</style>
</head>
<body>
<h1>Коммерческий предрасчёт</h1>
<div class="meta">
    <div><strong>{$referenceLabel}:</strong> {$referenceNumberEscaped} · {$titleEscaped}</div>
    {$secondaryLine}
    <div><strong>Клиент:</strong> {$counterparty}</div>
    <div><strong>Статус:</strong> {$statusLabel}</div>
</div>

<h2>Товары</h2>
<table>
<thead>
<tr>
    <th>#</th><th>Описание</th><th>ТН ВЭД</th><th>Инвойс</th><th>Вал.</th><th>Итого, ₽</th>
</tr>
</thead>
<tbody>{$goodsRows}</tbody>
</table>

<h2>Услуги</h2>
<table>
<thead><tr><th>Услуга</th><th style="text-align:right">Сумма, ₽</th></tr></thead>
<tbody>{$serviceRows}</tbody>
</table>

<table class="totals">
<tr><td class="label">Товары + таможня</td><td class="value">{$this->money($computed['goods_total'] ?? null)}</td></tr>
<tr><td class="label">Услуги</td><td class="value">{$this->money($computed['services_total'] ?? null)}</td></tr>
<tr><td class="label grand">Итого клиенту</td><td class="value grand">{$this->money($computed['grand_total'] ?? null)}</td></tr>
</table>

<p class="note">{$disclaimer}</p>
</body>
</html>
HTML;
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 0, '.', ' ').' ₽';
    }
}
