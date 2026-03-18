<?php

namespace ArctisDev\Announcements\Filament\Admin\Resources\Announcements\Pages;

use ArctisDev\Announcements\Filament\Admin\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;
}
