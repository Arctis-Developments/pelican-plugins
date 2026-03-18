<?php

namespace ArctisDev\PlayerCounter\Commands;

use App\Models\Server;
use ArctisDev\PlayerCounter\Services\MinecraftJavaPlayerLogRecorder;
use ArctisDev\PlayerCounter\Services\PlayerQueryService;
use Illuminate\Console\Command;

class PollPlayerHistoryCommand extends Command
{
    protected $signature = 'player-counter:poll-history {--server=* : Limit polling to one or more server IDs}';

    protected $description = 'Collect player count snapshots and player sessions for query-enabled servers.';

    public function handle(PlayerQueryService $queryService, MinecraftJavaPlayerLogRecorder $logRecorder): int
    {
        $serverIds = collect((array) $this->option('server'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $query = Server::query()->with(['allocation', 'egg']);

        if ($serverIds->isNotEmpty()) {
            $query->whereIn('id', $serverIds->all());
        }

        $processed = 0;
        $historyRecorded = 0;
        $logsRecorded = 0;

        $query->chunkById(100, function ($servers) use ($queryService, $logRecorder, &$processed, &$historyRecorded, &$logsRecorded) {
            foreach ($servers as $server) {
                ++$processed;

                if (!$queryService->canQuery($server)) {
                    continue;
                }

                if ($queryService->query($server)) {
                    ++$historyRecorded;
                }

                if ($logRecorder->record($server)) {
                    ++$logsRecorded;
                }
            }
        });

        $this->components->info("Processed {$processed} servers, recorded query history for {$historyRecorded} and parsed logs for {$logsRecorded}.");

        return self::SUCCESS;
    }
}
