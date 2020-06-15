<?php

namespace Symbiote\QueuedJobs\Tests\Jobs;

use SilverStripe\Dev\TestOnly;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Test dummy job for capturing data and not doing anything
 */
class TestDummyJob extends AbstractQueuedJob implements TestOnly
{
    /**
     * Parameters passed to the constructor
     *
     * @var array
     */
    public $constructParams;

    public function __construct($params = [])
    {
        parent::__construct();
        $this->constructParams = func_get_args();
    }

    public function getTitle()
    {
        return static::class;
    }

    public function process()
    {
        // no op
    }
}
