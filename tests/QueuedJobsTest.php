<?php

namespace Symbiote\QueuedJobs\Tests;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\TestExceptingJob;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\TestQJService;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\TestQueuedJob;

/**
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 */
class QueuedJobsTest extends AbstractTest
{
    /**
     * We need the DB for this test
     *
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        parent::setUp();

        // Two restarts are allowed per job
        Config::modify()->set(QueuedJobService::class, 'stall_threshold', 2);

        // Allow large memory limit in cases of integration tests
        Config::modify()->set(QueuedJobService::class, 'memory_limit', 2 * 1024 * 1024 * 1024);

        Injector::inst()->registerService($this->createMock(LoggerInterface::class), LoggerInterface::class);

        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);

        DBDatetime::set_mock_now('2016-01-01 16:00:00');
    }

    protected function tearDown()
    {
        parent::tearDown();

        DBDatetime::clear_mock_now();
    }

    /**
     * @return TestQJService
     */
    protected function getService()
    {
        return singleton(TestQJService::class);
    }

    public function testQueueJob()
    {
        $svc = $this->getService();

        // lets create a new job and add it tio the queue
        $job = new TestQueuedJob();
        $jobId = $svc->queueJob($job);
        $list = $svc->getJobList();

        $this->assertCount(1, $list);

        $myJob = null;
        foreach ($list as $job) {
            if ($job->Implementation == TestQueuedJob::class) {
                $myJob = $job;
                break;
            }
        }

        $this->assertNotNull($myJob);
        $this->assertGreaterThan(0, $jobId);
        $this->assertEquals(TestQueuedJob::class, $myJob->Implementation);
        $this->assertNotNull($myJob->SavedJobData);
    }

    public function testJobRunAs()
    {
        $svc = $this->getService();
        $list = $svc->getJobList();
        foreach ($list as $job) {
            $job->delete();
        }

        $this->logInWithPermission('DUMMY');

        // lets create a new job and add it tio the queue
        $job = new TestQueuedJob();
        $job->runningAs = "DUMMY";
        $jobId = $svc->queueJob($job);
        $list = $svc->getJobList();

        $myJob = $list->First();

        $this->assertEquals("DUMMY@example.org", $myJob->RunAs()->Email);
    }

    public function testQueueSignature()
    {
        $svc = $this->getService();

        // lets create a new job and add it tio the queue
        $job = new TestQueuedJob();
        $jobId = $svc->queueJob($job);

        $newJob = new TestQueuedJob();
        $newId = $svc->queueJob($newJob);

        $this->assertEquals($jobId, $newId);

        // now try another, but with different params
        $newJob = new TestQueuedJob();
        $newJob->randomParam = 'stuff';
        $newId = $svc->queueJob($newJob);

        $this->assertNotEquals($jobId, $newId);
    }

    public function testProcessJob()
    {
        $job = new TestQueuedJob();
        $job->setup();
        $job->process();
        // we should now have some  data
        $data = $job->getJobData();
        $this->assertNotNull($data->messages);
        $this->assertFalse($data->isComplete);

        $jd = $data->jobData;
        $this->assertTrue(isset($jd->times));
        $this->assertCount(1, $jd->times);

        // now take the 'saved' data and try restoring the job
    }

    public function testResumeJob()
    {
        $job = new TestQueuedJob();
        $job->setup();
        $job->process();
        // we should now have some  data
        $data = $job->getJobData();

        // so create a new job and restore it from this data

        $job = new TestQueuedJob();
        $job->setup();

        $job->setJobData($data->totalSteps, $data->currentStep, $data->isComplete, $data->jobData, $data->messages);
        $job->process();

        $data = $job->getJobData();
        $this->assertFalse($data->isComplete);
        $jd = $data->jobData;
        $this->assertTrue(isset($jd->times));
        $this->assertCount(2, $jd->times);
    }

    public function testInitialiseJob()
    {
        // okay, lets test it out on the actual service
        $svc = $this->getService();
        // lets create a new job and add it to the queue
        $job = new TestQueuedJob();
        $id = $svc->queueJob($job);

        $descriptor = DataObject::get_by_id(QueuedJobDescriptor::class, $id);

        $job = $svc->testInit($descriptor);
        $this->assertInstanceOf(TestQueuedJob::class, $job, 'Job has been triggered');

        $descriptor = DataObject::get_by_id(QueuedJobDescriptor::class, $id);

        $this->assertEquals(QueuedJob::STATUS_INIT, $descriptor->JobStatus);
    }

