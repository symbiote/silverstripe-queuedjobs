<?php

namespace Symbiote\QueuedJobs\Tests\Jobs;

use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Jobs\DoormanQueuedJobTask;
use Symbiote\QueuedJobs\Services\QueuedJob;
use PHPUnit\Framework\Attributes\DataProvider;

class DoormanQueuedJobTaskTest extends SapphireTest
{
    protected static $fixture_file = 'DoormanQueuedJobTaskTest.yml';

    /**
     * @param string $status
     * @param bool $expected
     */
    #[DataProvider('canRunTaskProvider')]
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
    public static function canRunTaskProvider()
    {
        return [
            [QueuedJob::STATUS_NEW, true],
            [QueuedJob::STATUS_INIT, true],
            [QueuedJob::STATUS_WAIT, true],
            [QueuedJob::STATUS_RUN, false],
        ];
    }

    /**
     * @param string $status
     * @param bool $expected
     */
    #[DataProvider('isCancelledProvider')]
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
    public static function isCancelledProvider()
    {
        return [
            [QueuedJob::STATUS_CANCELLED, true],
            [QueuedJob::STATUS_COMPLETE, true],
            [QueuedJob::STATUS_INIT, false],
        ];
    }
}
