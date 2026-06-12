<?php

namespace App\Http\Controllers;

use App\Models\OrderDocument;
use App\Support\PrintFormVerificationCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class PublicOrderDocumentVerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request, OrderDocument $orderDocument): Response
    {
        abort_unless(
            PrintFormVerificationCode::matchesOrderDocument($orderDocument, (string) $request->query('code')),
            404
        );

        $orderDocument->loadMissing(['order.client', 'order.carrier']);
        $order = $orderDocument->order;

        $metadata = is_array($orderDocument->metadata) ? $orderDocument->metadata : [];
        $sha256 = (string) ($metadata['pdf_certified_sha256'] ?? $metadata['pdf_verification_stamped_sha256'] ?? '');
        $certifiedAt = (string) ($metadata['pdf_certified_at'] ?? '');

        $signatureStatus = $this->signatureStatusLabel($orderDocument);

        return response($this->html($orderDocument, $order, $sha256, $certifiedAt, $signatureStatus), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  \App\Models\Order|null  $order
     */
    private function html(OrderDocument $document, $order, string $sha256, string $certifiedAt, string $signatureStatus): string
    {
        $title = 'Проверка целостности документа';

        $orderNumber = $order !== null && filled($order->order_number)
            ? htmlspecialchars((string) $order->order_number, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : '—';
        $orderDate = $order !== null && $order->order_date instanceof Carbon
            ? htmlspecialchars($order->order_date->format('d.m.Y'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : '—';
        $customerName = $order !== null && $order->client !== null && filled($order->client->name)
            ? htmlspecialchars((string) $order->client->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : '—';
        $carrierName = $order !== null && $order->carrier !== null && filled($order->carrier->name)
            ? htmlspecialchars((string) $order->carrier->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : '—';
        $hash = htmlspecialchars($sha256 !== '' ? $sha256 : 'PDF ещё не зафиксирован в CRM', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $date = htmlspecialchars($certifiedAt !== '' ? $certifiedAt : 'нет данных', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $signatureBadge = $signatureStatus === 'Подписан с двух сторон'
            ? '<span class="ok">✓ Подписан с двух сторон</span>'
            : '<span class="warn">⚠ '.htmlspecialchars($signatureStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>';

        $hashMatch = $sha256 !== ''
            ? '<p>Сверьте этот хеш с SHA-256 полученного PDF — они должны совпадать.</p>'
            : '';

        return <<<HTML
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; color: #111827; }
        main { max-width: 760px; }
        h1 { font-size: 1.25rem; margin-bottom: 24px; }
        dt { color: #6b7280; margin-top: 16px; font-size: 0.875rem; }
        dd { margin: 4px 0 0; font-weight: 500; overflow-wrap: anywhere; }
        .ok { display: inline-block; padding: 4px 10px; background: #dcfce7; color: #166534; border-radius: 6px; font-size: 0.875rem; }
        .warn { display: inline-block; padding: 4px 10px; background: #fef3c7; color: #92400e; border-radius: 6px; font-size: 0.875rem; }
        .hash { font-family: monospace; font-size: 0.75rem; background: #f3f4f6; padding: 8px; border-radius: 4px; word-break: break-all; }
        .hint { color: #6b7280; font-size: 0.875rem; margin-top: 8px; }
    </style>
</head>
<body>
<main>
    <h1>{$title}</h1>
    <p>{$signatureBadge}</p>
    <dl>
        <dt>Заявка</dt><dd>№ {$orderNumber} от {$orderDate}</dd>
        <dt>Заказчик</dt><dd>{$customerName}</dd>
        <dt>Перевозчик</dt><dd>{$carrierName}</dd>
        <dt>Статус подписания</dt><dd>{$signatureStatus}</dd>
        <dt>Дата фиксации PDF</dt><dd>{$date}</dd>
        <dt>SHA-256 (контрольная сумма)</dt><dd class="hash">{$hash}</dd>
    </dl>
    {$hashMatch}
    <p class="hint">Сравните хеш на этой странице с хешем файла — если совпадают, документ не менялся после подписания.</p>
</main>
</body>
</html>
HTML;
    }

    private function signatureStatusLabel(OrderDocument $document): string
    {
        return match ((string) ($document->signature_status ?? '')) {
            'signed_both_sides' => 'Подписан с двух сторон',
            'signed_internal' => 'Подписан внутри (нужна подпись контрагента)',
            'pending_signature' => 'Ожидает подписания',
            'not_requested' => 'Подпись не запрошена',
            default => '—',
        };
    }
}
