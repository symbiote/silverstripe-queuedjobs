<?php

namespace Symbiote\QueuedJobs\Tests\Jobs;

use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Jobs\DoormanQueuedJobTask;
use Symbiote\QueuedJobs\Services\QueuedJob;

class DoormanQueuedJobTaskTest extends SapphireTest
{
    protected static $fixture_file = 'DoormanQueuedJobTaskTest.yml';

    /**
     * @dataProvider canRunTaskProvider
     * @param string $status
     * @param bool $expected
     */
    public function testCanRunTask($status, $expected)
    {
        /** @var QueuedJobDescriptor $descriptor */
        $descriptor = $this->objFromFixture(QueuedJobDescriptor::class, 'dummy_job');
        $descriptor->JobStatus = $status;
        $descriptor->write();

        $task = new DoormanQueuedJobTask($descriptor);
        $this->assertSame($expected, $task->canRunTask());
    }

    /**
     * @return array[]
     */
    public function canRunTaskProvider()
    {
        return [
            [QueuedJob::STATUS_NEW, true],
            [QueuedJob::STATUS_INIT, true],
            [QueuedJob::STATUS_WAIT, true],
            [QueuedJob::STATUS_RUN, false],
        ];
    }

    /**
     * @dataProvider isCancelledProvider
     * @param string $status
     * @param bool $expected
     */
    public function testIsCancelled($status, $expected)
    {
        /** @var QueuedJobDescriptor $descriptor */
        $descriptor = $this->objFromFixture(QueuedJobDescriptor::class, 'dummy_job');
        $descriptor->JobStatus = $status;
        $descriptor->write();

        $task = new DoormanQueuedJobTask($descriptor);
        $this->assertSame($expected, $task->isCancelled());
    }

    /**
     * @return array[]
     */
    public function isCancelledProvider()
    {
        return [
            [QueuedJob::STATUS_CANCELLED, true],
            [QueuedJob::STATUS_COMPLETE, true],
            [QueuedJob::STATUS_INIT, false],
        ];
    }
}