    public function testStartJob()
    {
        // okay, lets test it out on the actual service
        $svc = $this->getService();
        // lets create a new job and add it to the queue

        $this->logInWithPermission('DUMMYUSER');

        $job = new TestQueuedJob();
        $job->testingStartJob = true;
        $id = $svc->queueJob($job);

        $this->logInWithPermission('ADMIN');

        $result = $svc->runJob($id);
        $this->assertTrue($result);

        // we want to make sure that the current user is the runas user of the job
        $descriptor = DataObject::get_by_id(QueuedJobDescriptor::class, $id);
        $this->assertEquals('Complete', $descriptor->JobStatus);
    }

    public function testImmediateQueuedJob()
    {
        // okay, lets test it out on the actual service
        $svc = $this->getService();
        // lets create a new job and add it to the queue

        $job = new TestQueuedJob(QueuedJob::IMMEDIATE);
        $job->firstJob = true;
        $id = $svc->queueJob($job);

        $job = new TestQueuedJob(QueuedJob::IMMEDIATE);
        $job->secondJob = true;
        $id = $svc->queueJob($job);

        $jobs = $svc->getJobList(QueuedJob::IMMEDIATE);
        $this->assertCount(2, $jobs);

        // now fake a shutdown
        $svc->onShutdown();

        $jobs = $svc->getJobList(QueuedJob::IMMEDIATE);
        $this->assertInstanceOf(DataList::class, $jobs);
        $this->assertCount(0, $jobs);
    }

    /**
     * @throws ValidationException
     */
    public function testNextJob()
    {
        $svc = $this->getService();
        $list = $svc->getJobList();

        foreach ($list as $job) {
            $job->delete();
        }

        $list = $svc->getJobList();
        $this->assertCount(0, $list);

        $job = new TestQueuedJob();
        $id1 = $svc->queueJob($job);

        $job = new TestQueuedJob();
        // to get around the signature checks
        $job->randomParam = 'me';
        $id2 = $svc->queueJob($job);

        $job = new TestQueuedJob();
        // to get around the signature checks
        $job->randomParam = 'mo';
        $id3 = $svc->queueJob($job);

        $this->assertEquals(2, $id3 - $id1);

        $list = $svc->getJobList();
        $this->assertCount(3, $list);

        // okay, lets get the first one and initialise it, then make sure that a subsequent init attempt fails
        $job = $svc->getNextPendingJob();

        $this->assertEquals($id1, $job->ID);
        $svc->testInit($job);

        // assign job locking properties
        $job->Worker = 'test worker';
        $job->WorkerCount = (int) $job->WorkerCount + 1;
        $job->Expiry = '2016-01-01 16:00:01';
        $job->write();

        // now try and get another, it should be different from the job that is already being processed
        $next = $svc->getNextPendingJob();
        $this->assertNotNull($next);
        $this->assertTrue($next->isInDB());
        $this->assertNotEquals((int) $job->ID, (int) $next->ID);
    }

