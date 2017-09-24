<?php

namespace Symbiote\QueuedJobs\Tests;

use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\Services\QueuedJobService;

abstract class AbstractTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();

        // The shutdown handler doesn't play nicely with SapphireTest's database handling
        QueuedJobService::config()->set('use_shutdown_function', false);
    }
}
