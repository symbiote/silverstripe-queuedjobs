<?php

namespace App\Queue\Dev;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class Task
 *
 * This dev task is intended to be used for testing the queue runner
 * use it to create test jobs in your queue so you can run them and observe the whole process
 *
 * @package App\Queue\Dev
 */
class Task extends BuildTask
{

    /**
     * @var string
     */
    private static $segment = 'generate-test-jobs-task';

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create test jobs for queue testing purposes.';
    }

    /**
     * @param HTTPRequest $request
     * @throws ValidationException
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        echo '<p>Pass GET param ?total=x to create x jobs.</p>';
        echo '<p>Pass GET param ?type=(2|3) to create jobs in medium|large queues respectively'
            . ' (defaults to large).</p>';

        $total = $request->getVar('total') ?: 0;
        $type = $request->getVar('type') ?: QueuedJob::LARGE;
        $service = QueuedJobService::singleton();

        for ($i = 1; $i <= $total; $i += 1) {
            $randomId = $i . DBDatetime::now()->getTimestamp();
            $job = new Job();
            $job->hydrate((int) $type, (int) $randomId);
            $service->queueJob($job);
        }
    }
}
