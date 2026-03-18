<?php

namespace ArctisDev\PlayerCounter\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property string|null $hostname
 * @property string|null $map
 * @property int $current_players
 * @property int|null $max_players
 * @property \Illuminate\Support\Carbon $collected_at
 */
class PlayerCountSnapshot extends Model
{
    protected $fillable = [
        'server_id',
        'hostname',
        'map',
        'current_players',
        'max_players',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'current_players' => 'integer',
            'max_players' => 'integer',
            'collected_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
