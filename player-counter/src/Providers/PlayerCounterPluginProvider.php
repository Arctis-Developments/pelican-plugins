<?php

namespace ArctisDev\PlayerCounter\Providers;

use App\Enums\ConsoleWidgetPosition;
use App\Filament\Server\Pages\Console;
use App\Models\Egg;
use App\Models\Role;
use ArctisDev\PlayerCounter\Commands\PollPlayerHistoryCommand;
use ArctisDev\PlayerCounter\Extensions\Query\QueryTypeService;
use ArctisDev\PlayerCounter\Extensions\Query\Schemas\CitizenFXQueryTypeSchema;
use ArctisDev\PlayerCounter\Extensions\Query\Schemas\GoldSourceQueryTypeSchema;
use ArctisDev\PlayerCounter\Extensions\Query\Schemas\MinecraftBedrockQueryTypeSchema;
use ArctisDev\PlayerCounter\Extensions\Query\Schemas\MinecraftJavaQueryTypeSchema;
use ArctisDev\PlayerCounter\Extensions\Query\Schemas\SourceQueryTypeSchema;
use ArctisDev\PlayerCounter\Filament\Server\Widgets\ConsolePlayerStatusWidget;
use ArctisDev\PlayerCounter\Models\EggGameQuery;
use ArctisDev\PlayerCounter\Models\GameQuery;
use Illuminate\Support\ServiceProvider;

class PlayerCounterPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        Role::registerCustomDefaultPermissions('game_query');
        Role::registerCustomModelIcon('game_query', 'tabler-device-desktop-search');

        Console::registerCustomWidgets(ConsoleWidgetPosition::BelowConsole, [ConsolePlayerStatusWidget::class]);
        $this->commands([
            PollPlayerHistoryCommand::class,
        ]);

        $this->app->singleton(QueryTypeService::class, function () {
            $service = new QueryTypeService();

            // Default Query types
            $service->register(new SourceQueryTypeSchema());
            $service->register(new GoldSourceQueryTypeSchema());
            $service->register(new MinecraftJavaQueryTypeSchema());
            $service->register(new MinecraftBedrockQueryTypeSchema());
            $service->register(new CitizenFXQueryTypeSchema());

            return $service;
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(plugin_path('player-counter', 'resources/views'), 'player-counter');

        Egg::resolveRelationUsing('gameQuery', fn (Egg $egg) => $egg->hasOneThrough(GameQuery::class, EggGameQuery::class, 'egg_id', 'id', 'id', 'game_query_id'));
    }
}
