<?php

namespace ArctisDev\ServerSplit\Services;

use App\Models\User;
use ArctisDev\ServerSplit\Models\ServerSplitQuota;

class ServerSplitQuotaService
{
    /**
     * @return array{
     *     max_servers: int|null,
     *     max_cpu: int|null,
     *     max_memory: int|null,
     *     max_disk: int|null,
     *     max_databases: int|null,
     *     max_backups: int|null,
     *     max_allocations: int|null
     * }
     */
    public function quota(User $user): array
    {
        $quota = ServerSplitQuota::query()->where('user_id', $user->id)->first();

        return [
            'max_servers' => $this->normalize($quota?->max_servers, config('server-split.defaults.max_servers')),
            'max_cpu' => $this->normalize($quota?->max_cpu, config('server-split.defaults.max_cpu')),
            'max_memory' => $this->normalize($quota?->max_memory, config('server-split.defaults.max_memory')),
            'max_disk' => $this->normalize($quota?->max_disk, config('server-split.defaults.max_disk')),
            'max_databases' => $this->normalize($quota?->max_databases, config('server-split.defaults.max_databases')),
            'max_backups' => $this->normalize($quota?->max_backups, config('server-split.defaults.max_backups')),
            'max_allocations' => $this->normalize($quota?->max_allocations, config('server-split.defaults.max_allocations')),
        ];
    }

    /**
     * @return array{
     *     servers: int,
     *     cpu: int,
     *     memory: int,
     *     disk: int,
     *     databases: int,
     *     backups: int,
     *     allocations: int
     * }
     */
    public function usage(User $user): array
    {
        $usage = $user->servers()
            ->selectRaw('COUNT(*) as servers_count')
            ->selectRaw('COALESCE(SUM(cpu), 0) as cpu_total')
            ->selectRaw('COALESCE(SUM(memory), 0) as memory_total')
            ->selectRaw('COALESCE(SUM(disk), 0) as disk_total')
            ->selectRaw('COALESCE(SUM(database_limit), 0) as database_total')
            ->selectRaw('COALESCE(SUM(backup_limit), 0) as backup_total')
            ->selectRaw('COALESCE(SUM(allocation_limit), 0) as allocation_total')
            ->first();

        return [
            'servers' => (int) ($usage?->servers_count ?? 0),
            'cpu' => (int) ($usage?->cpu_total ?? 0),
            'memory' => (int) ($usage?->memory_total ?? 0),
            'disk' => (int) ($usage?->disk_total ?? 0),
            'databases' => (int) ($usage?->database_total ?? 0),
            'backups' => (int) ($usage?->backup_total ?? 0),
            'allocations' => (int) ($usage?->allocation_total ?? 0),
        ];
    }

    /**
     * @return array{
     *     servers: int|null,
     *     cpu: int|null,
     *     memory: int|null,
     *     disk: int|null,
     *     databases: int|null,
     *     backups: int|null,
     *     allocations: int|null
     * }
     */
    public function remaining(User $user): array
    {
        $quota = $this->quota($user);
        $usage = $this->usage($user);

        return [
            'servers' => $this->remainingValue($quota['max_servers'], $usage['servers']),
            'cpu' => $this->remainingValue($quota['max_cpu'], $usage['cpu']),
            'memory' => $this->remainingValue($quota['max_memory'], $usage['memory']),
            'disk' => $this->remainingValue($quota['max_disk'], $usage['disk']),
            'databases' => $this->remainingValue($quota['max_databases'], $usage['databases']),
            'backups' => $this->remainingValue($quota['max_backups'], $usage['backups']),
            'allocations' => $this->remainingValue($quota['max_allocations'], $usage['allocations']),
        ];
    }

    /**
     * @return array{
     *     quota: array{
     *         max_servers: int|null,
     *         max_cpu: int|null,
     *         max_memory: int|null,
     *         max_disk: int|null,
     *         max_databases: int|null,
     *         max_backups: int|null,
     *         max_allocations: int|null
     *     },
     *     usage: array{
     *         servers: int,
     *         cpu: int,
     *         memory: int,
     *         disk: int,
     *         databases: int,
     *         backups: int,
     *         allocations: int
     *     },
     *     remaining: array{
     *         servers: int|null,
     *         cpu: int|null,
     *         memory: int|null,
     *         disk: int|null,
     *         databases: int|null,
     *         backups: int|null,
     *         allocations: int|null
     *     }
     * }
     */
    public function summary(User $user): array
    {
        return [
            'quota' => $this->quota($user),
            'usage' => $this->usage($user),
            'remaining' => $this->remaining($user),
        ];
    }

    public function hasFiniteQuota(User $user): bool
    {
        return collect($this->quota($user))->contains(fn ($value) => $value !== null);
    }

    private function normalize(mixed $value, mixed $fallback): ?int
    {
        $value ??= $fallback;

        return filled($value) ? (int) $value : null;
    }

    private function remainingValue(?int $limit, int $used): ?int
    {
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $used);
    }
}
