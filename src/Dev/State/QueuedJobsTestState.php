<?php

namespace Symbiote\QueuedJobs\Dev\State;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class QueuedJobsTestState implements TestState
{
    /**
     * Never run the shutdown handler during unit tests
     *
     * @param SapphireTest $test
     */
    public function setUp(SapphireTest $test)
    {

        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);
    }

    public function tearDown(SapphireTest $test)
    {
        // noop
    }

    public function setUpOnce($class)
    {
        // noop
    }

    public function tearDownOnce($class)
    {
        // noop
    }
}
