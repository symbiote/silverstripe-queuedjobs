<?php

namespace Symbiote\QueuedJobs\Tests\QueuedJobsTest;

use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\Dev\TestOnly;

// stub class to be able to call init from an external context
class TestQJService extends QueuedJobService implements TestOnly
{
    /**
     * Not inherited from QueuedJobService unfortunately...
     * @var array
     */
    private $logger;

    private static $dependencies = [
        'queueHandler' => '%$QueueHandler'
    ];

    public function testInit($descriptor)
    {
        return $this->initialiseJob($descriptor);
    }

    public function getLogger() 
    {
        return isset($this->logger) ? $this->logger : $this->logger = new QueuedJobsTest_RecordingLogger();
    }
}
