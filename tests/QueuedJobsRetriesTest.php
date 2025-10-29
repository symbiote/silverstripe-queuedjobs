<?php

namespace Symbiote\QueuedJobs\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Dev\Job\QueueTestJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class QueuedJobsRetriesTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        QueuedJobService::config()
            // Disable the immediate queue processing to avoid unexpected issues
            ->set('use_shutdown_function', false)
            ->set('job_retry_sentinel', 1);

        DBDatetime::set_mock_now('2020-01-01 00:00:00');
    }

    #[DataProvider('jobRetryLimitCasesProvider')]
    public function testJobRetryLimit(int $limit)
    {
        QueuedJobService::config()
            ->set('job_retry_limit', $limit)
            ->set('job_retry_status', [
                QueuedJob::STATUS_BROKEN => QueuedJob::STATUS_NEW,
            ]);
        Config::modify()->set(QueueTestJob::class, 'max_retry_attempts', 1);

        $this->createMockJob(QueuedJob::STATUS_BROKEN, QueuedJob::QUEUED);

        // Move the clock forward to bypass the sentinel limit
        DBDatetime::set_mock_now('2020-01-01 01:00:00');

        QueuedJobService::singleton()->checkJobHealth(QueuedJob::QUEUED);
        $retriedJobs = QueuedJobDescriptor::get()->filter([
            'Implementation' => QueueTestJob::class,
            'JobStatus' => QueuedJob::STATUS_NEW,
        ]);

        $this->assertCount(
            $limit,
            $retriedJobs,
            'We expect a specific number of retried jobs based on configured retry limit'
        );
    }

    public static function jobRetryLimitCasesProvider(): array
    {
        return [
            'job retries disabled' => [
                0,
            ],
            'job retries enabled' => [
                1,
            ],
        ];
    }

    private function createMockJob(string $status, string $queue): void
    {
        $jobDescriptor = QueuedJobDescriptor::create();
        $jobDescriptor->Implementation = QueueTestJob::class;
        $jobDescriptor->JobType = $queue;
        $jobDescriptor->JobStatus = $status;
        $jobDescriptor->write();
    }
}
