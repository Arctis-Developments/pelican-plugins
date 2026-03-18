<?php

namespace ArctisDev\PlayerCounter\Filament\Server\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Traits\Filament\BlockAccessInConflict;
use ArctisDev\PlayerCounter\Models\PlayerSession;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use ArctisDev\PlayerCounter\Support\PlayerIdentity;
use ArctisDev\PlayerCounter\Support\PlayerRouteKey;
use Carbon\CarbonInterval;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class PlayersPage extends Page implements HasTable
{
    use BlockAccessInConflict;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-users-group';

    protected static ?string $slug = 'players';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        if (!app(PlayerQueryService::class)->canQuery($server)) {
            return false;
        }

        return parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return trans('player-counter::query.players');
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

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $gameQuery = app(PlayerQueryService::class)->resolveGameQuery($server);

        $isMinecraft = $gameQuery?->query_type === 'minecraft_java';

        $whitelist = [];
        $ops = [];

        if ($isMinecraft) {
            $fileRepository = (new DaemonFileRepository())->setServer($server);
            $whitelist = $this->loadNamedEntries($fileRepository, 'whitelist.json');
            $ops = $this->loadNamedEntries($fileRepository, 'ops.json');
        }

        return $table
            ->poll('15s')
            ->records(function (?string $search, int $page, int $recordsPerPage) {
                /** @var Server $server */
                $server = Filament::getTenant();

                $players = [];

                $data = app(PlayerQueryService::class)->query($server);

                if ($data) {
                    $players = $data['players'] ?? [];
                    $openSessions = PlayerSession::query()
                        ->where('server_id', $server->id)
                        ->whereNull('left_at')
                        ->get();

                    $openSessionsByKey = $openSessions->keyBy('player_key');
                    $openSessionsByName = $openSessions->keyBy(fn (PlayerSession $session) => mb_strtolower($session->player_name));

                    $players = array_map(function (array $player) use ($openSessionsByKey, $openSessionsByName) {
                        $session = $openSessionsByKey->get(PlayerIdentity::key($player))
                            ?? $openSessionsByName->get(mb_strtolower((string) ($player['name'] ?? '')));

                        if ($session) {
                            $player['recorded_time'] = $session->joined_at->diffInSeconds(now());
                            $player['profile_route_key'] = PlayerRouteKey::encode($session->player_key);
                        } else {
                            $player['profile_route_key'] = PlayerRouteKey::encode(PlayerIdentity::key($player));
                        }

                        return $player;
                    }, $players);
                }

                if ($search) {
                    $players = array_filter($players, fn ($player) => str($player['name'])->contains($search, true));
                }

                return new LengthAwarePaginator(array_slice($players, ($page - 1) * $recordsPerPage, $recordsPerPage), count($players), $recordsPerPage, $page);
            })
            ->paginated([30, 60])
            ->contentGrid([
                'default' => 1,
                'lg' => 2,
                'xl' => $isMinecraft ? 2 : 3,
            ])
            ->columns([
                Split::make([
                    ImageColumn::make('avatar')
                        ->visible(fn () => $isMinecraft)
                        ->state(fn (array $record) => 'https://cravatar.eu/helmhead/' . $record['name'] . '/256.png')
                        ->grow(false),
                    TextColumn::make('name')
                        ->label('Name')
                        ->tooltip(fn (array $record) => array_key_exists('id', $record) ? $record['id'] : null)
                        ->url(fn (array $record) => PlayerProfilePage::getUrl(['player' => $record['profile_route_key'] ?? null]))
                        ->searchable(),
                    TextColumn::make('is_whitelisted')
                        ->visible(fn () => $isMinecraft)
                        ->badge()
                        ->grow(false)
                        ->state(fn (array $record) => in_array($record['name'], $whitelist) ? trans('player-counter::query.whitelisted') : null),
                    TextColumn::make('is_op')
                        ->visible(fn () => $isMinecraft)
                        ->badge()
                        ->grow(false)
                        ->state(fn (array $record) => in_array($record['name'], $ops) ? trans('player-counter::query.op') : null),
                    TextColumn::make('session_time')
                        ->label(trans('player-counter::query.session_duration'))
                        ->badge()
                        ->grow(false)
                        ->state(fn (array $record) => $record['recorded_time'] ?? $record['time'] ?? null)
                        ->formatStateUsing(fn ($state) => $state ? CarbonInterval::seconds($state)->cascade()->forHumans() : null),
                ]),
            ])
            ->recordActions([
                Action::make('exclude_kick')
                    ->visible(fn () => $isMinecraft)
                    ->label(trans('player-counter::query.kick'))
                    ->icon('tabler-door-exit')
                    ->color('danger')
                    ->action(function (array $record) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        try {
                            $server->send('kick ' . $record['name']);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_kicked'))
                                ->body($record['name'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        } catch (Exception $exception) {
                            report($exception);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_kick_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('profile')
                    ->label(trans('player-counter::query.view_profile'))
                    ->icon('tabler-user-search')
                    ->url(fn (array $record) => PlayerProfilePage::getUrl(['player' => $record['profile_route_key'] ?? null])),
                Action::make('exclude_ban')
                    ->visible(fn () => $isMinecraft)
                    ->label(trans('player-counter::query.ban'))
                    ->icon('tabler-hammer')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        try {
                            $server->send('ban ' . $record['name']);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_banned'))
                                ->body($record['name'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        } catch (Exception $exception) {
                            report($exception);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_ban_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('exclude_whitelist')
                    ->visible(fn () => $isMinecraft)
                    ->label(fn (array $record) => in_array($record['name'], $whitelist) ? trans('player-counter::query.remove_from_whitelist') : trans('player-counter::query.add_to_whitelist'))
                    ->icon(fn (array $record) => in_array($record['name'], $whitelist) ? 'tabler-playlist-x' : 'tabler-playlist-add')
                    ->color(fn (array $record) => in_array($record['name'], $whitelist) ? 'danger' : 'success')
                    ->action(function (array $record) use ($whitelist) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        try {
                            $action = in_array($record['name'], $whitelist) ? 'remove' : 'add';

                            $server->send('whitelist ' . $action . ' ' . $record['name']);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_whitelist_' . $action))
                                ->body($record['name'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        } catch (Exception $exception) {
                            report($exception);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_whitelist_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('exclude_op')
                    ->visible(fn () => $isMinecraft)
                    ->label(fn (array $record) => in_array($record['name'], $ops) ? trans('player-counter::query.remove_from_ops') : trans('player-counter::query.add_to_ops'))
                    ->icon(fn (array $record) => in_array($record['name'], $ops) ? 'tabler-shield-minus' : 'tabler-shield-plus')
                    ->color(fn (array $record) => in_array($record['name'], $ops) ? 'warning' : 'success')
                    ->action(function (array $record) use ($ops) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        try {
                            $action = in_array($record['name'], $ops) ? 'deop' : 'op';

                            $server->send($action  . ' ' . $record['name']);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_' . $action))
                                ->body($record['name'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        } catch (Exception $exception) {
                            report($exception);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_op_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading(function () {
                /** @var Server $server */
                $server = Filament::getTenant();

                if ($server->retrieveStatus()->isOffline()) {
                    return trans('player-counter::query.table.server_offline');
                }

                return trans('player-counter::query.table.no_players');
            })
            ->emptyStateDescription(function () {
                /** @var Server $server */
                $server = Filament::getTenant();

                if ($server->retrieveStatus()->isOffline()) {
                    return null;
                }

                return trans('player-counter::query.table.no_players_description');
            });
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
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('history')
                ->label(trans('player-counter::query.player_history'))
                ->icon('tabler-history')
                ->url(PlayerHistoryPage::getUrl()),
        ];
    }

    private function refreshPage(): void
    {
        $url = self::getUrl();
        $this->redirect($url, FilamentView::hasSpaMode($url));
    }

    /**
     * @return array<int, string>
     */
    private function loadNamedEntries(DaemonFileRepository $fileRepository, string $path): array
    {
        try {
            $content = $fileRepository->getContent($path);

            if (!is_string($content) || trim($content) === '') {
                return [];
            }

            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                return [];
            }

            return collect($decoded)
                ->map(fn ($entry) => is_array($entry) ? trim((string) ($entry['name'] ?? '')) : '')
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }
}
