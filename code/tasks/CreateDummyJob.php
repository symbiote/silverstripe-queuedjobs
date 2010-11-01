<?php

/**
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class CreateDummyJob extends BuildTask {
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
			$this->jobData->startNumber = $number;
			$this->totalSteps = $this->jobData->startNumber;
		}
	}

	public function getTitle() {
		return "Some test job for ".$this->jobData->startNumber.' seconds';
	}

	public function getJobType() {
		return $this->jobData->startNumber > 50 ? QueuedJob::LARGE : QueuedJob::QUEUED;
	}

	public function setup() {
		// just demonstrating how to get a job going...
		$this->totalSteps = $this->jobData->startNumber;
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