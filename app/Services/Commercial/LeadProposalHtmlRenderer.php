<?php

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Models\ProposalHtmlTemplate;
use App\Services\LeadPrintFormDraftService;
use Illuminate\Support\Str;

class LeadProposalHtmlRenderer
{
    public function __construct(
        private readonly LeadPrintFormDraftService $leadPrintFormDraftService,
    ) {}

    /**
     * @return array{html: string, snapshot: array<string, mixed>}
     */
    public function render(ProposalHtmlTemplate $template, Lead $lead): array
    {
        $snapshot = $this->leadPrintFormDraftService->buildLeadSnapshot($lead);
        $body = $this->replacePlaceholders((string) $template->html_body, $snapshot);
        $css = trim((string) ($template->css_inline ?? ''));

        return [
            'html' => $this->wrapDocument($body, $css),
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function replacePlaceholders(string $html, array $snapshot): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z0-9_.]+)\}/i',
            function (array $matches) use ($snapshot): string {
                $path = $matches[1];
                $value = data_get($snapshot, $path);

                return $this->stringifyValue($value);
            },
            $html,
        );
    }

    private function wrapDocument(string $body, string $css): string
    {
        $safeBody = trim($body);
        $styleBlock = $css !== '' ? '<style>'.$css.'</style>' : '';

        if (Str::contains(strtolower($safeBody), '<html')) {
            if ($styleBlock !== '' && ! Str::contains(strtolower($safeBody), '<style')) {
                return preg_replace('/<head>/i', '<head>'.$styleBlock, $safeBody, 1) ?? $safeBody;
            }

            return $safeBody;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{$styleBlock}
</head>
<body>
{$safeBody}
</body>
</html>
HTML;
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }

        if (is_scalar($value)) {
            return e((string) $value);
        }

        return '';
    }
}
