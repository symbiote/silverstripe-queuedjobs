<?php

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\QueuedJobs\DataObjects\QueuedJobDescriptor;
use SilverStripe\QueuedJobs\Services\QueuedJob;
use SilverStripe\QueuedJobs\Tests\TestQueuedJob;
use SilverStripe\QueuedJobs\Tests\TestQJService;

/**
 *
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class QueuedJobsTest extends SapphireTest
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
    public function setUp()
    {
        parent::setUp();

        Config::nest();
        // Two restarts are allowed per job
        Config::inst()->update('SilverStripe\\QueuedJobs\\Services\\QueuedJobService', 'stall_threshold', 2);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    /**
     * @return QueuedJobService
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

        $this->assertEquals(1, $list->count());

        $myJob = null;
        foreach ($list as $job) {
            if ($job->Implementation == TestQueuedJob::class) {
                $myJob = $job;
                break;
            }
        }

        $this->assertNotNull($myJob);
        $this->assertTrue($jobId > 0);
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
        $this->assertEquals(1, count($jd->times));

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
        $this->assertEquals(2, count($jd->times));
    }

    public function testInitialiseJob()
    {
        // okay, lets test it out on the actual service
        $svc = $this->getService();
        // lets create a new job and add it to the queue
        $job = new TestQueuedJob();
        $id = $svc->queueJob($job);

        $descriptor = DataObject::get_by_id('SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor', $id);

        $job = $svc->testInit($descriptor);
        $this->assertInstanceOf(TestQueuedJob::class, $job, 'Job has been triggered');

        $descriptor = DataObject::get_by_id('SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor', $id);

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
        $descriptor = DataObject::get_by_id('SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor', $id);
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
        $this->assertEquals(2, $jobs->count());

        // now fake a shutdown
        $svc->onShutdown();

        $jobs = $svc->getJobList(QueuedJob::IMMEDIATE);
        $this->assertInstanceOf('SilverStripe\\ORM\\DataList', $jobs);
        $this->assertEquals(0, $jobs->count());
    }

    public function testNextJob()
    {
        $svc = $this->getService();
        $list = $svc->getJobList();

        foreach ($list as $job) {
            $job->delete();
        }

        $list = $svc->getJobList();
        $this->assertEquals(0, $list->count());

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
        $this->assertEquals(3, $list->count());

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
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // This job is resumed and exeuction is attempted this round
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals($nextJob->ID, $descriptor->ID);
        $this->assertEquals(QueuedJob::STATUS_WAIT, $descriptor->JobStatus);
        $this->assertEquals(0, $descriptor->StepsProcessed);
        $this->assertEquals(0, $descriptor->LastProcessedCount);
        $this->assertEquals(1, $descriptor->ResumeCounts);

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
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // This job is resumed and exeuction is attempted this round
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals($nextJob->ID, $descriptor->ID);
        $this->assertEquals(QueuedJob::STATUS_WAIT, $descriptor->JobStatus);
        $this->assertEquals(1, $descriptor->StepsProcessed);
        $this->assertEquals(1, $descriptor->LastProcessedCount);
        $this->assertEquals(2, $descriptor->ResumeCounts);

        // Run 3 - Second and last restart (no work is done)
        $descriptor->JobStatus = QueuedJob::STATUS_RUN;
        $descriptor->write();

        // Loop 6 - As no progress has been made since loop 3, we can mark this as dead
        $svc->checkJobHealth(QueuedJob::IMMEDIATE);
        $nextJob = $svc->getNextPendingJob(QueuedJob::IMMEDIATE);

        // Since no StepsProcessed has been done, don't wait another loop to mark this as dead
        $descriptor = QueuedJobDescriptor::get()->byID($id);
        $this->assertEquals(QueuedJob::STATUS_PAUSED, $descriptor->JobStatus);
        $this->assertEmpty($nextJob);
    }
}
