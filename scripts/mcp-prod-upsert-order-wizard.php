<?php

declare(strict_types=1);

/**
 * Publish order wizard guides to prod Sales Book via MCP.
 * Usage: php scripts/mcp-prod-upsert-order-wizard.php
 * Filter: MCP_UPSERT_ONLY=Финансовые php scripts/mcp-prod-upsert-order-wizard.php
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
        $text = $content[0]['text'];
        if (($data['result']['isError'] ?? false) === true) {
            throw new RuntimeException((string) $text);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['message' => $text];
    }

    return $data['result'] ?? $data;
}

function prepareBookMarkdown(string $path): string
{
    $markdown = (string) file_get_contents($path);
    $markdown = preg_replace('/^#\s+.+\R+/u', '', $markdown) ?? $markdown;

    $replacements = [
        '[Мастер заказов](order-wizard-user-guide.md)' => '«Мастер заказов» (раздел «Руководство по CRM»)',
        '[Финансовые условия в мастере заказов](order-wizard-financial-terms-user-guide.md)' => '«Финансовые условия в мастере заказов» (эта статья)',
        '[Документы](documents-user-guide.md)' => '«Документы — инструкция для пользователя» (раздел «Руководство по CRM»)',
        '[Ассистенты CRM](ai-assistants-user-guide.md)' => '«Ассистенты CRM — инструкция для пользователя» (раздел «Руководство по CRM»)',
        '[График оплат (техн.)](payment-schedule-architecture.md)' => 'техническая документация по графику оплат (для разработчиков)',
        '[Регламент оформления заявки и изменения базовых условий](order-application-basic-terms-regulation.md)' => '«Регламент оформления заявки и изменения базовых условий» (раздел «Регламенты работы»)',
        '[order-wizard-user-guide.md](order-wizard-user-guide.md)' => '«Мастер заказов»',
        '[order-wizard-financial-terms-user-guide.md](order-wizard-financial-terms-user-guide.md)' => '«Финансовые условия в мастере заказов»',
        '[documents-user-guide.md](documents-user-guide.md)' => '«Документы — инструкция для пользователя»',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $markdown);
}

$articles = array_values(array_filter([
    [
        'parent_title' => 'Руководство по CRM',
        'title' => 'Мастер заказов — инструкция для пользователя',
        'path' => dirname(__DIR__).'/docs/order-wizard-user-guide.md',
    ],
    [
        'parent_title' => 'Руководство по CRM',
        'title' => 'Финансовые условия в мастере заказов',
        'path' => dirname(__DIR__).'/docs/order-wizard-financial-terms-user-guide.md',
    ],
], static function (array $spec): bool {
    $only = getenv('MCP_UPSERT_ONLY');
    if (! is_string($only) || trim($only) === '') {
        return true;
    }

    return str_contains(mb_strtolower($spec['title']), mb_strtolower(trim($only)));
}));

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

$list = mcpPost($url, $auth, [
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/list',
    'params' => (object) [],
]);
echo "tools/list HTTP {$list['http_code']}\n";

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
            'create_parent_if_missing' => true,
        ]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }
}

echo "\nOK — статьи отправлены (новые создаются как черновик; опубликуйте в UI Книги продаж).\n";
