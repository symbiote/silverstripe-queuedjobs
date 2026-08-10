<?php

namespace Symbiote\QueuedJobs\Tests\ProcessJobQueueTaskTest;

use Psr\Log\NullLogger;
use SilverStripe\Dev\TestOnly;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class RecordingQueuedJobService extends QueuedJobService implements TestOnly
{
    public array $jobsRun = [];

    public array $queuesRun = [];

    public function runJob($jobId)
    {
        $this->jobsRun[] = $jobId;
        return true;
    }

    public function runQueue($queue)
    {
        $this->queuesRun[] = $queue;
        return true;
    }

    public function getLogger()
    {
        return new NullLogger();
    }
}
