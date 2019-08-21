<?php

namespace Symbiote\QueuedJobs\Interfaces;

/**
 * Interface UserContextInterface
 * used for jobs which need to specify which member to log in when running the jobs
 *
 * example cases:
 * initial site migration (special user needs to be used at all times)
 * jobs that require no user to be logged in
 *
 * @package Symbiote\QueuedJobs\Interfaces
 */
interface UserContextInterface
{
    /**
     * Specifies what user ID should be when running the job
     * valid values:
     * null - (default) - run the job as current user
     * 0 - run the job without a user
     * greater than zero - run the job as a specific user
     *
     * This is useful in situations like:
     * - a job needs to always run without a user (like a static cache job)
     * - a job needs to run as a specific user (for example data migration job)
     *
     * Note that this value can be overridden in the @see QueuedJobService::queueJob()
     *
     * @return int|null
     */
    public function getRunAsMemberID();
}
