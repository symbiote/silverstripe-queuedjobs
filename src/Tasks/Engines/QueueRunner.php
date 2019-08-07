<?php

namespace Symbiote\QueuedJobs\Tasks\Engines;

use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Runs all jobs in a queue loop in one process
 */
class QueueRunner extends BaseRunner implements TaskRunnerEngine
{
    /**
     * @param string $queue
     */
    public function runQueue($queue)
    {
        if (QueuedJobService::singleton()->isMaintenanceLockActive()) {
            return;
        }

        $service = $this->getService();

        $nextJob = $service->getNextPendingJob($queue);
        $this->logDescriptorStatus($nextJob, $queue);

        if ($nextJob instanceof QueuedJobDescriptor) {
            $service->processJobQueue($queue);
        }
    }
}
