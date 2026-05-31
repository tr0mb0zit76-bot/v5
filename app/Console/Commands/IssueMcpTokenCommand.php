<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueMcpTokenCommand extends Command
{
    protected $signature = 'mcp:issue-token
                            {user : ID или email пользователя CRM}
                            {--name=mcp-cursor : Имя токена в personal_access_tokens}
                            {--abilities=* : Способности Sanctum (* — все)}';

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

        $abilities = $this->option('abilities') === '*'
            ? ['*']
            : array_values(array_filter(array_map('trim', explode(',', (string) $this->option('abilities')))));

        $token = $user->createToken((string) $this->option('name'), $abilities);

        $this->info('Токен создан. Сохраните его сейчас — повторно он не показывается.');
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

        return self::SUCCESS;
    }
}
