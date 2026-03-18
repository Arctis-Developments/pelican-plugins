<?php

namespace ArctisDev\ServerSplit\Services\Servers;

use App\Models\Objects\DeploymentObject;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Deployment\AllocationSelectionService;
use App\Services\Deployment\FindViableNodesService;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\ServerDeletionService;
use App\Services\Servers\VariableValidatorService;
use ArctisDev\ServerSplit\Services\ServerSplitQuotaEnforcer;
use Illuminate\Database\ConnectionInterface;
use Throwable;

class QuotaAwareServerCreationService extends ServerCreationService
{
    public function __construct(
        AllocationSelectionService $allocationSelectionService,
        ConnectionInterface $connection,
        DaemonServerRepository $daemonServerRepository,
        FindViableNodesService $findViableNodesService,
        ServerDeletionService $serverDeletionService,
        VariableValidatorService $validatorService,
        private ServerSplitQuotaEnforcer $quotaEnforcer,
    ) {
        parent::__construct(
            $allocationSelectionService,
            $connection,
            $daemonServerRepository,
            $findViableNodesService,
            $serverDeletionService,
            $validatorService,
        );
    }

    /**
     * @param  array<mixed, mixed>  $data
     *
     * @throws Throwable
     */
    public function handle(array $data, ?DeploymentObject $deployment = null): Server
    {
        if (config('server-split.enforce_server_creation')) {
            $owner = User::query()->findOrFail((int) ($data['owner_id'] ?? 0));

            $this->quotaEnforcer->assertCanProvision($owner, $data);
        }

        return parent::handle($data, $deployment);
    }
}
