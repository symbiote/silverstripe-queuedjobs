<?php

namespace SilverStripe\QueuedJobs\Tests\QueuedJobsTest;

use SilverStripe\QueuedJobs\Services\QueuedJobService;
use SilverStripe\Dev\TestOnly;

// stub class to be able to call init from an external context
class TestQJService extends QueuedJobService implements TestOnly
{
    /**
     * Not inherited from QueuedJobService unfortunately...
     * @var array
     */
    private static $dependencies = [
        'queueHandler' => '%$QueueHandler'
    ];

    public function testInit($descriptor)
    {
        return $this->initialiseJob($descriptor);
    }
}
