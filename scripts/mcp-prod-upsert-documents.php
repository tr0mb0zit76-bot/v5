<?php

declare(strict_types=1);

/**
 * One-off: publish docs/documents-user-guide.md to prod Sales Book via MCP.
 * Usage: php scripts/mcp-prod-upsert-documents.php
 */
$userProfile = getenv('USERPROFILE') ?: getenv('HOME') ?: '';
$mcpConfigPath = rtrim(str_replace('\\', '/', $userProfile), '/').'/.cursor/mcp.json';

if (! is_readable($mcpConfigPath)) {
    fwrite(STDERR, "mcp.json not found at {$mcpConfigPath}\n");
    exit(1);
}

$config = json_decode((string) file_get_contents($mcpConfigPath), true, 512, JSON_THROW_ON_ERROR);
$prod = $config['mcpServers']['v5-crm-prod'] ?? null;
$url = is_array($prod) ? ($prod['url'] ?? null) : null;
$auth = is_array($prod['headers'] ?? null) ? ($prod['headers']['Authorization'] ?? null) : null;

if (! is_string($url) || ! is_string($auth) || $auth === '') {
    fwrite(STDERR, "v5-crm-prod url/Authorization missing in mcp.json\n");
    exit(1);
}

$guidePath = dirname(__DIR__).'/docs/documents-user-guide.md';
if (! is_readable($guidePath)) {
    fwrite(STDERR, "Guide not found: {$guidePath}\n");
    exit(1);
}

$markdown = file_get_contents($guidePath);

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
        CURLOPT_TIMEOUT => 120,
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

$sessionId = null;

// Streamable HTTP: initialize session
$init = mcpPost($url, $auth, [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2024-11-05',
        'capabilities' => (object) [],
        'clientInfo' => [
            'name' => 'crm-publish-script',
            'version' => '1.0.0',
        ],
    ],
]);

echo "initialize HTTP {$init['http_code']}\n";

if ($init['http_code'] >= 400) {
    fwrite(STDERR, substr($init['raw'], 0, 2000)."\n");
    exit(1);
}

$initData = parseMcpResponse($init['raw']);
echo json_encode($initData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";

$list = mcpPost($url, $auth, [
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/list',
    'params' => (object) [],
]);
echo "tools/list HTTP {$list['http_code']}\n";
echo substr($list['raw'], 0, 1500)."\n\n";

$call = mcpPost($url, $auth, [
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'upsert_sales_book_article',
        'arguments' => [
            'parent_title' => 'Руководство по CRM',
            'title' => 'Документы',
            'markdown_content' => $markdown,
        ],
    ],
]);

echo "tools/call HTTP {$call['http_code']}\n";
echo substr($call['raw'], 0, 4000)."\n";

if ($call['http_code'] >= 400) {
    exit(1);
}

$callData = parseMcpResponse($call['raw']);
if (isset($callData['error'])) {
    fwrite(STDERR, 'MCP error: '.json_encode($callData['error'], JSON_UNESCAPED_UNICODE)."\n");
    exit(1);
}

echo "OK\n";
