<?php

namespace App\Services\Commercial;

/**
 * Готовит HTML-затравку к отправке: публичные пути картинок → cid: + список embed.
 */
class ProposalHtmlCidMailPreparer
{
    /**
     * @param  list<array{cid?: string, public_path?: string, relative_path?: string, mime?: string, filename?: string}>  $emailAssets
     * @return array{html: string, embeds: list<array{path: string, cid: string, mime: string}>}
     */
    public function prepare(string $html, array $emailAssets): array
    {
        $embeds = [];
        $replacements = [];

        foreach ($emailAssets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $publicPath = trim((string) ($asset['public_path'] ?? ''));
            $relativePath = trim((string) ($asset['relative_path'] ?? ''));
            $cid = trim((string) ($asset['cid'] ?? $asset['filename'] ?? ''));
            $mime = trim((string) ($asset['mime'] ?? 'image/png')) ?: 'image/png';

            if ($cid === '') {
                continue;
            }

            $absolute = $this->resolveAbsolutePath($publicPath, $relativePath);
            if ($absolute === null) {
                continue;
            }

            $embeds[] = [
                'path' => $absolute,
                'cid' => $cid,
                'mime' => $mime,
            ];

            if ($publicPath !== '') {
                $replacements['src="'.$publicPath.'"'] = 'src="cid:'.$cid.'"';
                $replacements["src='".$publicPath."'"] = "src='cid:".$cid."'";
            }

            if ($relativePath !== '') {
                $withSlash = '/'.ltrim($relativePath, '/');
                $replacements['src="'.$withSlash.'"'] = 'src="cid:'.$cid.'"';
                $replacements["src='".$withSlash."'"] = "src='cid:".$cid."'";
            }
        }

        return [
            'html' => $replacements === [] ? $html : strtr($html, $replacements),
            'embeds' => $embeds,
        ];
    }

    /**
     * Встраивает картинки как data: URI (для Gotenberg PDF).
     *
     * @param  list<array{public_path?: string, relative_path?: string, mime?: string}>  $emailAssets
     */
    public function inlineAsDataUris(string $html, array $emailAssets): string
    {
        $replacements = [];

        foreach ($emailAssets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $publicPath = trim((string) ($asset['public_path'] ?? ''));
            $relativePath = trim((string) ($asset['relative_path'] ?? ''));
            $mime = trim((string) ($asset['mime'] ?? 'image/png')) ?: 'image/png';
            $absolute = $this->resolveAbsolutePath($publicPath, $relativePath);

            if ($absolute === null) {
                continue;
            }

            $binary = file_get_contents($absolute);
            if ($binary === false || $binary === '') {
                continue;
            }

            $dataUri = 'data:'.$mime.';base64,'.base64_encode($binary);

            if ($publicPath !== '') {
                $replacements['src="'.$publicPath.'"'] = 'src="'.$dataUri.'"';
                $replacements["src='".$publicPath."'"] = "src='".$dataUri."'";
            }

            if ($relativePath !== '') {
                $withSlash = '/'.ltrim($relativePath, '/');
                $replacements['src="'.$withSlash.'"'] = 'src="'.$dataUri.'"';
                $replacements["src='".$withSlash."'"] = "src='".$dataUri."'";
            }
        }

        return $replacements === [] ? $html : strtr($html, $replacements);
    }

    private function resolveAbsolutePath(string $publicPath, string $relativePath): ?string
    {
        $candidates = [];

        if ($relativePath !== '') {
            $candidates[] = public_path(ltrim($relativePath, '/\\'));
        }

        if ($publicPath !== '') {
            $candidates[] = public_path(ltrim($publicPath, '/\\'));
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
