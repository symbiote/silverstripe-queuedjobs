<?php

namespace Symbiote\QueuedJobs\Services;

use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * execute jobs immediately in the current request context
 *
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ImmediateQueueHandler
{
    /**
     * @var array
     */
    private static $dependencies = [
        'queuedJobService' => '%$' . QueuedJobService::class,
    ];

    /**
     * @var QueuedJobService
     */
    public $queuedJobService;

    /**
     * @param QueuedJobDescriptor $job
     */
    public function startJobOnQueue(QueuedJobDescriptor $job)
    {
        $this->queuedJobService->runJob($job->ID);
    }

    /**
     * @param QueuedJobDescriptor $job
     * @param string $date
     */
    public function scheduleJob(QueuedJobDescriptor $job, $date)
    {
        $this->queuedJobService->runJob($job->ID);
    }
}
