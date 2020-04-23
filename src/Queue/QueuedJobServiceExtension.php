<?php

namespace App\Extensions\QueuedJobs;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Throwable;

/**
 * Class QueuedJobServiceExtension
 *
 * @property $this|QueuedJobService $owner
 * @package App\Extensions\QueuedJobs
 */
class QueuedJobServiceExtension extends Extension
{
    /**
     *  Retry delay configuration
     * <retry_attempt> => <time_delay>
     */
    const RETRY_INTERVALS = [
        1 => 60, // 1 minute
        2 => 4 * 60, // 4 minutes
        3 => 10 * 60, // 10 minutes
        4 => 30 * 60, // 30 minutes
        5 => 60 * 60, // 1 hour
    ];

    /**
     * This extension point is invoked by QueuedJobService any time an Exception is thrown as part of runJob().
     * QueuedJobService (prior to invoking this extension) has set the JobStatus to STATUS_BROKEN - this extension point
     * grants us an opportunity to reset this JobStatus back to STATUS_NEW (so that the job will retry) if we have met
     * certain criteria.
     * extension point in @see QueuedJobService::runJob()
     *
     * @param QueuedJobDescriptor|QueuedJobDescriptorExtension $descriptor
     * @param QueuedJob $job
     * @param Throwable|Exception $e
     */
    public function updateJobDescriptorAndJobOnException(
        QueuedJobDescriptor $descriptor,
        QueuedJob $job,
        Throwable $e
    ): void {
        $descriptor->FailedAttempts += 1;

        // If the Job has already tried to process our maximum number of times, then leave it as STATUS_BROKEN.
        if ($descriptor->FailedAttempts > QueuedJobDescriptor::config()->get('max_retry_attempts')) {
            // The job no longer gets retried - add specific logging here

            return;
        }

        // If the Job is not a type that supports retrying, then leave it as STATUS_BROKEN.
        if (!in_array(get_class($job), QueuedJobDescriptor::config()->get('allowed_retry_jobs'))) {
            // The job doesn't get retried at all - add specific logging here

            return;
        }

        // We should retry this Job.
        $descriptor->JobStatus = QueuedJob::STATUS_NEW;

        // add a random delay so we wouldn't retry the job immediately, but with some spread
        // this lowers the chance of multiple jobs accessing the same data, thus preventing conflicts
        $descriptor->StartAfter = static::randomiseStartAfter($descriptor->FailedAttempts);

        // release the job lock so it could be picked up again
        $descriptor->Worker = null;
        $descriptor->Expiry = null;
    }

    /**
     * @param int $attempts
     * @return int
     */
    public static function randomiseStartAfter(int $attempts): int
    {
        $now = DBDatetime::now();
        $time = $now->getTimestamp();

        $intervals = static::RETRY_INTERVALS;

        if (!array_key_exists($attempts, $intervals)) {
            return $time;
        }

        $previousDuration = 0;
        $currentDuration = 0;

        foreach ($intervals as $retries => $duration) {
            if ($retries === $attempts) {
                $currentDuration = $duration;

                break;
            }

            $previousDuration += $duration;
        }

        $delay = mt_rand($previousDuration, $previousDuration + $currentDuration);
        $time += $delay;

        return $time;
    }
}
