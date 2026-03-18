<?php

namespace ArctisDev\PlayerCounter\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerLogCursor extends Model
{
    protected $fillable = [
        'server_id',
        'path',
        'file_hash',
        'last_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'last_scanned_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
