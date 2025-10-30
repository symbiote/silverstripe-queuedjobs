<?php

namespace Symbiote\QueuedJobs\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
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

    #[DataProvider('maxRetryAttemptsCasesProvider')]
    public function testMaxRetryAttempts(int $jobAttempts, int $maxAttempts, int $expected): void
    {
        Config::modify()->set(QueueTestJob::class, 'max_retry_attempts', $maxAttempts);

        $jobDescriptor = $this->createMockJob(QueuedJob::STATUS_BROKEN, QueuedJob::QUEUED);
        $jobDescriptor->RetryCount = $jobAttempts;
        $jobDescriptor->write();

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

    public static function maxRetryAttemptsCasesProvider(): array
    {
        return [
            'disabled on job level' => [
                0,
                0,
                0,
            ],
            'enabled on job level (valid retry)' => [
                0,
                1,
                1,
            ],
            'enabled on job level (invalid retry)' => [
                1,
                1,
                0,
            ],
        ];
    }

    #[DataProvider('initialRetryDelayCasesProvider')]
    public function testInitialRetryDelay(int $delay, ?string $expected): void
    {
        Config::modify()->set(QueueTestJob::class, 'initial_retry_delay', $delay);

        $this->createMockJob(QueuedJob::STATUS_BROKEN, QueuedJob::QUEUED);

        // Move the clock forward to bypass the sentinel limit
        DBDatetime::set_mock_now('2020-01-01 01:00:00');

        QueuedJobService::singleton()->checkJobHealth(QueuedJob::QUEUED);
        $retriedJobs = QueuedJobDescriptor::get()->filter([
            'Implementation' => QueueTestJob::class,
            'JobStatus' => QueuedJob::STATUS_NEW,
        ]);

        $this->assertCount(1, $retriedJobs, 'We expect a successful job retry');

        /** @var QueuedJobDescriptor $jobDescriptor */
        $jobDescriptor = $retriedJobs->first();

        $this->assertEquals($expected, $jobDescriptor->StartAfter, 'We expect a specific retry schedule');
    }

    public static function initialRetryDelayCasesProvider(): array
    {
        return [
            'no retry delay (start immediately)' => [
                0,
                null,
            ],
            'short retry delay' => [
                60,
                '2020-01-01 01:01:00',
            ],
            'long retry delay' => [
                600,
                '2020-01-01 01:10:00',
            ],
            'very long retry delay' => [
                3600,
                '2020-01-01 02:00:00',
            ],
        ];
    }

    #[DataProvider('retryFalloffMultiplierCasesProvider')]
    public function testRetryFalloffMultiplier(int $multiplier, int $jobAttempts, string $expected): void
    {
        Config::modify()
            ->set(QueueTestJob::class, 'initial_retry_delay', 60)
            ->set(QueueTestJob::class, 'max_retry_attempts', 3)
            ->set(QueueTestJob::class, 'retry_falloff_multiplier', $multiplier);

        $jobDescriptor = $this->createMockJob(QueuedJob::STATUS_BROKEN, QueuedJob::QUEUED);
        $jobDescriptor->RetryCount = $jobAttempts;
        $jobDescriptor->write();

        // Move the clock forward to bypass the sentinel limit
        DBDatetime::set_mock_now('2020-01-01 01:00:00');

        QueuedJobService::singleton()->checkJobHealth(QueuedJob::QUEUED);
        $retriedJobs = QueuedJobDescriptor::get()->filter([
            'Implementation' => QueueTestJob::class,
            'JobStatus' => QueuedJob::STATUS_NEW,
        ]);

        $this->assertCount(1, $retriedJobs, 'We expect a successful job retry');

        /** @var QueuedJobDescriptor $jobDescriptor */
        $jobDescriptor = $retriedJobs->first();

        $this->assertEquals($expected, $jobDescriptor->StartAfter, 'We expect a specific retry schedule');
    }

    public static function retryFalloffMultiplierCasesProvider(): array
    {
        return [
            'no multiplier (first attempt)' => [
                1,
                0,
                '2020-01-01 01:01:00',
            ],
            'no multiplier (second attempt)' => [
                1,
                1,
                '2020-01-01 01:01:00',
            ],
            'no multiplier (third attempt)' => [
                1,
                2,
                '2020-01-01 01:01:00',
            ],
            'with multiplier (first attempt)' => [
                2,
                0,
                '2020-01-01 01:01:00',
            ],
            'with multiplier (second attempt)' => [
                2,
                1,
                '2020-01-01 01:02:00',
            ],
            'with multiplier (third attempt)' => [
                2,
                2,
                '2020-01-01 01:04:00',
            ],
        ];
    }

    #[DataProvider('retryFalloffMultiplierVarianceCasesProvider')]
    public function testRetryFalloffMultiplierVariance(
        float $variance,
        int $jobAttempts,
        bool $negativeOffset,
        string $expected
    ): void {
        // Register a non-randomised service so we can have reliable tests running
        $service = new class extends QueuedJobService {
            public bool $negativeOffset = false;

            protected function getJobRetryRandomMultiplierKey(int $baseValue, int $offsetValue): int
            {
                // This returns just the extreme values so we can have somewhat representative tests
                if ($this->negativeOffset) {
                    return $baseValue - $offsetValue;
                }

                return $baseValue + $offsetValue;
            }
        };

        $service->negativeOffset = $negativeOffset;

        Injector::inst()->registerService($service, QueuedJobService::class);

        Config::modify()
            ->set(QueueTestJob::class, 'initial_retry_delay', 3600)
            ->set(QueueTestJob::class, 'max_retry_attempts', 3)
            ->set(QueueTestJob::class, 'retry_falloff_multiplier', 1)
            ->set(QueueTestJob::class, 'retry_falloff_multiplier_variance', $variance);

        $jobDescriptor = $this->createMockJob(QueuedJob::STATUS_BROKEN, QueuedJob::QUEUED);
        $jobDescriptor->RetryCount = $jobAttempts;
        $jobDescriptor->write();

        // Move the clock forward to bypass the sentinel limit
        DBDatetime::set_mock_now('2020-01-01 01:00:00');

        QueuedJobService::singleton()->checkJobHealth(QueuedJob::QUEUED);
        $retriedJobs = QueuedJobDescriptor::get()->filter([
            'Implementation' => QueueTestJob::class,
            'JobStatus' => QueuedJob::STATUS_NEW,
        ]);

        $this->assertCount(1, $retriedJobs, 'We expect a successful job retry');

        /** @var QueuedJobDescriptor $jobDescriptor */
        $jobDescriptor = $retriedJobs->first();

        $this->assertEquals($expected, $jobDescriptor->StartAfter, 'We expect a specific retry schedule');
    }

    public static function retryFalloffMultiplierVarianceCasesProvider(): array
    {
        return [
            'no variance (first attempt)' => [
                0,
                0,
                false,
                '2020-01-01 02:00:00',
            ],
            'no variance (second attempt)' => [
                0,
                1,
                false,
                '2020-01-01 02:00:00',
            ],
            'no variance (third attempt)' => [
                0,
                2,
                false,
                '2020-01-01 02:00:00',
            ],
            'with variance (first attempt, negative offset)' => [
                0.2,
                0,
                true,
                '2020-01-01 02:00:00',
            ],
            'no variance (second attempt, negative offset)' => [
                0.2,
                1,
                true,
                '2020-01-01 01:48:00',
            ],
            'no variance (third attempt, negative offset)' => [
                0.2,
                2,
                true,
                '2020-01-01 01:38:24',
            ],
            'with variance (first attempt, positive offset)' => [
                0.2,
                0,
                false,
                '2020-01-01 02:00:00',
            ],
            'no variance (second attempt, positive offset)' => [
                0.2,
                1,
                false,
                '2020-01-01 02:12:00',
            ],
            'no variance (third attempt, positive offset)' => [
                0.2,
                2,
                false,
                '2020-01-01 02:26:24',
            ],
        ];
    }

    private function createMockJob(string $status, string $queue, bool $skipWrite = false): QueuedJobDescriptor
    {
        $jobDescriptor = QueuedJobDescriptor::create();
        $jobDescriptor->Implementation = QueueTestJob::class;
        $jobDescriptor->JobType = $queue;
        $jobDescriptor->JobStatus = $status;

        if ($skipWrite) {
            return $jobDescriptor;
        }

        $jobDescriptor->write();

        return $jobDescriptor;
    }
}
