<?php

namespace ArctisDev\PlayerCounter\Services;

use App\Models\Server;
use ArctisDev\PlayerCounter\Models\PlayerEvent;
use ArctisDev\PlayerCounter\Models\PlayerSession;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PlayerProfileService
{
    /**
     * @return array{
     *     display_name: string,
     *     player_key: string,
     *     player_source_id: ?string,
     *     aliases: array<int, string>,
     *     ip_addresses: array<int, array{ip_address: string, occurrences: int, first_seen_at: ?string, last_seen_at: ?string}>,
     *     current_session: ?array{joined_at: ?string, last_seen_at: ?string, left_at: ?string, duration_seconds: ?int},
     *     sessions: array<int, array{joined_at: ?string, last_seen_at: ?string, left_at: ?string, duration_seconds: ?int}>,
     *     events: array<int, array{event_type: string, message: ?string, ip_address: ?string, occurred_at: ?string}>,
     *     total_sessions: int,
     *     total_logins: int,
     *     total_playtime: string,
     *     first_seen: ?string,
     *     last_seen: ?string,
     *     unique_ip_count: int,
     *     message_count: int,
     *     command_count: int
     * }
     */
    public function build(Server $server, string $playerKey): array
    {
        /** @var Collection<int, PlayerSession> $sessions */
        $sessions = PlayerSession::query()
            ->where('server_id', $server->id)
            ->where('player_key', $playerKey)
            ->latest('joined_at')
            ->get();

        /** @var Collection<int, PlayerEvent> $events */
        $events = PlayerEvent::query()
            ->where('server_id', $server->id)
            ->where('player_key', $playerKey)
            ->latest('occurred_at')
            ->get();

        if ($sessions->isEmpty() && $events->isEmpty()) {
            abort(404);
        }

        $currentSession = $sessions->first(fn (PlayerSession $session) => $session->left_at === null);
        $completedPlaytime = (int) $sessions->sum('duration_seconds');

        if ($currentSession) {
            $completedPlaytime += $currentSession->joined_at->diffInSeconds(now());
        }

        $aliases = $sessions->pluck('player_name')
            ->merge($events->pluck('player_name'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $ipAddresses = $events
            ->filter(fn (PlayerEvent $event) => filled($event->ip_address))
            ->groupBy('ip_address')
            ->map(function (Collection $group, string $ipAddress) {
                return [
                    'ip_address' => $ipAddress,
                    'occurrences' => $group->count(),
                    'first_seen_at' => $this->serializeDateTime($group->min('occurred_at')),
                    'last_seen_at' => $this->serializeDateTime($group->max('occurred_at')),
                ];
            })
            ->sortByDesc('last_seen_at')
            ->values();

        $playerSourceId = $sessions->pluck('player_source_id')
            ->merge($events->pluck('player_source_id'))
            ->filter()
            ->first();

        $joinCount = $events->where('event_type', PlayerEvent::TYPE_JOIN)->count();
        $firstSeen = collect([
            $sessions->min('joined_at'),
            $events->min('occurred_at'),
        ])->filter()->sortBy(fn ($date) => $date?->getTimestamp())->first();
        $lastSeen = collect([
            $sessions->max('last_seen_at'),
            $events->max('occurred_at'),
        ])->filter()->sortByDesc(fn ($date) => $date?->getTimestamp())->first();
        $displayName = $sessions->first()?->player_name
            ?? $events->first()?->player_name
            ?? $playerKey;

        return [
            'display_name' => $displayName,
            'player_key' => $playerKey,
            'player_source_id' => $playerSourceId,
            'aliases' => $aliases,
            'ip_addresses' => $ipAddresses->all(),
            'current_session' => $currentSession ? $this->serializeSession($currentSession) : null,
            'sessions' => $sessions->take(25)->map(fn (PlayerSession $session) => $this->serializeSession($session))->values()->all(),
            'events' => $events->take(50)->map(fn (PlayerEvent $event) => $this->serializeEvent($event))->values()->all(),
            'total_sessions' => $sessions->count(),
            'total_logins' => $joinCount > 0 ? $joinCount : $sessions->count(),
            'total_playtime' => CarbonInterval::seconds($completedPlaytime)->cascade()->forHumans(),
            'first_seen' => $this->serializeDateTime($firstSeen),
            'last_seen' => $this->serializeDateTime($lastSeen),
            'unique_ip_count' => count($ipAddresses),
            'message_count' => $events->where('event_type', PlayerEvent::TYPE_CHAT)->count(),
            'command_count' => $events->where('event_type', PlayerEvent::TYPE_COMMAND)->count(),
        ];
    }

    /**
     * @return array{joined_at: ?string, last_seen_at: ?string, left_at: ?string, duration_seconds: ?int}
     */
    private function serializeSession(PlayerSession $session): array
    {
        return [
            'joined_at' => $this->serializeDateTime($session->joined_at),
            'last_seen_at' => $this->serializeDateTime($session->last_seen_at),
            'left_at' => $this->serializeDateTime($session->left_at),
            'duration_seconds' => $session->duration_seconds,
        ];
    }

    /**
     * @return array{event_type: string, message: ?string, ip_address: ?string, occurred_at: ?string}
     */
    private function serializeEvent(PlayerEvent $event): array
    {
        return [
            'event_type' => $event->event_type,
            'message' => $event->message,
            'ip_address' => $event->ip_address,
            'occurred_at' => $this->serializeDateTime($event->occurred_at),
        ];
    }

    private function serializeDateTime(mixed $dateTime): ?string
    {
        if (!$dateTime instanceof Carbon) {
            return null;
        }

        return $dateTime->toIso8601String();
    }
}
