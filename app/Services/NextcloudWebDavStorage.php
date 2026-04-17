<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class NextcloudWebDavStorage
{
    private bool $rootTailPrepared = false;

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $webdavRoot,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function put(string $path, string $contents): void
    {
        $this->guardConfigured();
        $this->ensureConfiguredRootTailExists();
        $this->ensureRemoteDirectory(dirname($path));

        $response = $this->request('PUT', $this->buildFileUrl($path), $contents);
        $this->assertStatusIn($response->status(), [200, 201, 204], 'Не удалось загрузить файл в Nextcloud.');
    }

    public function get(string $path): string
    {
        $this->guardConfigured();

        $response = $this->request('GET', $this->buildFileUrl($path));
        $this->assertStatusIn($response->status(), [200], 'Не удалось получить файл из Nextcloud.');

        return (string) $response->body();
    }

    public function delete(string $path): void
    {
        $this->guardConfigured();

        $response = $this->request('DELETE', $this->buildFileUrl($path));
        $this->assertStatusIn($response->status(), [200, 204, 404], 'Не удалось удалить файл из Nextcloud.');
    }

    public function exists(string $path): bool
    {
        $this->guardConfigured();

        $response = $this->request('HEAD', $this->buildFileUrl($path));

        return in_array($response->status(), [200, 204], true);
    }

    private function ensureRemoteDirectory(string $directory): void
    {
        $normalized = trim(str_replace('\\', '/', $directory), '/');
        if ($normalized === '' || $normalized === '.') {
            return;
        }

        $segments = explode('/', $normalized);
        $current = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $current .= '/'.$segment;
            $response = $this->request('MKCOL', $this->buildFileUrl(ltrim($current, '/')));

            // 201 - created, 405 - already exists
            if (! in_array($response->status(), [201, 405], true)) {
                throw new RuntimeException(sprintf(
                    'Не удалось создать директорию в Nextcloud (%s), HTTP %d.',
                    $current,
                    $response->status()
                ));
            }
        }
    }

    private function ensureConfiguredRootTailExists(): void
    {
        if ($this->rootTailPrepared) {
            return;
        }

        [$baseRoot, $tailSegments] = $this->splitWebDavRootBaseAndTail(
            $this->normalizeWebDavRoot($this->webdavRoot, (string) $this->username)
        );

        if ($tailSegments === []) {
            $this->rootTailPrepared = true;

            return;
        }

        $current = '';
        foreach ($tailSegments as $segment) {
            $current .= '/'.$segment;

            $response = $this->request(
                'MKCOL',
                $this->buildAbsoluteUrl($baseRoot, ltrim($current, '/'))
            );

            // 201 - created, 405 - already exists
            if (! in_array($response->status(), [201, 405], true)) {
                throw new RuntimeException(sprintf(
                    'Не удалось создать корневую директорию Nextcloud (%s), HTTP %d.',
                    $current,
                    $response->status()
                ));
            }
        }

        $this->rootTailPrepared = true;
    }

    private function buildFileUrl(string $path): string
    {
        $root = $this->normalizeWebDavRoot($this->webdavRoot, (string) $this->username);
        $filePath = $this->encodePathSegments(ltrim(str_replace('\\', '/', $path), '/'));

        return $this->buildAbsoluteUrl($root, $filePath);
    }

    private function normalizeWebDavRoot(string $root, string $username): string
    {
        $normalizedRoot = trim(str_replace('\\', '/', $root), '/');
        $normalizedUser = trim($username);

        if ($normalizedUser === '') {
            return $normalizedRoot;
        }

        // Allow NEXTCLOUD_WEBDAV_ROOT=/remote.php/dav/files and auto-append current user.
        if (preg_match('#^remote\.php/dav/files/?$#', $normalizedRoot) === 1) {
            return $normalizedRoot.'/'.rawurlencode($normalizedUser);
        }

        return $normalizedRoot;
    }

    private function encodePathSegments(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return collect(explode('/', $path))
            ->map(fn (string $segment): string => rawurlencode($segment))
            ->implode('/');
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function splitWebDavRootBaseAndTail(string $normalizedRoot): array
    {
        $parts = array_values(array_filter(explode('/', trim($normalizedRoot, '/')), static fn (string $part): bool => $part !== ''));
        $filesIndex = array_search('files', $parts, true);

        if ($filesIndex === false || ! isset($parts[$filesIndex + 1])) {
            return [$normalizedRoot, []];
        }

        $base = implode('/', array_slice($parts, 0, $filesIndex + 2));
        $tail = array_slice($parts, $filesIndex + 2);

        return [$base, $tail];
    }

    private function buildAbsoluteUrl(string $normalizedRoot, string $encodedPath): string
    {
        $base = rtrim((string) $this->baseUrl, '/');
        $root = trim($normalizedRoot, '/');

        if ($encodedPath === '') {
            return sprintf('%s/%s', $base, $root);
        }

        return sprintf('%s/%s/%s', $base, $root, $encodedPath);
    }

    private function request(string $method, string $url, ?string $body = null)
    {
        $request = Http::withBasicAuth((string) $this->username, (string) $this->password)
            ->timeout($this->timeoutSeconds)
            ->withHeaders([
                'OCS-APIRequest' => 'true',
            ]);

        if ($body !== null) {
            return $request->send($method, $url, ['body' => $body]);
        }

        return $request->send($method, $url);
    }

    private function assertStatusIn(int $status, array $allowedStatuses, string $errorMessage): void
    {
        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        throw new RuntimeException(sprintf('%s HTTP %d.', $errorMessage, $status));
    }

    private function guardConfigured(): void
    {
        if (blank($this->baseUrl) || blank($this->username) || blank($this->password) || blank($this->webdavRoot)) {
            throw new RuntimeException('Nextcloud WebDAV не настроен. Проверьте переменные DOCUMENT_STORAGE/NEXTCLOUD_* в .env.');
        }
    }
}
