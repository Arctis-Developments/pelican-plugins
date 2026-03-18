<?php

namespace ArctisDev\ServerSplit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSplitQuota extends Model
{
    protected $table = 'server_split_quotas';

    protected $fillable = [
        'user_id',
        'max_servers',
        'max_cpu',
        'max_memory',
        'max_disk',
        'max_databases',
        'max_backups',
        'max_allocations',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'max_servers' => 'integer',
            'max_cpu' => 'integer',
            'max_memory' => 'integer',
            'max_disk' => 'integer',
            'max_databases' => 'integer',
            'max_backups' => 'integer',
            'max_allocations' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
