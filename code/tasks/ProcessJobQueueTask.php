<?php

/**
 * Task used to process the job queue
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class ProcessJobQueueTask extends BuildTask {

	protected $description = 'Used via a cronjob to execute queued jobs that need running';

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

		echo "$datestamp Processing queue $queue\n";

		if ($request->getVar('list')) {
			for ($i = 1; $i  <= 3; $i++) {
				$jobs = $service->getJobList($i);
				$num = $jobs ? $jobs->Count() : 0;
				echo "$datestamp Found $num jobs for mode $i\n";
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
			echo "$datestamp Running $nextJob->JobTitle \n";
			$service->runJob($nextJob->ID);
		}

		if (is_null($nextJob)) {
			echo "$datestamp No new jobs\n";
		}
		if ($nextJob === false) {
			echo "$datestamp Job is still running\n";
		}
	}
}