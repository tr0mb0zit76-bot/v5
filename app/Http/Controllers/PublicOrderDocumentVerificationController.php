<?php

namespace App\Http\Controllers;

use App\Models\OrderDocument;
use App\Support\PrintFormVerificationCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicOrderDocumentVerificationController extends Controller
{
    public function show(Request $request, OrderDocument $orderDocument): Response
    {
        abort_unless(
            PrintFormVerificationCode::matchesOrderDocument($orderDocument, (string) $request->query('code')),
            404
        );

        $metadata = is_array($orderDocument->metadata) ? $orderDocument->metadata : [];
        $sha256 = (string) ($metadata['pdf_certified_sha256'] ?? $metadata['pdf_verification_stamped_sha256'] ?? '');
        $certifiedAt = (string) ($metadata['pdf_certified_at'] ?? '');

        return response($this->html($orderDocument, $sha256, $certifiedAt), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function html(OrderDocument $document, string $sha256, string $certifiedAt): string
    {
        $title = 'Проверка документа';
        $number = htmlspecialchars((string) ($document->number ?: 'без номера'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $orderId = htmlspecialchars((string) ($document->order_id ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $documentId = htmlspecialchars((string) $document->id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $hash = htmlspecialchars($sha256 !== '' ? $sha256 : 'PDF ещё не зафиксирован в CRM', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $date = htmlspecialchars($certifiedAt !== '' ? $certifiedAt : 'нет данных', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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
        dt { color: #6b7280; margin-top: 16px; }
        dd { margin: 4px 0 0; overflow-wrap: anywhere; }
        .ok { display: inline-block; padding: 6px 10px; background: #dcfce7; color: #166534; border-radius: 6px; }
    </style>
</head>
<body>
<main>
    <h1>{$title}</h1>
    <p class="ok">QR-код принадлежит документу CRM</p>
    <dl>
        <dt>Заявка</dt><dd>{$orderId}</dd>
        <dt>Документ</dt><dd>{$documentId}</dd>
        <dt>Номер документа</dt><dd>{$number}</dd>
        <dt>Дата фиксации PDF</dt><dd>{$date}</dd>
        <dt>SHA-256 финального PDF</dt><dd>{$hash}</dd>
    </dl>
</main>
</body>
</html>
HTML;
    }
}
