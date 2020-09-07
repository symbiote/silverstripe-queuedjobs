<?php

namespace Symbiote\QueuedJobs\Util;

use Psr\Log\LogLevel;
use Symbiote\QueuedJobs\Tests\AbstractTest;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\TestQJService;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\TestQueuedJob;

class LoggerTest extends AbstractTest
{
    /**
     * We need the DB for this test
     *
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @return TestQJService
     */
    protected function getService()
    {
        return singleton(TestQJService::class);
    }

    private function getLogger()
    {
        $service = $this->getService();

        // Create a job and add it to the queue
        $job = new TestQueuedJob();
        $service->queueJob($job);

        // Create a logger and set it for the created job
        $logger = new Logger();
        return $logger->setJob($job);
    }

    /**
     * @group logger
     */
    public function testSetLogger()
    {
        $logger = $this->getLogger();
        $this->assertNotNull($logger);
    }

    /**
     * @group logger
     */
    public function testDebug()
    {
        $logger = $this->getLogger();

        $message = 'This is debug message';
        $logger->debug($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::DEBUG), $jobData);
    }

    /**
     * @group logger
     */
    public function testCritical()
    {
        $logger = $this->getLogger();

        $message = 'This is critical message';
        $logger->critical($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::CRITICAL), $jobData);
    }

    /**
     * @group logger
     */
    public function testAlert()
    {
        $logger = $this->getLogger();

        $message = 'This is alert message';
        $logger->alert($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::ALERT), $jobData);
    }

    /**
     * @group logger
     */
    public function testEmergency()
    {
        $logger = $this->getLogger();

        $message = 'This is emergency message';
        $logger->emergency($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::EMERGENCY), $jobData);
    }

    /**
     * @group logger
     */
    public function testWarning()
    {
        $logger = $this->getLogger();

        $message = 'This is warning message';
        $logger->warning($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::WARNING), $jobData);
    }

    /**
     * @group logger
     */
    public function testError()
    {
        $logger = $this->getLogger();

        $message = 'This is error message';
        $logger->error($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::ERROR), $jobData);
    }

    /**
     * @group logger
     */
    public function testNotice()
    {
        $logger = $this->getLogger();

        $message = 'This is notice message';
        $logger->notice($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::NOTICE), $jobData);
    }

    /**
     * @group logger
     */
    public function testInfo()
    {
        $logger = $this->getLogger();

        $message = 'This is info message';
        $logger->info($message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper(LogLevel::INFO), $jobData);
    }

    /**
     * @dataProvider loggerProvider
     * @param $logLevel
     * @group logger
     */
    public function testLog($logLevel)
    {
        $logger = $this->getLogger();

        $message = 'This is info message';
        $logger->log($logLevel, $message);

        $jobData = $logger->getJob()->getJobData()->messages[0];
        $this->assertContains($message, $jobData);
        $this->assertContains(strtoupper($logLevel), $jobData);
    }

    /**
     * @return array
     */
    public function loggerProvider()
    {
        return [
            [LogLevel::WARNING],
            [LogLevel::EMERGENCY],
            [LogLevel::ALERT],
            [LogLevel::CRITICAL],
            [LogLevel::ERROR],
            [LogLevel::NOTICE],
            [LogLevel::INFO],
            [LogLevel::DEBUG],
        ];
    }
}
