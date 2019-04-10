<?php

namespace Symbiote\QueuedJobs\Services;

use Monolog\Handler\AbstractProcessingHandler;
use SilverStripe\Core\Injector\Injectable;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Writes log output to a job descriptor
 */
class QueuedJobHandler extends AbstractProcessingHandler
{
    use Injectable;

    /** @var QueuedJob */
    protected $job;

    /** @var QueuedJobDescriptor */
    protected $jobDescriptor;

    public function __construct(QueuedJob $job, QueuedJobDescriptor $jobDescriptor)
    {
        $this->job = $job;
        $this->jobDescriptor = $jobDescriptor;
    }

    /**
     * @return QueuedJob
     */
    public function getJob(): QueuedJob
    {
        return $this->job;
    }

    /**
     * @return QueuedJobDescriptor
     */
    public function getJobDescriptor(): QueuedJobDescriptor
    {
        return $this->jobDescriptor;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function write(array $record)
    {
        $this->job->addMessage($record['message']);
        $this->jobDescriptor->SavedJobMessages = serialize($this->job->getJobData()->messages);
        $this->jobDescriptor->write();
    }
}
