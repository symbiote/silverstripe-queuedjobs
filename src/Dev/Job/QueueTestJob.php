<?php

namespace Symbiote\QueuedJobs\Dev\Job;

use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Dev\Service\QueueTestService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Intended to support local development, unit tests but also debugging and refining queue settings in general
 *
 * @property int $randomID
 * @property array $times
 */
class QueueTestJob extends AbstractQueuedJob
{
    private const int TOTAL_STEPS = 5;

    public function hydrate(int $randomID): void
    {
        $this->randomID = $randomID;
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function getTitle(): string
    {
        return 'Queue test job';
    }

    public function setup(): void
    {
        $this->times = [];
        $this->totalSteps = QueueTestJob::TOTAL_STEPS;
    }

    /**
     * Very basic process for testing jobs
     *
     * @return void
     */
    public function process(): void
    {
        $now = DBDatetime::now()->Rfc2822();

        $times = $this->times;
        $times[] = $now;
        $this->times = $times;

        $this->addMessage('Updated time to ' . $now);

        // Wait a bit so we can simulate processing
        QueueTestService::singleton()->sleep(1);

        // make sure we're incrementing
        $this->currentStep += 1;

        // and checking whether we're complete
        if ($this->currentStep < 5) {
            return;
        }

        $this->isComplete = true;
    }
}
