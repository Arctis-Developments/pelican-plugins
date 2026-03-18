<?php

namespace ArctisDev\ServerSplit\Services;

use App\Exceptions\DisplayException;
use App\Models\User;
use Illuminate\Support\Arr;

class ServerSplitQuotaEnforcer
{
    public function __construct(private ServerSplitQuotaService $quotaService) {}

    /** @param array<string, mixed> $payload */
    public function assertCanProvision(User $user, array $payload): void
    {
        $summary = $this->quotaService->summary($user);
        $quota = $summary['quota'];
        $usage = $summary['usage'];
        $remaining = $summary['remaining'];

        if ($quota['max_servers'] !== null && ($usage['servers'] + 1) > $quota['max_servers']) {
            throw new DisplayException(trans('server-split::server-split.exceptions.server_limit_reached'));
        }

        $this->assertResource(
            quota: $quota['max_cpu'],
            used: $usage['cpu'],
            remaining: $remaining['cpu'],
            requested: (int) Arr::get($payload, 'cpu', 0),
            resource: trans('server-split::server-split.labels.cpu'),
        );

        $this->assertResource(
            quota: $quota['max_memory'],
            used: $usage['memory'],
            remaining: $remaining['memory'],
            requested: (int) Arr::get($payload, 'memory', 0),
            resource: trans('server-split::server-split.labels.memory'),
        );

        $this->assertResource(
            quota: $quota['max_disk'],
            used: $usage['disk'],
            remaining: $remaining['disk'],
            requested: (int) Arr::get($payload, 'disk', 0),
            resource: trans('server-split::server-split.labels.disk'),
        );

        $this->assertCountLimit(
            quota: $quota['max_databases'],
            used: $usage['databases'],
            remaining: $remaining['databases'],
            requested: (int) Arr::get($payload, 'database_limit', 0),
            resource: trans('server-split::server-split.labels.databases'),
        );

        $this->assertCountLimit(
            quota: $quota['max_backups'],
            used: $usage['backups'],
            remaining: $remaining['backups'],
            requested: (int) Arr::get($payload, 'backup_limit', 0),
            resource: trans('server-split::server-split.labels.backups'),
        );

        $this->assertCountLimit(
            quota: $quota['max_allocations'],
            used: $usage['allocations'],
            remaining: $remaining['allocations'],
            requested: (int) Arr::get($payload, 'allocation_limit', 0),
            resource: trans('server-split::server-split.labels.allocations'),
        );
    }

    private function assertResource(?int $quota, int $used, ?int $remaining, int $requested, string $resource): void
    {
        if ($quota === null) {
            return;
        }

        if ($requested <= 0) {
            throw new DisplayException(trans('server-split::server-split.exceptions.unlimited_resource_not_allowed', [
                'resource' => $resource,
            ]));
        }

        if (($used + $requested) > $quota) {
            throw new DisplayException(trans('server-split::server-split.exceptions.resource_limit_reached', [
                'resource' => $resource,
                'requested' => $requested,
                'remaining' => max(0, (int) $remaining),
            ]));
        }
    }

    private function assertCountLimit(?int $quota, int $used, ?int $remaining, int $requested, string $resource): void
    {
        if ($quota === null) {
            return;
        }

        if ($requested < 0) {
            throw new DisplayException(trans('server-split::server-split.exceptions.invalid_feature_limit', [
                'resource' => $resource,
            ]));
        }

        if (($used + $requested) > $quota) {
            throw new DisplayException(trans('server-split::server-split.exceptions.resource_limit_reached', [
                'resource' => $resource,
                'requested' => $requested,
                'remaining' => max(0, (int) $remaining),
            ]));
        }
    }
}
