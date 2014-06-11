<?php

/**
 * Task used to process the job queue
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class ProcessJobQueueTask extends BuildTask {

	public function getDescription() {
		return _t(
			'ProcessJobQueueTask.Description',
			'Used via a cronjob to execute queued jobs that need running'
		);
	}

	/**
	 * Write in a format expected by the output medium (CLI/HTML).
	 *
	 * @param $line Line to be written out, without the newline character.
	 */
	private function writeLogLine($line) {
		if (Director::is_cli()) {
			echo "$line\n";
		} else {
			echo Convert::raw2xml($line) . "<br>";
		}
	}

	public function run($request) {
		$service = singleton('QueuedJobService');
		/* @var $service QueuedJobService */

		$datestamp = '['.date('Y-m-d H:i:s').']';
		$queue = $request->getVar('queue');
		if (!$queue) {
			$queue = 'Queued';
		}

		switch (strtolower($queue)) {
			case 'immediate': {
				$queue = QueuedJob::IMMEDIATE;
				break;
			}
			case 'queued': {
				$queue = QueuedJob::QUEUED;
				break;
			}
			case 'large': {
				$queue = QueuedJob::LARGE;
				break;
			}
			default: {
				// leave it as whatever this queue name is
			}
		}

		$this->writeLogLine("$datestamp Processing queue $queue");

		if ($request->getVar('list')) {
			for ($i = 1; $i  <= 3; $i++) {
				$jobs = $service->getJobList($i);
				$num = $jobs ? $jobs->Count() : 0;
				$this->writeLogLine("$datestamp Found $num jobs for mode $i");
			}
			return;
		}
		
		/* @var $service QueuedJobService */
		$nextJob = null;
		
		// see if we've got an explicit job ID, otherwise we'll just check the queue directly
		if ($request->getVar('job') && strpos($request->getVar('job'), '-')) {
			list($junk, $jobId) = split('-', $request->getVar('job'));
			$nextJob = DataObject::get_by_id('QueuedJobDescriptor', $jobId);
		} else {
			$nextJob = $service->getNextPendingJob($queue);
		}

		$service->checkJobHealth();

		if ($nextJob) {
			$this->writeLogLine("$datestamp Running $nextJob->JobTitle and others from $queue.");
			$service->processJobQueue($queue);
		}

		if (is_null($nextJob)) {
			$this->writeLogLine("$datestamp No new jobs");
		}
		if ($nextJob === false) {
			$this->writeLogLine("$datestamp Job is still running on $queue");
		}
	}
}
