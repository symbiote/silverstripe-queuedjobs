<?php

use AsyncPHP\Doorman\Handler;
use AsyncPHP\Doorman\Task;

class ProcessJobQueueChildTask extends BuildTask {
	/**
	 * @param SS_HTTPRequest $request
	 */
	public function run($request) {
		if (!isset($_SERVER['argv'][2])) {
			print "No task data provided.\n";
			return;
		}

		$task = @unserialize(@base64_decode($_SERVER['argv'][2]));

		if ($task) {
			$this->getService()->runJob($task->getDescriptor()->ID);
		}
	}

	/**
	 * Returns an instance of the QueuedJobService.
	 *
	 * @return QueuedJobService
	 */
	protected function getService() {
		return singleton('QueuedJobService');
	}
}
