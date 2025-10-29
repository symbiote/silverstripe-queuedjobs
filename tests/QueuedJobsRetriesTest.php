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
            // Set defaults for job retry related config
            ->set('job_retry_limit', 1)
            ->set('job_retry_sentinel', 1)
            ->set('job_retry_status', [
                QueuedJob::STATUS_BROKEN => QueuedJob::STATUS_NEW,
                QueuedJob::STATUS_PAUSED => QueuedJob::STATUS_WAIT,
            ]);

        // Enable job retries for this specific job
        Config::modify()->set(QueueTestJob::class, 'max_retry_attempts', 1);

        DBDatetime::set_mock_now('2020-01-01 00:00:00');
    }

    #[DataProvider('jobRetryLimitCasesProvider')]
    public function testJobRetryLimit(int $limit): void
    {
        QueuedJobService::config()
            ->set('job_retry_limit', $limit)
            ->set('job_retry_status', [
                QueuedJob::STATUS_BROKEN => QueuedJob::STATUS_NEW,
            ]);

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

    #[DataProvider('jobRetrySentinelCasesProvider')]
    public function testJobRetrySentinel(int $sentinel, int $expected): void
    {
        QueuedJobService::config()->set('job_retry_sentinel', $sentinel);

        $this->createMockJob(QueuedJob::STATUS_BROKEN, QueuedJob::QUEUED);

        // Move the clock forward to bypass the sentinel limit
        DBDatetime::set_mock_now('2020-01-01 01:00:00');

        QueuedJobService::singleton()->checkJobHealth(QueuedJob::QUEUED);
        $retriedJobs = QueuedJobDescriptor::get()->filter([
            'Implementation' => QueueTestJob::class,
            'JobStatus' => QueuedJob::STATUS_NEW,
        ]);

        $this->assertCount(
            $expected,
            $retriedJobs,
            'We expect a specific number of retried jobs based on configured retry sentinel'
        );
    }

    public static function jobRetrySentinelCasesProvider(): array
    {
        return [
            'no sentinel' => [
                0,
                1,
            ],
            'small sentinel limit (shorter than hour)' => [
                10,
                1,
            ],
            'large sentinel limit (longer than hour)' => [
                100,
                0,
            ],
        ];
    }

    #[DataProvider('jobRetryStatusCasesProvider')]
    public function testJobRetryStatus(array $status, ?string $expected): void
    {
        QueuedJobService::config()->set('job_retry_status', $status);

        $this->createMockJob(QueuedJob::STATUS_BROKEN, QueuedJob::QUEUED);

        // Move the clock forward to bypass the sentinel limit
        DBDatetime::set_mock_now('2020-01-01 01:00:00');

        QueuedJobService::singleton()->checkJobHealth(QueuedJob::QUEUED);

        if ($expected) {
            $retriedJobs = QueuedJobDescriptor::get()->filter([
                'Implementation' => QueueTestJob::class,
                'JobStatus' => $expected,
            ]);

            $this->assertCount(
                1,
                $retriedJobs,
                'We expect the retried job to be in the status which was configured'
            );

            return;
        }
        $retriedJobs = QueuedJobDescriptor::get()->filter([
            'Implementation' => QueueTestJob::class,
            'JobStatus' => QueuedJob::STATUS_BROKEN,
        ]);

        $this->assertCount(
            1,
            $retriedJobs,
            'We expect a no retried jobs as the configured conditions did not match'
        );
    }

    public static function jobRetryStatusCasesProvider(): array
    {
        return [
            'no retry status' => [
                [],
                null,
            ],
            'paused status only' => [
                [
                    QueuedJob::STATUS_PAUSED => QueuedJob::STATUS_WAIT,
                ],
                null,
            ],
            'broken status only' => [
                [
                    QueuedJob::STATUS_BROKEN => QueuedJob::STATUS_NEW,
                ],
                QueuedJob::STATUS_NEW,
            ],
            'broken status (disabled)' => [
                [
                    QueuedJob::STATUS_BROKEN => null,
                ],
                null,
            ],
        ];
    }

    #[DataProvider('jobRetryQueueTypeCasesProvider')]
    public function testJobRetryQueueType(string $queueType, string $jobType, int $expected): void
    {
        $this->createMockJob(QueuedJob::STATUS_BROKEN, $jobType);

        // Move the clock forward to bypass the sentinel limit
        DBDatetime::set_mock_now('2020-01-01 01:00:00');

        QueuedJobService::singleton()->checkJobHealth($queueType);
        $retriedJobs = QueuedJobDescriptor::get()->filter([
            'Implementation' => QueueTestJob::class,
            'JobStatus' => QueuedJob::STATUS_NEW,
        ]);

        $this->assertCount(
            $expected,
            $retriedJobs,
            'We expect a specific number of retried jobs based on which queue does '
            . 'the job go into and which queue gets processed'
        );
    }

    public static function jobRetryQueueTypeCasesProvider(): array
    {
        return [
            'queue type mismatch' => [
                QueuedJob::QUEUED,
                QueuedJob::LARGE,
                0,
            ],
            'queue type match' => [
                QueuedJob::QUEUED,
                QueuedJob::QUEUED,
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
