<?php

namespace ArctisDev\Announcements;

use App\Livewire\AlertBanner;
use ArctisDev\Announcements\Models\Announcement;
use Filament\Contracts\Plugin;
use Filament\Panel;

class AnnouncementsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'announcements';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverResources(plugin_path($this->getId(), "src/Filament/$id/Resources"), "ArctisDev\\Announcements\\Filament\\$id\\Resources");
    }

    public function boot(Panel $panel): void
    {
        foreach (Announcement::all() as $announcement) {
            if (!$announcement->shouldDisplay($panel)) {
                continue;
            }

            AlertBanner::make('announcement_' . $announcement->id)
                ->title($announcement->title)
                ->body($announcement->body)
                ->status($announcement->type)
                ->send();
        }
    }
}
