<?php

namespace App\Queue;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Class Job
 *
 * This job represent a generic job which consumes items and each item is processed in one step
 * useful as a template for jobs which need to process multiple items
 * item can be an arbitrary piece of data for example: ID or array
 *
 * @property array $items
 * @property array $remaining
 * @package App\Queue
 */
abstract class Job extends AbstractQueuedJob
{

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->remaining = $this->items;
        $this->totalSteps = count($this->items);
    }

    public function process(): void
    {
        $remaining = $this->remaining;

        // check for trivial case
        if (count($remaining) === 0) {
            $this->isComplete = true;

            return;
        }

        $item = array_shift($remaining);

        $this->processItem($item);

        // update job progress
        $this->remaining = $remaining;
        $this->currentStep += 1;

        // check for job completion
        if (count($remaining) > 0) {
            return;
        }

        $this->isComplete = true;
    }

    /**
     * @param mixed $item
     */
    abstract protected function processItem($item): void;
}
