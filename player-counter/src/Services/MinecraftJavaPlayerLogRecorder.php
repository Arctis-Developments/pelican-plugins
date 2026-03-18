<?php

namespace ArctisDev\PlayerCounter\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use ArctisDev\PlayerCounter\Models\PlayerEvent;
use ArctisDev\PlayerCounter\Models\PlayerLogCursor;
use ArctisDev\PlayerCounter\Models\PlayerSession;
use ArctisDev\PlayerCounter\Support\PlayerIdentity;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MinecraftJavaPlayerLogRecorder
{
    private const LATEST_LOG_PATH = 'logs/latest.log';
    private const USERCACHE_PATH = 'usercache.json';

    public function record(Server $server): bool
    {
        if (!$this->shouldRecord($server)) {
            return false;
        }

        $fileRepository = (new DaemonFileRepository())->setServer($server);
        $knownUuids = $this->loadUserCacheMappings($server, $fileRepository);
        $content = $this->readFile($fileRepository, self::LATEST_LOG_PATH);

        if (blank($content)) {
            return false;
        }

        $hash = hash('sha256', $content);
        $cursor = PlayerLogCursor::query()->firstOrNew([
            'server_id' => $server->id,
            'path' => self::LATEST_LOG_PATH,
        ]);

        if ($cursor->file_hash === $hash) {
            return false;
        }

        $this->parseLogContent($server, $content, $knownUuids);

        $cursor->fill([
            'file_hash' => $hash,
            'last_scanned_at' => now(),
        ])->save();

        return true;
    }

    public function shouldRecord(Server $server): bool
    {
        return config('player-counter.minecraft_java_logs.enabled', true)
            && app(PlayerQueryService::class)->resolveGameQuery($server)?->query_type === 'minecraft_java';
    }

    public function syncIfStale(Server $server): bool
    {
        if (!$this->shouldRecord($server)) {
            return false;
        }

        $interval = max(15, (int) config('player-counter.minecraft_java_logs.sync_interval_seconds', 60));
        $throttleKey = "player-counter:minecraft-java-log-sync:{$server->id}";

        if (Cache::has($throttleKey)) {
            return false;
        }

        return Cache::lock("player-counter:minecraft-java-log-sync-lock:{$server->id}", 10)
            ->get(function () use ($server, $interval, $throttleKey) {
                if (Cache::has($throttleKey)) {
                    return false;
                }

                Cache::put($throttleKey, true, now()->addSeconds($interval));

                return $this->record($server);
            }) ?? false;
    }

    /**
     * @param array<string, string> $knownUuids
     */
    private function parseLogContent(Server $server, string $content, array $knownUuids): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $maxLines = max(250, (int) config('player-counter.minecraft_java_logs.max_lines', 2500));

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, -$maxLines);
        }

        foreach ($lines as $line) {
            $event = $this->parseLine(trim($line), $knownUuids);

            if (!$event) {
                continue;
            }

            if ($event['player_source_id']) {
                $knownUuids[mb_strtolower($event['player_name'])] = $event['player_source_id'];
            }

            $event['player_source_id'] ??= $knownUuids[mb_strtolower($event['player_name'])] ?? null;
            $event['player_key'] = PlayerIdentity::key([
                'id' => $event['player_source_id'] ?? null,
                'name' => $event['player_name'],
            ]);

            if ($event['player_source_id']) {
                $this->consolidateIdentity($server, $event['player_name'], $event['player_source_id']);
            }

            PlayerEvent::query()->firstOrCreate(
                [
                    'server_id' => $server->id,
                    'fingerprint' => $this->fingerprint($server, $event, $line),
                ],
                [
                    'player_key' => $event['player_key'],
                    'player_name' => $event['player_name'],
                    'player_source_id' => $event['player_source_id'],
                    'event_type' => $event['event_type'],
                    'message' => $event['message'],
                    'ip_address' => $event['ip_address'],
                    'metadata' => $event['metadata'],
                    'occurred_at' => $event['occurred_at'],
                ],
            );
        }
    }

    /**
     * @param array<string, string> $knownUuids
     * @return array{player_name: string, player_source_id: ?string, event_type: string, message: ?string, ip_address: ?string, metadata: array<string, mixed>, occurred_at: Carbon}|null
     */
    private function parseLine(string $line, array $knownUuids): ?array
    {
        if ($line === '') {
            return null;
        }

        if (preg_match('/^\[(?<time>\d{2}:\d{2}:\d{2})\] \[[^\]]+\]: UUID of player (?<name>[A-Za-z0-9_]{1,16}) is (?<uuid>[0-9a-fA-F-]{32,36})$/', $line, $matches)) {
            $uuid = $this->normalizeUuid($matches['uuid']);

            return [
                'player_name' => $matches['name'],
                'player_source_id' => $uuid,
                'event_type' => PlayerEvent::TYPE_UUID,
                'message' => $uuid,
                'ip_address' => null,
                'metadata' => [],
                'occurred_at' => $this->resolveOccurredAt($matches['time']),
            ];
        }

        if (preg_match('/^\[(?<time>\d{2}:\d{2}:\d{2})\] \[[^\]]+\]: (?<name>[A-Za-z0-9_]{1,16})\[\/(?<ip>[0-9a-fA-F\.:]+):\d+\] logged in with entity id (?<entity_id>-?\d+) at \((?<position>.+)\)$/', $line, $matches)) {
            return [
                'player_name' => $matches['name'],
                'player_source_id' => $knownUuids[mb_strtolower($matches['name'])] ?? null,
                'event_type' => PlayerEvent::TYPE_JOIN,
                'message' => 'Logged in',
                'ip_address' => $matches['ip'],
                'metadata' => [
                    'entity_id' => (int) $matches['entity_id'],
                    'position' => $matches['position'],
                ],
                'occurred_at' => $this->resolveOccurredAt($matches['time']),
            ];
        }

        if (preg_match('/^\[(?<time>\d{2}:\d{2}:\d{2})\] \[[^\]]+\]: (?<name>[A-Za-z0-9_]{1,16}) lost connection: (?<reason>.+)$/', $line, $matches)) {
            return [
                'player_name' => $matches['name'],
                'player_source_id' => $knownUuids[mb_strtolower($matches['name'])] ?? null,
                'event_type' => PlayerEvent::TYPE_LEAVE,
                'message' => $matches['reason'],
                'ip_address' => null,
                'metadata' => [],
                'occurred_at' => $this->resolveOccurredAt($matches['time']),
            ];
        }

        if (preg_match('/^\[(?<time>\d{2}:\d{2}:\d{2})\] \[[^\]]+\]: (?:\[[^\]]+\] )?<(?<name>[A-Za-z0-9_]{1,16})> (?<message>.+)$/', $line, $matches)) {
            return [
                'player_name' => $matches['name'],
                'player_source_id' => $knownUuids[mb_strtolower($matches['name'])] ?? null,
                'event_type' => PlayerEvent::TYPE_CHAT,
                'message' => $matches['message'],
                'ip_address' => null,
                'metadata' => [],
                'occurred_at' => $this->resolveOccurredAt($matches['time']),
            ];
        }

        if (preg_match('/^\[(?<time>\d{2}:\d{2}:\d{2})\] \[[^\]]+\]: (?<name>[A-Za-z0-9_]{1,16}) issued server command: (?<command>.+)$/', $line, $matches)) {
            return [
                'player_name' => $matches['name'],
                'player_source_id' => $knownUuids[mb_strtolower($matches['name'])] ?? null,
                'event_type' => PlayerEvent::TYPE_COMMAND,
                'message' => $matches['command'],
                'ip_address' => null,
                'metadata' => [],
                'occurred_at' => $this->resolveOccurredAt($matches['time']),
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function loadUserCacheMappings(Server $server, DaemonFileRepository $fileRepository): array
    {
        $content = $this->readFile($fileRepository, self::USERCACHE_PATH);

        if (blank($content)) {
            return [];
        }

        $hash = hash('sha256', $content);
        $cursor = PlayerLogCursor::query()->firstOrNew([
            'server_id' => $server->id,
            'path' => self::USERCACHE_PATH,
        ]);
        $shouldConsolidate = $cursor->file_hash !== $hash;

        try {
            $entries = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            report($exception);

            return [];
        }

        if (!is_array($entries)) {
            return [];
        }

        $mappings = [];

        foreach ($entries as $entry) {
            $playerName = trim((string) ($entry['name'] ?? ''));
            $uuid = $this->normalizeUuid((string) ($entry['uuid'] ?? ''));

            if ($playerName === '' || $uuid === null) {
                continue;
            }

            $mappings[mb_strtolower($playerName)] = $uuid;
            if ($shouldConsolidate) {
                $this->consolidateIdentity($server, $playerName, $uuid);
            }
        }

        if ($shouldConsolidate) {
            $cursor->fill([
                'file_hash' => $hash,
                'last_scanned_at' => now(),
            ])->save();
        }

        return $mappings;
    }

    private function consolidateIdentity(Server $server, string $playerName, string $uuid): void
    {
        $playerName = Str::limit(Str::squish($playerName), 255, '');
        $normalizedName = mb_strtolower($playerName);
        $normalizedUuid = mb_strtolower($uuid);
        $legacyNameKey = PlayerIdentity::nameKey($playerName);
        $legacyMirroredIdKey = PlayerIdentity::mirroredIdKey($playerName);
        $canonicalKey = PlayerIdentity::key([
            'id' => $uuid,
            'name' => $playerName,
        ]);

        PlayerSession::query()
            ->where('server_id', $server->id)
            ->where(function (Builder $query) use ($legacyNameKey, $legacyMirroredIdKey, $normalizedName, $normalizedUuid) {
                $query->whereIn('player_key', [$legacyNameKey, $legacyMirroredIdKey])
                    ->orWhere(function (Builder $query) use ($normalizedName, $normalizedUuid) {
                        $query->whereRaw('LOWER(player_name) = ?', [$normalizedName])
                            ->where(function (Builder $query) use ($normalizedName, $normalizedUuid) {
                                $query->whereNull('player_source_id')
                                    ->orWhereRaw('LOWER(player_source_id) = ?', [$normalizedName])
                                    ->orWhereRaw('LOWER(player_source_id) = ?', [$normalizedUuid]);
                            });
                    });
            })
            ->update([
                'player_key' => $canonicalKey,
                'player_source_id' => $uuid,
            ]);

        PlayerEvent::query()
            ->where('server_id', $server->id)
            ->where(function (Builder $query) use ($legacyNameKey, $legacyMirroredIdKey, $normalizedName, $normalizedUuid) {
                $query->whereIn('player_key', [$legacyNameKey, $legacyMirroredIdKey])
                    ->orWhere(function (Builder $query) use ($normalizedName, $normalizedUuid) {
                        $query->whereRaw('LOWER(player_name) = ?', [$normalizedName])
                            ->where(function (Builder $query) use ($normalizedName, $normalizedUuid) {
                                $query->whereNull('player_source_id')
                                    ->orWhereRaw('LOWER(player_source_id) = ?', [$normalizedName])
                                    ->orWhereRaw('LOWER(player_source_id) = ?', [$normalizedUuid]);
                            });
                    });
            })
            ->update([
                'player_key' => $canonicalKey,
                'player_source_id' => $uuid,
            ]);
    }

    private function resolveOccurredAt(string $time): Carbon
    {
        $occurredAt = Carbon::today()->setTimeFromTimeString($time);

        if ($occurredAt->greaterThan(now()->addMinute())) {
            $occurredAt->subDay();
        }

        return $occurredAt;
    }

    private function fingerprint(Server $server, array $event, string $line): string
    {
        return hash('sha256', implode('|', [
            $server->id,
            $event['occurred_at']->toIso8601String(),
            $event['event_type'],
            trim($line),
        ]));
    }

    private function normalizeUuid(string $uuid): ?string
    {
        $uuid = mb_strtolower(trim($uuid));
        $stripped = str_replace('-', '', $uuid);

        if (!preg_match('/^[0-9a-f]{32}$/', $stripped)) {
            return null;
        }

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($stripped, 4));
    }

    private function readFile(DaemonFileRepository $fileRepository, string $path): ?string
    {
        try {
            $content = $fileRepository->getContent($path);

            return is_string($content) ? $content : null;
        } catch (Exception $exception) {
            report($exception);

            return null;
        }
    }
}
