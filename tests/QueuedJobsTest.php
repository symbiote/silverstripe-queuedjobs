<?php

namespace Symbiote\QueuedJobs\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
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

        // now try and get another, it should be === false
        $next = $svc->getNextPendingJob();

        $this->assertFalse($next);
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
        $descriptor = QueuedJobDescriptor::get()->byID($id);

        // Verify initial state is new and LastProcessedCount is not marked yet
        $this->assertEquals(QueuedJob::STATUS_NEW, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(-1, $descriptor->LastProcessedCount);
        $this->assertEquals(0, $descriptor->ResumeCounts);

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

        // Run 1 - Start the job (no work is done)
        $descriptor->JobStatus = QueuedJob::STATUS_INIT;
        $descriptor->write();

        // Assume that something bad happens at this point, the process dies during execution, and
        // the task is re-initiated somewhere down the track

        // Loop 2 - Detect broken job, and mark it for future checking.
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // Note that we don't immediately try to restart it until StepsProcessed = LastProcessedCount
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertFalse($nextJob); // Don't run it this round please!
        $this->assertEquals(QueuedJob::STATUS_INIT, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(0, $descriptor->LastProcessedCount);
        $this->assertEquals(0, $descriptor->ResumeCounts);

        // Loop 3 - We've previously marked this job as broken, so restart it this round
        // If no more work has been done on the job at this point, assume that we are able to
        // restart it
        $logger->clear();
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // This job is resumed and exeuction is attempted this round
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals($nextJob->ID, $descriptor->ID);
        $this->assertEquals(QueuedJob::STATUS_WAIT, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(0, $descriptor->LastProcessedCount);
        $this->assertEquals(1, $descriptor->ResumeCounts);
        $this->assertContains(
            'A job named A Test job appears to have stalled. It will be stopped and restarted, please login to '
            . 'make sure it has continued',
            $logger->getMessages()
        );

        // Run 2 - First restart (work is done)
        $descriptor->JobStatus = QueuedJob::STATUS_RUN;
        $descriptor->StepsProcessed++; // Essentially delays the next restart by 1 loop
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
        $this->assertFalse($nextJob); // Don't run jobs we aren't sure should be restarted
        $this->assertEquals(QueuedJob::STATUS_RUN, $descriptor->JobStatus);
        $this->assertEquals(1, $descriptor->StepsProcessed);
        $this->assertEquals(1, $descriptor->LastProcessedCount);
        $this->assertEquals(1, $descriptor->ResumeCounts);

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
        $this->assertContains(
            'A job named A Test job appears to have stalled. It will be stopped and restarted, please login '
            . 'to make sure it has continued',
            $logger->getMessages()
        );

        // Run 3 - Second and last restart (no work is done)
        $descriptor->JobStatus = QueuedJob::STATUS_RUN;
        $descriptor->write();

        // Loop 6 - As no progress has been made since loop 3, we can mark this as dead
        $logger->clear();
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // Since no StepsProcessed has been done, don't wait another loop to mark this as dead
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals(QueuedJob::STATUS_PAUSED, $descriptor->JobStatus);
        $this->assertEmpty($nextJob);
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
}
