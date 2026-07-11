<?php

declare(strict_types=1);

/**
 * Upsert sales-practice pages from storage/sales-practice-book/manifest.json to prod MCP.
 *
 * - Creates new articles as drafts (MCP default).
 * - Updates if same parent_title + title already exists (intentional for re-run).
 * - Does not delete anything.
 *
 * Usage: php scripts/sales-practice-book-import-prod.php [--dry-run]
 */
$baseDir = dirname(__DIR__);
$manifestPath = $baseDir.'/storage/sales-practice-book/manifest.json';
$dryRun = in_array('--dry-run', $argv, true);

if (! is_readable($manifestPath)) {
    fwrite(STDERR, "Manifest not found: {$manifestPath}\n");
    exit(1);
}

/** @var list<array{parent_title:string,title:string,markdown_path:string,create_parent_if_missing?:bool}> $entries */
$entries = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

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
        'clientInfo' => ['name' => 'sales-practice-import', 'version' => '1.0.0'],
    ],
]);

if ($init['http_code'] >= 400) {
    fwrite(STDERR, 'initialize failed: '.substr($init['raw'], 0, 2000)."\n");
    exit(1);
}

$created = 0;
$updated = 0;
$failed = 0;
$id = 10;

foreach ($entries as $entry) {
    $parentTitle = trim((string) ($entry['parent_title'] ?? ''));
    $title = trim((string) ($entry['title'] ?? ''));
    $markdownPath = (string) ($entry['markdown_path'] ?? '');
    $createParent = (bool) ($entry['create_parent_if_missing'] ?? false);

    $absoluteMarkdown = str_starts_with($markdownPath, '/')
        ? $markdownPath
        : $baseDir.'/'.ltrim($markdownPath, '/');

    if ($parentTitle === '' || $title === '' || ! is_readable($absoluteMarkdown)) {
        fwrite(STDERR, "Invalid entry: {$title} / {$absoluteMarkdown}\n");
        $failed++;

        continue;
    }

    $markdown = trim((string) file_get_contents($absoluteMarkdown));
    if ($markdown === '') {
        fwrite(STDERR, "Empty markdown: {$absoluteMarkdown}\n");
        $failed++;

        continue;
    }

    echo ($dryRun ? 'DRY-RUN ' : '')."UPSERT «{$title}» under «{$parentTitle}»\n";

    if ($dryRun) {
        $created++;

        continue;
    }

    try {
        $result = mcpCall($mcp['url'], $mcp['auth'], $id++, 'upsert_sales_book_article', [
            'parent_title' => $parentTitle,
            'title' => $title,
            'markdown_content' => $markdown,
            'create_parent_if_missing' => $createParent,
            'tags' => ['практика продаж', 'черновик-из-лекции'],
        ]);
        $action = is_array($result) ? ($result['action'] ?? 'unknown') : 'unknown';
        $articleId = is_array($result) ? ($result['article_id'] ?? '?') : '?';
        echo "  → {$action} id={$articleId}\n";
        if ($action === 'updated') {
            $updated++;
        } else {
            $created++;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '  FAIL: '.$e->getMessage()."\n");
        $failed++;
    }
}

echo "\nDone: created={$created}, updated={$updated}, failed={$failed}".($dryRun ? ' (dry-run)' : '')."\n";
