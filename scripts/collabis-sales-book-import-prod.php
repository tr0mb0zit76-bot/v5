<?php

declare(strict_types=1);

/**
 * Import Collabis-exported Sales Book pages to prod via MCP.
 * Skips articles that already exist (same parent_title + title).
 *
 * Usage:
 *   php scripts/collabis-sales-book-import-prod.php [--dry-run] [--manifest=path]
 */
$baseDir = dirname(__DIR__);
$manifestPath = $baseDir.'/storage/collabis-export/manifest.json';
$existingPath = $baseDir.'/tools/prod-existing-utf8.tsv';

$dryRun = in_array('--dry-run', $argv, true);
$refreshExisting = in_array('--refresh-existing', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--manifest=')) {
        $manifestPath = substr($arg, 11);
    }
    if (str_starts_with($arg, '--existing=')) {
        $existingPath = substr($arg, 11);
    }
}

if (! is_readable($manifestPath)) {
    fwrite(STDERR, "Manifest not found: {$manifestPath}\n");
    exit(1);
}

/** @var list<array{parent_title:string,title:string,markdown_path:string,collabis_url?:string}> $entries */
$entries = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

if (! is_array($entries)) {
    fwrite(STDERR, "Invalid manifest JSON.\n");
    exit(1);
}

if ($refreshExisting) {
    refreshExistingFromProd($baseDir.'/scripts/prod-plink.ps1', $existingPath);
}

$existing = loadExistingKeys($existingPath);

function loadExistingKeys(string $path): array
{
    if (! is_readable($path)) {
        fwrite(STDERR, "Warning: existing articles file missing ({$path}); will not skip duplicates.\n");

        return [];
    }

    $keys = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $parts = explode("\t", $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $parent = maybeFixMojibake(trim($parts[0]));
        if (strcasecmp($parent, 'NULL') === 0) {
            $parent = '';
        }
        $title = maybeFixMojibake(trim($parts[1]));
        $keys[normalizeKey($parent, $title)] = true;
        $keys[normalizeKey(normalizeTitleToken($parent), $title)] = true;
        $keys['|'.normalizeTitleToken($title)] = true;
    }

    return $keys;
}

function maybeFixMojibake(string $value): string
{
    if (! preg_match('/[ЁЯ╨]/u', $value)) {
        return $value;
    }

    $bytes = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    if (! is_string($bytes) || $bytes === '') {
        return $value;
    }

    $fixed = @mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1');

    return is_string($fixed) && $fixed !== '' ? $fixed : $value;
}

function normalizeKey(string $parentTitle, string $title): string
{
    return mb_strtolower(trim($parentTitle).'|'.normalizeTitleToken($title));
}

function normalizeTitleToken(string $title): string
{
    $title = trim($title);
    $title = preg_replace('/[\x{E000}-\x{F8FF}\x{FE0F}\x{200D}]/u', '', $title) ?? $title;
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title) ?? $title;
    $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

    return mb_strtolower(trim($title));
}

function refreshExistingFromProd(string $plinkScript, string $targetPath): void
{
    if (! is_readable($plinkScript)) {
        throw new RuntimeException("prod-plink script not found: {$plinkScript}");
    }

    $sql = "SELECT IFNULL(p.title, ''), a.title FROM sales_book_articles a "
        .'LEFT JOIN sales_book_articles p ON p.id=a.parent_id ORDER BY a.id';

    $remote = 'mysql -u logodmin -pvP1xU4qV0s clear_base --default-character-set=utf8mb4 -N -e '
        .escapeshellarg($sql).' 2>/dev/null';

    $command = 'powershell -NoProfile -Command "& {'
        .'Set-Location '.escapeshellarg(dirname($plinkScript, 2)).'; '
        .'& '.escapeshellarg($plinkScript).' '.escapeshellarg($remote).' | Out-File -Encoding utf8 '
        .escapeshellarg($targetPath).'}"';

    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || ! is_readable($targetPath)) {
        throw new RuntimeException('Failed to refresh prod existing articles list.');
    }
}

function loadProdMcpConfig(): array
{
    $userProfile = getenv('USERPROFILE') ?: getenv('HOME') ?: '';
    $mcpConfigPath = rtrim(str_replace('\\', '/', $userProfile), '/').'/.cursor/mcp.json';

    if (! is_readable($mcpConfigPath)) {
        throw new RuntimeException("mcp.json not found at {$mcpConfigPath}");
    }

    $config = json_decode((string) file_get_contents($mcpConfigPath), true, 512, JSON_THROW_ON_ERROR);
    $prod = $config['mcpServers']['v5-crm-prod'] ?? null;
    $url = is_array($prod) ? ($prod['url'] ?? null) : null;
    $auth = is_array($prod['headers'] ?? null) ? ($prod['headers']['Authorization'] ?? null) : null;

    if (! is_string($url) || ! is_string($auth) || $auth === '') {
        throw new RuntimeException('v5-crm-prod url/Authorization missing in mcp.json');
    }

    return ['url' => $url, 'auth' => $auth];
}

