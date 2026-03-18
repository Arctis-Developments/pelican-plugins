<?php

namespace ArctisDev\PlayerCounter\Services;

use App\Models\Server;
use ArctisDev\PlayerCounter\Models\PlayerCountSnapshot;
use ArctisDev\PlayerCounter\Models\PlayerSession;
use ArctisDev\PlayerCounter\Support\PlayerIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlayerHistoryRecorder
{
    /** @param array{hostname: string, map: string, current_players: int, max_players: int, players: ?array<array{id: string, name: string}>} $data */
    public function record(Server $server, array $data, ?Carbon $collectedAt = null): void
    {
        if (!config('player-counter.history.enabled')) {
            return;
        }

        $collectedAt ??= now();

        Cache::lock("player-counter:history:{$server->id}", 10)->block(3, function () use ($server, $data, $collectedAt) {
            DB::transaction(function () use ($server, $data, $collectedAt) {
                $this->recordSnapshot($server, $data, $collectedAt);
                $this->syncSessions($server, $data['players'] ?? null, $collectedAt);
            });
        });
    }

    /** @param array{hostname: string, map: string, current_players: int, max_players: int, players: ?array<array{id: string, name: string}>} $data */
    private function recordSnapshot(Server $server, array $data, Carbon $collectedAt): void
    {
        $latestSnapshot = PlayerCountSnapshot::query()
            ->where('server_id', $server->id)
            ->latest('collected_at')
            ->first();

        $interval = max(30, (int) config('player-counter.history.snapshot_interval_seconds', 300));
        $shouldRecord = !$latestSnapshot
            || $latestSnapshot->collected_at->lte($collectedAt->copy()->subSeconds($interval))
            || $latestSnapshot->current_players !== (int) $data['current_players']
            || $latestSnapshot->max_players !== (int) $data['max_players']
            || $latestSnapshot->hostname !== $data['hostname']
            || $latestSnapshot->map !== $data['map'];

        if (!$shouldRecord) {
            return;
        }

        PlayerCountSnapshot::query()->create([
            'server_id' => $server->id,
            'hostname' => Str::limit(Str::squish((string) $data['hostname']), 255, ''),
            'map' => Str::limit(Str::squish((string) $data['map']), 255, ''),
            'current_players' => max(0, (int) $data['current_players']),
            'max_players' => max(0, (int) $data['max_players']),
            'collected_at' => $collectedAt,
        ]);
    }

    /** @param ?array<array{id: string, name: string}> $players */
    private function syncSessions(Server $server, ?array $players, Carbon $collectedAt): void
    {
        if ($players === null) {
            return;
        }

        $activePlayers = $this->normalizePlayers($players);

        /** @var Collection<string, PlayerSession> $openSessions */
        $openSessions = PlayerSession::query()
            ->where('server_id', $server->id)
            ->whereNull('left_at')
            ->lockForUpdate()
            ->get()
            ->keyBy('player_key');

        foreach ($activePlayers as $playerKey => $player) {
            $session = $openSessions->get($playerKey);

            if ($session) {
                $dirty = false;

                if ($session->player_name !== $player['player_name']) {
                    $session->player_name = $player['player_name'];
                    $dirty = true;
                }

                if ($session->player_source_id !== $player['player_source_id']) {
                    $session->player_source_id = $player['player_source_id'];
                    $dirty = true;
                }

                if (!$session->last_seen_at->equalTo($collectedAt)) {
                    $session->last_seen_at = $collectedAt;
                    $dirty = true;
                }

                if ($dirty) {
                    $session->save();
                }

                continue;
            }

            PlayerSession::query()->create([
                'server_id' => $server->id,
                'player_key' => $playerKey,
                'player_name' => $player['player_name'],
                'player_source_id' => $player['player_source_id'],
                'joined_at' => $collectedAt,
                'last_seen_at' => $collectedAt,
            ]);
        }

        foreach ($openSessions as $playerKey => $session) {
            if ($activePlayers->has($playerKey)) {
                continue;
            }

            $leftAt = $collectedAt->greaterThan($session->last_seen_at) ? $collectedAt : $session->last_seen_at;

            $session->update([
                'last_seen_at' => $leftAt,
                'left_at' => $leftAt,
                'duration_seconds' => max(0, $session->joined_at->diffInSeconds($leftAt)),
            ]);
        }
    }

    /** @param array<array{id: string, name: string}> $players @return Collection<string, array{player_name: string, player_source_id: ?string}> */
    private function normalizePlayers(array $players): Collection
    {
        return collect($players)
            ->filter(fn (array $player) => filled($player['name'] ?? null))
            ->mapWithKeys(function (array $player) {
                $playerName = Str::limit(Str::squish((string) $player['name']), 255, '');
                $sourceId = PlayerIdentity::normalizeSourceId($player['id'] ?? null, $playerName);

                return [
                    PlayerIdentity::key($player) => [
                        'player_name' => $playerName,
                        'player_source_id' => $sourceId ? Str::limit($sourceId, 255, '') : null,
                    ],
                ];
            });
    }
}
