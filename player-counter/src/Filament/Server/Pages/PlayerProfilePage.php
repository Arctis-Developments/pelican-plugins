<?php

namespace ArctisDev\PlayerCounter\Filament\Server\Pages;

use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use ArctisDev\PlayerCounter\Models\PlayerEvent;
use ArctisDev\PlayerCounter\Services\MinecraftJavaPlayerLogRecorder;
use ArctisDev\PlayerCounter\Services\PlayerProfileService;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use ArctisDev\PlayerCounter\Support\PlayerRouteKey;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class PlayerProfilePage extends Page
{
    use BlockAccessInConflict;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-user-search';

    protected static ?string $slug = 'players/history/profile';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'player-counter::filament.server.pages.player-profile-page';

    /**
     * @var array{
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
    public array $profile = [];

    public ?string $playerKey = null;

    public int $eventsPage = 1;

    public int $eventsPerPage = 15;

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return app(PlayerQueryService::class)->canQuery($server) && parent::canAccess();
    }

    public function mount(): void
    {
        $playerRouteKey = request()->query('player');

        abort_unless(is_string($playerRouteKey), 404);

        $this->playerKey = PlayerRouteKey::decode($playerRouteKey);

        abort_unless($this->playerKey !== null, 404);

        $this->loadProfile();
    }

    public function hydrate(): void
    {
        if ($this->playerKey !== null) {
            $this->loadProfile();
        }
    }

    public function getTitle(): string
    {
        return $this->profile['display_name'] ?? trans('player-counter::query.player_profile');
    }

    public function getSubheading(): ?string
    {
        return $this->profile['player_source_id'] ?? trans('player-counter::query.player_profile');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('live_players')
                ->label(trans('player-counter::query.live_players'))
                ->icon('tabler-users-group')
                ->color('gray')
                ->url(PlayersPage::getUrl()),
            Action::make('history')
                ->label(trans('player-counter::query.player_history'))
                ->icon('tabler-history')
                ->color('gray')
                ->url(PlayerHistoryPage::getUrl()),
        ];
    }

    public function formatDateTime(CarbonInterface|string|null $dateTime): string
    {
        if (is_string($dateTime)) {
            $dateTime = Carbon::parse($dateTime);
        }

        if (!$dateTime instanceof CarbonInterface) {
            return trans('player-counter::query.unknown');
        }

        return sprintf('%s (%s)', $dateTime->translatedFormat('d/m/Y H:i:s'), $dateTime->diffForHumans());
    }

    public function formatDuration(?int $seconds, CarbonInterface|string|null $startedAt = null): string
    {
        if (is_string($startedAt)) {
            $startedAt = Carbon::parse($startedAt);
        }

        if ($seconds === null && $startedAt === null) {
            return trans('player-counter::query.unknown');
        }

        $resolvedSeconds = $seconds ?? max(0, $startedAt?->diffInSeconds(now()) ?? 0);

        return CarbonInterval::seconds($resolvedSeconds)->cascade()->forHumans();
    }

    public function eventTypeLabel(string $type): string
    {
        return trans("player-counter::query.event_types.{$type}");
    }

    public function eventTypeClasses(string $type): string
    {
        return match ($type) {
            PlayerEvent::TYPE_JOIN => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400',
            PlayerEvent::TYPE_LEAVE => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-white/10 dark:text-gray-300',
            PlayerEvent::TYPE_CHAT => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400',
            PlayerEvent::TYPE_COMMAND => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400',
            default => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-400',
        };
    }

    /**
     * @return array<int, array{event_type: string, message: ?string, ip_address: ?string, occurred_at: ?string}>
     */
    public function paginatedEvents(): array
    {
        return array_slice(
            $this->profile['events'] ?? [],
            ($this->eventsPage - 1) * $this->eventsPerPage,
            $this->eventsPerPage,
        );
    }

    public function eventsLastPage(): int
    {
        return max(1, (int) ceil(count($this->profile['events'] ?? []) / $this->eventsPerPage));
    }

    public function previousEventsPage(): void
    {
        $this->eventsPage = max(1, $this->eventsPage - 1);
    }

    public function nextEventsPage(): void
    {
        $this->eventsPage = min($this->eventsLastPage(), $this->eventsPage + 1);
    }

    private function loadProfile(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        abort_unless($this->playerKey !== null, 404);

        app(MinecraftJavaPlayerLogRecorder::class)->syncIfStale($server);
        $this->profile = app(PlayerProfileService::class)->build($server, $this->playerKey);
        $this->eventsPage = min($this->eventsPage, $this->eventsLastPage());
    }
}
