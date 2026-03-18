<?php

namespace ArctisDev\PlayerCounter\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property string $player_key
 * @property string $player_name
 * @property string|null $player_source_id
 * @property \Illuminate\Support\Carbon $joined_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $left_at
 * @property int|null $duration_seconds
 */
class PlayerSession extends Model
{
    protected $fillable = [
        'server_id',
        'player_key',
        'player_name',
        'player_source_id',
        'joined_at',
        'last_seen_at',
        'left_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'left_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