function mcpPost(string $url, string $auth, array $payload): array
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: '.$auth,
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 180,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('curl error: '.$err);
    }

    return ['http_code' => $code, 'raw' => $raw];
}

function parseMcpResponse(string $raw): mixed
{
    $trimmed = trim($raw);
    if (str_starts_with($trimmed, 'event:')) {
        foreach (preg_split("/\r?\n/", $trimmed) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $trimmed = substr($line, 6);
                break;
            }
        }
    }

    return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
}

function mcpCall(string $url, string $auth, int $id, string $tool, array $arguments): mixed
{
    $call = mcpPost($url, $auth, [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool,
            'arguments' => $arguments,
        ],
    ]);

    if ($call['http_code'] >= 400) {
        throw new RuntimeException("HTTP {$call['http_code']}: ".substr($call['raw'], 0, 2000));
    }

    $data = parseMcpResponse($call['raw']);
    if (isset($data['error'])) {
        throw new RuntimeException('MCP error: '.json_encode($data['error'], JSON_UNESCAPED_UNICODE));
    }

    $content = $data['result']['content'] ?? null;
    if (is_array($content) && isset($content[0]['text'])) {
        return json_decode($content[0]['text'], true, 512, JSON_THROW_ON_ERROR);
    }

    return $data['result'] ?? $data;
}

try {
    $mcp = loadProdMcpConfig();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}

$init = mcpPost($mcp['url'], $mcp['auth'], [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2024-11-05',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'collabis-import', 'version' => '1.0.0'],
    ],
]);

if ($init['http_code'] >= 400) {
    fwrite(STDERR, "initialize failed: ".substr($init['raw'], 0, 2000)."\n");
    exit(1);
}

$created = 0;
$skipped = 0;
$failed = 0;
$id = 10;

foreach ($entries as $entry) {
    $parentTitle = trim((string) ($entry['parent_title'] ?? ''));
    $title = trim((string) ($entry['title'] ?? ''));
    $markdownPath = (string) ($entry['markdown_path'] ?? '');

    if ($parentTitle === '' || $title === '') {
        if ($parentTitle === '' && $title !== '') {
            echo "SKIP root section: «{$title}»\n";
            $skipped++;

            continue;
        }

        fwrite(STDERR, "Skip invalid entry: ".json_encode($entry, JSON_UNESCAPED_UNICODE)."\n");
        $failed++;

        continue;
    }

    $absoluteMarkdown = str_starts_with($markdownPath, '/')
        ? $markdownPath
        : $baseDir.'/'.ltrim($markdownPath, '/');

    if (! is_readable($absoluteMarkdown)) {
        fwrite(STDERR, "Markdown missing: {$absoluteMarkdown}\n");
        $failed++;

        continue;
    }

    $key = normalizeKey($parentTitle, $title);
    if (isset($existing[$key]) || isset($existing['|'.normalizeTitleToken($title)])) {
        echo "SKIP existing: «{$title}» under «{$parentTitle}»\n";
        $skipped++;

        continue;
    }

    $markdown = trim((string) file_get_contents($absoluteMarkdown));
    if ($markdown === '') {
        fwrite(STDERR, "Empty markdown: {$absoluteMarkdown}\n");
        $failed++;

        continue;
    }

    echo ($dryRun ? 'DRY-RUN create' : 'CREATE').": «{$title}» under «{$parentTitle}»\n";

    if ($dryRun) {
        $created++;

        continue;
    }

    try {
        $result = mcpCall($mcp['url'], $mcp['auth'], $id++, 'upsert_sales_book_article', [
            'parent_title' => $parentTitle,
            'title' => $title,
            'markdown_content' => $markdown,
            'create_parent_if_missing' => true,
        ]);
        $action = is_array($result) ? ($result['action'] ?? 'unknown') : 'unknown';
        if ($action === 'updated') {
            echo "  → updated (unexpected, article may already exist under different title)\n";
        } else {
            echo "  → {$action}\n";
        }
        $existing[$key] = true;
        $created++;
    } catch (Throwable $e) {
        fwrite(STDERR, "  FAIL: {$e->getMessage()}\n");
        $failed++;
    }
}

echo "\nDone: created={$created}, skipped={$skipped}, failed={$failed}".($dryRun ? ' (dry-run)' : '')."\n";

exit($failed > 0 ? 1 : 0);
