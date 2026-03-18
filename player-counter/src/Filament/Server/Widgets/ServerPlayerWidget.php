<?php

namespace ArctisDev\PlayerCounter\Filament\Server\Widgets;

use App\Filament\Server\Components\SmallStatBlock;
use App\Models\Server;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;

class ServerPlayerWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        if ($server->isInConflictState()) {
            return false;
        }

        if (!app(PlayerQueryService::class)->canQuery($server)) {
            return false;
        }

        return !$server->retrieveStatus()->isOffline();
    }

    protected function getStats(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $data = app(PlayerQueryService::class)->query($server) ?? [];

        return [
            SmallStatBlock::make(trans('player-counter::query.players'), ($data['current_players'] ?? '?') . ' / ' . ($data['max_players'] ?? '?')),
        ];
    }
}
