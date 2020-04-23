<?php

namespace App\Queue\Dev;

use App\Queue;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Class Job
 *
 * This is a test job which is intended to be used for testing the queue runner
 * the job runs multiple steps and eventually completes
 *
 * @property int $randomID
 * @package App\Queue\Dev
 */
class Job extends Queue\Job
{

    /**
     * @var string|null
     */
    private $type = QueuedJob::QUEUED;

    public function hydrate(int $type, int $randomID): void
    {
        $this->type = $type;
        $this->randomID = $randomID;
        $this->items = [1, 2, 3, 4, 5];
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'Test job';
    }

    /**
     * @return int|null
     */
    public function getJobType(): int
    {
        return (int) $this->type;
    }

    public function getRunAsMemberID(): ?int
    {
        return 0;
    }

    /**
     * @param mixed $item
     */
    public function processItem($item): void
    {
        $this->addMessage(sprintf('Step %d at %s', $item, DBDatetime::now()->Rfc2822()));
        sleep(1);
    }
}
