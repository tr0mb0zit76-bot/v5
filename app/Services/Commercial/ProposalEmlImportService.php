<?php

namespace App\Services\Commercial;

use App\Models\ProposalHtmlTemplate;
use App\Support\ProposalHtmlEmailDocumentNormalizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ProposalEmlImportService
{
    /**
     * @return array{
     *     template: ProposalHtmlTemplate,
     *     assets_written: int,
     *     html_bytes: int
     * }
     */
    public function importFile(string $emlPath, string $name, string $slug, ?int $ownerUserId = null): array
    {
        if (! is_file($emlPath)) {
            throw new InvalidArgumentException('EML-файл не найден: '.$emlPath);
        }

        $raw = file_get_contents($emlPath);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Не удалось прочитать EML-файл.');
        }

        return $this->importContents($raw, $name, $slug, $ownerUserId);
    }

    /**
     * @return array{
     *     template: ProposalHtmlTemplate,
     *     assets_written: int,
     *     html_bytes: int
     * }
     */
    public function importContents(string $emlContents, string $name, string $slug, ?int $ownerUserId = null): array
    {
        $slug = Str::slug($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('Slug шаблона пустой.');
        }

        $parsed = $this->parseMultipartRelated($emlContents);
        $html = $parsed['html'];
        $assetDirRelative = 'assets/proposal-emails/'.$slug;
        $assetDirAbsolute = public_path($assetDirRelative);
        File::ensureDirectoryExists($assetDirAbsolute);

        $assets = [];
        $replacements = [];

        foreach ($parsed['parts'] as $part) {
            $cid = $part['content_id'];
            $ext = $this->extensionForMime($part['mime']);
            $safeBase = $this->safeFilenameFromCid($cid);
            $filename = $safeBase.'.'.$ext;
            $absolutePath = $assetDirAbsolute.DIRECTORY_SEPARATOR.$filename;
            file_put_contents($absolutePath, $part['binary']);

            $publicPath = '/'.$assetDirRelative.'/'.$filename;
            $assets[] = [
                'cid' => $filename,
                'public_path' => $publicPath,
                'relative_path' => $assetDirRelative.'/'.$filename,
                'mime' => $part['mime'],
                'filename' => $filename,
                'source_cid' => $cid,
            ];

            $replacements['cid:'.$cid] = $publicPath;
            $replacements['cid:'.trim($cid, '<>')] = $publicPath;
        }

        $html = strtr($html, $replacements);
        $html = $this->normalizePlaceholders($html);
        $normalized = ProposalHtmlEmailDocumentNormalizer::normalize($html);

        $template = ProposalHtmlTemplate::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'is_active' => true,
                'html_body' => $normalized['body'],
                'css_inline' => $normalized['css'] !== '' ? $normalized['css'] : null,
                'email_assets' => $assets,
                'published_at' => now(),
                'owner_user_id' => $ownerUserId,
                'visibility' => 'workspace',
            ],
        );

        if (! $template->wasRecentlyCreated) {
            $template->forceFill([
                'version' => max(1, (int) $template->version) + 1,
            ])->save();
        }

        return [
            'template' => $template->refresh(),
            'assets_written' => count($assets),
            'html_bytes' => strlen($normalized['body']),
        ];
    }

    /**
     * Привести уже сохранённый full-document HTML к форме для редактора (body + css).
     *
     * @return array{html_body: string, css_inline: string|null, changed: bool}
     */
    public function normalizeStoredTemplate(ProposalHtmlTemplate $template): array
    {
        $html = (string) $template->html_body;
        $normalized = ProposalHtmlEmailDocumentNormalizer::normalize(
            $html,
            is_string($template->css_inline) ? $template->css_inline : null,
        );

        $changed = $normalized['body'] !== trim($html)
            || trim((string) ($template->css_inline ?? '')) !== $normalized['css'];

        if ($changed) {
            $template->forceFill([
                'html_body' => $normalized['body'],
                'css_inline' => $normalized['css'] !== '' ? $normalized['css'] : null,
                'version' => max(1, (int) $template->version) + 1,
            ])->save();
        }

        return [
            'html_body' => $normalized['body'],
            'css_inline' => $normalized['css'] !== '' ? $normalized['css'] : null,
            'changed' => $changed,
        ];
    }

    /**
     * @return array{
     *     html: string,
     *     parts: list<array{content_id: string, mime: string, binary: string}>
     * }
     */
    public function parseMultipartRelated(string $raw): array
    {
        if (! preg_match('/boundary="?([^";\r\n]+)"?/i', $raw, $boundaryMatch)) {
            throw new InvalidArgumentException('В EML не найден MIME boundary.');
        }

        $boundary = trim($boundaryMatch[1]);
        $parts = preg_split('/--'.preg_quote($boundary, '/').'(?:--)?\r?\n/', $raw) ?: [];

        $html = null;
        $images = [];

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || str_starts_with($part, '--')) {
                continue;
            }

            $split = preg_split("/\r?\n\r?\n/", $part, 2);
            if ($split === false || count($split) < 2) {
                continue;
            }

            [$headersRaw, $body] = $split;
            $headers = $this->parseHeaders($headersRaw);
            $contentType = strtolower((string) ($headers['content-type'] ?? ''));
            $encoding = strtolower((string) ($headers['content-transfer-encoding'] ?? '7bit'));
            $contentId = isset($headers['content-id'])
                ? trim((string) $headers['content-id'], " <>\"'")
                : '';

            if (str_contains($contentType, 'text/html')) {
                $decoded = $this->decodeBody($body, $encoding);
                $charset = 'UTF-8';
                if (preg_match('/charset=["\']?([^"\'\s;]+)/i', $contentType, $charsetMatch) === 1) {
                    $charset = $charsetMatch[1];
                }
                if (strcasecmp($charset, 'UTF-8') !== 0) {
                    $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);
                    if (is_string($converted) && $converted !== '') {
                        $decoded = $converted;
                    }
                }
                $html = $decoded;

                continue;
            }

            if ($contentId === '' || ! str_starts_with($contentType, 'image/')) {
                continue;
            }

            $mime = strtok($contentType, ';') ?: 'application/octet-stream';
            $images[] = [
                'content_id' => $contentId,
                'mime' => $mime,
                'binary' => $this->decodeBody($body, $encoding),
            ];
        }

        if ($html === null || trim($html) === '') {
            throw new InvalidArgumentException('В EML не найдена HTML-часть.');
        }

        return [
            'html' => $html,
            'parts' => $images,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $headersRaw): array
    {
        $headers = [];
        $normalized = preg_replace("/\r?\n[ \t]+/", ' ', $headersRaw) ?? $headersRaw;

        foreach (preg_split("/\r?\n/", $normalized) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    private function decodeBody(string $body, string $encoding): string
    {
        $body = rtrim($body, "\r\n");

        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? $body, true),
            'quoted-printable' => (string) quoted_printable_decode($body),
            default => $body,
        };
    }

    private function normalizePlaceholders(string $html): string
    {
        $map = [
            'МЕНЯЕМ_ИМЯ' => '{counterparty.contact_person}',
            'меняем_имя' => '{counterparty.contact_person}',
        ];

        return strtr($html, $map);
    }

    private function extensionForMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
    }

    private function safeFilenameFromCid(string $cid): string
    {
        $base = Str::before($cid, '@');
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $base) ?: 'asset';

        return trim($base, '-._') ?: 'asset';
    }
}
