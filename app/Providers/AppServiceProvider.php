<?php

namespace App\Providers;

use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Policies\SalesScriptNodePolicy;
use App\Policies\SalesScriptPlaySessionPolicy;
use App\Policies\SalesScriptPolicy;
use App\Policies\SalesScriptTransitionPolicy;
use App\Policies\SalesScriptVersionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(SalesScript::class, SalesScriptPolicy::class);
        Gate::policy(SalesScriptVersion::class, SalesScriptVersionPolicy::class);
        Gate::policy(SalesScriptNode::class, SalesScriptNodePolicy::class);
        Gate::policy(SalesScriptTransition::class, SalesScriptTransitionPolicy::class);
        Gate::policy(SalesScriptPlaySession::class, SalesScriptPlaySessionPolicy::class);
    }
}
