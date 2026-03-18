<?php

namespace ArctisDev\PlayerCounter\Filament\Server\Widgets;

use App\Enums\CustomizationKey;
use App\Models\Server;
use ArctisDev\PlayerCounter\Models\PlayerCountSnapshot;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ConsolePlayerStatusWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '15s';

    protected ?string $maxHeight = '180px';

    public ?Server $server = null;

    public static function canView(): bool
    {
        $server = Filament::getTenant();

        if (!$server instanceof Server) {
            return false;
        }

        return app(PlayerQueryService::class)->canQuery($server) && !$server->retrieveStatus()->isOffline();
    }

    protected function getData(): array
    {
        $server = Filament::getTenant();

        if (!$server instanceof Server) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        app(PlayerQueryService::class)->query($server);

        $points = max(10, (int) user()?->getCustomization(CustomizationKey::ConsoleGraphPeriod));

        $snapshots = PlayerCountSnapshot::query()
            ->where('server_id', $server->id)
            ->latest('collected_at')
            ->limit($points)
            ->get()
            ->reverse()
            ->map(fn (PlayerCountSnapshot $snapshot) => [
                'players' => $snapshot->current_players,
                'timestamp' => Carbon::parse($snapshot->collected_at)->setTimezone(user()->timezone ?? 'UTC')->format('H:i'),
            ])
            ->values();

        return [
            'datasets' => [
                [
                    'data' => $snapshots->pluck('players')->all(),
                    'backgroundColor' => [
                        'rgba(100, 255, 105, 0.18)',
                    ],
                    'borderColor' => 'rgba(100, 255, 105, 0.95)',
                    'pointRadius' => 0,
                    'pointHoverRadius' => 3,
                    'tension' => '0.3',
                    'fill' => true,
                ],
            ],
            'labels' => $snapshots->pluck('timestamp')->all(),
            'locale' => user()->language ?? 'en',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            scales: {
                y: {
                    min: 0,
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                    },
                },
                x: {
                    display: false,
                }
            },
            plugins: {
                legend: {
                    display: false,
                }
            }
        }
    JS);
    }

    public function getHeading(): string
    {
        /** @var ?Server $server */
        $server = $this->server ?? Filament::getTenant();

        if (!$server instanceof Server) {
            return trans('player-counter::query.players');
        }

        $latestSnapshot = PlayerCountSnapshot::query()
            ->where('server_id', $server->id)
            ->latest('collected_at')
            ->first();

        if (!$latestSnapshot) {
            return trans('player-counter::query.players');
        }

        return trans('player-counter::query.players') . ' - ' . $latestSnapshot->current_players . ' / ' . ($latestSnapshot->max_players ?? '?');
    }
}
