<?php

namespace ArctisDev\PlayerCounter\Filament\Server\Widgets;

use App\Filament\Server\Components\SmallStatBlock;
use App\Models\Server;
use ArctisDev\PlayerCounter\Models\PlayerCountSnapshot;
use ArctisDev\PlayerCounter\Models\PlayerSession;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;

class PlayerActivityOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '15s';

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return app(PlayerQueryService::class)->canQuery($server);
    }

    protected function getStats(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $windowStart = now()->subDay();

        $activeSessions = PlayerSession::query()
            ->where('server_id', $server->id)
            ->whereNull('left_at')
            ->count();

        $uniquePlayers = PlayerSession::query()
            ->where('server_id', $server->id)
            ->where('last_seen_at', '>=', $windowStart)
            ->distinct()
            ->count('player_key');

        $peakPlayers = (int) PlayerCountSnapshot::query()
            ->where('server_id', $server->id)
            ->where('collected_at', '>=', $windowStart)
            ->max('current_players');

        $latestSnapshot = PlayerCountSnapshot::query()
            ->where('server_id', $server->id)
            ->latest('collected_at')
            ->first();

        return [
            SmallStatBlock::make(trans('player-counter::query.active_sessions'), $activeSessions),
            SmallStatBlock::make(trans('player-counter::query.unique_players_24h'), $uniquePlayers),
            SmallStatBlock::make(trans('player-counter::query.peak_players_24h'), $peakPlayers),
            SmallStatBlock::make(
                trans('player-counter::query.last_snapshot'),
                $latestSnapshot?->collected_at?->diffForHumans() ?? trans('player-counter::query.never')
            ),
        ];
    }
}
