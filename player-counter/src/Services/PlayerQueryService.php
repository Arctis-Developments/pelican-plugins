<?php

namespace ArctisDev\PlayerCounter\Services;

use App\Models\Server;
use ArctisDev\PlayerCounter\Models\GameQuery;

class PlayerQueryService
{
    public function canQuery(Server $server): bool
    {
        if (!GameQuery::canRunQuery($server->allocation)) {
            return false;
        }

        return $this->resolveGameQuery($server) !== null;
    }

    /** @return ?array{hostname: string, map: string, current_players: int, max_players: int, players: ?array<array{id: string, name: string}>} */
    public function query(Server $server, bool $recordHistory = true): ?array
    {
        if (!$this->canQuery($server) || $server->retrieveStatus()->isOffline()) {
            return null;
        }

        $data = $this->resolveGameQuery($server)?->runQuery($server->allocation);

        if ($recordHistory && $data) {
            app(PlayerHistoryRecorder::class)->record($server, $data);
        }

        return $data;
    }

    public function resolveGameQuery(Server $server): ?GameQuery
    {
        /** @var ?GameQuery $gameQuery */
        $gameQuery = $server->egg->gameQuery; // @phpstan-ignore property.notFound

        return $gameQuery;
    }
}
