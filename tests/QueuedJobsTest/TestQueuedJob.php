<?php

namespace Symbiote\QueuedJobs\Tests\QueuedJobsTest;

use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

class TestQueuedJob extends AbstractQueuedJob implements QueuedJob
{
    private $type = QueuedJob::QUEUED;

    public function __construct($type = null)
    {
        if ($type) {
            $this->type = $type;
        }
        $this->times = array();
    }

    public function getJobType()
    {
        return $this->type;
    }

    public function getTitle()
    {
        return "A Test job";
    }

    public function setup()
    {
        $this->totalSteps = 5;
    }

    public function process()
    {
        $times = $this->times;
        // needed due to quirks with __set
        $time = DBDatetime::now()->Rfc2822();
        $times[] = $time;
        $this->times = $times;

        $this->addMessage('Updated time to ' . $time);
        sleep(1);

        // make sure we're incrementing
        $this->currentStep++;

        // and checking whether we're complete
        if ($this->currentStep == 5) {
            $this->isComplete = true;
        }
    }
}
