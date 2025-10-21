<?php

namespace Symbiote\QueuedJobs\Dev\Service;

use SilverStripe\Core\Injector\Injectable;

/**
 * Utility features intended to support locale development and debugging
 */
class QueueTestService
{

    use Injectable;

    public function isUnitTestExecutionActive(): bool
    {
        return defined('UNIT_TESTS_EXECUTION') && UNIT_TESTS_EXECUTION;
    }

    public function sleep(int $seconds): void
    {
        // We never want to execute sleep() during a test run as it only slows our test suit execution
        if ($this->isUnitTestExecutionActive()) {
            return;
        }

        sleep($seconds);
    }

}
