<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class JobWorker implements GearmanHandler {
	
	public static $dependencies = array(
		'queuedJobService' => '%$QueuedJobService',
	);
	/**
	 * @var QueuedJobService
	 */
	public $queuedJobService;
	
	public function getName() {
		return 'jobqueueExecute';
	}

	public function jobqueueExecute($jobId) {
		$this->queuedJobService->checkJobHealth();
		$job = DataList::create('QueuedJobDescriptor')->byID($jobId);
		if ($job) {
			// check that we're not trying to execute something tooo soon
			if (strtotime($job->StartAfter) > time()) {
				return;
			}

			$this->queuedJobService->runJob($jobId);
		}
	}
}
