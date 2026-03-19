<?php

namespace ArctisDev\ServerSplit\Filament\App\Resources;

use App\Enums\TablerIcon;
use App\Models\Server;
use ArctisDev\ServerSplit\Filament\App\Resources\SplitServerResource\Pages\CreateSplitServer;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;

class SplitServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Server;

    protected static ?string $slug = 'server-split';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return trans('server-split::server-split.app.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('server-split::server-split.app.plural_model_label');
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->check();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateSplitServer::route('/'),
        ];
    }
}
