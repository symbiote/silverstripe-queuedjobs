<?php

/**
 *
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 */
class QueuedJobsTest extends SapphireTest {

    /**
     * We need the DB for this test
     *
     * @var bool
     */
    protected $usesDatabase = true;

	public function setUp() {
		parent::setUp();

		Config::nest();
		// Two restarts are allowed per job
		Config::inst()->update('QueuedJobService', 'stall_threshold', 2);
	}

	public function tearDown() {
		Config::unnest();
		parent::tearDown();
	}


	/**
	 * @return QueuedJobService
	 */
	protected function getService() {
		return singleton("TestQJService");
	}

	public function testQueueJob() {
		$svc = $this->getService();

		// lets create a new job and add it tio the queue
		$job = new TestQueuedJob();
		$jobId = $svc->queueJob($job);
		$list = $svc->getJobList();

		$this->assertEquals(1, $list->count());

		$myJob = null;
		foreach ($list as $job) {
			if ($job->Implementation == 'TestQueuedJob') {
				$myJob = $job;
				break;
			}
		}

		$this->assertNotNull($myJob);
		$this->assertTrue($jobId > 0);
		$this->assertEquals('TestQueuedJob', $myJob->Implementation);
		$this->assertNotNull($myJob->SavedJobData);
	}

	public function testJobRunAs() {
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

	public function testQueueSignature() {
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

	public function testProcessJob() {
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

	public function testResumeJob() {
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

	public function testInitialiseJob() {
		// okay, lets test it out on the actual service
		$svc = $this->getService();
		// lets create a new job and add it to the queue
		$job = new TestQueuedJob();
		$id = $svc->queueJob($job);

		$descriptor = DataObject::get_by_id('QueuedJobDescriptor', $id);

		$job = $svc->testInit($descriptor);
		$this->assertInstanceOf('TestQueuedJob', $job, 'Job has been triggered');

		$descriptor = DataObject::get_by_id('QueuedJobDescriptor', $id);

		$this->assertEquals(QueuedJob::STATUS_INIT, $descriptor->JobStatus);
	}

	public function testStartJob() {
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
		$descriptor = DataObject::get_by_id('QueuedJobDescriptor', $id);
		$this->assertEquals('Complete', $descriptor->JobStatus);
	}

	public function testImmediateQueuedJob() {
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
		$this->assertInstanceOf('DataList', $jobs);
		$this->assertEquals(0, $jobs->count());
	}

	public function testNextJob() {
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
	public function testJobHealthCheck() {
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

    public function testExceptionWithMemoryExhaustion() {
        $svc = $this->getService();
        $job = new TestExceptingJob();
		$job->firstJob = true;
		$id = $svc->queueJob($job);
		$descriptor = QueuedJobDescriptor::get()->byID($id);

        // we want to set the memory limit _really_ low so that our first run triggers
        $mem = Config::inst()->get('QueuedJobService', 'memory_limit');
        Config::inst()->update('QueuedJobService', 'memory_limit', 1);

        $svc->runJob($id);

        Config::inst()->update('QueuedJobService', 'memory_limit', $mem);

        $descriptor = QueuedJobDescriptor::get()->byID($id);

        $this->assertEquals(QueuedJob::STATUS_BROKEN, $descriptor->JobStatus);
    }

	public function testCheckdefaultJobs() {
		// Create a job and add it to the queue
		$svc = $this->getService();
		$testDefaultJobsArray = array(
			'ArbitraryName' => array(
				# I'll get restarted and create an alert email
				'type' => 'TestQueuedJob',
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
				'JobStatus', array(
					QueuedJob::STATUS_NEW,
					QueuedJob::STATUS_INIT,
					QueuedJob::STATUS_RUN,
					QueuedJob::STATUS_WAIT,
					QueuedJob::STATUS_PAUSED
				)
		);
		//assert no jobs currently active
		$this->assertEquals(0, $activeJobs->count());

		//add a default job to the queue
		$svc->checkdefaultJobs();
		$this->assertEquals(1, $activeJobs->count());
		$descriptor = $activeJobs->filter(array_merge(
								array('Implementation' => $jobConfig['type']), $jobConfig['filter']
				))->first();
		// Verify initial state is new
		$this->assertEquals(QueuedJob::STATUS_NEW, $descriptor->JobStatus);

		//update Job to paused
		$descriptor->JobStatus = QueuedJob::STATUS_PAUSED;
		$descriptor->write();
		//check defaults the paused job shoudl be ignored
		$svc->checkdefaultJobs();
		$this->assertEquals(1, $activeJobs->count());
		//assert we now still have 1 of our job (paused)
		$this->assertEquals(1, QueuedJobDescriptor::get()->count());

		//update Job to broken
		$descriptor->JobStatus = QueuedJob::STATUS_BROKEN;
		$descriptor->write();
		//check and add job for broken job
		$svc->checkdefaultJobs();
		$this->assertEquals(1, $activeJobs->count());
		//assert we now have 2 of our job (one good one broken)
		$this->assertEquals(2, QueuedJobDescriptor::get()->count());

		//test not adding a job when job is there already
		$svc->checkdefaultJobs();
		$this->assertEquals(1, $activeJobs->count());
		//assert we now have 2 of our job (one good one broken)
		$this->assertEquals(2, QueuedJobDescriptor::get()->count());

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
		$this->assertEquals(1, $activeJobs->count());
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

// stub class to be able to call init from an external context
class TestQJService extends QueuedJobService {
	public function testInit($descriptor) {
		return $this->initialiseJob($descriptor);
	}
}

class TestExceptingJob extends  AbstractQueuedJob implements QueuedJob {
    private $type = QueuedJob::QUEUED;

	public function __construct($type = null) {
        $this->type = QueuedJob::IMMEDIATE;
		$this->times = array();
	}

	public function getJobType() {
		return $this->type;
	}

	public function getTitle() {
		return "A Test job throwing exceptions";
	}

	public function setup() {
		$this->totalSteps = 1;
	}

	public function process() {
		throw new Exception("just excepted");
	}
}

class TestQueuedJob extends AbstractQueuedJob implements QueuedJob {
	private $type = QueuedJob::QUEUED;

	public function __construct($type = null) {
		if ($type) {
			$this->type = $type;
		}
		$this->times = array();
	}

	public function getJobType() {
		return $this->type;
	}

	public function getTitle() {
		return "A Test job";
	}

	public function setup() {
		$this->totalSteps = 5;
	}

	public function process() {
		$times = $this->times;
		// needed due to quirks with __set
		$times[] = date('Y-m-d H:i:s');
		$this->times = $times;

		$this->addMessage("Updated time to " . date('Y-m-d H:i:s'));
		sleep(1);

		// make sure we're incrementing
		$this->currentStep++;

		// and checking whether we're complete
		if ($this->currentStep == 5) {
			$this->isComplete = true;
		}
	}
}
