<?php

namespace Symbiote\QueuedJobs\Services;

use Psr\Log\AbstractLogger;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Writes log output to a job descriptor
 */
class QueuedJobLogger extends AbstractLogger
{
    /** @var QueuedJob */
    public $job;

    /** @var QueuedJobDescriptor */
    public $jobDescriptor;

    public function __construct(QueuedJob $job, QueuedJobDescriptor $jobDescriptor)
    {
        $this->job = $job;
        $this->jobDescriptor = $jobDescriptor;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function log($level, $message, array $context = array())
    {
        $this->job->addMessage($message);
        $this->jobDescriptor->SavedJobMessages = serialize($this->job->getJobData()->messages);
        $this->jobDescriptor->write();
    }
}
