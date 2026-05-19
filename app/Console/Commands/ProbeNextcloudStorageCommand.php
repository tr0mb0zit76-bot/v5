<?php

namespace App\Console\Commands;

use App\Services\DocumentStorageService;
use App\Services\NextcloudWebDavStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProbeNextcloudStorageCommand extends Command
{
    protected $signature = 'documents:probe-nextcloud';

    protected $description = 'Проверяет доступность Nextcloud (веб, WebDAV, создание каталога CRM)';

    public function handle(NextcloudWebDavStorage $nextcloudStorage, DocumentStorageService $documentStorage): int
    {
        $driver = $documentStorage->configuredDriver();
        $baseUrl = rtrim((string) config('document_storage.nextcloud.base_url'), '/');
        $user = (string) config('document_storage.nextcloud.webdav_user');
        $webdavRoot = (string) config('document_storage.nextcloud.webdav_root', '/remote.php/dav/files');

        $this->info('Драйвер хранения: '.$driver);
        $this->line('NEXTCLOUD_BASE_URL: '.($baseUrl !== '' ? $baseUrl : '(не задан)'));
        $this->line('NEXTCLOUD_WEBDAV_USER: '.($user !== '' ? $user : '(не задан)'));
        $this->line('NEXTCLOUD_WEBDAV_ROOT: '.$webdavRoot);

        if ($driver !== DocumentStorageService::DRIVER_NEXTCLOUD) {
            $this->warn('DOCUMENT_STORAGE не nextcloud — проверка WebDAV пропущена.');

            return self::SUCCESS;
        }

        if ($baseUrl === '') {
            $this->error('Задайте NEXTCLOUD_BASE_URL в .env');

            return self::FAILURE;
        }

        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $this->newLine();
        $this->info('1) Проверка веб-интерфейса (status.php)…');

        try {
            $statusResponse = Http::timeout(15)
                ->withOptions(['verify' => (bool) config('document_storage.nextcloud.verify_ssl', true)])
                ->get($baseUrl.'/status.php');
        } catch (\Throwable $exception) {
            $this->error('   Не удалось подключиться: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('   HTTP '.$statusResponse->status());
        $body = (string) $statusResponse->body();

        if (str_contains($body, 'Приветствуем') || str_contains($body, 'Содержимое появится позже')) {
            $this->error('   Ответ похож на заглушку Reg.ru — nginx не проксирует на Docker Nextcloud.');
            $this->line('   Восстановите reverse proxy на 127.0.0.1:18081 (docs/nextcloud-install.md).');

            return self::FAILURE;
        }

        if (! $statusResponse->successful() || ! str_contains($body, 'installed')) {
            $this->warn('   Nextcloud status.php не вернул ожидаемый JSON — возможно, неверный хост или прокси.');

            return self::FAILURE;
        }

        $this->info('   OK — Nextcloud отвечает.');

        $this->newLine();
        $this->info('2) Проверка записи тестового файла через WebDAV…');

        $testPath = 'order_documents/_probe/'.now()->format('YmdHis').'.txt';

        try {
            $nextcloudStorage->put($testPath, 'probe '.now()->toIso8601String());
            $nextcloudStorage->delete($testPath);
        } catch (\RuntimeException $exception) {
            $this->error('   '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('   OK — каталоги и загрузка работают.');

        $this->newLine();
        $this->info('Nextcloud готов к работе с CRM.');

        return self::SUCCESS;
    }
}
