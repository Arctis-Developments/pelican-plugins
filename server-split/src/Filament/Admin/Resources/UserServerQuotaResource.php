<?php

namespace ArctisDev\ServerSplit\Filament\Admin\Resources;

use App\Enums\TablerIcon;
use App\Models\User;
use ArctisDev\ServerSplit\Filament\Admin\Resources\UserServerQuotaResource\Pages\ListUserServerQuotas;
use ArctisDev\ServerSplit\Models\ServerSplitQuota;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserServerQuotaResource extends Resource
{
    protected static ?string $model = ServerSplitQuota::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Server2;

    protected static ?string $recordTitleAttribute = 'user.username';

    public static function getNavigationLabel(): string
    {
        return trans('server-split::server-split.admin.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return trans('server-split::server-split.admin.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('server-split::server-split.admin.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.user');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('user_id')
                            ->label(trans('server-split::server-split.admin.fields.user'))
                            ->relationship('user', 'username')
                            ->searchable(['username', 'email'])
                            ->preload()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->getOptionLabelFromRecordUsing(fn (User $user) => "{$user->username} ({$user->email})"),
                        Section::make(trans('server-split::server-split.admin.sections.limits'))
                            ->description(trans('server-split::server-split.admin.fields.empty_is_unlimited'))
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextInput::make('max_servers')
                                    ->label(trans('server-split::server-split.admin.fields.max_servers'))
                                    ->placeholder(trans('server-split::server-split.common.unlimited'))
                                    ->formatStateUsing(fn ($state) => filled($state) ? $state : null)
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(fn ($state) => static::unlimitedHelperText($state))
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                TextInput::make('max_cpu')
                                    ->label(trans('server-split::server-split.admin.fields.max_cpu'))
                                    ->suffix('%')
                                    ->placeholder(trans('server-split::server-split.common.unlimited'))
                                    ->formatStateUsing(fn ($state) => filled($state) ? $state : null)
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(fn ($state) => static::unlimitedHelperText($state))
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                TextInput::make('max_memory')
                                    ->label(trans('server-split::server-split.admin.fields.max_memory'))
                                    ->suffix('MiB')
                                    ->placeholder(trans('server-split::server-split.common.unlimited'))
                                    ->formatStateUsing(fn ($state) => filled($state) ? $state : null)
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(fn ($state) => static::unlimitedHelperText($state))
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                TextInput::make('max_disk')
                                    ->label(trans('server-split::server-split.admin.fields.max_disk'))
                                    ->suffix('MiB')
                                    ->placeholder(trans('server-split::server-split.common.unlimited'))
                                    ->formatStateUsing(fn ($state) => filled($state) ? $state : null)
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(fn ($state) => static::unlimitedHelperText($state))
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                TextInput::make('max_backups')
                                    ->label(trans('server-split::server-split.admin.fields.max_backups'))
                                    ->placeholder(trans('server-split::server-split.common.unlimited'))
                                    ->formatStateUsing(fn ($state) => filled($state) ? $state : null)
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(fn ($state) => static::unlimitedHelperText($state))
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                TextInput::make('max_databases')
                                    ->label(trans('server-split::server-split.admin.fields.max_databases'))
                                    ->placeholder(trans('server-split::server-split.common.unlimited'))
                                    ->formatStateUsing(fn ($state) => filled($state) ? $state : null)
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(fn ($state) => static::unlimitedHelperText($state))
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                TextInput::make('max_allocations')
                                    ->label(trans('server-split::server-split.admin.fields.max_allocations'))
                                    ->columnSpanFull()
                                    ->placeholder(trans('server-split::server-split.common.unlimited'))
                                    ->formatStateUsing(fn ($state) => filled($state) ? $state : null)
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(fn ($state) => static::unlimitedHelperText($state))
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')
                    ->label(trans('server-split::server-split.admin.fields.user'))
                    ->description(fn (ServerSplitQuota $record) => $record->user?->email)
                    ->searchable(['username', 'email']),
                TextColumn::make('max_servers')
                    ->label(trans('server-split::server-split.admin.fields.max_servers'))
                    ->formatStateUsing(fn ($state) => filled($state) ? $state : trans('server-split::server-split.common.unlimited')),
                TextColumn::make('max_cpu')
                    ->label(trans('server-split::server-split.admin.fields.max_cpu'))
                    ->formatStateUsing(fn ($state) => filled($state) ? $state : trans('server-split::server-split.common.unlimited')),
                TextColumn::make('max_memory')
                    ->label(trans('server-split::server-split.admin.fields.max_memory'))
                    ->formatStateUsing(fn ($state) => filled($state) ? "{$state} MiB" : trans('server-split::server-split.common.unlimited')),
                TextColumn::make('max_disk')
                    ->label(trans('server-split::server-split.admin.fields.max_disk'))
                    ->formatStateUsing(fn ($state) => filled($state) ? "{$state} MiB" : trans('server-split::server-split.common.unlimited')),
                TextColumn::make('max_databases')
                    ->label(trans('server-split::server-split.admin.fields.max_databases'))
                    ->formatStateUsing(fn ($state) => filled($state) ? $state : trans('server-split::server-split.common.unlimited')),
                TextColumn::make('max_backups')
                    ->label(trans('server-split::server-split.admin.fields.max_backups'))
                    ->formatStateUsing(fn ($state) => filled($state) ? $state : trans('server-split::server-split.common.unlimited')),
                TextColumn::make('max_allocations')
                    ->label(trans('server-split::server-split.admin.fields.max_allocations'))
                    ->formatStateUsing(fn ($state) => filled($state) ? $state : trans('server-split::server-split.common.unlimited')),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('3xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->modalWidth('3xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserServerQuotas::route('/'),
        ];
    }

    private static function unlimitedHelperText(mixed $state): ?string
    {
        if (filled($state)) {
            return null;
        }

        return trans('server-split::server-split.admin.fields.currently_unlimited');
    }
}
