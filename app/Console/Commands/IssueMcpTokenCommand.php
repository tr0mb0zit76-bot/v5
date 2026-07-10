<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\McpTokenAbilities;
use Illuminate\Console\Command;

class IssueMcpTokenCommand extends Command
{
    protected $signature = 'mcp:issue-token
                            {user : ID или email пользователя CRM}
                            {--name=mcp-cursor : Имя токена в personal_access_tokens}
                            {--abilities= : Способности Sanctum (через запятую; * = полный доступ; пусто = mcp:read)}
                            {--write : Добавить mcp:write (запись в CRM)}
                            {--days=90 : Срок действия в днях (0 = только глобальный лимит Sanctum)}';

    protected $description = 'Выпустить Sanctum-токен для MCP (Cursor, внешние агенты)';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');

        $user = ctype_digit($identifier)
            ? User::query()->find($identifier)
            : User::query()->where('email', $identifier)->first();

        if (! $user instanceof User) {
            $this->error('Пользователь не найден.');

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error('Учётная запись деактивирована.');

            return self::FAILURE;
        }

        $abilities = $this->resolveAbilities();

        $token = $user->createToken(
            (string) $this->option('name'),
            $abilities,
            $this->resolveExpiresAt(),
        );

        $this->info('Токен создан. Сохраните его сейчас — повторно он не показывается.');
        $this->line('Способности: '.implode(', ', $abilities));
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->line('Cursor (~/.cursor/mcp.json):');
        $this->line(json_encode([
            'mcpServers' => [
                'v5-crm' => [
                    'url' => url('/mcp/crm'),
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->plainTextToken,
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (! in_array(McpTokenAbilities::WRITE, $abilities, true) && ! in_array(McpTokenAbilities::FULL, $abilities, true)) {
            $this->newLine();
            $this->warn('Токен только для чтения (mcp:read). Для create/update/send добавьте --write или --abilities=mcp:read,mcp:write');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveAbilities(): array
    {
        $raw = $this->option('abilities');
        $withWrite = (bool) $this->option('write');

        if ($raw === null) {
            return McpTokenAbilities::defaultIssueAbilities($withWrite);
        }

        if ($raw === '' || $raw === '*') {
            return $raw === '*' ? [McpTokenAbilities::FULL] : McpTokenAbilities::defaultIssueAbilities($withWrite);
        }

        if (is_array($raw)) {
            return McpTokenAbilities::normalizeIssueAbilities($raw, $withWrite);
        }

        $parsed = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));

        return McpTokenAbilities::normalizeIssueAbilities($parsed, $withWrite);
    }

    private function resolveExpiresAt(): ?\DateTimeInterface
    {
        $days = (int) $this->option('days');

        if ($days <= 0) {
            return null;
        }

        return now()->addDays($days);
    }
}
