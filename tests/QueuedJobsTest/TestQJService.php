<?php

namespace SilverStripe\QueuedJobs\Tests;

use SilverStripe\QueuedJobs\Services\QueuedJobService;

// stub class to be able to call init from an external context
class TestQJService extends QueuedJobService
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
