<?php

namespace ArctisDev\PlayerCounter\Filament\Server\Pages;

use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use ArctisDev\PlayerCounter\Filament\Server\Widgets\PlayerActivityOverviewWidget;
use ArctisDev\PlayerCounter\Models\PlayerEvent;
use ArctisDev\PlayerCounter\Models\PlayerSession;
use ArctisDev\PlayerCounter\Services\MinecraftJavaPlayerLogRecorder;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use ArctisDev\PlayerCounter\Support\PlayerRouteKey;
use Carbon\CarbonInterval;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

class PlayerHistoryPage extends Page implements HasTable
{
    use BlockAccessInConflict;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-history';

    protected static ?string $slug = 'players/history';

    protected static ?int $navigationSort = 31;

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return app(PlayerQueryService::class)->canQuery($server) && parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return trans('player-counter::query.player_history');
    }

    public static function getModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function getSubheading(): ?string
    {
        return trans('player-counter::query.history_grouped_description');
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->records(fn (?string $search, int $page, int $recordsPerPage) => $this->getPlayerRecords($search, $page, $recordsPerPage))
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('display_name')
                    ->label(trans('player-counter::query.player'))
                    ->url(fn (array $record) => PlayerProfilePage::getUrl(['player' => $record['player_route_key']]))
                    ->searchable(),
                TextColumn::make('player_source_id')
                    ->label(trans('player-counter::query.player_id'))
                    ->placeholder(trans('player-counter::query.unknown'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(trans('player-counter::query.current_session'))
                    ->state(fn (array $record) => $record['is_online'] ? trans('player-counter::query.online') : trans('player-counter::query.offline'))
                    ->badge()
                    ->color(fn (string $state) => $state === trans('player-counter::query.online') ? 'success' : 'gray'),
                TextColumn::make('last_seen_at')
                    ->label(trans('player-counter::query.last_seen'))
                    ->formatStateUsing(fn ($state) => $state?->diffForHumans() ?? trans('player-counter::query.unknown')),
                TextColumn::make('total_sessions')
                    ->label(trans('player-counter::query.total_sessions'))
                    ->badge(),
                TextColumn::make('total_playtime_seconds')
                    ->label(trans('player-counter::query.total_playtime'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => CarbonInterval::seconds((int) $state)->cascade()->forHumans()),
                TextColumn::make('unique_ip_count')
                    ->label(trans('player-counter::query.unique_ips'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('message_count')
                    ->label(trans('player-counter::query.messages'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('command_count')
                    ->label(trans('player-counter::query.commands'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('profile')
                    ->label(trans('player-counter::query.view_profile'))
                    ->icon('tabler-user-search')
                    ->url(fn (array $record) => PlayerProfilePage::getUrl(['player' => $record['player_route_key']])),
            ])
            ->emptyStateHeading(trans('player-counter::query.no_history'))
            ->emptyStateDescription(trans('player-counter::query.no_history_description'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PlayerActivityOverviewWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('live_players')
                ->label(trans('player-counter::query.live_players'))
                ->icon('tabler-users-group')
                ->url(PlayersPage::getUrl()),
        ];
    }

    private function getPlayerRecords(?string $search, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        app(MinecraftJavaPlayerLogRecorder::class)->syncIfStale($server);

        $sessionSummary = PlayerSession::query()
            ->where('server_id', $server->id)
            ->selectRaw('player_key')
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw('MAX(last_seen_at) as last_seen_at')
            ->selectRaw('SUM(COALESCE(duration_seconds, 0)) as completed_playtime_seconds')
            ->selectRaw('MAX(CASE WHEN left_at IS NULL THEN 1 ELSE 0 END) as is_online')
            ->groupBy('player_key')
            ->get()
            ->keyBy('player_key');

        $eventSummary = PlayerEvent::query()
            ->where('server_id', $server->id)
            ->selectRaw('player_key')
            ->selectRaw('MAX(occurred_at) as last_event_at')
            ->selectRaw("COUNT(DISTINCT CASE WHEN ip_address IS NOT NULL AND ip_address <> '' THEN ip_address END) as unique_ip_count")
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as message_count', [PlayerEvent::TYPE_CHAT])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as command_count', [PlayerEvent::TYPE_COMMAND])
            ->groupBy('player_key')
            ->get()
            ->keyBy('player_key');

        $playerKeys = $sessionSummary->keys()
            ->merge($eventSummary->keys())
            ->unique()
            ->values();

        if ($playerKeys->isEmpty()) {
            return new LengthAwarePaginator([], 0, $recordsPerPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        $latestSessions = PlayerSession::query()
            ->where('server_id', $server->id)
            ->whereIn('player_key', $playerKeys->all())
            ->orderByDesc('last_seen_at')
            ->get()
            ->unique('player_key')
            ->keyBy('player_key');

        $latestEvents = PlayerEvent::query()
            ->where('server_id', $server->id)
            ->whereIn('player_key', $playerKeys->all())
            ->orderByDesc('occurred_at')
            ->get()
            ->unique('player_key')
            ->keyBy('player_key');

        $records = $playerKeys
            ->map(function (string $playerKey) use ($sessionSummary, $eventSummary, $latestSessions, $latestEvents) {
                $session = $sessionSummary->get($playerKey);
                $event = $eventSummary->get($playerKey);
                $latestSession = $latestSessions->get($playerKey);
                $latestEvent = $latestEvents->get($playerKey);
                $totalPlaytimeSeconds = (int) ($session?->completed_playtime_seconds ?? 0);

                if ($latestSession && $latestSession->left_at === null) {
                    $totalPlaytimeSeconds += $latestSession->joined_at->diffInSeconds(now());
                }

                $lastSeenAt = $latestSession?->last_seen_at;

                if ($latestEvent && (!$lastSeenAt || $latestEvent->occurred_at->greaterThan($lastSeenAt))) {
                    $lastSeenAt = $latestEvent->occurred_at;
                }

                return [
                    'player_key' => $playerKey,
                    'player_route_key' => PlayerRouteKey::encode($playerKey),
                    'display_name' => $latestSession?->player_name ?? $latestEvent?->player_name ?? trans('player-counter::query.unknown'),
                    'player_source_id' => $latestSession?->player_source_id ?? $latestEvent?->player_source_id,
                    'last_seen_at' => $lastSeenAt,
                    'total_sessions' => (int) ($session?->total_sessions ?? 0),
                    'total_playtime_seconds' => $totalPlaytimeSeconds,
                    'is_online' => (bool) ($session?->is_online ?? false),
                    'unique_ip_count' => (int) ($event?->unique_ip_count ?? 0),
                    'message_count' => (int) ($event?->message_count ?? 0),
                    'command_count' => (int) ($event?->command_count ?? 0),
                ];
            })
            ->filter(function (array $record) use ($search) {
                if (!filled($search)) {
                    return true;
                }

                $search = mb_strtolower(trim((string) $search));

                return str_contains(mb_strtolower($record['display_name']), $search)
                    || str_contains(mb_strtolower((string) ($record['player_source_id'] ?? '')), $search);
            })
            ->sortByDesc(fn (array $record) => $record['last_seen_at']?->getTimestamp() ?? 0)
            ->values();

        $items = $records
            ->slice(($page - 1) * $recordsPerPage, $recordsPerPage)
            ->values()
            ->all();

        return new LengthAwarePaginator(
            $items,
            $records->count(),
            $recordsPerPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }
}
