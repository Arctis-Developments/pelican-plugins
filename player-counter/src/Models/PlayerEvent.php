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
 * @property string $event_type
 * @property string|null $message
 * @property string|null $ip_address
 * @property string $fingerprint
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class PlayerEvent extends Model
{
    public const TYPE_UUID = 'uuid';
    public const TYPE_JOIN = 'join';
    public const TYPE_LEAVE = 'leave';
    public const TYPE_CHAT = 'chat';
    public const TYPE_COMMAND = 'command';

    protected $fillable = [
        'server_id',
        'player_key',
        'player_name',
        'player_source_id',
        'event_type',
        'message',
        'ip_address',
        'fingerprint',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
