<?php

namespace ArctisDev\PlayerCounter\Http\Controllers\Api\Client\Servers;

use App\Http\Controllers\Api\Client\ClientApiController;
use App\Models\Server;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Group('Server - Players')]
class PlayerCounterController extends ClientApiController
{
    /**
     * Get query
     *
     * Returns query information.
     *
     * @throws HttpException
     */
    public function query(Server $server, PlayerQueryService $queryService): JsonResponse
    {
        $data = $this->runQuery($server, $queryService);

        return response()->json(array_except($data, 'players'));
    }

    /**
     * Get players
     *
     * Returns the names of the current players.
     *
     * @throws HttpException
     */
    public function players(Server $server, PlayerQueryService $queryService): JsonResponse
    {
        $data = $this->runQuery($server, $queryService);

        if (is_null($data['players'])) {
            abort(Response::HTTP_NOT_ACCEPTABLE, 'Server query has no player list');
        }

        /** @var string[] $players */
        $players = array_map(fn ($player) => $player['name'], $data['players']);

        return response()->json($players);
    }

    /** @return ?array{hostname: string, map: string, current_players: int, max_players: int, players: ?array<array{id: string, name: string}>} */
    private function runQuery(Server $server, PlayerQueryService $queryService): ?array
    {
        if (!$queryService->canQuery($server)) {
            abort(Response::HTTP_NOT_ACCEPTABLE, 'Server has invalid allocation');
        }

        if ($server->retrieveStatus()->isOffline()) {
            abort(Response::HTTP_NOT_ACCEPTABLE, 'Server is offline');
        }

        $data = $queryService->query($server);

        if ($data === null) {
            abort(Response::HTTP_NOT_ACCEPTABLE, 'Server query failed');
        }

        return $data;
    }
}
