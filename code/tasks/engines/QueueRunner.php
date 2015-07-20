<?php

/**
 * Runs all jobs in a queue loop in one process
 */
class QueueRunner extends BaseRunner implements TaskRunnerEngine {

	/**
	 * @param string $queue
	 */
	public function runQueue($queue) {
		$service = $this->getService();
		$service->checkJobHealth();
		
		$nextJob = $service->getNextPendingJob($queue);
		$this->logDescriptorStatus($nextJob, $queue);

		if($nextJob instanceof QueuedJobDescriptor) {
			$service->processJobQueue($queue);
		}
	}

	public function runJob($id) {
		$this
			->getService()
			->runJob($id);
	}
}
