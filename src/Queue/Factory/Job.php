<?php

namespace App\Queue\Factory;

use App\Queue;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class Job
 *
 * generate jobs based on specification
 *
 * @property string $jobClass
 * @package App\Queue\Factory
 */
class Job extends Queue\Job
{

    public function hydrate(string $jobClass, array $items): void
    {
        $this->jobClass = $jobClass;
        $this->items = $items;
    }

    public function getTitle(): string
    {
        return 'Factory job';
    }

    /**
     * @param mixed $item
     * @throws ValidationException
     */
    protected function processItem($item): void
    {
        if (!is_array($item) || count($item) === 0) {
            return;
        }

        $job = Injector::inst()->create($this->jobClass);
        $job->hydrate(array_values($item));
        QueuedJobService::singleton()->queueJob($job);
    }
}
