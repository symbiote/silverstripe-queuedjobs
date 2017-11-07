<?php

namespace Symbiote\QueuedJobs\Tests\QueuedJobsTest;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Exception;

class TestExceptingJob extends AbstractQueuedJob implements QueuedJob
{
    private $type = QueuedJob::QUEUED;

    public function __construct($type = null)
    {
        $this->type = QueuedJob::IMMEDIATE;
        $this->times = array();
    }

    public function getJobType()
    {
        return $this->type;
    }

    public function getTitle()
    {
        return "A Test job throwing exceptions";
    }

    public function setup()
    {
        $this->totalSteps = 1;
    }

    public function process()
    {
        throw new Exception("just excepted");
    }
}
