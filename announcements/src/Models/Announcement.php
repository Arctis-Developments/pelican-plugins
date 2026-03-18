<?php

namespace ArctisDev\Announcements\Models;

use Carbon\Carbon;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property string $type
 * @property string[]|null $panels
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'type',
        'panels',
        'valid_from',
        'valid_to',
    ];

    protected $attributes = [
        'panels' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'panels' => 'array',
        ];
    }

    public function shouldDisplay(Panel $panel): bool
    {
        if (!empty($this->panels) && !in_array($panel->getId(), $this->panels)) {
            return false;
        }

        if (!empty($this->valid_from) && now() < $this->valid_from) {
            return false;
        }

        if (!empty($this->valid_to) && now() > $this->valid_to) {
            return false;
        }

        return true;
    }
}
