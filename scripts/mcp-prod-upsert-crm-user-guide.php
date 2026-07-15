<?php

declare(strict_types=1);

/**
 * Publish the CRM user guide collection to the production Sales Book via MCP.
 *
 * Usage:
 *   php scripts/mcp-prod-upsert-crm-user-guide.php
 *   MCP_UPSERT_ONLY=Контрагенты php scripts/mcp-prod-upsert-crm-user-guide.php
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

/**
 * @return array{http_code: int, raw: string}
 */
function mcpPost(string $url, string $auth, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: '.$auth,
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT => 180,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('curl error: '.$error);
    }

    return ['http_code' => $httpCode, 'raw' => $raw];
}

function parseMcpResponse(string $raw): array
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

function mcpCall(string $url, string $auth, int $id, string $tool, array $arguments): array
{
    $response = mcpPost($url, $auth, [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool,
            'arguments' => $arguments,
        ],
    ]);

    if ($response['http_code'] >= 400) {
        throw new RuntimeException("HTTP {$response['http_code']}: ".substr($response['raw'], 0, 2000));
    }

    $data = parseMcpResponse($response['raw']);

    if (isset($data['error'])) {
        throw new RuntimeException('MCP error: '.json_encode($data['error'], JSON_UNESCAPED_UNICODE));
    }

    if (($data['result']['isError'] ?? false) === true) {
        throw new RuntimeException((string) ($data['result']['content'][0]['text'] ?? 'MCP tool error'));
    }

    $text = $data['result']['content'][0]['text'] ?? null;

    if (is_string($text)) {
        $decoded = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return $data['result'] ?? $data;
}

function prepareBookMarkdown(string $path): string
{
    $markdown = (string) file_get_contents($path);

    return preg_replace('/^#\s+.+\R+/u', '', $markdown, 1) ?? $markdown;
}

$articles = [
    [
        'title' => 'CRM «Автоальянс» — полное руководство пользователя',
        'path' => dirname(__DIR__).'/docs/crm-user-guide.md',
        'sort_order' => 0,
    ],
    [
        'title' => 'CRM: первый вход, навигация и мобильная работа',
        'path' => dirname(__DIR__).'/docs/crm-basics-user-guide.md',
        'sort_order' => 10,
    ],
    [
        'title' => 'Контрагенты: карточка, проверка и условия сотрудничества',
        'path' => dirname(__DIR__).'/docs/contractors-user-guide.md',
        'sort_order' => 20,
    ],
    [
        'title' => 'Мессенджер и Traklo: чаты, группы, файлы и ссылки',
        'path' => dirname(__DIR__).'/docs/messenger-user-guide.md',
        'sort_order' => 30,
    ],
    [
        'title' => 'Финансы: график оплат, сверки и управленческий учёт',
        'path' => dirname(__DIR__).'/docs/finance-user-guide.md',
        'sort_order' => 40,
    ],
    [
        'title' => 'Собственный парк: рейсы и эффективность',
        'path' => dirname(__DIR__).'/docs/fleet-user-guide.md',
        'sort_order' => 50,
    ],
    [
        'title' => 'Помощник продавца и модули CRM',
        'path' => dirname(__DIR__).'/docs/sales-assistant-modules-user-guide.md',
        'sort_order' => 60,
    ],
    [
        'title' => 'Администратор CRM: пользователи, роли, процессы и настройки',
        'path' => dirname(__DIR__).'/docs/crm-admin-user-guide.md',
        'sort_order' => 70,
    ],
];

$only = trim((string) getenv('MCP_UPSERT_ONLY'));

if ($only !== '') {
    $articles = array_values(array_filter(
        $articles,
        static fn (array $article): bool => str_contains(
            mb_strtolower($article['title']),
            mb_strtolower($only),
        ),
    ));
}

if ($articles === []) {
    fwrite(STDERR, "No articles matched MCP_UPSERT_ONLY={$only}\n");
    exit(1);
}

$initialize = mcpPost($url, $auth, [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2024-11-05',
        'capabilities' => (object) [],
        'clientInfo' => [
            'name' => 'crm-user-guide-publisher',
            'version' => '1.0.0',
        ],
    ],
]);

if ($initialize['http_code'] >= 400) {
    fwrite(STDERR, "MCP initialize HTTP {$initialize['http_code']}\n");
    exit(1);
}

$id = 10;

foreach ($articles as $article) {
    if (! is_readable($article['path'])) {
        fwrite(STDERR, "Source not found: {$article['path']}\n");
        exit(1);
    }

    $result = mcpCall($url, $auth, $id++, 'upsert_sales_book_article', [
        'parent_title' => 'Руководство по CRM',
        'title' => $article['title'],
        'markdown_content' => prepareBookMarkdown($article['path']),
        'sort_order' => $article['sort_order'],
        'create_parent_if_missing' => false,
    ]);

    echo json_encode([
        'title' => $article['title'],
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
}

echo "OK — CRM user guides were upserted into the production Sales Book.\n";
