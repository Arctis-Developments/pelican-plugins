<?php

namespace ArctisDev\ServerSplit\Filament\App\Resources\SplitServerResource\Pages;

use App\Enums\TablerIcon;
use App\Exceptions\DisplayException;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Filament\Components\Forms\Fields\StartupVariable;
use App\Models\Allocation;
use App\Models\Egg;
use App\Services\Servers\RandomWordService;
use App\Services\Servers\ServerCreationService;
use ArctisDev\ServerSplit\Filament\App\Resources\SplitServerResource;
use ArctisDev\ServerSplit\Services\ServerSplitQuotaService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class CreateSplitServer extends CreateRecord
{
    protected static string $resource = SplitServerResource::class;

    protected static bool $canCreateAnother = false;

    private ServerCreationService $serverCreationService;

    private ServerSplitQuotaService $quotaService;

    /**
     * @var array<string, int|null>|null
     */
    private ?array $cachedRemainingQuota = null;

    public function boot(ServerCreationService $serverCreationService, ServerSplitQuotaService $quotaService): void
    {
        $this->serverCreationService = $serverCreationService;
        $this->quotaService = $quotaService;
    }

    public function getHeading(): string
    {
        return trans('server-split::server-split.app.heading');
    }

    public function getSubheading(): ?string
    {
        return trans('server-split::server-split.app.subheading');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::ScreenTwoExtraLarge;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('server-split::server-split.app.quota.heading'))
                    ->columnSpanFull()
                    ->description(trans('server-split::server-split.app.quota.description'))
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema($this->getQuotaSummaryInputs()),
                Wizard::make($this->getSteps())
                    ->columnSpanFull()
                    ->nextAction(fn (Action $action) => $action
                        ->label(trans('server-split::server-split.app.actions.next'))
                        ->icon(TablerIcon::ArrowRight))
                    ->previousAction(fn (Action $action) => $action
                        ->label(trans('server-split::server-split.app.actions.back'))
                        ->color('gray')
                        ->icon(TablerIcon::ArrowLeft))
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button
                            type="submit"
                            icon="tabler-plus"
                            size="lg"
                        >
                            {{ trans('server-split::server-split.app.actions.submit') }}
                        </x-filament::button>
                    BLADE))),
            ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(trans('server-split::server-split.app.actions.back_to_home'))
                ->icon('tabler-arrow-left')
                ->color('gray')
                ->url(ListServers::getUrl()),
        ];
    }

    /**
     * @return Step[]
     */
    protected function getSteps(): array
    {
        return [
            Step::make(trans('server-split::server-split.app.sections.server'))
                ->icon(TablerIcon::InfoCircle)
                ->completedIcon(TablerIcon::Check)
                ->schema([
                    Section::make(trans('server-split::server-split.app.cards.server'))
                        ->description(trans('server-split::server-split.app.descriptions.server'))
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'xl' => 6,
                        ])
                        ->schema([
                            TextInput::make('name')
                                ->label(trans('server-split::server-split.app.fields.name'))
                                ->default(fn () => $this->generateRandomName())
                                ->suffixAction(
                                    Action::make('generate_random_name')
                                        ->icon('tabler-refresh')
                                        ->tooltip(trans('server-split::server-split.app.actions.generate_name'))
                                        ->action(fn (Set $set) => $set('name', $this->generateRandomName()))
                                )
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 2,
                                    'xl' => 3,
                                ])
                                ->required()
                                ->maxLength(255),
                            Select::make('node_id')
                                ->label(trans('server-split::server-split.app.fields.node'))
                                ->relationship('node', 'name', fn (Builder $query) => $query->whereIn('id', user()?->accessibleNodes()->pluck('id') ?? []))
                                ->default(fn () => user()?->accessibleNodes()->orderBy('name')->value('id'))
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'xl' => 1,
                                ])
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->afterStateUpdated(fn (Set $set) => $set('allocation_id', null)),
                            Select::make('allocation_id')
                                ->label(trans('server-split::server-split.app.fields.allocation'))
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'xl' => 2,
                                ])
                                ->disabled(fn (Get $get) => blank($get('node_id')))
                                ->relationship(
                                    'allocation',
                                    'ip',
                                    fn (Builder $query, Get $get) => $query
                                        ->where('node_id', $get('node_id'))
                                        ->whereNull('server_id'),
                                )
                                ->getOptionLabelFromRecordUsing(fn (Allocation $allocation) => $allocation->address ?? '')
                                ->searchable(['ip', 'port', 'ip_alias'])
                                ->preload()
                                ->required(),
                            Textarea::make('description')
                                ->label(trans('server-split::server-split.app.fields.description'))
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                ]),
            Step::make(trans('server-split::server-split.app.sections.egg'))
                ->icon(TablerIcon::Egg)
                ->completedIcon(TablerIcon::Check)
                ->schema([
                    Section::make(trans('server-split::server-split.app.cards.software'))
                        ->description(trans('server-split::server-split.app.descriptions.software'))
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'xl' => 6,
                        ])
                        ->schema([
                            Select::make('egg_id')
                                ->label(trans('server-split::server-split.app.fields.egg'))
                                ->relationship('egg', 'name')
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 2,
                                    'xl' => 4,
                                ])
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    /** @var Egg|null $egg */
                                    $egg = Egg::query()->find($state);

                                    $set('image', '');
                                    $set('select_image', null);

                                    if (!$egg) {
                                        $set('server_variables', []);
                                        $set('environment', []);

                                        return;
                                    }

                                    $serverVariables = $egg->variables
                                        ->map(fn ($variable) => $variable->toArray())
                                        ->sortBy('sort')
                                        ->values();

                                    $environment = [];

                                    $set('server_variables', $serverVariables->all());

                                    foreach ($serverVariables as $index => $variable) {
                                        $value = $variable['default_value'] ?? '';

                                        $set("server_variables.{$index}.variable_value", $value);
                                        $set("server_variables.{$index}.variable_id", $variable['id']);

                                        if (filled($variable['env_variable'] ?? null)) {
                                            $environment[$variable['env_variable']] = $value;
                                        }
                                    }

                                    $images = $egg->docker_images ?? [];
                                    if ($images) {
                                        $defaultImage = collect($images)->first();
                                        $set('image', $defaultImage);
                                        $set('select_image', $defaultImage);
                                    }

                                    $set('environment', $environment);

                                    if (!$get('name')) {
                                        $set('name', $egg->getKebabName());
                                    }
                                }),
                            Fieldset::make(trans('server-split::server-split.app.sections.variables'))
                                ->columnSpanFull()
                                ->schema([
                                    Hidden::make('environment')->default([]),
                                    Repeater::make('server_variables')
                                        ->hiddenLabel()
                                        ->reorderable(false)
                                        ->addable(false)
                                        ->deletable(false)
                                        ->default([])
                                        ->hidden(fn ($state) => empty($state))
                                        ->schema([
                                            StartupVariable::make('variable_value')
                                                ->fromForm()
                                                ->disabled(false)
                                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                    $environment = $get('../../environment') ?? [];
                                                    $environment[$get('env_variable')] = $state;
                                                    $set('../../environment', $environment);
                                                }),
                                        ]),
                                ]),
                        ]),
                ]),
            Step::make(trans('server-split::server-split.app.sections.environment'))
                ->icon(TablerIcon::BrandDocker)
                ->completedIcon(TablerIcon::Check)
                ->columns([
                    'default' => 1,
                    'xl' => 2,
                ])
                ->schema([
                    Fieldset::make(trans('server-split::server-split.app.sections.resources'))
                        ->columnSpan(1)
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                        ])
                        ->schema([
                            TextInput::make('cpu')
                                ->label(trans('server-split::server-split.app.fields.cpu'))
                                ->suffix('%')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(fn () => $this->remainingLimit('cpu'))
                                ->required()
                                ->helperText(fn () => $this->buildRemainingHelper('cpu')),
                            TextInput::make('memory')
                                ->label(trans('server-split::server-split.app.fields.memory'))
                                ->suffix('MiB')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(fn () => $this->remainingLimit('memory'))
                                ->required()
                                ->helperText(fn () => $this->buildRemainingHelper('memory', ' MiB')),
                            TextInput::make('disk')
                                ->label(trans('server-split::server-split.app.fields.disk'))
                                ->suffix('MiB')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(fn () => $this->remainingLimit('disk'))
                                ->required()
                                ->helperText(fn () => $this->buildRemainingHelper('disk', ' MiB')),
                        ]),
                    Fieldset::make(trans('server-split::server-split.app.sections.features'))
                        ->columnSpan(1)
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                        ])
                        ->schema([
                            TextInput::make('allocation_limit')
                                ->label(trans('server-split::server-split.app.fields.allocations'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(fn () => $this->remainingLimit('allocations'))
                                ->required()
                                ->default(0)
                                ->helperText(fn () => $this->buildRemainingHelper('allocations')),
                            TextInput::make('database_limit')
                                ->label(trans('server-split::server-split.app.fields.databases'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(fn () => $this->remainingLimit('databases'))
                                ->required()
                                ->default(0)
                                ->helperText(fn () => $this->buildRemainingHelper('databases')),
                            TextInput::make('backup_limit')
                                ->label(trans('server-split::server-split.app.fields.backups'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(fn () => $this->remainingLimit('backups'))
                                ->required()
                                ->default(0)
                                ->helperText(fn () => $this->buildRemainingHelper('backups')),
                        ]),
                    Fieldset::make(trans('server-split::server-split.app.sections.docker'))
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 4,
                        ])
                        ->schema([
                            Select::make('select_image')
                                ->label(trans('server-split::server-split.app.fields.image_name'))
                                ->live()
                                ->afterStateUpdated(fn (Set $set, $state) => $set('image', $state))
                                ->options(function (Get $get, Set $set) {
                                    $egg = Egg::query()->find($get('egg_id'));
                                    $images = $egg?->docker_images ?? [];

                                    $currentImage = $get('image');
                                    if (!$currentImage && $images) {
                                        $defaultImage = collect($images)->first();
                                        $set('image', $defaultImage);
                                        $set('select_image', $defaultImage);
                                    }

                                    return array_flip($images) + ['ghcr.io/custom-image' => trans('server-split::server-split.app.fields.custom_image')];
                                })
                                ->selectablePlaceholder(false)
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 2,
                                    'md' => 3,
                                    'lg' => 2,
                                ]),
                            TextInput::make('image')
                                ->label(trans('server-split::server-split.app.fields.image'))
                                ->required()
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 2,
                                    'md' => 3,
                                    'lg' => 2,
                                ])
                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                    $egg = Egg::query()->find($get('egg_id'));
                                    $images = $egg?->docker_images ?? [];

                                    if (in_array($state, $images, true)) {
                                        $set('select_image', $state);
                                    } else {
                                        $set('select_image', 'ghcr.io/custom-image');
                                    }
                                }),
                        ]),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        if (!user()) {
            throw new Halt();
        }

        $data['owner_id'] = user()->id;
        $data['io'] = (int) config('server-split.provisioning.default_io', 500);
        $data['swap'] = (int) config('server-split.provisioning.default_swap', 0);
        $data['database_limit'] = (int) ($data['database_limit'] ?? config('server-split.provisioning.default_database_limit', 0));
        $data['allocation_limit'] = (int) ($data['allocation_limit'] ?? config('server-split.provisioning.default_allocation_limit', 0));
        $data['backup_limit'] = (int) ($data['backup_limit'] ?? config('server-split.provisioning.default_backup_limit', 0));
        $data['oom_killer'] = (bool) config('server-split.provisioning.default_oom_killer', false);
        $data['start_on_completion'] = (bool) config('server-split.provisioning.default_start_on_completion', false);
        $data['skip_scripts'] = (bool) config('server-split.provisioning.default_skip_scripts', false);

        try {
            return $this->serverCreationService->handle($data);
        } catch (DisplayException|Exception $exception) {
            Notification::make()
                ->title(trans('server-split::server-split.app.notifications.create_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return '/';
    }

    private function formatRemaining(?int $remaining, string $suffix = ''): string
    {
        if ($remaining === null) {
            return trans('server-split::server-split.common.unlimited');
        }

        return $remaining . $suffix;
    }

    private function buildRemainingHelper(string $resource, string $suffix = ''): string
    {
        $remaining = $this->remainingQuota()[$resource] ?? null;

        return trans('server-split::server-split.app.remaining_helper', [
            'value' => $this->formatRemaining($remaining, $suffix),
        ]);
    }

    private function remainingLimit(string $resource): ?int
    {
        return $this->remainingQuota()[$resource] ?? null;
    }

    /**
     * @return array<string, int|null>
     */
    private function remainingQuota(): array
    {
        if (is_array($this->cachedRemainingQuota)) {
            return $this->cachedRemainingQuota;
        }

        $this->cachedRemainingQuota = user() ? $this->quotaService->remaining(user()) : [];

        return $this->cachedRemainingQuota;
    }

    private function generateRandomName(): string
    {
        return app(RandomWordService::class)->word();
    }

    /**
     * @return array<int, TextInput>
     */
    private function getQuotaSummaryInputs(): array
    {
        $remaining = $this->remainingQuota();

        return [
            $this->makeQuotaInput('quota_servers_display', trans('server-split::server-split.app.fields.server_limit'), $this->formatRemaining($remaining['servers'] ?? null)),
            $this->makeQuotaInput('quota_cpu_display', trans('server-split::server-split.app.fields.cpu'), $this->formatRemaining($remaining['cpu'] ?? null, '%')),
            $this->makeQuotaInput('quota_memory_display', trans('server-split::server-split.app.fields.memory'), $this->formatRemaining($remaining['memory'] ?? null, ' MiB')),
            $this->makeQuotaInput('quota_disk_display', trans('server-split::server-split.app.fields.disk'), $this->formatRemaining($remaining['disk'] ?? null, ' MiB')),
            $this->makeQuotaInput('quota_databases_display', trans('server-split::server-split.app.fields.databases'), $this->formatRemaining($remaining['databases'] ?? null)),
            $this->makeQuotaInput('quota_backups_display', trans('server-split::server-split.app.fields.backups'), $this->formatRemaining($remaining['backups'] ?? null)),
            $this->makeQuotaInput('quota_allocations_display', trans('server-split::server-split.app.fields.allocations'), $this->formatRemaining($remaining['allocations'] ?? null)),
        ];
    }

    private function makeQuotaInput(string $name, string $label, string $value): TextInput
    {
        return TextInput::make($name)
            ->hiddenLabel()
            ->prefix($label)
            ->default($value)
            ->readOnly()
            ->dehydrated(false);
    }
}
