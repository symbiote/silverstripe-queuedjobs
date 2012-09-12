<?php

/**
 * 
 * A task that can be used to create a queued job.
 * 
 * Useful to hook a queued job in to place that needs to exist if it doesn't already.
 * 
 * If no name is given, it creates a demo dummy job to help test that things
 * are set up and working
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class CreateQueuedJobTask extends BuildTask {
	
	protected $description = 'A task used to create a queued job. Pass the queued job class name as the "name" parameter';
	
    public function run($request) {
		if (isset($request['name']) && ClassInfo::exists($request['name'])) {
			$clz = $request['name'];
			$job = new $clz;
		} else {
			$job = new DummyQueuedJob(mt_rand(10, 100));
		}
		singleton('QueuedJobService')->queueJob($job);
	}
}

class DummyQueuedJob extends AbstractQueuedJob implements QueuedJob {
	public function __construct($number = 0) {
		if ($number) {
			$this->startNumber = $number;
			$this->totalSteps = $this->startNumber;
		}
	}

	public function getTitle() {
		return "Some test job for ".$this->startNumber.' seconds';
	}

	public function getJobType() {
		return QueuedJob::IMMEDIATE;
		
		return $this->startNumber > 50 ? QueuedJob::LARGE : QueuedJob::QUEUED;
	}

	public function setup() {
		// just demonstrating how to get a job going...
		$this->totalSteps = $this->startNumber;
		$this->times = array();
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

//		if ($this->currentStep > 1) {
//			$this->currentStep = 1;
//		}

		// and checking whether we're complete
		if ($this->currentStep >= $this->totalSteps) {
			$this->isComplete = true;
		}
	}
}