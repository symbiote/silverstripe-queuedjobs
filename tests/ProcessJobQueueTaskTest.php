<?php

namespace Symbiote\QueuedJobs\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Tasks\ProcessJobQueueTask;
use Symbiote\QueuedJobs\Tests\ProcessJobQueueTaskTest\RecordingQueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

class ProcessJobQueueTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(new RecordingQueuedJobService(), QueuedJobService::class);
    }

    public static function provideJobOption(): array
    {
        return [
            'numeric ID' => [
                'job' => '734803',
                'expectedId' => 734803,
            ],
            'job cache file name' => [
                'job' => 'queuedjob-734803',
                'expectedId' => 734803,
            ],
            'ID with a leading dash' => [
                'job' => '-734803',
                'expectedId' => 734803,
            ],
            'ID with multiple dashes in the prefix' => [
                'job' => 'queued-job-prefix-734803',
                'expectedId' => 734803,
            ],
        ];
    }

    #[DataProvider('provideJobOption')]
    public function testJobOption(string $job, int $expectedId): void
    {
        $service = QueuedJobService::singleton();
        $exitCode = $this->runTask(['--job' => $job]);
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame([$expectedId], $service->jobsRun);
        $this->assertSame([], $service->queuesRun);
    }

    public function testInvalidJobOption(): void
    {
        $service = QueuedJobService::singleton();
        $exitCode = $this->runTask(['--job' => 'not-a-job']);
        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertSame([], $service->jobsRun);
        $this->assertSame([], $service->queuesRun);
    }

    public function testNoJobOptionRunsQueue(): void
    {
        $service = QueuedJobService::singleton();
        $exitCode = $this->runTask([]);
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame([], $service->jobsRun);
        $this->assertSame([QueuedJob::QUEUED], $service->queuesRun);
    }

    private function runTask(array $arguments): int
    {
        $task = new ProcessJobQueueTask();
        $input = new ArrayInput($arguments, new InputDefinition($task->getOptions()));
        $input->setInteractive(false);
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: new BufferedOutput());

        return $task->run($input, $output);
    }
}
