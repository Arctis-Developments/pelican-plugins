<?php

namespace ArctisDev\ServerSplit;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class ServerSplitPlugin implements Plugin
{
    public function getId(): string
    {
        return 'server-split';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->discoverResources(
                in: __DIR__ . '/Filament/Admin/Resources',
                for: 'ArctisDev\\ServerSplit\\Filament\\Admin\\Resources',
            );

            return;
        }

        if ($panel->getId() === 'app') {
            $panel->discoverResources(
                in: __DIR__ . '/Filament/App/Resources',
                for: 'ArctisDev\\ServerSplit\\Filament\\App\\Resources',
            );
        }
    }

    public function boot(Panel $panel): void
    {
        if ($panel->getId() !== 'app') {
            return;
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_END,
            fn (): string => view('server-split::filament.app.components.create-server-list-button')->render(),
        );
    }
}
