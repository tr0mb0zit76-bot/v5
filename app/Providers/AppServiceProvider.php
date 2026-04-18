<?php

namespace App\Providers;

use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Models\Task;
use App\Policies\SalesScriptNodePolicy;
use App\Policies\SalesScriptPlaySessionPolicy;
use App\Policies\SalesScriptPolicy;
use App\Policies\SalesScriptTransitionPolicy;
use App\Policies\SalesScriptVersionPolicy;
use App\Policies\TaskPolicy;
use App\Services\NextcloudWebDavStorage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpWord\Settings as PhpWordSettings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NextcloudWebDavStorage::class, function () {
            return new NextcloudWebDavStorage(
                baseUrl: config('document_storage.nextcloud.base_url'),
                username: config('document_storage.nextcloud.webdav_user'),
                password: config('document_storage.nextcloud.webdav_password'),
                webdavRoot: (string) config('document_storage.nextcloud.webdav_root', '/remote.php/dav/files'),
                timeoutSeconds: (int) config('document_storage.nextcloud.timeout', 30),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePhpWordTempDir();

        Vite::prefetch(concurrency: 3);

        Gate::policy(SalesScript::class, SalesScriptPolicy::class);
        Gate::policy(SalesScriptVersion::class, SalesScriptVersionPolicy::class);
        Gate::policy(SalesScriptNode::class, SalesScriptNodePolicy::class);
        Gate::policy(SalesScriptTransition::class, SalesScriptTransitionPolicy::class);
        Gate::policy(SalesScriptPlaySession::class, SalesScriptPlaySessionPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
    }

    /**
     * На хостингах с open_basedir без /tmp PhpWord падает на tempnam() в /tmp.
     * Пишем временные файлы внутри storage (обычно уже в разрешённом пути).
     */
    private function configurePhpWordTempDir(): void
    {
        if (! class_exists(PhpWordSettings::class)) {
            return;
        }

        $tempDir = storage_path('framework/phpword-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        PhpWordSettings::setTempDir($tempDir);
    }
}