    /**
     * Verify that broken jobs are correctly verified for health and restarted as necessary
     *
     * Order of checkJobHealth() and getNextPendingJob() is important
     *
     * Execution of this job is broken into several "loops", each of which represents one invocation
     * of ProcessJobQueueTask
     */
    public function testJobHealthCheck()
    {
        // Create a job and add it to the queue
        $svc = $this->getService();
        $logger = $svc->getLogger();
        $job = new TestQueuedJob(QueuedJob::IMMEDIATE);
        $job->firstJob = true;
        $id = $svc->queueJob($job);

        /** @var QueuedJobDescriptor $descriptor */
        $descriptor = QueuedJobDescriptor::get()->byID($id);

        // Verify initial state is new and LastProcessedCount is not marked yet
        $this->assertEquals(QueuedJob::STATUS_NEW, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(-1, $descriptor->LastProcessedCount);
        $this->assertEquals(0, $descriptor->ResumeCounts);
        $this->assertNull($descriptor->Worker);
        $this->assertEquals(0, (int) $descriptor->WorkerCount);
        $this->assertNull($descriptor->Expiry);

        // Loop 1 - Pick up new job and attempt to run it
        // Job health should not attempt to cleanup unstarted jobs
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // Ensure that this is the next job ready to go
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals($nextJob->ID, $descriptor->ID);
        $this->assertEquals(QueuedJob::STATUS_NEW, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(-1, $descriptor->LastProcessedCount);
        $this->assertEquals(0, $descriptor->ResumeCounts);
        $this->assertNull($descriptor->Worker);
        $this->assertEquals(0, (int) $descriptor->WorkerCount);
        $this->assertNull($descriptor->Expiry);

        // Run 1 - Start the job (no work is done)
        $descriptor->JobStatus = QueuedJob::STATUS_INIT;

        // assign job locking properties
        $descriptor->Worker = 'test worker';
        $descriptor->WorkerCount = (int) $descriptor->WorkerCount + 1;
        $descriptor->Expiry = '2016-01-01 16:00:01';
        $descriptor->write();

        // Assume that something bad happens at this point, the process dies during execution, and
        // the task is re-initiated somewhere down the track

        // Loop 2 - Detect broken job, and mark it for future checking.
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);

        // job in init or running state always has assigned worker
        // getNextPendingJob will ignore such jobs so we have to check for null
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // Note that we don't immediately try to restart it until StepsProcessed = LastProcessedCount
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertNull($nextJob); // Don't run it this round please!
        $this->assertEquals(QueuedJob::STATUS_INIT, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(0, $descriptor->LastProcessedCount);
        $this->assertEquals(0, $descriptor->ResumeCounts);
        $this->assertEquals('test worker', $descriptor->Worker);
        $this->assertEquals(1, (int) $descriptor->WorkerCount);
        $this->assertEquals('2016-01-01 16:00:01', $descriptor->Expiry);

        // Loop 3 - We've previously marked this job as broken, so restart it this round
        // If no more work has been done on the job at this point, assume that we are able to
        // restart it
        $logger->clear();

        // run health check without moving the time forward - we should get no next job as it is still locked
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);
        $this->assertNull($nextJob);

        // we have to move current time to the future as the job is locked for a while
        // this will enable the queue health procedure to pick it up
        DBDatetime::set_mock_now('2017-01-01 16:00:00');

        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // This job is resumed and exeuction is attempted this round
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals($nextJob->ID, $descriptor->ID);
        $this->assertEquals(QueuedJob::STATUS_WAIT, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(0, $descriptor->LastProcessedCount);
        $this->assertEquals(1, $descriptor->ResumeCounts);
        $this->assertNull($descriptor->Worker);
        $this->assertEquals(1, (int) $descriptor->WorkerCount);
        $this->assertEquals('2016-01-01 16:00:01', $descriptor->Expiry);

        // worker job lock should be cleared by now
        $this->assertNull($descriptor->Worker);
        $this->assertContains(
            'A job named A Test job appears to have stalled. It will be stopped and restarted, please login to '
            . 'make sure it has continued',
            $logger->getMessages()
        );

        // Run 2 - First restart (work is done)
        $descriptor->JobStatus = QueuedJob::STATUS_RUN;
        $descriptor->StepsProcessed++; // Essentially delays the next restart by 1 loop

        // assign job locking properties
        $descriptor->Worker = 'test worker';
        $descriptor->WorkerCount = (int) $descriptor->WorkerCount + 1;
        $descriptor->Expiry = '2017-01-01 16:00:01';
        $descriptor->write();

        // Once again, at this point, assume the job fails and crashes

        // Loop 4 - Assuming a job has LastProcessedCount < StepsProcessed we are in the same
        // situation as step 2.
        // Because the last time the loop ran, StepsProcessed was incremented,
        // this indicates that it's likely that another task could be working on this job, so
        // don't run this.
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertNull($nextJob); // Don't run jobs we aren't sure should be restarted
        $this->assertEquals(QueuedJob::STATUS_RUN, $descriptor->JobStatus);
        $this->assertEquals(1, $descriptor->StepsProcessed);
        $this->assertEquals(1, $descriptor->LastProcessedCount);
        $this->assertEquals(1, $descriptor->ResumeCounts);
        $this->assertEquals('test worker', $descriptor->Worker);
        $this->assertEquals(2, (int) $descriptor->WorkerCount);
        $this->assertEquals('2017-01-01 16:00:01', $descriptor->Expiry);

        // run health check without moving the time forward - we should get no next job as it is still locked
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);
        $this->assertNull($nextJob);

        // we have to move current time to the future as the job is locked for a while
        // this will enable the queue health procedure to pick it up
        DBDatetime::set_mock_now('2018-01-01 16:00:00');

        // Loop 5 - Job is again found to not have been restarted since last iteration, so perform second
        // restart. The job should be attempted to run this loop
        $logger->clear();
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // This job is resumed and exeuction is attempted this round
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals($nextJob->ID, $descriptor->ID);
        $this->assertEquals(QueuedJob::STATUS_WAIT, $descriptor->JobStatus);
        $this->assertEquals(1, $descriptor->StepsProcessed);
        $this->assertEquals(1, $descriptor->LastProcessedCount);
        $this->assertEquals(2, $descriptor->ResumeCounts);
        $this->assertNull($descriptor->Worker);
        $this->assertEquals(2, (int) $descriptor->WorkerCount);
        $this->assertEquals('2017-01-01 16:00:01', $descriptor->Expiry);
        $this->assertContains(
            'A job named A Test job appears to have stalled. It will be stopped and restarted, please login '
            . 'to make sure it has continued',
            $logger->getMessages()
        );

        // we have to move current time to the future as the job is locked for a while
        // this will enable the queue health procedure to pick it up
        DBDatetime::set_mock_now('2019-01-01 16:00:00');

        // Run 3 - Second and last restart (no work is done)
        $descriptor->JobStatus = QueuedJob::STATUS_RUN;

        // assign job locking properties
        $descriptor->Worker = 'test worker';
        $descriptor->WorkerCount = (int) $descriptor->WorkerCount + 1;
        $descriptor->Expiry = '2018-01-01 16:00:01';
        $descriptor->write();

        // Loop 6 - As no progress has been made since loop 3, we can mark this as dead
        $logger->clear();
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // Since no StepsProcessed has been done, don't wait another loop to mark this as dead
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals(QueuedJob::STATUS_PAUSED, $descriptor->JobStatus);
        $this->assertNull($nextJob);
        $this->assertNull($descriptor->Worker);
        $this->assertEquals(3, (int) $descriptor->WorkerCount);
        $this->assertEquals('2018-01-01 16:00:01', $descriptor->Expiry);
        $this->assertContains(
            'A job named A Test job appears to have stalled. It has been paused, please login to check it',
            $logger->getMessages()
        );
    }

    public function testExceptionWithMemoryExhaustion()
    {
        $svc = $this->getService();
        $job = new TestExceptingJob();
        $job->firstJob = true;
        $id = $svc->queueJob($job);
        $descriptor = QueuedJobDescriptor::get()->byID($id);

        // we want to set the memory limit _really_ low so that our first run triggers
        $mem = Config::inst()->get('QueuedJobService', 'memory_limit');
        Config::modify()->set(QueuedJobService::class, 'memory_limit', 1);

        $svc->runJob($id);

        Config::modify()->set(QueuedJobService::class, 'memory_limit', $mem);

        $descriptor = QueuedJobDescriptor::get()->byID($id);

        $this->assertEquals(QueuedJob::STATUS_BROKEN, $descriptor->JobStatus);
    }

    public function testCheckdefaultJobs()
    {
        // Create a job and add it to the queue
        $svc = $this->getService();
        $testDefaultJobsArray = array(
            'ArbitraryName' => array(
                # I'll get restarted and create an alert email
                'type' => TestQueuedJob::class,
                'filter' => array(
                    'JobTitle' => "A Test job"
                ),
                'recreate' => 1,
                'construct' => array(
                    'queue' => QueuedJob::QUEUED
                ),
                'startDateFormat' => 'Y-m-d 02:00:00',
                'startTimeString' => 'tomorrow',
                'email' => 'test@queuejobtest.com'
            ));
        $svc->defaultJobs = $testDefaultJobsArray;
        $jobConfig = $testDefaultJobsArray['ArbitraryName'];

        $activeJobs = QueuedJobDescriptor::get()->filter(
            'JobStatus',
            array(
                QueuedJob::STATUS_NEW,
                QueuedJob::STATUS_INIT,
                QueuedJob::STATUS_RUN,
                QueuedJob::STATUS_WAIT,
                QueuedJob::STATUS_PAUSED
            )
        );
        //assert no jobs currently active
        $this->assertCount(0, $activeJobs);

        //add a default job to the queue
        $svc->checkdefaultJobs();
        $this->assertCount(1, $activeJobs);
        $descriptor = $activeJobs->filter(array_merge(
            array('Implementation' => $jobConfig['type']),
            $jobConfig['filter']
        ))->first();
        // Verify initial state is new
        $this->assertEquals(QueuedJob::STATUS_NEW, $descriptor->JobStatus);

        //update Job to paused
        $descriptor->JobStatus = QueuedJob::STATUS_PAUSED;
        $descriptor->write();
        //check defaults the paused job shoudl be ignored
        $svc->checkdefaultJobs();
        $this->assertCount(1, $activeJobs);
        //assert we now still have 1 of our job (paused)
        $this->assertCount(1, QueuedJobDescriptor::get());

        //update Job to broken
        $descriptor->JobStatus = QueuedJob::STATUS_BROKEN;
        $descriptor->write();
        //check and add job for broken job
        $svc->checkdefaultJobs();
        $this->assertCount(1, $activeJobs);
        //assert we now have 2 of our job (one good one broken)
        $this->assertCount(2, QueuedJobDescriptor::get());

        //test not adding a job when job is there already
        $svc->checkdefaultJobs();
        $this->assertCount(1, $activeJobs);
        //assert we now have 2 of our job (one good one broken)
        $this->assertCount(2, QueuedJobDescriptor::get());

        //test add jobs with various start dates
        $job = $activeJobs->first();
        date('Y-m-d 02:00:00', strtotime('+1 day'));
        $this->assertEquals(date('Y-m-d 02:00:00', strtotime('+1 day')), $job->StartAfter);
        //swap start time to midday
        $testDefaultJobsArray['ArbitraryName']['startDateFormat'] = 'Y-m-d 12:00:00';
        //clean up then add new jobs
        $svc->defaultJobs = $testDefaultJobsArray;
        $activeJobs->removeAll();
        $svc->checkdefaultJobs();
        //assert one jobs currently active
        $this->assertCount(1, $activeJobs);
        $job = $activeJobs->first();
        $this->assertEquals(date('Y-m-d 12:00:00', strtotime('+1 day')), $job->StartAfter);
        //test alert email
        $email = $this->findEmail('test@queuejobtest.com');
        $this->assertNotNull($email);

        //test broken job config
        unset($testDefaultJobsArray['ArbitraryName']['startDateFormat']);
        //clean up then add new jobs
        $svc->defaultJobs = $testDefaultJobsArray;
        $activeJobs->removeAll();
        $svc->checkdefaultJobs();
    }

    public function testGrabMutex(): void
    {
        Config::modify()->set(QueuedJobService::class, 'worker_ttl', 'PT5M');

        /** @var QueuedJobDescriptor $descriptor */
        $descriptor = QueuedJobDescriptor::create();
        $descriptor->write();

        $descriptor = QueuedJobDescriptor::get()->byID($descriptor->ID);

        // job descriptor is unlocked
        $this->assertEquals('', $descriptor->Worker);
        $this->assertEquals(0, $descriptor->WorkerCount);
        $this->assertEquals('', $descriptor->Expiry);

        $class = new ReflectionClass(QueuedJobService::class);
        $method = $class->getMethod('grabMutex');
        $method->setAccessible(true);

        // attempt to claim lock on descriptor
        $result = $method->invokeArgs(QueuedJobService::singleton(), [$descriptor]);

        // lock is successful
        $this->assertTrue($result);

        $descriptor = QueuedJobDescriptor::get()->byID($descriptor->ID);

        // lock data is in place
        $this->assertNotEmpty($descriptor->Worker);
        $this->assertEquals(1, (int) $descriptor->WorkerCount);
        $this->assertEquals('2016-01-01 16:05:00', $descriptor->Expiry);

        // attempt to claim lock on descriptor
        $result = $method->invokeArgs(QueuedJobService::singleton(), [$descriptor]);

        // lock is not granted as it's already claimed
        $this->assertFalse($result);
    }

    /**
     * @param array $jobs
     * @param int $expected
     * @throws ValidationException
     * @dataProvider jobsProvider
     */
    public function testBrokenJobNotification(array $jobs, int $expected): void
    {
        /** @var QueuedJobDescriptor $descriptor */
        foreach ($jobs as $notified) {
            $descriptor = QueuedJobDescriptor::create();
            $descriptor->JobType = QueuedJob::IMMEDIATE;
            $descriptor->JobStatus = QueuedJob::STATUS_BROKEN;

            if ($notified) {
                $descriptor->NotifiedBroken = 1;
            }

            $descriptor->write();
        }

        $result = QueuedJobService::singleton()->checkJobHealth(QueuedJob::IMMEDIATE);

        $this->assertArrayHasKey('broken', $result);
        $this->assertArrayHasKey('stalled', $result);
        $this->assertCount($expected, $result['broken']);
        $this->assertCount(0, QueuedJobDescriptor::get()->filter(['NotifiedBroken' => 0]));
    }

    public function jobsProvider(): array
    {
        return [
            [
                [true, true, true, true, true],
                0,
            ],
            [
                [false, true, true, true, true],
                1,
            ],
            [
                [false, false, true, true, true],
                2,
            ],
            [
                [false, false, false, false, false],
                5,
            ],
        ];
    }
}
