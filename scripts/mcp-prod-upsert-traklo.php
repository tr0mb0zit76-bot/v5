<?php

declare(strict_types=1);

/**
 * Publish Traklo user guides to prod Sales Book via MCP.
 * Usage: php scripts/mcp-prod-upsert-traklo.php
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

function prepareBookMarkdown(string $path): string
{
    $markdown = (string) file_get_contents($path);
    $markdown = preg_replace('/^#\s+.+\R+/u', '', $markdown) ?? $markdown;

    $replacements = [
        '[Документы — инструкция](documents-user-guide.md)' => '«Документы — инструкция для пользователя» (раздел «Руководство по CRM»)',
        '[Мастер заказов](order-wizard-user-guide.md)' => '«Мастер заказов» (раздел «Руководство по CRM»)',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $markdown);
}

$articles = [
    [
        'parent_title' => 'Руководство по CRM',
        'title' => 'Traklo для менеджера',
        'path' => dirname(__DIR__).'/docs/traklo-manager-guide.md',
    ],
    [
        'parent_title' => 'Руководство по CRM',
        'title' => 'Traklo для контрагента',
        'path' => dirname(__DIR__).'/docs/traklo-counterparty-guide.md',
    ],
];

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

$id = 10;
foreach ($articles as $spec) {
    if (! is_readable($spec['path'])) {
        fwrite(STDERR, "File not found: {$spec['path']}\n");
        exit(1);
    }

    $markdown = prepareBookMarkdown($spec['path']);
    echo "\n--- Upsert: «{$spec['title']}» under «{$spec['parent_title']}» ---\n";

    try {
        $result = mcpCall($url, $auth, $id++, 'upsert_sales_book_article', [
            'parent_title' => $spec['parent_title'],
            'title' => $spec['title'],
            'markdown_content' => $markdown,
        ]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }
}

echo "\nOK — статьи Traklo отправлены в Книгу продаж (черновик, если новые).\n";
