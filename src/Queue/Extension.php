<?php

namespace App\Queue;

use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extension as BaseExtension;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Throwable;

/**
 * Class Extension
 *
 * @property $this|QueuedJobService $owner
 * @package App\Queue
 */
class Extension extends BaseExtension
{

    /**
     * Extension point in @see QueuedJobService::runJob()
     *
     * @param QueuedJobDescriptor $descriptor
     * @param QueuedJob $job
     * @param Throwable|Exception $e
     */
    public function updateJobDescriptorAndJobOnException(
        QueuedJobDescriptor $descriptor,
        QueuedJob $job,
        Throwable $e
    ): void {
        // capture exception in the messages of the broken job for better debug options
        $job->addMessage(sprintf('%s : %s', ClassInfo::shortName($e), $e->getMessage()));
    }
}
