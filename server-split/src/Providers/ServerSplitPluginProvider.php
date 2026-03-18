<?php

namespace ArctisDev\ServerSplit\Providers;

use App\Services\Servers\ServerCreationService;
use ArctisDev\ServerSplit\Services\Servers\QuotaAwareServerCreationService;
use Illuminate\Support\ServiceProvider;

class ServerSplitPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServerCreationService::class, QuotaAwareServerCreationService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(plugin_path('server-split', 'resources/views'), 'server-split');
    }
}
