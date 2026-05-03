<?php

namespace App\Console\Commands;

use App\Models\SalesScript;
use App\Models\SalesScriptVersion;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SalesTrainerPlaygroundCommand extends Command
{
    protected $signature = 'sales:trainer-playground {--seed : Обновить демо-сценарии в БД (SalesScriptsDemoSeeder)}';

    protected $description = 'Проверка данных и URL для ручного прогона тренажёра (под вашей учётной записью)';

    public function handle(): int
    {
        if ($this->option('seed')) {
            $this->call(SalesScriptsDemoSeeder::class);
        }

        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            $base = 'http://localhost';
        }

        $this->newLine();
        $this->components->info('Тренажёр — ручной прогон');
        $this->line('  Вход в CRM под своей учётной записью.');
        $this->newLine();
        $this->line('  Тренажёр: '.$base.'/sales-assistant/trainer');
        $this->line('  Скрипты:   '.$base.'/scripts');
        $this->line('  Аналитика: '.$base.'/sales-assistant/trainer/analytics');
        $this->newLine();

        if (! Schema::hasTable('sales_scripts')) {
            $this->components->warn('Таблица sales_scripts отсутствует — выполните php artisan migrate');

            return self::FAILURE;
        }

        $published = SalesScriptVersion::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->count();
        $scripts = SalesScript::query()->count();
        $this->line("  Сценариев: {$scripts}, опубликованных активных версий: {$published}");

        if ($published === 0) {
            $this->components->warn('Нет опубликованных версий — запустите: php artisan db:seed --class=SalesScriptsDemoSeeder');
        }

        $this->newLine();
        $this->line('  DeepSeek: задайте DEEPSEEK_API_KEY в .env, иначе в чате тренажёра будет заглушка.');
        $this->newLine();
        $this->line('  Обновить демо-сценарии: php artisan sales:trainer-playground --seed');
        $this->newLine();

        return self::SUCCESS;
    }
}
